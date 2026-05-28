<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Pricing;
use App\Models\PointOfSale; // Added to retrieve POS for filtering
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response; // Added for HTTP status constants

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
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            $isAdmin = $user->hasRole('admin');
            $activePosId = $request->attributes->get('activePosId');

            $query = Product::query();

            // Admins can see all products or filter by requested POS
            if ($isAdmin) {
                $requestedPosId = $request->query('point_of_sale_id');
                if ($requestedPosId) {
                    $query->whereHas('pointsOfSale', fn($q) => $q->where('point_of_sales.id', $requestedPosId));
                } elseif ($activePosId) {
                    $query->whereHas('pointsOfSale', fn($q) => $q->where('point_of_sales.id', $activePosId));
                }
            }
            // Non-admins must have an active POS set by middleware
            else {
                if (!$activePosId) {
                    return response()->json(['message' => 'Point de vente actif non défini pour l\'utilisateur.'], 403);
                }
                if (!$user->pointsOfSale->contains($activePosId)) {
                    return response()->json(['message' => 'Accès refusé pour ce point de vente.'], 403);
                }
                $query->whereHas('pointsOfSale', fn($q) => $q->where('point_of_sales.id', $activePosId));
            }

            $cacheKey = "products_pos_{$activePosId}_admin_{$isAdmin}_req_{$requestedPosId}";
            $products = \Illuminate\Support\Facades\Cache::remember($cacheKey, 60, function () use ($query, $activePosId, $requestedPosId, $isAdmin) {
                return $query->with([
                    'pricings' => function ($q) use ($activePosId, $requestedPosId, $isAdmin) {
                        // Filter pricing by active POS or requested POS if admin
                        if ($isAdmin && $requestedPosId) {
                             $q->where('point_of_sale_id', $requestedPosId);
                        } elseif ($activePosId) {
                            $q->where('point_of_sale_id', $activePosId);
                        }
                    }
                ])->get();
            });


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
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            $isAdmin = $user->hasRole('admin');
            $activePosId = $request->attributes->get('activePosId');

            $validated = $request->validate([
                'name'        => 'required|string|max:255',
                'ref'         => 'required|string|max:8',
                'price'       => 'required|numeric|min:0',
                'status'      => 'required|boolean',
                'category_id' => 'required|exists:categories,id',
                'image'       => 'nullable|base64image|base64max:2048',
                'point_of_sale_id' => ($isAdmin ? 'nullable' : 'prohibited') . '|exists:point_of_sales,id',
            ]);

            // Determine target POS: prefer payload, then active POS from middleware, then any existing POS
            $targetPosId = $validated['point_of_sale_id'] ?? $activePosId ?? \App\Models\PointOfSale::first()?->id;
            if (!$targetPosId) {
                return response()->json(['message' => 'Un point de vente doit être spécifié ou actif pour un administrateur.'], 422);
            }
            if (!$isAdmin) {
                if (!$activePosId) {
                    return response()->json(['message' => 'Point de vente actif non défini pour l\'utilisateur.'], 403);
                }
                if (!$user->pointsOfSale->contains($activePosId)) {
                    return response()->json(['message' => 'Accès refusé pour ce point de vente.'], 403);
                }
                $targetPosId = $activePosId;
            }

            $imagePath = !empty($validated['image']) ? $this->storeBase64Image($validated['image']) : 'default-product-image.jpg';

            $product = Product::create([
                'name'        => $validated['name'],
                'ref'         => $validated['ref'],
                'category_id' => $validated['category_id'],
                'status'      => $validated['status'],
                'image'       => $imagePath,
            ]);

            // Attach product to the target point of sale via pivot table
            $pos = PointOfSale::find($targetPosId);
            $pos->products()->syncWithoutDetaching([$product->id]);

            // Création du pricing associé au produit pour le POS cible
            Pricing::create([
                'product_id'       => $product->id,
                'point_of_sale_id' => $targetPosId,
                'price'            => $validated['price'],
            ]);

            return response()->json([
                'success' => 'Produit créé avec succès',
                'product' => $product->load(['pricings' => fn($q) => $q->where('point_of_sale_id', $targetPosId)])
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
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            $isAdmin = $user->hasRole('admin');
            $activePosId = $request->attributes->get('activePosId');

            $validated = $request->validate([
                'name'        => 'sometimes|required|string|max:255',
                'ref'         => 'sometimes|required|string|max:8',
                'price'       => 'sometimes|required|numeric|min:0',
                'status'      => 'sometimes|required|boolean',
                'category_id' => 'sometimes|required|exists:categories,id',
                'image'       => 'nullable|base64image|base64max:2048',
                'point_of_sale_id' => ($isAdmin ? 'nullable' : 'prohibited') . '|exists:point_of_sales,id',
            ]);

            $targetPosId = null;
            if ($isAdmin) {
                $targetPosId = $validated['point_of_sale_id'] ?? $activePosId;
                // Fallback: use any POS if none specified
                if (!$targetPosId) {
                    // Fallback: use the first POS in the system if admin has none assigned
                    $firstPos = \App\Models\PointOfSale::first();
                    $targetPosId = $firstPos ? $firstPos->id : null;
                }
                if (!$targetPosId) {
                    return response()->json(['message' => 'Un point de vente doit être spécifié ou actif pour un administrateur.'], 422);
                }
            } else {
                if (!$activePosId) {
                    return response()->json(['message' => 'Point de vente actif non défini pour l\'utilisateur.'], 403);
                }
                if (!$user->pointsOfSale->contains($activePosId)) {
                    return response()->json(['message' => 'Accès refusé pour ce point de vente.'], 403);
                }
                $targetPosId = $activePosId;
            }

            if (!empty($validated['image'])) {
                $imagePath = $this->storeBase64Image($validated['image']);
                if ($product->image !== 'default-product-image.jpg') {
                    Storage::disk('public')->delete($product->image);
                }
                $validated['image'] = $imagePath;
            } else {
                // Ensure image field is not unintentionally removed if not provided
                $validated['image'] = $product->image;
            }

            // Mise à jour du produit
            $product->update($validated);

            // Attach product to the target point of sale pivot table (ensuring it's still attached)
            $pos = PointOfSale::find($targetPosId);
            $pos->products()->syncWithoutDetaching([$product->id]); // Ensure product is associated with this POS

            // Mise à jour ou création du tarif associé
            if (isset($validated['price'])) {
                $pricing = Pricing::where('product_id', $product->id)
                    ->where('point_of_sale_id', $targetPosId)
                    ->first();

                if ($pricing) {
                    $pricing->update(['price' => $validated['price']]);
                } else {
                    Pricing::create([
                        'product_id'       => $product->id,
                        'point_of_sale_id' => $targetPosId,
                        'price'            => $validated['price'],
                    ]);
                }
            }
            return response()->json([
                'product' => $product->fresh(['pricings' => fn($q) => $q->where('point_of_sale_id', $targetPosId)]),
                'message' => 'Produit mis à jour avec succès'
            ], 200);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->validator->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur de mise à jour : ' . $e->getMessage()], 500);
        }
    }

    /**
     * Supprime un produit et son tarif.
     */
    public function destroy(Product $product, Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            $isAdmin = $user->hasRole('admin');
            $activePosId = $request->attributes->get('activePosId');

            $targetPosId = null;
            if ($isAdmin) {
                $targetPosId = $request->input('point_of_sale_id') ?? $activePosId;
                if (!$targetPosId) {
                    return response()->json(['message' => 'Un point de vente doit être spécifié ou actif pour un administrateur.'], 422);
                }

            } else {
                if (!$activePosId) {
                    return response()->json(['message' => 'Point de vente actif non défini pour l\'utilisateur.'], 403);
                }
                if (!$user->pointsOfSale->contains($activePosId)) {
                    return response()->json(['message' => 'Accès refusé pour ce point de vente.'], 403);
                }
                $targetPosId = $activePosId;
            }
            
            // Ensure product is associated with the target POS before deletion
            if (!$product->pointsOfSale->contains($targetPosId)) {
                 return response()->json(['message' => 'Produit non associé à ce point de vente.'], 403);
            }


            if ($product->image !== 'default-product-image.jpg') {
                Storage::disk('public')->delete($product->image);
            }

            // Detach product from point of sale pivot table for the specific target POS
            $pos = PointOfSale::find($targetPosId);
            $pos->products()->detach($product->id);

            // Suppression du pricing lié au produit pour ce POS
            Pricing::where('product_id', $product->id)
                ->where('point_of_sale_id', $targetPosId)
                ->delete();

            // Si le produit n'est plus associé à aucun POS, on peut envisager de le supprimer complètement
            // Cela dépend de la logique métier : un produit existe-t-il indépendamment des POS ?
            // Pour l'instant, ne pas supprimer le produit si il est encore rattaché à d'autres POS.
            if ($product->pointsOfSale()->count() === 0) {
                 $product->delete();
            }

            return response()->json(['success' => 'Produit supprimé du point de vente avec succès'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur de suppression : ' . $e->getMessage()], 500);
        }
    }

    /**
     * Affiche les détails d'un produit spécifique avec son tarif pour le point de vente courant.
     */
    public function show(Product $product, Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            $isAdmin = $user->hasRole('admin');
            $activePosId = $request->attributes->get('activePosId');

            $targetPosId = null;
            if ($isAdmin) {
                $targetPosId = $request->query('point_of_sale_id') ?? $activePosId;
                if (!$targetPosId) {
                    return response()->json(['message' => 'Un point de vente doit être spécifié ou actif pour un administrateur.'], 422);
                }

            } else {
                if (!$activePosId) {
                    return response()->json(['message' => 'Point de vente actif non défini pour l\'utilisateur.'], 403);
                }
                if (!$user->pointsOfSale->contains($activePosId)) {
                    return response()->json(['message' => 'Accès refusé pour ce point de vente.'], 403);
                }
                $targetPosId = $activePosId;
            }

            // Ensure the product is associated with the target POS
            if (!$product->pointsOfSale->contains($targetPosId)) {
                return response()->json(['message' => 'Produit non associé à ce point de vente.'], 404);
            }

            $pricing = Pricing::where('product_id', $product->id)
                ->where('point_of_sale_id', $targetPosId)
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
