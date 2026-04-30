<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\PointOfSale;
use App\Models\User;


class PointOfSaleController extends Controller
{
    public function getProductsByPointOfSale($pointOfSaleId)
    {
        $pointOfSale = PointOfSale::with(['pricings.product'])
            ->findOrFail($pointOfSaleId);

        $productsWithPrices = $pointOfSale->pricings->map(function ($pricing) {
            return [
                'name' => $pricing->product->name,
                'price' => $pricing->price,
                'category_name' => $pricing->product->category ? $pricing->product->category->name : null, // Vérifier si la catégorie existe
            ];
        });

        return response()->json($productsWithPrices);
    }
    // Liste tous les points de vente

    public function index()
    {
        $pointOfSales = PointOfSale::with('users')->get();

        return response()->json($pointOfSales);
    }

    // Créer un point de vente
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:point_of_sales,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pointOfSale = PointOfSale::create([
            'name' => $request->name,
        ]);

        return response()->json($pointOfSale, 201);
    }

    // Affiche un point de vente
    public function show($id)
    {
        $pointOfSale = PointOfSale::with('users')->find($id);

        if (!$pointOfSale) {
            return response()->json(['message' => 'Point de vente non trouvé'], 404);
        }

        return response()->json($pointOfSale);
    }

    // Met à jour un point de vente
    public function update(Request $request, $id)
    {
        $pointOfSale = PointOfSale::find($id);

        if (!$pointOfSale) {
            return response()->json(['message' => 'Point de vente non trouvé'], 404);
        }

        $pointOfSale->update([
            'name' => $request->name ?? $pointOfSale->name,
        ]);

        return response()->json($pointOfSale);
    }

    // Supprime un point de vente
    public function destroy($id)
    {
        $pointOfSale = PointOfSale::find($id);

        if (!$pointOfSale) {
            return response()->json(['message' => 'Point de vente non trouvé'], 404);
        }

        $pointOfSale->delete();

        return response()->json(['message' => 'Point de vente supprimé']);
    }
    /* Détacher un utilisateur d'un point de vente.
     * Met à jour l'utilisateur en définissant point_of_sale_id = null.
     */
    public function detachUser(PointOfSale $pointOfSale, User $user)
    {
        // Vérifier que l'utilisateur appartient bien à ce point de vente (optionnel)
        if ($user->point_of_sale_id !== $pointOfSale->id) {
            return response()->json([
                'message' => 'Cet utilisateur n\'est pas associé à ce point de vente.'
            ], 422);
        }

        // Dissocier l'utilisateur
        $user->point_of_sale_id = null;
        $user->save();

        return response()->json([
            'message' => 'Utilisateur retiré du point de vente avec succès.',
            'user' => $user
        ]);
    }
    /**
     * Associer un utilisateur à un point de vente.
     */
    public function attachUser(PointOfSale $pointOfSale, User $user)
    {
        // Vérifier que l'utilisateur n'est pas déjà associé à un autre point de vente (optionnel)
        if ($user->point_of_sale_id !== null && $user->point_of_sale_id !== $pointOfSale->id) {
            return response()->json([
                'message' => 'Cet utilisateur est déjà associé à un autre point de vente.'
            ], 422);
        }

        // Associer l'utilisateur au point de vente
        $user->point_of_sale_id = $pointOfSale->id;
        $user->save();

        return response()->json([
            'message' => 'Utilisateur associé au point de vente avec succès.',
            'user' => $user
        ]);
    }
}
