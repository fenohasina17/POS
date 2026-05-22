<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\PointOfSale;
use App\Models\User;


class PointOfSaleController extends Controller
{
    // Liste tous les points de vente
    public function index()
    {
        // Eager load assigned users to reflect many-to-many relationship
        $pointOfSales = PointOfSale::with('assignedUsers')->get();

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
        // Eager load assigned users to reflect many-to-many relationship
        $pointOfSale = PointOfSale::with('assignedUsers')->find($id);

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
     * Utilise la table pivot point_of_sale_user.
     */
    public function detachUser(PointOfSale $pointOfSale, User $user)
    {
        // Dissocier l'utilisateur via la table pivot
        $pointOfSale->assignedUsers()->detach($user->id);

        // Logic for default point_of_sale_id removed as it no longer exists

        return response()->json([
            'message' => 'Utilisateur retiré du point de vente avec succès.',
            'user' => $user->load('pointsOfSale')
        ]);
    }
    /**
     * Associer un utilisateur à un point de vente.
     */
    public function attachUser(PointOfSale $pointOfSale, User $user)
    {
        // Associer l'utilisateur au point de vente via la table pivot
        $pointOfSale->assignedUsers()->syncWithoutDetaching([$user->id]);

        // Logic for default point_of_sale_id removed as it no longer exists

        return response()->json([
            'message' => 'Utilisateur associé au point de vente avec succès.',
            'user' => $user->load('pointsOfSale')
        ]);
    }
}
