<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogController extends Controller
{
    /**
     * Catalogue complet consommé par chaque caisse POS (pos:sync-catalog).
     * Pas de logique incrémentale : ~162 lignes = quelques Ko, c'est côté
     * POS que la comparaison avant/après détermine ce qui a changé.
     */
    public function index(): JsonResponse
    {
        // category exposée par nom, pas par id : l'id de Central n'a aucun
        // sens dans la base locale de chaque caisse, qui doit retrouver/créer
        // sa propre catégorie par nom.
        $products = Product::with('category:id,name,price_editable')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => [
                'ref'      => $p->ref,
                'name'     => $p->name,
                'category' => $p->category?->name,
                'price'    => $p->price,
                // Règle portée par la catégorie, recopiée sur chaque produit :
                // une caisse d'une version antérieure ignore simplement la clé.
                'price_editable' => (bool) $p->category?->price_editable,
                // Empreinte de la photo : la caisse ne la retélécharge
                // (GET /api/catalog/images/{ref}) que si elle a changé.
                'image_hash'     => $p->image_hash,
            ]);

        return response()->json([
            'categories' => Category::orderBy('name')->pluck('name'),
            'products'   => $products,
        ]);
    }

    /**
     * Photo d'un produit, téléchargée par les caisses.
     * GET /api/catalog/images/{ref}
     */
    public function image(string $ref): StreamedResponse|JsonResponse
    {
        $product = Product::where('ref', $ref)->first();

        if (!$product?->image || !Storage::disk('local')->exists($product->image)) {
            return response()->json(['message' => 'Aucune photo pour ce produit.'], 404);
        }

        return Storage::disk('local')->response($product->image, null, [
            'X-Image-Hash' => $product->image_hash,
        ]);
    }
}
