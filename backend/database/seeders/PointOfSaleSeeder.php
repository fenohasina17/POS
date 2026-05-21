<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PointOfSale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PointOfSaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@igp.com')->first();
        
        if (!$admin) {
            $this->command->error('Admin user not found. Run RoleSeeder/DatabaseSeeder first.');
            return;
        }

        for ($i = 101; $i <= 137; $i++) {
            $pos = PointOfSale::firstOrCreate(
                ['name' => 'PV' . $i],
                ['created_at' => now(), 'updated_at' => now()]
            );

            // Associer à l'admin s'il n'est pas déjà associé
            DB::table('point_of_sale_user')->updateOrInsert(
                ['point_of_sale_id' => $pos->id, 'user_id' => $admin->id],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $this->command->info('PointOfSales PV101 to PV137 seeded and assigned to admin.');
    }
}
