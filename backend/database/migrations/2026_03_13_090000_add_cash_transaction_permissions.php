<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Vider le cache des permissions pour éviter les erreurs
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'api';
        $permissions = [
            'create.cash_transactions',
            'delete.transactions',
            // 'update.transactions', // Ajoutez celle-ci si vous comptez sécuriser l'update
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guardName]);
        }

        // Assigner ces permissions au rôle admin automatiquement
        $adminRole = Role::where('name', 'admin')->where('guard_name', $guardName)->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
        }
    }

    public function down(): void {}
};
