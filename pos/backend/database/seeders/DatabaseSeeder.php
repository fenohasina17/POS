<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\PointOfSale;
use Database\Seeders\{
    RoleSeeder,
    PermissionSeeder,
    RolePermissionRelationSeeder,
    CashRegisterSeeder,
    CashierSeeder,
    ManagerSeeder,
    PosTablesSeeder,
    PaymentSeeder,
    PointOfSaleSeeder,
    CategoryProductSeeder,
    SalesSeeder,
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
        // Mot de passe en clair : le modèle User caste 'password' en 'hashed',
        // qui le hache automatiquement à l'assignation. Passer bcrypt('password')
        // ici le hacherait une seconde fois et rendrait la connexion impossible.
        // Pas de mot de passe fixe (dépôt public) : SEED_ADMIN_PASSWORD si
        // fourni, sinon aléatoire, affiché une seule fois à la création.
        $password = env('SEED_ADMIN_PASSWORD') ?: Str::password(16, symbols: false);
        $admin = User::firstOrCreate(
            ['email' => 'admin@igp.group'],
            [
                'name' => 'Administrateur',
                'password' => $password,
            ]
        );
        $admin->assignRole('admin');

        if ($admin->wasRecentlyCreated) {
            $this->command?->info("Admin : admin@igp.group / {$password}");
        }

        // 3. Création de base : Points de vente, Caisses, Caissiers, Tables et Paiements
        $this->call([
            PointOfSaleSeeder::class,
            CashRegisterSeeder::class,
            CashierSeeder::class,
            ManagerSeeder::class,
            CategoryProductSeeder::class,
            PosTablesSeeder::class,
            PaymentSeeder::class,
            SalesSeeder::class,
        ]);
    }
}

