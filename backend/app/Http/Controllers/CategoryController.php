<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
     * - point_of_sale_id : (int) Obligatoire si with_pricing=true
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'with_pricing' => 'sometimes|boolean',
                'point_of_sale_id' => 'required_if:with_pricing,true|integer',
                'with_products' => 'sometimes|boolean'
            ]);

            $pointOfSaleId = $request->input('point_of_sale_id');
            $withProducts = $request->boolean('with_products');
            $withPricing = $request->boolean('with_pricing');

            $query = Category::query();

            if ($withProducts) {
                $productsRelation = function ($q) use ($pointOfSaleId, $withPricing) {
                    // CORRECTION : Utiliser la relation 'pricing' pour filtrer les produits du POS
                    if ($pointOfSaleId) {
                        $q->whereHas('pricing', function ($pq) use ($pointOfSaleId) {
                            $pq->where('point_of_sale_id', $pointOfSaleId);
                        });
                    }
                    if ($withPricing && $pointOfSaleId) {
                        $q->with(['pricing' => function ($pq) use ($pointOfSaleId) {
                            $pq->where('point_of_sale_id', $pointOfSaleId);
                        }]);
                    }
                };
                $query->with(['products' => $productsRelation]);
            } elseif ($withPricing && $pointOfSaleId) {
                // Si seulement with_pricing sans with_products, on charge les produits avec pricing
                $query->with([
                    'products' => function ($q) use ($pointOfSaleId) {
                        $q->whereHas('pricing', fn($pq) => $pq->where('point_of_sale_id', $pointOfSaleId))
                          ->with(['pricing' => fn($pq) => $pq->where('point_of_sale_id', $pointOfSaleId)]);
                    }
                ]);
            }

            $categories = $query->get();

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
        $relations = [];
        $pointOfSaleId = $request->input('point_of_sale_id');

        if ($request->boolean('with_products')) {
            $relations[] = 'products';
        }

        if ($request->boolean('with_pricing')) {
            $relations['products'] = function ($query) use ($pointOfSaleId) {
                $query->with([
                    'pricing' => function ($q) use ($pointOfSaleId) {
                        $q->where('point_of_sale_id', $pointOfSaleId);
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
            $category = Category::find($id);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Catégorie non trouvée'
                ], 404);
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
            $pointOfSaleId = $request->query('point_of_sale_id');

            if (!$pointOfSaleId) {
                return response()->json([
                    'success' => false,
                    'message' => 'point_of_sale_id est requis'
                ], 422);
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
                    'pricings' => function ($query) use ($pointOfSaleId) {
                        $query->where('point_of_sale_id', $pointOfSaleId)
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
                'printer' => 'nullable|in:kitchen,bar,receipt,pizza',
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
                'printer' => 'sometimes|nullable|in:kitchen,bar,receipt,pizza',
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
                'printer' => 'required|in:kitchen,bar,receipt,pizza',
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
        if (!in_array($printerType, ['kitchen', 'bar', 'receipt', 'pizza'])) {
            return response()->json([
                'message' => 'Invalid printer type',
                'allowed' => ['kitchen', 'bar', 'receipt', 'pizza']
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
