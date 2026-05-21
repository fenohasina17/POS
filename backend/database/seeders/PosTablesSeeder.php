<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PointOfSale;
use App\Models\Table;

class PosTablesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pointsOfSale = PointOfSale::all();

        foreach ($pointsOfSale as $pos) {
            for ($i = 1; $i <= 40; $i++) {
                Table::firstOrCreate(
                    [
                        'point_of_sale_id' => $pos->id,
                        'table_number' => $pos->name . '-' . $i
                    ],
                    [
                        'name' => 'Table ' . $i,
                        'capacity' => 4,
                        'status' => 'available',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
            }
        }

        $this->command->info('40 tables created for each PointOfSale.');
    }
}
