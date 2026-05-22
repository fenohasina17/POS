<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseController;

class AuthController extends BaseController
{
    // Méthode pour l'inscription
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Utilisateur enregistré avec succès',
                'token' => $token,
                'user' => $user->load('pointsOfSale'), // Load pointsOfSale here
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'La validation a échoué',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'inscription : ' . $e->getMessage());
            return response()->json(['message' => 'Erreur interne du serveur'], 500);
        }
    }

    // Méthode pour la connexion
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string|min:8',
            ]);

            if (!Auth::guard('web')->attempt($request->only('email', 'password'))) {
                return response()->json(['message' => 'Identifiants invalides'], 401);
            }

            $user = Auth::guard('web')->user();
            $token = $user->createToken('auth_token')->plainTextToken;

            // Récupérer le nom du premier rôle Spatie
            $roleName = $user->getRoleNames()->first();

            return response()->json([
                'message' => 'Connexion réussie',
                'token' => $token,
                'user' => $user->load('pointsOfSale'), // Load pointsOfSale here
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }


    // Méthode pour déconnexion
    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['message' => 'Aucun utilisateur connecté'], 401);
            }

            $user->currentAccessToken()->delete();

            return response()->json(['message' => 'Déconnexion réussie']);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la déconnexion : ' . $e->getMessage());
            return response()->json(['message' => 'Erreur interne lors de la déconnexion'], 500);
        }
    }

    // Méthode pour récupérer l'utilisateur connecté
    public function me()
    {
        try {
            $user = Auth::user();

            if ($user) {
                return response()->json([
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'points_of_sale' => $user->pointsOfSale->map(fn($p) => ['id' => $p->id, 'name' => $p->name]), // Return plural
                    ]
                ], 200);
            }

            return response()->json(['message' => 'Aucun utilisateur connecté'], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la récupération de l\'utilisateur'], 500);
        }
    }

}

