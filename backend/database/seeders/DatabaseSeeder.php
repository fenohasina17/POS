<?php

use App\Models\CashRegister;
use App\Models\OrderLine;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\PointOfSale;
use Database\Seeders\CashRegisterSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PricingSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\PaymentSeeder;
use Database\Seeders\PointOfSaleProductSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\CashRegisterSessionSeeder;
use Database\Seeders\SalePaymentPermissionSeeder;


class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Créer 5 points de vente Pv101 → Pv105
        $pointOfSales = PointOfSale::factory()->count(2)->create();

        // Pour chaque point de vente, on crée 3 utilisateurs
        $pointOfSales->each(function ($pos) {
            User::factory()->count(2)->create([
                'point_of_sale_id' => $pos->id,
            ]);
        });

        // Utilisateurs spécifiques avec des points de vente précis
        User::factory()->create([
            'name' => 'test',
            'email' => 'test@igp.com',
            'password' => bcrypt('password'),
            'point_of_sale_id' => $pointOfSales[0]->id, // Premier PV
        ]);

        User::factory()->create([
            'name' => 'benzenito',
            'email' => 'benzenito@igp.com',
            'password' => bcrypt('password'),
            'point_of_sale_id' => $pointOfSales[1]->id, // Deuxième PV
        ]);



        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            CategorySeeder::class,
            CashRegisterSeeder::class,
            ProductSeeder::class,
            PricingSeeder::class,
            PaymentSeeder::class,
            PointOfSaleProductSeeder::class,
            CashRegisterSessionSeeder::class,
            SalePaymentPermissionSeeder::class,
            
        ]);
              // Assign admin role to benzenito
              $benzenito = User::where('email', 'benzenito@igp.com')->first();
              if ($benzenito) {
                  $benzenito->assignRole('admin');
              }

    }
}
