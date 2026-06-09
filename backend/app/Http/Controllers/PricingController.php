<?php

namespace App\Http\Controllers;

use App\Models\Pricing;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;

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
            $user = Auth::user();

            if (!$user) {
                return response()->json(['error' => 'Utilisateur non authentifié.'], 401);
            }

            $isAdmin = $user->hasRole('admin');
            $activePosId = $request->attributes->get('activePosId');

            if (!$isAdmin) {
                if (!$activePosId) {
                    return response()->json(['message' => 'Point de vente actif non défini pour l\'utilisateur.'], 403);
                }
                if (!$user->pointsOfSale->contains($activePosId)) {
                    return response()->json(['message' => 'Accès refusé pour ce point de vente.'], 403);
                }
            }

            $query = Pricing::with('product');

            if (!$isAdmin) {
                $query->where('point_of_sale_id', $activePosId);
            } else {
                // Admin can optionally filter by a specific point_of_sale_id from query, otherwise use activePosId
                $requestedPosId = $request->query('point_of_sale_id');
                if ($requestedPosId) {
                    $query->where('point_of_sale_id', $requestedPosId);
                } elseif ($activePosId) {
                    $query->where('point_of_sale_id', $activePosId);
                }
                // If admin and neither activePosId nor requestedPosId is present, they see all.
            }

            $pricings = $query->get();

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

            $isAdmin = $user->hasRole('admin');
            $activePosId = $request->attributes->get('activePosId');

            $query = Pricing::with('product')->where('product_id', $id);

            if (!$isAdmin) {
                if (!$activePosId) {
                    return response()->json(['message' => 'Point de vente actif non défini pour l\'utilisateur.'], 403);
                }
                if (!$user->pointsOfSale->contains($activePosId)) {
                    return response()->json(['message' => 'Accès refusé pour ce point de vente.'], 403);
                }
                $query->where('point_of_sale_id', $activePosId);
            } else {
                // Admin can optionally filter by a specific point_of_sale_id from query
                $requestedPosId = $request->query('point_of_sale_id');
                if ($requestedPosId) {
                    $query->where('point_of_sale_id', $requestedPosId);
                } elseif ($activePosId) {
                    $query->where('point_of_sale_id', $activePosId);
                }
            }

            $pricing = $query->first();

            if (!$pricing) {
                return response()->json(['error' => 'Pricing introuvable pour cet utilisateur ou ce POS.'], 404);
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

            $isAdmin = $user->hasRole('admin');
            $activePosId = $request->attributes->get('activePosId');

            $validated = $request->validate([
                'product_id' => 'required|exists:products,id', // Changed from product to products for table name
                'price'      => 'required|numeric',
            ]);

            $targetPosId = null;

            if ($isAdmin) {
                $targetPosId = $request->input('point_of_sale_id') ?? $activePosId;
                if (!$targetPosId) { // Admin must specify POS or have an active one
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

            $validated['point_of_sale_id'] = $targetPosId;

            // Check for unique pricing entry for product and POS
            if (Pricing::where('product_id', $validated['product_id'])
                        ->where('point_of_sale_id', $validated['point_of_sale_id'])
                        ->exists()) {
                return response()->json(['message' => 'Un prix existe déjà pour ce produit dans ce point de vente.'], Response::HTTP_CONFLICT);
            }


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
        \Log::info("PricingController@update started for ID: $id");
        try {
            // Récupérer l'utilisateur connecté
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'Utilisateur non authentifié.'], 401);
            }

            $isAdmin = $user->hasRole('admin');
            $activePosId = $request->attributes->get('activePosId');

            \Log::info("DEBUG - isAdmin: " . ($isAdmin ? 'yes' : 'no') . ", activePosId: " . ($activePosId ?? 'NULL'));

            if (!$isAdmin && !$activePosId) {
                return response()->json(['message' => 'Point de vente actif non défini pour l\'utilisateur.'], 403);
            }
            if (!$isAdmin && !$user->pointsOfSale->contains($activePosId)) {
                return response()->json(['message' => 'Accès refusé pour ce point de vente.'], 403);
            }

            // Validate the incoming data
            \Log::info("Validating request data...");
            $validated = $request->validate([
                'price' => 'required|numeric',
                'product_id' => 'sometimes|required|exists:products,id',
                'point_of_sale_id' => 'sometimes|required|exists:point_of_sales,id',
            ]);
            \Log::info("Validation successful.");
            // Determine the actual POS ID for the query
            $targetPosIdForQuery = null;
            if ($isAdmin) {
                $targetPosIdForQuery = $validated['point_of_sale_id'] ?? $activePosId;
                if (!$targetPosIdForQuery) { // Admin must specify POS or have an active one
                     return response()->json(['message' => 'Un point de vente doit être spécifié ou actif pour un administrateur.'], 422);
                }

            } else {
                $targetPosIdForQuery = $activePosId;
            }


            // Rechercher l'enregistrement de pricing correspondant au product_id ($id) et au point_of_sale_id de l'utilisateur
            // Here $id is the pricing ID, not product ID
            \Log::info("Searching for pricing ID: $id");
            $pricing = Pricing::where('id', $id)
                ->when(!$isAdmin, fn($query) => $query->where('point_of_sale_id', $targetPosIdForQuery))
                ->first();

            if (!$pricing) {
                \Log::info("Pricing not found.");
                return response()->json(['error' => 'Pricing introuvable ou accès refusé.'], 404);
            }

            // Met à jour l'enregistrement de pricing avec la nouvelle valeur de "price"

            \Log::info("Updating pricing ID: $id");
            $pricing->update([
                'price' => $validated['price'],
                'product_id' => $validated['product_id'] ?? $pricing->product_id,
                'point_of_sale_id' => $validated['point_of_sale_id'] ?? $pricing->point_of_sale_id,
            ]);
            \Log::info("Pricing updated successfully.");


            return response()->json($pricing, 200);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);
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
    public function destroy($id, Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'Utilisateur non authentifié.'], 401);
            }

            $isAdmin = $user->hasRole('admin');
            $activePosId = $request->attributes->get('activePosId');

            $pricing = Pricing::query();

            if (!$isAdmin) {
                if (!$activePosId) {
                    return response()->json(['message' => 'Point de vente actif non défini pour l\'utilisateur.'], 403);
                }
                if (!$user->pointsOfSale->contains($activePosId)) {
                    return response()->json(['message' => 'Accès refusé pour ce point de vente.'], 403);
                }
                $pricing->where('point_of_sale_id', $activePosId);
            }

            $pricing = $pricing->findOrFail($id);
            $pricing->delete();
            return response()->json(null, 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Pricing non trouvé ou accès refusé.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to delete pricing.'], 500);
        }
    }
}
