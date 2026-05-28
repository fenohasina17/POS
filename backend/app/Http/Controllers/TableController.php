<?php

namespace App\Http\Controllers;

use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class TableController extends Controller
{
    /**
     * Helper to get active POS ID and perform basic authorization.
     * Returns the active POS ID or an error response.
     */
    private function getAuthorizedPosId(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
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
            return $activePosId;
        }

        // Admin can optionally pass point_of_sale_id in query or use activePosId
        $requestedPosId = $request->query('point_of_sale_id');
        if ($requestedPosId) {
             if (!$user->pointsOfSale->contains($requestedPosId)) {
                return response()->json(['message' => 'Accès refusé pour ce point de vente.'], 403);
            }
            return (int) $requestedPosId;
        }

        return $activePosId; // Admin can operate globally if no POS specified, or on active POS
    }


    // 🔍 Récupérer toutes les tables d'un point de vente
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            $isAdmin = $user->hasRole('admin');
            $targetPosId = $this->getAuthorizedPosId($request);

            if ($targetPosId instanceof \Illuminate\Http\JsonResponse) {
                return $targetPosId;
            }

            $query = Table::with(['pointOfSale']);
            if ($targetPosId) {
                $query->where('point_of_sale_id', $targetPosId);
            } elseif (!$isAdmin) {
                return response()->json(['message' => 'Point de vente actif non défini pour l\'utilisateur.'], 403);
            }

            $tables = $query->orderBy('table_number')->get();

            return response()->json($tables);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération des tables.'], 500);
        }
    }

    // ➕ Créer une nouvelle table
    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            $isAdmin = $user->hasRole('admin');
            $targetPosId = $this->getAuthorizedPosId($request);

            if ($targetPosId instanceof \Illuminate\Http\JsonResponse) {
                return $targetPosId;
            }
            if (!$targetPosId) {
                // Admin can create tables without a specific POS? Usually not.
                return response()->json(['message' => 'Un point de vente doit être spécifié.'], 422);
            }


            $validatedData = $request->validate([
                'table_number' => [
                    'required',
                    'string',
                    Rule::unique('tables', 'table_number')->where(function ($query) use ($targetPosId) {
                        return $query->where('point_of_sale_id', $targetPosId);
                    })
                ],
                'name' => 'nullable|string|max:255',
                'capacity' => 'required|integer|min:1|max:50',
                'description' => 'nullable|string',
                'location' => 'nullable|array',
            ]);

            $table = Table::create(array_merge($validatedData, [
                'point_of_sale_id' => $targetPosId,
                'status' => 'available'
            ]));

            return response()->json($table, 201);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la création de la table.'], 500);
        }
    }

    // 📌 Afficher une table spécifique
    public function show($id, Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            $targetPosId = $this->getAuthorizedPosId($request);

            if ($targetPosId instanceof \Illuminate\Http\JsonResponse) {
                return $targetPosId;
            }

            $table = Table::with(['pointOfSale', 'sales.orderLines.product'])
                ->where('point_of_sale_id', $targetPosId)
                ->findOrFail($id);

            return response()->json($table);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Table non trouvée.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération de la table.'], 500);
        }
    }

    // 🛠 Mettre à jour une table
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            $targetPosId = $this->getAuthorizedPosId($request);

            if ($targetPosId instanceof \Illuminate\Http\JsonResponse) {
                return $targetPosId;
            }
            if (!$targetPosId) {
                return response()->json(['message' => 'Un point de vente doit être spécifié.'], 422);
            }

            $validatedData = $request->validate([
                'table_number' => [
                    'required',
                    'string',
                    Rule::unique('tables', 'table_number')->ignore($id)->where(function ($query) use ($targetPosId) {
                        return $query->where('point_of_sale_id', $targetPosId);
                    })
                ],
                'name' => 'nullable|string|max:255',
                'capacity' => 'required|integer|min:1|max:50',
                'status' => 'required|string|in:available,occupied,reserved,out_of_order',
                'description' => 'nullable|string',
                'location' => 'nullable|array',
            ]);

            $table = Table::where('point_of_sale_id', $targetPosId)->findOrFail($id);

            $table->update($validatedData);

            return response()->json($table);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Table non trouvée.'], 404);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la mise à jour de la table.'], 500);
        }
    }

    // ❌ Supprimer une table
    public function destroy($id, Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }
            
            $targetPosId = $this->getAuthorizedPosId($request);

            if ($targetPosId instanceof \Illuminate\Http\JsonResponse) {
                return $targetPosId;
            }

            $table = Table::where('point_of_sale_id', $targetPosId)->findOrFail($id);

            // Vérifier si la table a des ventes actives
            $activeSales = $table->sales()->whereIn('status', ['pending', 'in_progress'])->count();

            if ($activeSales > 0) {
                return response()->json([
                    'error' => 'Impossible de supprimer cette table car elle contient des ventes actives.'
                ], 400);
            }

            $table->delete();

            return response()->json(['message' => 'Table supprimée avec succès'], 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Table non trouvée.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la suppression de la table.'], 500);
        }
    }

    // 📊 Récupérer les tables disponibles
    public function getAvailableTables(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            $targetPosId = $this->getAuthorizedPosId($request);

            if ($targetPosId instanceof \Illuminate\Http\JsonResponse) {
                return $targetPosId;
            }

            $tables = Table::available()
                ->where('point_of_sale_id', $targetPosId)
                ->orderBy('table_number')
                ->get();

            return response()->json($tables);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération des tables disponibles.'], 500);
        }
    }

    // 📊 Récupérer les tables occupées
    public function getOccupiedTables(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            $targetPosId = $this->getAuthorizedPosId($request);

            if ($targetPosId instanceof \Illuminate\Http\JsonResponse) {
                return $targetPosId;
            }

            $tables = Table::occupied()
                ->where('point_of_sale_id', $targetPosId)
                ->with(['sales' => function($query) {
                    $query->whereIn('status', ['pending', 'in_progress'])
                          ->latest()
                          ->first();
                }])
                ->orderBy('table_number')
                ->get();

            return response()->json($tables);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération des tables occupées.'], 500);
        }
    }

    // 🔄 Changer le statut d'une table
    public function updateStatus(Request $request, $id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            $targetPosId = $this->getAuthorizedPosId($request);

            if ($targetPosId instanceof \Illuminate\Http\JsonResponse) {
                return $targetPosId;
            }
            if (!$targetPosId) {
                return response()->json(['message' => 'Un point de vente doit être spécifié.'], 422);
            }

            $validatedData = $request->validate([
                'status' => 'required|string|in:available,occupied,reserved,out_of_order',
            ]);

            $table = Table::where('point_of_sale_id', $targetPosId)->findOrFail($id);

            $table->update(['status' => $validatedData['status']]);

            return response()->json($table);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Table non trouvée.'], 404);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la mise à jour de la table.'], 500);
        }
    }

    // 📈 Statistiques des tables
    public function getStatistics(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            $targetPosId = $this->getAuthorizedPosId($request);

            if ($targetPosId instanceof \Illuminate\Http\JsonResponse) {
                return $targetPosId;
            }

            $totalTables = Table::where('point_of_sale_id', $targetPosId)->count();
            $availableTables = Table::available()->where('point_of_sale_id', $targetPosId)->count();
            $occupiedTables = Table::occupied()->where('point_of_sale_id', $targetPosId)->count();
            $reservedTables = Table::reserved()->where('point_of_sale_id', $targetPosId)->count();
            $outOfOrderTables = Table::outOfOrder()->where('point_of_sale_id', $targetPosId)->count();

            return response()->json([
                'total_tables' => $totalTables,
                'available_tables' => $availableTables,
                'occupied_tables' => $occupiedTables,
                'reserved_tables' => $reservedTables,
                'out_of_order_tables' => $outOfOrderTables,
                'occupancy_rate' => $totalTables > 0 ? round(($occupiedTables / $totalTables) * 100, 1) : 0
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération des statistiques.'], 500);
        }
    }
}
