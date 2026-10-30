<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

/**
 * Photos des produits du catalogue, diffusées aux caisses par
 * pos:sync-catalog (GET /api/catalog/images/{ref}).
 */
class ProductImageService
{
    public const DIR = 'catalog-images';

    /** Enregistre le contenu d'une image pour un produit et remplace l'ancienne. */
    public function store(Product $product, string $contents, string $extension): Product
    {
        $hash = sha1($contents);
        $path = self::DIR . "/{$product->ref}-" . substr($hash, 0, 8) . '.' . strtolower($extension);

        $previous = $product->image;
        Storage::disk('local')->put($path, $contents);
        $product->update(['image' => $path, 'image_hash' => $hash]);

        // Même photo renvoyée = même chemin : ne pas effacer ce qu'on vient d'écrire.
        if ($previous && $previous !== $path) {
            Storage::disk('local')->delete($previous);
        }

        return $product;
    }

    public function remove(Product $product): Product
    {
        if ($product->image) {
            Storage::disk('local')->delete($product->image);
        }
        $product->update(['image' => null, 'image_hash' => null]);

        return $product;
    }
}
