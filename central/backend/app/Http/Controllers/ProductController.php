<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Product::with('category:id,name')->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ref'         => 'required|string|max:20|unique:products,ref',
            'name'        => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'price'       => 'required|numeric|min:0',
            'size'        => 'nullable|string|max:50',
        ]);

        $product = Product::create($data);

        return response()->json($product->load('category:id,name'), 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'ref'         => "sometimes|required|string|max:20|unique:products,ref,{$product->id}",
            'name'        => 'sometimes|required|string|max:255',
            'category_id' => 'sometimes|required|exists:categories,id',
            'price'       => 'sometimes|required|numeric|min:0',
            'size'        => 'nullable|string|max:50',
        ]);

        $product->update($data);

        return response()->json($product->load('category:id,name'));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Produit supprimé.']);
    }

    /**
     * Importe/met à jour le catalogue depuis un fichier Excel (.xlsx).
     * Upsert par "ref" — ne supprime jamais une ligne absente du fichier
     * (évite un vidage accidentel du catalogue sur un réimport partiel).
     * Colonnes attendues : Référence, Nomenclature, Catégorie, Taille, Prix de vente (Ar)
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        $rows = Excel::toArray([], $request->file('file'))[0];
        $header = array_map('trim', $rows[0] ?? []);

        $col = fn(string $name) => array_search($name, $header, true);
        $iRef      = $col('Référence');
        $iName     = $col('Nomenclature');
        $iCategory = $col('Catégorie');
        $iSize     = $col('Taille');
        $iPrice    = $col('Prix de vente (Ar)');

        if ($iRef === false || $iName === false || $iCategory === false || $iPrice === false) {
            return response()->json(['message' => 'Colonnes attendues manquantes (Référence, Nomenclature, Catégorie, Prix de vente (Ar)).'], 422);
        }

        $created = 0;
        $updated = 0;
        $categoryCache = [];

        foreach (array_slice($rows, 1) as $row) {
            $ref = trim((string) ($row[$iRef] ?? ''));
            if ($ref === '') {
                continue;
            }

            $categoryName = trim((string) ($row[$iCategory] ?? ''));
            if (! isset($categoryCache[$categoryName])) {
                $categoryCache[$categoryName] = Category::firstOrCreate(['name' => $categoryName])->id;
            }

            $existing = Product::where('ref', $ref)->first();

            Product::updateOrCreate(
                ['ref' => $ref],
                [
                    'name'        => trim((string) ($row[$iName] ?? '')),
                    'category_id' => $categoryCache[$categoryName],
                    'size'        => $iSize !== false ? (trim((string) ($row[$iSize] ?? '')) ?: null) : null,
                    'price'       => (float) ($row[$iPrice] ?? 0),
                ]
            );

            $existing ? $updated++ : $created++;
        }

        return response()->json(['created' => $created, 'updated' => $updated]);
    }
}
