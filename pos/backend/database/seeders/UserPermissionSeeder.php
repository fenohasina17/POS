<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Permission;

class UserPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Fetch some permissions to assign directly to users
        $permissionsToAssign = [
            'view.cash_register_sessions',
            'create.cash_register_sessions',
        ];

        $permissions = Permission::whereIn('name', $permissionsToAssign)->get();

        // Assign permissions directly to first 3 users as example
        $users = User::take(3)->get();

        foreach ($users as $user) {
            $user->syncPermissions($permissions);
        }
    }
}
