<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PointOfSale;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CashierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pointsOfSale = PointOfSale::all();
        $caissierRole = Role::where('name', 'caissier')->first();

        if (!$caissierRole) {
            $this->command->error('Role "caissier" not found. Run RoleSeeder first.');
            return;
        }

        foreach ($pointsOfSale as $pos) {
            for ($i = 1; $i <= 2; $i++) {
                $email = strtolower($pos->name) . '_caissier' . $i . '@igp.com';
                
                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => 'Caissier ' . $pos->name . '-' . $i,
                        'password' => Hash::make('password'),
                        'point_of_sale_id' => $pos->id,
                    ]
                );

                $user->assignRole($caissierRole);
            }
        }

        $this->command->info('2 Cashiers created for each PointOfSale.');
    }
}
