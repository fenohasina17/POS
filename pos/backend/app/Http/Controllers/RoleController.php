<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::select('id', 'name', 'guard_name')
            ->with(['permissions' => function ($query) {
                $query->select('name', 'guard_name');
            }])
            ->get();
        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|unique:roles,name']);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'api'
        ]);
        return response()->json($role, 201);
    }

    public function show(Role $role)
    {
        return response()->json($role->load('permissions'));
    }

    public function update(Request $request, Role $role)
    {
        $request->validate(['name' => 'required|unique:roles,name,' . $role->id]);
        $role->update(['name' => $request->name]);
        return response()->json($role);
    }

    public function destroy(Role $role)
    {
        if ($role->name === 'admin') {
            return response()->json(['error' => 'Cannot delete admin role'], 403);
        }

        $role->delete();
        return response()->json(null, 204);
    }
}
