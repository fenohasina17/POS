<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Permission;

class UserPermissionController extends Controller
{
    public function index(User $user)
    {
        return response()->json($user->getAllPermissions());
    }

    public function check(User $user, Permission $permission)
    {
        return response()->json(['has_permission' => $user->hasPermissionTo($permission)]);
    }
}
