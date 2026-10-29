<?php

namespace App\Http\Controllers;

use App\Models\PointOfSalePrice;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosPriceController extends Controller
{
    /**
     * Reçoit l'état complet des prix d'une caisse.
     * POST /api/sync/prices
     *
     * Instantané : les références absentes de l'envoi sont retirées pour ce
     * point de vente (produit supprimé ou retiré de la caisse).
     */
    public function receive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'terminal_id'               => 'required|string|max:100',
            'restaurant_id'             => 'required|string|max:100',
            'prices'                    => 'present|array|max:2000',
            'prices.*.ref'              => 'required|string|max:255',
            'prices.*.price'            => 'required|numeric|min:0',
            'prices.*.is_local_override'=> 'required|boolean',
        ]);

        $restaurantId = $validated['restaurant_id'];
        $now = now();

        DB::transaction(function () use ($validated, $restaurantId, $now) {
            $rows = array_map(fn ($p) => [
                'restaurant_id'     => $restaurantId,
                'terminal_id'       => $validated['terminal_id'],
                'product_ref'       => $p['ref'],
                'price'             => $p['price'],
                'is_local_override' => (bool) $p['is_local_override'],
                'reported_at'       => $now,
                'created_at'        => $now,
                'updated_at'        => $now,
            ], $validated['prices']);

            if ($rows) {
                PointOfSalePrice::upsert(
                    $rows,
                    ['restaurant_id', 'product_ref'],
                    ['terminal_id', 'price', 'is_local_override', 'reported_at', 'updated_at']
                );
            }

            PointOfSalePrice::where('restaurant_id', $restaurantId)
                ->whereNotIn('product_ref', array_column($rows, 'product_ref'))
                ->delete();
        });

        return response()->json(['received' => count($validated['prices'])]);
    }

    /**
     * Tableau produit × point de vente pour le dashboard siège.
     * GET /api/pos-prices
     */
    public function index(): JsonResponse
    {
        $byRef = PointOfSalePrice::orderBy('restaurant_id')->get()->groupBy('product_ref');

        $products = Product::with('category:id,name,price_editable')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => [
                'ref'            => $p->ref,
                'name'           => $p->name,
                'category'       => $p->category?->name,
                'price_editable' => (bool) $p->category?->price_editable,
                'central_price'  => $p->price,
                'prices'         => ($byRef[$p->ref] ?? collect())->mapWithKeys(fn ($row) => [
                    $row->restaurant_id => [
                        'price'             => $row->price,
                        'is_local_override' => $row->is_local_override,
                        'reported_at'       => $row->reported_at,
                    ],
                ]),
            ]);

        return response()->json([
            'restaurants' => PointOfSalePrice::distinct()->orderBy('restaurant_id')->pluck('restaurant_id'),
            'products'    => $products,
        ]);
    }
}
