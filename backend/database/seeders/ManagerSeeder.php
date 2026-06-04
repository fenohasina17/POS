<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PointOfSale;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class ManagerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pointsOfSale = PointOfSale::all();
        $managerRole = Role::where('name', 'gérant')->first();

        if (!$managerRole) {
            $this->command->error('Role "gérant" not found. Run RoleSeeder first.');
            return;
        }

        foreach ($pointsOfSale as $pos) {
            for ($i = 1; $i <= 2; $i++) {
                $email = strtolower(str_replace(' ', '_', $pos->name)) . '_gerant' . $i . '@igp.com';
                
                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => 'Gérant ' . $pos->name . '-' . $i,
                        'password' => Hash::make('password'),
                    ]
                );

                // Associer explicitement dans la table pivot
                DB::table('point_of_sale_user')->updateOrInsert(
                    ['point_of_sale_id' => $pos->id, 'user_id' => $user->id],
                    ['created_at' => now(), 'updated_at' => now()]
                );

                $user->assignRole($managerRole);
            }
        }

        $this->command->info('2 Managers created for each PointOfSale.');
    }
}
