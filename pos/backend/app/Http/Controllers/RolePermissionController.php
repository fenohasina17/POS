<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionController extends Controller
{
    public function assign(Request $request, Role $role)
    {
        $request->validate(['permission' => 'required|exists:permissions,name']);
        $role->givePermissionTo($request->permission);
        return response()->json(['message' => 'Permission assigned']);
    }

    public function revoke(Role $role, Permission $permission)
    {
        $role->revokePermissionTo($permission);
        return response()->json(null, 204);
    }
}
