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
                $categoryCache[$categoryName] = Category::firstOrCreate(['name' => $categoryName]);
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
                || (float) ($existingPricing->price ?? -1) !== (float) $row['price']
            );

            $product = Product::updateOrCreate(
                ['ref' => $row['ref']],
                ['name' => $row['name'], 'category_id' => $category->id]
            );

            // Rattachement pivot obligatoire — sans ça le produit reste invisible
            // pour le gérant/caissier, qui filtrent strictement par ce pivot.
            $pos->products()->syncWithoutDetaching([$product->id]);

            // Pricing::boot() met déjà à jour un tarif existant au lieu de le
            // dupliquer (hook "creating") — pas besoin de vérifier ici.
            Pricing::create([
                'product_id'       => $product->id,
                'point_of_sale_id' => $pos->id,
                'price'            => $row['price'],
            ]);

            if ($isNew) {
                $result['created'][] = $row['ref'];
            } elseif ($isChanged) {
                $result['updated'][] = $row['ref'];
            }
        }

        return $result;
    }
}
