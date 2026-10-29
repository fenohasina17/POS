<?php

namespace App\Services;

use App\Models\Category;
use App\Models\PointOfSale;
use App\Models\Pricing;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CatalogSyncService
{
    private string $centralUrl;
    private string $restaurantId;
    private string $apiKey;

    public function __construct()
    {
        $this->centralUrl   = rtrim(config('sync.central_url', ''), '/');
        $this->restaurantId = config('sync.restaurant_id', 'unknown');
        $this->apiKey       = config('sync.api_key', '');
    }

    /**
     * Récupère le catalogue depuis le Central et le reflète localement.
     * Ne touche jamais "status" — statut de disponibilité propre à ce point
     * de vente, jamais écrasé par une synchronisation venant du Central.
     * Retourne les références créées/modifiées pour piloter la notification.
     */
    public function pull(): array
    {
        $result = ['created' => [], 'updated' => []];

        if (empty($this->centralUrl)) {
            return $result;
        }

        $pos = PointOfSale::where('name', $this->restaurantId)->first();
        if (!$pos) {
            Log::warning('CatalogSyncService: aucun point de vente local pour ' . $this->restaurantId);
            return $result;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->retry(3, 1000, throw: false)
                ->get("{$this->centralUrl}/api/catalog");
        } catch (\Throwable $e) {
            // retry(..., throw: false) ne couvre que les réponses HTTP en échec —
            // une panne réseau (DNS, connexion refusée) lève quand même une
            // exception qu'il faut avaler ici : cette commande tourne en tâche
            // planifiée et ne doit jamais interrompre le scheduler.
            Log::warning('CatalogSyncService: erreur réseau lors de la récupération du catalogue', [
                'error' => $e->getMessage(),
            ]);
            return $result;
        }

        if (!$response->successful()) {
            Log::warning('CatalogSyncService: échec de récupération du catalogue', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return $result;
        }

        $categoryCache = [];

        foreach ($response->json('products', []) as $row) {
            $categoryName = $row['category'] ?? null;
            if (!$categoryName) {
                continue;
            }

            if (!isset($categoryCache[$categoryName])) {
                // "printer" (routage cuisine/bar) est une donnée purement locale,
                // jamais fournie par le Central — jamais touchée ici.
                $category = Category::firstOrCreate(['name' => $categoryName]);

                // Règle de prix décidée au Central. Clé absente (Central plus
                // ancien) = prix imposé, comme avant cette règle.
                $category->price_editable = (bool) ($row['price_editable'] ?? false);
                if ($category->isDirty('price_editable')) {
                    $category->save();
                }
                $categoryCache[$categoryName] = $category;
            }
            $category = $categoryCache[$categoryName];

            $existingProduct = Product::where('ref', $row['ref'])->first();
            $existingPricing = $existingProduct
                ? Pricing::where('product_id', $existingProduct->id)->where('point_of_sale_id', $pos->id)->first()
                : null;

            $isNew     = !$existingProduct;
            $isChanged = $existingProduct && (
                $existingProduct->name !== $row['name']
                || (int) $existingProduct->category_id !== $category->id
                || (float) ($existingPricing->central_price ?? $existingPricing->price ?? -1) !== (float) $row['price']
            );

            if ($existingProduct && (int) $existingProduct->category_id !== $category->id) {
                $this->inheritPrinter($category, $existingProduct);
            }

            $product = Product::updateOrCreate(
                ['ref' => $row['ref']],
                ['name' => $row['name'], 'category_id' => $category->id]
            );

            // Rattachement pivot obligatoire — sans ça le produit reste invisible
            // pour le gérant/caissier, qui filtrent strictement par ce pivot.
            $pos->products()->syncWithoutDetaching([$product->id]);

            $this->applyCentralPrice($existingPricing, $product, $pos, $category, $row['price']);

            if ($isNew) {
                $result['created'][] = $row['ref'];
            } elseif ($isChanged) {
                $result['updated'][] = $row['ref'];
            }
        }

        $this->removeObsoleteCategories($response->json('categories'));

        return $result;
    }

    /**
     * Un produit qui change de catégorie au Central garde son imprimante
     * (cuisine, bar…) : sans ça, une catégorie nouvellement créée ici n'a
     * aucun réglage et tout part par défaut en cuisine. Seule une catégorie
     * encore sans imprimante est complétée — un réglage local n'est jamais
     * écrasé.
     */
    private function inheritPrinter(Category $category, Product $product): void
    {
        if ($category->printer !== null) {
            return;
        }

        $printer = Category::whereKey($product->category_id)->value('printer');
        if ($printer !== null) {
            $category->update(['printer' => $printer]);
        }
    }

    /**
     * Retire les catégories locales devenues vides et absentes du Central
     * (recatégorisation). Une catégorie qui contient encore un produit —
     * créée localement par exemple — est toujours conservée.
     */
    private function removeObsoleteCategories($centralNames): void
    {
        // Liste absente ou vide = réponse incomplète : ne rien supprimer.
        if (!is_array($centralNames) || empty($centralNames)) {
            return;
        }

        Category::whereNotIn('name', $centralNames)
            ->whereDoesntHave('products')
            ->get()
            ->each(function (Category $category) {
                Log::info('CatalogSyncService: catégorie vide retirée', ['name' => $category->name]);
                $category->delete();
            });
    }

    /**
     * Prix reçu du Central. Dans une catégorie ouverte à la modification, un
     * prix fixé par le gérant est conservé : seul le prix de référence est
     * mis à jour. Sinon le prix du Central s'applique et efface tout prix
     * local (catégorie refermée entre-temps au Central).
     */
    private function applyCentralPrice(?Pricing $pricing, Product $product, PointOfSale $pos, Category $category, $centralPrice): void
    {
        if (!$pricing) {
            Pricing::create([
                'product_id'        => $product->id,
                'point_of_sale_id'  => $pos->id,
                'price'             => $centralPrice,
                'central_price'     => $centralPrice,
                'is_local_override' => false,
            ]);
            return;
        }

        if ($category->price_editable && $pricing->is_local_override) {
            $pricing->update(['central_price' => $centralPrice]);
            return;
        }

        $pricing->update([
            'price'             => $centralPrice,
            'central_price'     => $centralPrice,
            'is_local_override' => false,
        ]);
    }

    /**
     * Envoie au Central l'état complet des prix de ce point de vente, pour le
     * tableau « Prix par point de vente ». Jamais bloquant : un échec est
     * simplement retenté à la synchronisation suivante.
     */
    public function pushPrices(): bool
    {
        if (empty($this->centralUrl)) {
            return false;
        }

        $pos = PointOfSale::where('name', $this->restaurantId)->first();
        if (!$pos) {
            return false;
        }

        $prices = Pricing::where('point_of_sale_id', $pos->id)
            ->join('products', 'products.id', '=', 'pricing.product_id')
            ->get(['products.ref', 'pricing.price', 'pricing.is_local_override'])
            ->map(fn ($p) => [
                'ref'               => $p->ref,
                'price'             => (float) $p->price,
                'is_local_override' => (bool) $p->is_local_override,
            ])
            ->values()
            ->all();

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post("{$this->centralUrl}/api/sync/prices", [
                    'terminal_id'   => config('sync.terminal_id', 'unknown'),
                    'restaurant_id' => $this->restaurantId,
                    'prices'        => $prices,
                ]);
        } catch (\Throwable $e) {
            Log::warning('CatalogSyncService: erreur réseau lors de l\'envoi des prix', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        if (!$response->successful()) {
            Log::warning('CatalogSyncService: échec de l\'envoi des prix', [
                'status' => $response->status(),
            ]);
            return false;
        }

        return true;
    }
}
