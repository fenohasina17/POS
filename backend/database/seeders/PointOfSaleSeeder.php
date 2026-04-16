<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PointOfSale;

class PointOfSaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create 2 points of sale using factory
        PointOfSale::factory()->count(2)->create();
    }
}
