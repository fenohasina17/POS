<?php

namespace App\Http\Controllers;

use App\Models\Pricing;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class PricingController extends Controller
{
    /**
     * Affiche la liste des enregistrements de pricing pour le point de vente de l'utilisateur connecté.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Récupérer l'utilisateur connecté
            $user = auth()->user();

            if (!$user) {
                return response()->json(['error' => 'Utilisateur non authentifié.'], 401);
            }

            // Filtrer les pricings selon le point_of_sale_id provenant de l'utilisateur connecté
            $pricings = Pricing::with('product')
                ->where('point_of_sale_id', $user->point_of_sale_id)
                ->get();

            return response()->json($pricings, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération des pricings.'], 500);
        }
    }

    /**
     * Affiche le pricing associé à un produit spécifique pour le point de vente de l'utilisateur connecté.
     *
     * @param int $id  L'identifiant du produit
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id, Request $request)
    {
        try {
            // Récupérer l'utilisateur connecté
            $user = auth()->user();

            if (!$user) {
                return response()->json(['error' => 'Utilisateur non authentifié.'], 401);
            }

            // Vérifier que l'enregistrement de pricing existe pour ce product_id et le point_of_sale_id de l'utilisateur
            $pricing = Pricing::with('product')
                ->where('point_of_sale_id', $user->point_of_sale_id)
                ->where('product_id', $id)
                ->first();

            if (!$pricing) {
                return response()->json(['error' => 'Pricing introuvable pour cet utilisateur.'], 404);
            }

            return response()->json($pricing, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération du pricing.'], 500);
        }
    }

    /**
     * Crée un nouvel enregistrement de pricing.
     * Le champ "product_id" est requis et validé, et le "point_of_sale_id" est récupéré depuis l'utilisateur authentifié.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // Récupérer l'utilisateur connecté
            $user = auth()->user();

            if (!$user) {
                return response()->json(['error' => 'Utilisateur non authentifié.'], 401);
            }

            // Valider les champs requis (on ne valide pas point_of_sale_id ici)
            $validated = $request->validate([
                'product_id' => 'required|exists:product,id',
                'price'      => 'required|numeric',
            ]);

            // Affecter le point_of_sale_id depuis l'utilisateur authentifié
            $validated['point_of_sale_id'] = $user->point_of_sale_id;

            $pricing = Pricing::create($validated);
            return response()->json($pricing, 201);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to create pricing.'], 500);
        }
    }

    /**
     * Met à jour un enregistrement de pricing existant.
     *
     * Le champ "product_id" peut être modifié.
     *
     * @param Request $request
     * @param int $id  L'identifiant de l'enregistrement de pricing
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request)
    {
        try {
            // Récupérer l'utilisateur connecté
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'Utilisateur non authentifié.'], 401);
            }
         
  // Valider les données envoyées (ici, le champ "price" est requis et doit être numérique)
            $validated = $request->validate([
                'price' => 'required|numeric',
            ]);
    
            
            // Rechercher l'enregistrement de pricing correspondant au product_id ($id) et au point_of_sale_id de l'utilisateur
            $pricing = Pricing::with('product')
                ->where('point_of_sale_id', $user->point_of_sale_id)
                ->where('product_id', $id)
                ->first();

            if (!$pricing) {
                return response()->json(['error' => 'Pricing introuvable pour cet utilisateur.'], 404);
            }
  
            // Met à jour l'enregistrement de pricing avec la nouvelle valeur de "price"
            $pricing->update($validated);
            

            return response()->json($pricing, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la mise à jour du pricing.'], 500);
        }
    }
    
    

    /**
     * Supprime un enregistrement de pricing.
     *
     * @param int $id  L'identifiant de l'enregistrement de pricing
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $pricing = Pricing::findOrFail($id);
            $pricing->delete();
            return response()->json(null, 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Pricing not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to delete pricing.'], 500);
        }
    }
}
