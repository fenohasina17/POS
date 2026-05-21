<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PointOfSale;
use App\Models\CashRegister;

class CashRegisterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pointsOfSale = PointOfSale::all();

        foreach ($pointsOfSale as $pos) {
            for ($i = 1; $i <= 2; $i++) {
                CashRegister::firstOrCreate(
                    [
                        'point_of_sale_id' => $pos->id,
                        'name' => 'Caisse ' . $pos->name . '-' . $i
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
            }
        }

        $this->command->info('2 CashRegisters created for each PointOfSale.');
    }
}
