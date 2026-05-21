<?php

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\PointOfSale;
use Database\Seeders\{
    RoleSeeder,
    PermissionSeeder,
    RolePermissionRelationSeeder,
    CashRegisterSeeder,
    PaymentSeeder,
    PointOfSaleSeeder
};

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Création de base : Rôles et Permissions
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionRelationSeeder::class,
        ]);

        // 2. Création de base : Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@igp.com'],
            [
                'name' => 'Administrateur',
                'password' => bcrypt('password'),
            ]
        );
        $admin->assignRole('admin');

        // 3. Création de base : Points de vente, Caisses, Caissiers et Paiements
        $this->call([
            PointOfSaleSeeder::class,
            CashRegisterSeeder::class,
            CashierSeeder::class,
            PaymentSeeder::class,
        ]);
    }
}

