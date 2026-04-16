<?php

namespace App\Http\Controllers;

use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class TableController extends Controller
{
    /**
     * Vérifier que l'utilisateur a accès au point de vente
     */
    private function verifyPointOfSaleAccess($user)
    {
        $pointOfSaleId = $user->point_of_sale_id;

        if (!$pointOfSaleId) {
            return response()->json(['error' => 'Point de vente non défini pour cet utilisateur.'], 400);
        }

        // Vérifier que l'utilisateur a accès à ce point de vente
        $userPointOfSale = $user->pointOfSale;
        if (!$userPointOfSale || $userPointOfSale->id !== $pointOfSaleId) {
            return response()->json(['error' => 'Accès non autorisé à ce point de vente.'], 403);
        }

        return $pointOfSaleId;
    }

    // 🔍 Récupérer toutes les tables d'un point de vente
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $pointOfSaleId = $this->verifyPointOfSaleAccess($user);

            if ($pointOfSaleId instanceof \Illuminate\Http\JsonResponse) {
                return $pointOfSaleId;
            }

            $tables = Table::with(['pointOfSale'])
                ->where('point_of_sale_id', $pointOfSaleId)
                ->orderBy('table_number')
                ->get();

            return response()->json($tables);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération des tables.'], 500);
        }
    }

    // ➕ Créer une nouvelle table
    public function store(Request $request)
    {
        try {
            $user = $request->user();
            $pointOfSaleId = $this->verifyPointOfSaleAccess($user);

            if ($pointOfSaleId instanceof \Illuminate\Http\JsonResponse) {
                return $pointOfSaleId;
            }

            $validatedData = $request->validate([
                'table_number' => [
                    'required',
                    'string',
                    'unique:tables,table_number,NULL,id,point_of_sale_id,' . $pointOfSaleId
                ],
                'name' => 'nullable|string|max:255',
                'capacity' => 'required|integer|min:1|max:50',
                'description' => 'nullable|string',
                'location' => 'nullable|array',
            ]);

            $table = Table::create(array_merge($validatedData, [
                'point_of_sale_id' => $pointOfSaleId,
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
    public function show($id)
    {
        try {
            $user = request()->user();
            $pointOfSaleId = $this->verifyPointOfSaleAccess($user);

            if ($pointOfSaleId instanceof \Illuminate\Http\JsonResponse) {
                return $pointOfSaleId;
            }

            $table = Table::with(['pointOfSale', 'sales.orderLines.product'])
                ->where('point_of_sale_id', $pointOfSaleId)
                ->findOrFail($id);

            return response()->json($table);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Table non trouvée.'], 404);
        }
    }

    // 🛠 Mettre à jour une table
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            $pointOfSaleId = $this->verifyPointOfSaleAccess($user);

            if ($pointOfSaleId instanceof \Illuminate\Http\JsonResponse) {
                return $pointOfSaleId;
            }

            $validatedData = $request->validate([
                'table_number' => [
                    'required',
                    'string',
                    'unique:tables,table_number,' . $id . ',id,point_of_sale_id,' . $pointOfSaleId
                ],
                'name' => 'nullable|string|max:255',
                'capacity' => 'required|integer|min:1|max:50',
                'status' => 'required|string|in:available,occupied,reserved,out_of_order',
                'description' => 'nullable|string',
                'location' => 'nullable|array',
            ]);

            $table = Table::where('point_of_sale_id', $pointOfSaleId)->findOrFail($id);

            $table->update($validatedData);

            return response()->json($table);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Table non trouvée.'], 404);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);
        }
    }

    // ❌ Supprimer une table
    public function destroy($id)
    {
        try {
            $user = request()->user();
            $pointOfSaleId = $this->verifyPointOfSaleAccess($user);

            if ($pointOfSaleId instanceof \Illuminate\Http\JsonResponse) {
                return $pointOfSaleId;
            }

            $table = Table::where('point_of_sale_id', $pointOfSaleId)->findOrFail($id);

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
        }
    }

    // 📊 Récupérer les tables disponibles
    public function getAvailableTables(Request $request)
    {
        try {
            $user = $request->user();
            $pointOfSaleId = $this->verifyPointOfSaleAccess($user);

            if ($pointOfSaleId instanceof \Illuminate\Http\JsonResponse) {
                return $pointOfSaleId;
            }

            $tables = Table::available()
                ->where('point_of_sale_id', $pointOfSaleId)
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
            $user = $request->user();
            $pointOfSaleId = $this->verifyPointOfSaleAccess($user);

            if ($pointOfSaleId instanceof \Illuminate\Http\JsonResponse) {
                return $pointOfSaleId;
            }

            $tables = Table::occupied()
                ->where('point_of_sale_id', $pointOfSaleId)
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
            $user = $request->user();
            $pointOfSaleId = $this->verifyPointOfSaleAccess($user);

            if ($pointOfSaleId instanceof \Illuminate\Http\JsonResponse) {
                return $pointOfSaleId;
            }

            $validatedData = $request->validate([
                'status' => 'required|string|in:available,occupied,reserved,out_of_order',
            ]);

            $table = Table::where('point_of_sale_id', $pointOfSaleId)->findOrFail($id);

            $table->update(['status' => $validatedData['status']]);

            return response()->json($table);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Table non trouvée.'], 404);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);
        }
    }

    // 📈 Statistiques des tables
    public function getStatistics(Request $request)
    {
        try {
            $user = $request->user();
            $pointOfSaleId = $this->verifyPointOfSaleAccess($user);

            if ($pointOfSaleId instanceof \Illuminate\Http\JsonResponse) {
                return $pointOfSaleId;
            }

            $totalTables = Table::where('point_of_sale_id', $pointOfSaleId)->count();
            $availableTables = Table::available()->where('point_of_sale_id', $pointOfSaleId)->count();
            $occupiedTables = Table::occupied()->where('point_of_sale_id', $pointOfSaleId)->count();
            $reservedTables = Table::reserved()->where('point_of_sale_id', $pointOfSaleId)->count();
            $outOfOrderTables = Table::outOfOrder()->where('point_of_sale_id', $pointOfSaleId)->count();

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
