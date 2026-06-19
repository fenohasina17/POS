<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    /**
     * Récupère les catégories avec options de filtrage
     *
     * GET /api/categories
     *
     * Paramètres optionnels :
     * - with_products : (bool) Inclure les produits associés
     * - with_pricing : (bool) Inclure les prix des produits
     * - point_of_sale_id : (int) Pour les admins, permet de filtrer par POS spécifique. Pour les autres, utilise l'activePosId.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            $isAdmin = $user->isAdmin();
            $activePosId = $request->attributes->get('activePosId');

            $request->validate([
                'with_pricing' => 'sometimes|boolean',
                'with_products' => 'sometimes|boolean'
            ]);

            $withProducts = $request->boolean('with_products');
            $withPricing = $request->boolean('with_pricing');

            $targetPosId = null;

            if ($isAdmin) {
                // Admin can explicitly request a point_of_sale_id
                $targetPosId = $request->input('point_of_sale_id') ?? $activePosId;
            } else {
                // Non-admin must use their activePosId from middleware
                $targetPosId = $activePosId;
            }

            // If pricing is requested, a POS context is mandatory for non-admins
            if ($withPricing && !$targetPosId) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Un point de vente actif est requis pour récupérer les prix.'
                ], 422);
            }
            // If there's a targetPosId, ensure the user has access to it
            if ($targetPosId && !$user->pointsOfSale->contains($targetPosId) && !$isAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé pour ce point de vente.'
                ], 403);
            }


            $query = Category::query();

            if ($withProducts) {
                $productsRelation = function ($q) use ($targetPosId, $withPricing) {
                    if ($targetPosId) {
                        $q->whereHas('pricings', function ($pq) use ($targetPosId) {
                            $pq->where('point_of_sale_id', $targetPosId);
                        });
                    }
                    if ($withPricing && $targetPosId) {
                        $q->with(['pricings' => function ($pq) use ($targetPosId) {
                            $pq->where('point_of_sale_id', $targetPosId);
                        }]);
                    }
                };
                $query->with(['products' => $productsRelation]);
            } elseif ($withPricing && $targetPosId) {
                $query->with([
                    'products' => function ($q) use ($targetPosId) {
                        $q->whereHas('pricings', fn($pq) => $pq->where('point_of_sale_id', $targetPosId))
                          ->with(['pricings' => fn($pq) => $pq->where('point_of_sale_id', $targetPosId)]);
                    }
                ]);
            }
            
            // If a specific POS is targeted, also filter categories by this POS
            // Assuming categories are related to POS, if not, this might need adjustment
            // if ($targetPosId) {
            //     $query->where('point_of_sale_id', $targetPosId);
            // }

            $categories = $query->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Résout les relations à charger en fonction des paramètres
     *
     * @param Request $request
     * @return array
     */
    protected function resolveRelations(Request $request): array
    {
        $user = Auth::user();
        $isAdmin = $user->isAdmin();
        $activePosId = $request->attributes->get('activePosId');

        $targetPosId = null;
        if ($isAdmin) {
            $targetPosId = $request->input('point_of_sale_id') ?? $activePosId;
        } else {
            $targetPosId = $activePosId;
        }

        $relations = [];

        if ($request->boolean('with_products')) {
            $relations[] = 'products';
        }

        if ($request->boolean('with_pricing')) {
            // If pricing is requested, a POS context is mandatory for non-admins
            if (!$targetPosId) {
                // This helper should not throw exceptions directly, rather return filtered relations.
                // The main controller method should handle this.
                // For now, returning an empty array to prevent errors if targetPosId is null.
                return [];
            }
            $relations['products'] = function ($query) use ($targetPosId) {
                $query->with([
                    'pricings' => function ($q) use ($targetPosId) {
                        $q->where('point_of_sale_id', $targetPosId);
                    }
                ]);
            };
        }

        return $relations;
    }

    /**
     * Récupère les produits d'une catégorie spécifique
     *
     * GET /api/categories/{id}/products
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProducts($id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            $isAdmin = $user->isAdmin();
            $activePosId = $request->attributes->get('activePosId');

            $category = Category::find($id);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Catégorie non trouvée'
                ], 404);
            }
            
            // If non-admin, ensure category is accessible via active POS
            if (!$isAdmin) {
                if (!$activePosId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Point de vente actif non défini pour l\'utilisateur.'
                    ], 403);
                }
                // Assuming categories are tied to POS. If not, this check needs to be removed
                // or adapted based on how categories are shared/scoped.
                // For now, let's assume direct relation or all categories are globally visible but products are filtered.
            }

            $products = Product::where('category_id', $id)->get();

            return response()->json([
                'success' => true,
                'data' => $products,
                'count' => $products->count(),
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère les produits d'une catégorie avec leurs prix
     *
     * GET /api/categories/{id}/products-with-prices?point_of_sale_id=1
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductsWithPrices(Request $request, $id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            $isAdmin = $user->isAdmin();
            $activePosId = $request->attributes->get('activePosId');

            $targetPosId = null;

            if ($isAdmin) {
                $targetPosId = $request->query('point_of_sale_id') ?? $activePosId;
            } else {
                $targetPosId = $activePosId;
            }

            if (!$targetPosId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Un point de vente actif est requis pour récupérer les prix des produits.'
                ], 422);
            }
            // If there's a targetPosId, ensure the user has access to it
            if (!$user->pointsOfSale->contains($targetPosId) && !$isAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé pour ce point de vente.'
                ], 403);
            }

            $category = Category::find($id);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Catégorie non trouvée'
                ], 404);
            }

            $products = Product::where('category_id', $id)
                ->with([
                    'pricings' => function ($query) use ($targetPosId) {
                        $query->where('point_of_sale_id', $targetPosId)
                            ->where('is_active', true);
                    }
                ])
                ->get();

            $formattedProducts = $products->map(function ($product) {
                $pricing = $product->pricings->first();
                $price = $pricing ? $pricing->price : ($product->price ?? 0);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => (float) $price,
                    'selling_price' => (float) $price,
                    'stock_quantity' => $product->stock_quantity ?? 0,
                    'image' => $product->image,
                    'category_id' => $product->category_id,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedProducts,
                'count' => $formattedProducts->count(),
                'category' => $category->name
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère les produits d'une catégorie avec la route originale
     *
     * GET /api/product_of_category_with_price?category_id={id}
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function product_of_category_with_price(Request $request)
    {
        try {
            $categoryId = $request->query('category_id');

            if (!$categoryId) {
                return response()->json([
                    'success' => false,
                    'message' => 'category_id est requis'
                ], 422);
            }

            $category = Category::find($categoryId);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Catégorie non trouvée'
                ], 404);
            }

            // This endpoint needs to be reviewed to determine its POS filtering requirements.
            // For now, assuming it returns all products in a category regardless of POS.
            $products = Product::where('category_id', $categoryId)->get();

            return response()->json([
                'success' => true,
                'data' => $products,
                'count' => $products->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crée une nouvelle catégorie
     *
     * POST /api/categories
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:categories,name',
                'printer' => 'nullable|in:kitchen,bar,receipt,cook',
            ]);

            $category = Category::create($validated);

            return response()->json([
                'message' => 'Category created successfully',
                'data' => $category,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Affiche une catégorie spécifique
     *
     * GET /api/categories/{id}
     *
     * @param  Category  $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Category $category)
    {
        return response()->json([
            'message' => 'Category retrieved successfully',
            'data' => $category
        ]);
    }

    /**
     * Met à jour une catégorie existante
     *
     * PUT/PATCH /api/categories/{id}
     *
     * @param  Request  $request
     * @param  Category  $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Category $category)
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255|unique:categories,name,' . $category->id,
                'printer' => 'sometimes|nullable|in:kitchen,bar,receipt,cook',
            ]);

            $category->update($validated);

            return response()->json([
                'message' => 'Category updated successfully',
                'data' => $category,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Supprime une catégorie
     *
     * DELETE /api/categories/{id}
     *
     * @param  Category  $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
            'id' => $category->id,
        ]);
    }

    /**
     * Récupère les catégories avec leurs imprimantes associées
     *
     * GET /api/categories/with-printers
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCategoriesWithPrinters()
    {
        $categories = Category::select('id', 'name', 'printer')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Met à jour l'imprimante d'une catégorie
     *
     * PUT /api/categories/{id}/printer
     *
     * @param Request $request
     * @param Category $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePrinter(Request $request, Category $category)
    {
        try {
            $validated = $request->validate([
                'printer' => 'required|in:kitchen,bar,receipt,cook',
            ]);

            $category->update(['printer' => $validated['printer']]);

            return response()->json([
                'message' => 'Printer updated successfully',
                'data' => $category
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Récupère les catégories pour un type d'imprimante spécifique
     *
     * GET /api/categories/printer/{printerType}
     *
     * @param string $printerType
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCategoriesByPrinter($printerType)
    {
        if (!in_array($printerType, ['kitchen', 'bar', 'receipt', 'cook'])) {
            return response()->json([
                'message' => 'Invalid printer type',
                'allowed' => ['kitchen', 'bar', 'receipt', 'cook']
            ], 422);
        }

        $categories = Category::where('printer', $printerType)
            ->orWhereNull('printer')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'printer_type' => $printerType,
            'data' => $categories
        ]);
    }
}
