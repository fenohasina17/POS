<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserRoleController extends Controller
{
    public function index(User $user)
    {
        return response()->json($user->roles);
    }

    public function store(Request $request, User $user)
    {
        $request->validate(['role' => 'required|exists:roles,name']);
        $user->assignRole($request->role);
        return response()->json(['message' => 'Role assigned'], 201);
    }

    public function destroy(User $user, Role $role)
    {
        if ($role->name === 'admin' && $user->roles()->where('name', 'admin')->count() === 1) {
            return response()->json(['error' => 'User must have at least one admin role'], 403);
        }

        $user->removeRole($role);
        return response()->json(null, 204);
    }
}
