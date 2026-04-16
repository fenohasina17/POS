<?php

namespace App\Http\Controllers;

use App\Models\Category;
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
            // Validation des paramètres
            $request->validate([
                'with_pricing' => 'sometimes|boolean',
                'point_of_sale_id' => 'required_if:with_pricing,true|integer',
                'with_products' => 'sometimes|boolean'
            ]);

            // Configuration des relations à charger
            $relations = $this->resolveRelations($request);

            // Construction de la requête
            $query = Category::query();

            if (!empty($relations)) {
                $query->with($relations);
            }

            $categories = $query->get();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Server error',
                'error' => $e->getMessage()
            ], 500);
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

        // Option 1: Chargement simple des produits
        if ($request->boolean('with_products')) {
            $relations[] = 'products';
        }

        // Option 2: Chargement des produits avec prix
        if ($request->boolean('with_pricing')) {
            $relations['products'] = function ($query) use ($pointOfSaleId) {
                $query->with(['pricing' => function ($q) use ($pointOfSaleId) {
                    $q->where('point_of_sale_id', $pointOfSaleId);
                }]);
            };
        }

        return $relations;
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