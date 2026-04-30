<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Pricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    /**
     * Convertit une chaîne Base64 en une image stockée sur le disque public.
     */
    private function storeBase64Image($base64)
    {
        try {
            $decoded = base64_decode($base64);
            $mime = finfo_buffer(finfo_open(), $decoded, FILEINFO_MIME_TYPE);
            $extension = explode('/', $mime)[1];
            $filename = 'products/' . Str::uuid() . '.' . $extension;

            Storage::disk('public')->put($filename, $decoded);
            return $filename;
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'image' => 'Format d\'image invalide'
            ]);
        }
    }

    /**
     * Récupère les produits disponibles pour le point de vente courant.
     */
    public function index()
    {
        try {
            $pointOfSale = Auth::user()->pointOfSale;

            if (!$pointOfSale) {
                return response()->json(['error' => 'Point of sale not found for user'], 404);
            }

            $products = $pointOfSale->products()->with('pricing')->get();

            return response()->json($products, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur de récupération : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crée un nouveau produit et son tarif.
     */
    public function store(Request $request)
    {
        try {
            $pointOfSale = Auth::user()->pointOfSale;

            if (!$pointOfSale) {
                return response()->json(['error' => 'Point of sale not found for user'], 404);
            }

            $validated = $request->validate([
                'name'        => 'required|string|max:255',
                'ref'        => 'required|string|max:8',
                'price'       => 'required|numeric|min:0',
                'status'      => 'required|boolean',
                'category_id' => 'required|exists:categories,id',
                'image'       => 'nullable|base64image|base64max:2048'
            ]);

            $imagePath = !empty($validated['image']) ? $this->storeBase64Image($validated['image']) : 'default-product-image.jpg';

            $product = Product::create([
                'name'        => $validated['name'],
                'ref'         => $validated['ref'],
                'category_id' => $validated['category_id'],
                'status'      => $validated['status'],
                'image'       => $imagePath,
            ]);

            // Attach product to point of sale pivot table
            $pointOfSale->products()->syncWithoutDetaching([$product->id]);

            // Création du pricing associé au produit
            Pricing::create([
                'product_id'      => $product->id,
                'point_of_sale_id' => $pointOfSale->id,
                'price'            => $validated['price'],
            ]);

            return response()->json([
                'success' => 'Produit créé avec succès',
                'product' => $product->load('pricing')
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->validator->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur de création : ' . $e->getMessage()], 500);
        }
    }

    /**
     * Met à jour un produit et son prix associé.
     */
    public function update(Request $request, Product $product)
    {
        try {
            $pointOfSale = Auth::user()->pointOfSale;

            if (!$pointOfSale) {
                return response()->json(['error' => 'Point of sale not found for user'], 404);
            }

            $validated = $request->validate([
                'name'        => 'required|string|max:255',
                'ref'        => 'required|string|max:8',
                'price'       => 'required|numeric|min:0',
                'status'      => 'required|boolean',
                'category_id' => 'required|exists:categories,id',
                'image'       => 'nullable|base64image|base64max:2048'
            ]);

            if (!empty($validated['image'])) {
                $imagePath = $this->storeBase64Image($validated['image']);
                if ($product->image !== 'default-product-image.jpg') {
                    Storage::disk('public')->delete($product->image);
                }
                $validated['image'] = $imagePath;
            } else {
                $validated['image'] = $product->image;
            }

            // Mise à jour du produit
            $product->update($validated);

            // Attach product to point of sale pivot table
            $pointOfSale->products()->syncWithoutDetaching([$product->id]);

            // Mise à jour du tarif associé
            $pricing = Pricing::where('product_id', $product->id)
                ->where('point_of_sale_id', $pointOfSale->id)
                ->first();

            if ($pricing) {
                $pricing->update(['price' => $validated['price']]);
            } else {
                Pricing::create([
                    'product_id'      => $product->id,
                    'point_of_sale_id' => $pointOfSale->id,
                    'price'            => $validated['price'],
                ]);
            }
            return response()->json([
                'product' => $product->fresh('pricing'),
                'message' => 'Produit mis à jour avec succès'
            ], 200);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->validator->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Supprime un produit et son tarif.
     */
    public function destroy(Product $product)
    {
        try {
            $pointOfSale = Auth::user()->pointOfSale;

            if (!$pointOfSale) {
                return response()->json(['error' => 'Point of sale not found for user'], 404);
            }

            if ($product->image !== 'default-product-image.jpg') {
                Storage::disk('public')->delete($product->image);
            }

            // Detach product from point of sale pivot table
            $pointOfSale->products()->detach($product->id);

            // Suppression des pricings liés au produit
            Pricing::where('product_id', $product->id)->delete();

            // Suppression du produit
            $product->delete();

            return response()->json(['success' => 'Produit supprimé'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Affiche les détails d'un produit spécifique avec son tarif pour le point de vente courant.
     */
    public function show(Product $product)
    {
        try {
            $pointOfSaleId = Auth::user()->point_of_sale_id;

            $pricing = Pricing::where('product_id', $product->id)
                ->where('point_of_sale_id', $pointOfSaleId)
                ->first();

            $productData = $product->toArray();
            $productData['price'] = $pricing ? $pricing->price : null;

            return response()->json($productData, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la récupération du produit : ' . $e->getMessage()
            ], 500);
        }
    }
}
