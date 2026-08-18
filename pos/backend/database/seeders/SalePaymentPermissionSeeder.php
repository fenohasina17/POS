<?php
// database/seeders/SalePaymentPermissionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SalePaymentPermissionSeeder extends Seeder
{
    public function run()
    {
        // Créer les permissions pour SalePayment (style "dot notation")
        $permissions = [
            'view.sale_payments',
            'create.sale_payments',
            'update.sale_payments',
            'delete.sale_payments',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        // Assigner aux rôles
        $admin = Role::findOrCreate('admin', 'api');
        $gerant = Role::findOrCreate('gerant', 'api');
        $caissier = Role::findOrCreate('caissier', 'api');

        // Admin a toutes les permissions
        $admin->givePermissionTo($permissions);

        // Gérant peut voir
        $gerant->givePermissionTo(['view.sale_payments']);

        // Caissier peut voir et créer
        $caissier->givePermissionTo([
            'view.sale_payments',
            'create.sale_payments'
        ]);
    }
}