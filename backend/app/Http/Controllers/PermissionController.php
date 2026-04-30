<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        return response()->json(Permission::all());
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|unique:permissions,name']);
        $permission = Permission::create(['name' => $request->name,'guard_name'=> 'api']
    );
        return response()->json($permission, 201);
    }

    public function destroy(Permission $permission)
    {
        $permission->delete();
        return response()->json(null, 204);
    }
}
