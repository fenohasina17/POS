<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()?->can('view.users')) {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        try {
            $users = User::with(['pointsOfSale:id,name', 'roles:id,name'])->get()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'points_of_sale' => $user->pointsOfSale->map(fn($p) => ['id' => $p->id, 'name' => $p->name]),
                    'roles' => $user->roles->pluck('name'),
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];
            });
            return response()->json($users);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des utilisateurs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Créer un utilisateur
    public function store(Request $request)
    {
        if (!$request->user()?->can('create.users')) {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        // Validation des données
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'point_of_sale_ids' => 'nullable|array',
            'point_of_sale_ids.*' => 'exists:point_of_sales,id',
            'role' => 'nullable|string|exists:roles,name',
        ]);

        // Si la validation échoue
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Création de l'utilisateur
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            if ($request->has('point_of_sale_ids')) {
                $user->pointsOfSale()->sync($request->point_of_sale_ids);
            }

            if ($request->filled('role')) {
                $user->syncRoles([$request->role]);
            }

            return response()->json($user->load(['pointsOfSale', 'roles']), 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création de l\'utilisateur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Afficher un utilisateur spécifique
    public function show(Request $request, $id)
    {
        if (!$request->user()?->can('view.users')) {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        try {
            $user = User::with(['pointsOfSale', 'roles'])->findOrFail($id);
            return response()->json($user);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Utilisateur non trouvé'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération de l\'utilisateur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Mettre à jour un utilisateur
    public function update(Request $request, $id)
    {
        if (!$request->user()?->can('update.users')) {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        try {
            $user = User::findOrFail($id);

            // Validation des données
            $validator = Validator::make($request->all(), [
                'name' => 'string|max:255',
                'email' => 'email|unique:users,email,' . $id,
                'password' => 'string|min:8|nullable',
                'point_of_sale_ids' => 'nullable|array',
                'point_of_sale_ids.*' => 'exists:point_of_sales,id',
                'role' => 'nullable|string|exists:roles,name',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], 422);
            }

            // Mise à jour de l'utilisateur
            $user->update([
                'name' => $request->name ?? $user->name,
                'email' => $request->email ?? $user->email,
                'password' => $request->password ? Hash::make($request->password) : $user->password,
            ]);

            if ($request->has('point_of_sale_ids')) {
                $user->pointsOfSale()->sync($request->point_of_sale_ids);
            }

            if ($request->has('role')) {
                $user->syncRoles([$request->role]);
            }

            return response()->json($user->load(['pointsOfSale', 'roles']));
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Utilisateur non trouvé'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de l\'utilisateur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Supprimer un utilisateur
    public function destroy(Request $request, $id)
    {
        if (!$request->user()?->can('delete.users')) {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        try {
            $user = User::findOrFail($id);
            $user->delete();

            return response()->json([
                'message' => 'Utilisateur supprimé'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Utilisateur non trouvé'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de l\'utilisateur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
