<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CashRegister;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;

class CashRegisterController extends Controller
{
    // 🔍 Liste toutes les caisses du point de vente de l'utilisateur connecté
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $requestedPosId = $request->query('point_of_sale_id'); // From query parameter

            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }
            
            $isAdmin = $user->hasRole('admin');
            $activePosId = $request->attributes->get('activePosId'); // From middleware

            $assignedPosIds = $user->pointsOfSale()->pluck('point_of_sales.id')->toArray();

            // Admins can see all or filter by requested POS ID
            if ($isAdmin) {
                $query = CashRegister::with(['pointOfSale', 'currentSession.user']);
                if ($requestedPosId) {
                    $query->where('point_of_sale_id', $requestedPosId);
                }
                $cashRegisters = $query->get();
                return response()->json([
                    'success' => true,
                    'data' => $cashRegisters,
                    'message' => 'Toutes les caisses enregistreuses et leurs points de vente ont été récupérés.'
                ]);
            }

            // Non-admins must have an active POS set by middleware
            if (!$activePosId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Point de vente actif non défini pour l\'utilisateur.'
                ], 403);
            }

            // Non-admins can only see registers from their active POS
            if (!in_array($activePosId, $assignedPosIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé pour ce point de vente.'
                ], 403);
            }

            $cashRegisters = CashRegister::where('point_of_sale_id', $activePosId)
                ->with(['pointOfSale', 'currentSession.user'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $cashRegisters,
                'message' => 'Caisses enregistreuses récupérées pour le point de vente actif de l\'utilisateur.'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des caisses : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur interne du serveur.'
            ], 500);
        }
    }


    // 📌 Affiche une caisse spécifique associée à l'utilisateur
    public function show($id, Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) return response()->json(['error' => 'Non authentifié'], 401);
            
            $isAdmin = $user->hasRole('admin');
            $activePosId = $request->attributes->get('activePosId'); // From middleware

            $query = CashRegister::query();
            
            if (!$isAdmin) {
                if (!$activePosId) {
                    return response()->json(['error' => 'Point de vente actif non défini pour l\'utilisateur'], 403);
                }
                $assignedPosIds = $user->pointsOfSale()->pluck('point_of_sales.id')->toArray();
                if (!in_array($activePosId, $assignedPosIds)) {
                    return response()->json(['message' => 'Accès refusé pour ce point de vente.'], 403);
                }
                $query->where('point_of_sale_id', $activePosId);
            }
            
            $cashRegister = $query->findOrFail($id);
            return response()->json($cashRegister);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Caisse introuvable'], 404);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération de la caisse : ' . $e->getMessage());
            return response()->json(['error' => 'Erreur interne du serveur'], 500);
        }
    }

    // ➕ Créer une nouvelle caisse liée à l'utilisateur connecté
    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Utilisateur non authentifié'], 401);
            }

            $isAdmin = $user->hasRole('admin');
            $activePosId = $request->attributes->get('activePosId');

            $rules = [
                'name' => ['required', 'string', 'max:255', Rule::unique('cash_registers', 'name')->where(function ($query) use ($request, $isAdmin, $activePosId) {
                    $targetPosId = $isAdmin ? ($request->input('point_of_sale_id') ?? $activePosId) : $activePosId;
                    if ($targetPosId) {
                        return $query->where('point_of_sale_id', $targetPosId);
                    }
                    return $query; // Should not happen for non-admins
                })],
            ];

            if ($isAdmin) {
                $rules['point_of_sale_id'] = 'required|exists:point_of_sales,id';
            }

            $validated = $request->validate($rules);

            $targetPointOfSaleId = $isAdmin
                ? $validated['point_of_sale_id'] ?? $activePosId
                : $activePosId;

            if (!$targetPointOfSaleId) {
                return response()->json([
                    'error' => 'Point de vente actif non défini ou manquant.'
                ], 403);
            }

            // Vérifier que l'admin est associé à ce POS s'il le spécifie
            if ($isAdmin && $targetPointOfSaleId && !$user->pointsOfSale->contains($targetPointOfSaleId)) {
                return response()->json(['error' => 'Accès refusé pour ce point de vente.'], 403);
            }
            
            $cashRegister = CashRegister::create([
                'name' => $validated['name'],
                'point_of_sale_id' => $targetPointOfSaleId,
            ]);

            return response()->json([
                'message' => 'Caisse créée avec succès',
                'data' => $cashRegister->fresh()->load(['pointOfSale', 'currentSession.user']),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création de la caisse : ' . $e->getMessage());
            return response()->json(['error' => 'Erreur interne du serveur'], 500);
        }
    }

    // 🛠 Met à jour une caisse si elle appartient à l'utilisateur
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Utilisateur non authentifié'], 401);
            }

            $isAdmin = $user->hasRole('admin');
            $activePosId = $request->attributes->get('activePosId');

            if (!$isAdmin && !$activePosId) {
                return response()->json(['error' => 'Point de vente actif non défini pour l\'utilisateur'], 403);
            }

            $rules = [
                'name' => ['nullable', 'string', 'max:255', Rule::unique('cash_registers', 'name')->ignore($id)->where(function ($query) use ($request, $isAdmin, $activePosId) {
                    $targetPosId = $isAdmin ? ($request->input('point_of_sale_id') ?? $activePosId) : $activePosId;
                    if ($targetPosId) {
                        return $query->where('point_of_sale_id', $targetPosId);
                    }
                    return $query; // Should not happen for non-admins
                })],
            ];
            if ($isAdmin) {
                $rules['point_of_sale_id'] = 'nullable|exists:point_of_sales,id';
            }

            $validated = $request->validate($rules);

            $cashRegister = CashRegister::query();
            if (!$isAdmin) {
                $assignedPosIds = $user->pointsOfSale()->pluck('point_of_sales.id')->toArray();
                if (!in_array($activePosId, $assignedPosIds)) {
                    return response()->json(['message' => 'Accès refusé pour ce point de vente.'], 403);
                }
                $cashRegister->where('point_of_sale_id', $activePosId);
            }
            $cashRegister = $cashRegister->findOrFail($id);

            // Vérifier que l'admin est associé à ce POS s'il le spécifie
            if ($isAdmin && isset($validated['point_of_sale_id']) && $validated['point_of_sale_id'] && !$user->pointsOfSale->contains($validated['point_of_sale_id'])) {
                return response()->json(['error' => 'Accès refusé pour ce point de vente.'], 403);
            }

            if (array_key_exists('name', $validated)) {
                $cashRegister->name = $validated['name'] ?? $cashRegister->name;
            }

            if ($isAdmin && array_key_exists('point_of_sale_id', $validated)) {
                $cashRegister->point_of_sale_id = $validated['point_of_sale_id'] ?? $cashRegister->point_of_sale_id;
            }

            $cashRegister->save();

            return response()->json([
                'message' => 'Caisse mise à jour avec succès',
                'data' => $cashRegister->fresh()->load(['pointOfSale', 'currentSession.user'])
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Caisse introuvable'], 404);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour de la caisse : ' . $e->getMessage());
            return response()->json(['error' => 'Erreur interne du serveur'], 500);
        }
    }

    // ❌ Supprime une caisse uniquement si elle appartient à l'utilisateur
    public function destroy($id, Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Utilisateur non authentifié'], 401);
            }

            $isAdmin = $user->hasRole('admin');
            $activePosId = $request->attributes->get('activePosId');

            if (!$isAdmin && !$activePosId) {
                return response()->json(['error' => 'Point de vente actif non défini pour l\'utilisateur'], 403);
            }

            $cashRegister = CashRegister::query();
            if (!$isAdmin) {
                $assignedPosIds = $user->pointsOfSale()->pluck('point_of_sales.id')->toArray();
                if (!in_array($activePosId, $assignedPosIds)) {
                    return response()->json(['message' => 'Accès refusé pour ce point de vente.'], 403);
                }
                $cashRegister->where('point_of_sale_id', $activePosId);
            }
            $cashRegister = $cashRegister->findOrFail($id);

            $cashRegister->delete();

            return response()->json(['message' => 'Caisse supprimée avec succès']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Caisse introuvable'], 404);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression de la caisse : ' . $e->getMessage());
            return response()->json(['error' => 'Erreur interne du serveur'], 500);
        }
    }

    /**
     * Retourne l'adresse IP du client qui effectue la requête.
     */
    public function clientIp(Request $request)
    {
        $ip = $request->ip();
        $forwarded = $request->header('X-Forwarded-For');

        return response()->json([
            'ip' => $ip,
            'forwarded_for' => $forwarded,
            'detected_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Retourne la session ouverte pour une caisse donnée.
     */
    public function currentSession(Request $request, CashRegister $cashRegister)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $activePosId = $request->attributes->get('activePosId');

        if (!$user->hasRole('admin')) {
            if (!$activePosId) {
                return response()->json(['message' => 'Point de vente actif non défini pour l\'utilisateur'], Response::HTTP_FORBIDDEN);
            }
            $assignedPosIds = $user->pointsOfSale()->pluck('point_of_sales.id')->toArray();
            if (!in_array($activePosId, $assignedPosIds)) {
                return response()->json(['message' => 'Accès refusé pour ce point de vente'], Response::HTTP_FORBIDDEN);
            }
            // Ensure the cash register belongs to the active POS
            if ($cashRegister->point_of_sale_id !== $activePosId) {
                return response()->json(['message' => 'Caisse non associée au point de vente actif'], Response::HTTP_FORBIDDEN);
            }
        }

        $cashRegister->load(['currentSession.user']);

        return response()->json([
            'success' => true,
            'data' => $cashRegister->currentSession
        ]);
    }
}
