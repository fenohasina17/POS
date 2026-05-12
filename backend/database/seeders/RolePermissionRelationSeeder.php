<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionRelationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Clear existing relations
        DB::table('model_has_roles')->truncate();
        DB::table('model_has_permissions')->truncate();
        DB::table('role_has_permissions')->truncate();

        // Fetch roles and permissions
        $adminRole = Role::where('name', 'admin')->first();
        $gerantRole = Role::where('name', 'gerant')->first();
        $caissierRole = Role::where('name', 'caissier')->first();

        $allPermissions = Permission::all();

        // Assign all permissions to admin role
        if ($adminRole) {
            $adminRole->syncPermissions($allPermissions);
        }

        // Assign specific permissions to gerant role
        if ($gerantRole) {
            $gerantPermissions = $allPermissions->filter(function ($permission) {
                return in_array($permission->name, [
                    'view.cash_register_sessions',
                    'create.cash_register_sessions',
                    'update.cash_register_sessions',
                    'view.discrepancies',
                    'view.sales',
                ]);
            });
            $gerantRole->syncPermissions($gerantPermissions);
        }

        // Assign specific permissions to caissier role
        if ($caissierRole) {
            $caissierPermissions = $allPermissions->filter(function ($permission) {
                return in_array($permission->name, [
                    'view.cash_register_sessions',
                    'create.cash_register_sessions',
                    'update.cash_register_sessions',
                ]);
            });
            $caissierRole->syncPermissions($caissierPermissions);
        }

        // Assign roles to users (example: first user admin, second gerant, third caissier)
        $users = User::all();

        if ($users->count() >= 3) {
            $users[0]->syncRoles([$adminRole]);
            $users[1]->syncRoles([$gerantRole]);
            $users[2]->syncRoles([$caissierRole]);
        }
    }
}
