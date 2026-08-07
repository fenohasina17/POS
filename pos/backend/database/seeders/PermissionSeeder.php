<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Define tables with CRUD permissions
        $tables = [
            'sales',
            'products',
            'categories',
            'users',
            'order_lines',
            'payments',
            'cash_registers',
            'cash_register_sessions',
            'cash_transactions',
            'point_of_sales',
            'printers',
            'pricing'
        ];

        // Create CRUD permissions for each table
        foreach ($tables as $table) {
            $permissions = [
                "view.{$table}",
                "create.{$table}",
                "update.{$table}",
                "delete.{$table}"
            ];

            foreach ($permissions as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'api'
                ]);
            }
        }

        // Create admin role with all permissions
        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'api'
        ]);

        $adminRole->givePermissionTo(Permission::all());

        // Create basic user role with view permissions
        $userRole = Role::firstOrCreate([
            'name' => 'user',
            'guard_name' => 'api'
        ]);

        $userRole->givePermissionTo(
            Permission::where('name', 'like', 'view.%')->get()
        );
    }
}
