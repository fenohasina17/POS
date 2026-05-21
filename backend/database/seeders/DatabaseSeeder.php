<?php

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\PointOfSale;
use Database\Seeders\{
    RoleSeeder,
    PermissionSeeder,
    RolePermissionRelationSeeder,
    CashRegisterSeeder,
    PaymentSeeder
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

        // 2. Création de base : Admin (sans POS par défaut)
        User::firstOrCreate(
            ['email' => 'admin@igp.com'],
            [
                'name' => 'Administrateur',
                'password' => bcrypt('password'),
            ]
        );

        // 3. Création de base : Paiements
        $this->call([
            PaymentSeeder::class,
        ]);
        }
        }

