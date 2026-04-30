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
            $pointOfSaleId = $request->query('point_of_sale_id');

            // Vérifie si l'utilisateur a le rôle 'admin' en utilisant le package Spatie.
            if ($user && $user->hasRole('admin')) {
                // Si l'utilisateur est un admin, on lui permet de filtrer par point de vente
                $query = CashRegister::with(['pointOfSale', 'currentSession.user']);

                if (!empty($pointOfSaleId)) {
                    $query->where('point_of_sale_id', $pointOfSaleId);
                }

                $cashRegisters = $query->get();
                return response()->json([
                    'success' => true,
                    'data' => $cashRegisters,
                    'message' => 'Toutes les caisses enregistreuses et leurs points de vente ont été récupérés.'
                ]);
            }

            // Pour les utilisateurs non-admins, on vérifie s'ils sont liés à un point de vente.
            if (!$user || !$user->point_of_sale_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun point de vente associé à l\'utilisateur.'
                ], 403);
            }

            // Récupère uniquement les caisses du point de vente de l'utilisateur connecté
            // en chargeant également la relation "pointOfSale".
            $cashRegisters = CashRegister::where('point_of_sale_id', $user->point_of_sale_id)
                ->with(['pointOfSale', 'currentSession.user'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $cashRegisters,
                'message' => 'Caisses enregistreuses récupérées pour le point de vente de l\'utilisateur.'
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
    public function show($id)
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->point_of_sale_id) {
                return response()->json(['error' => 'Aucun point de vente associé à l\'utilisateur'], 403);
            }

            $cashRegister = CashRegister::where('point_of_sale_id', $user->point_of_sale_id)->findOrFail($id);
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

            $rules = [
                'name' => ['required', 'string', 'max:255', Rule::unique('cash_registers', 'name')],
            ];

            $isAdmin = $user->hasRole('admin');
            if ($isAdmin) {
                $rules['point_of_sale_id'] = 'required|exists:point_of_sales,id';
            }

            $validated = $request->validate($rules);

            $pointOfSaleId = $isAdmin
                ? $validated['point_of_sale_id'] ?? null
                : $user->point_of_sale_id;

            if (!$pointOfSaleId) {
                return response()->json([
                    'error' => 'Aucun point de vente associé à l\'utilisateur'
                ], 403);
            }

            $cashRegister = CashRegister::create([
                'name' => $validated['name'],
                'point_of_sale_id' => $pointOfSaleId,
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

            $rules = [
                'name' => ['nullable', 'string', 'max:255', Rule::unique('cash_registers', 'name')->ignore($id)],
            ];
            if ($isAdmin) {
                $rules['point_of_sale_id'] = 'nullable|exists:point_of_sales,id';
            }

            $validated = $request->validate($rules);

            if ($isAdmin) {
                $cashRegister = CashRegister::findOrFail($id);
            } else {
                if (!$user->point_of_sale_id) {
                    return response()->json(['error' => 'Aucun point de vente associé à l\'utilisateur'], 403);
                }
                $cashRegister = CashRegister::where('point_of_sale_id', $user->point_of_sale_id)->findOrFail($id);
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
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Utilisateur non authentifié'], 401);
            }

            $isAdmin = $user->hasRole('admin');

            if ($isAdmin) {
                $cashRegister = CashRegister::findOrFail($id);
            } else {
                if (!$user->point_of_sale_id) {
                    return response()->json(['error' => 'Aucun point de vente associé à l\'utilisateur'], 403);
                }
                $cashRegister = CashRegister::where('point_of_sale_id', $user->point_of_sale_id)->findOrFail($id);
            }

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

        if (!$user->hasRole('admin')) {
            if (!$user->point_of_sale_id || $user->point_of_sale_id !== $cashRegister->point_of_sale_id) {
                return response()->json(['message' => 'Accès refusé pour ce point de vente'], Response::HTTP_FORBIDDEN);
            }
        }

        $cashRegister->load(['currentSession.user']);

        return response()->json([
            'success' => true,
            'data' => $cashRegister->currentSession
        ]);
    }
}
