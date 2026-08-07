<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CashDenominationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $denominations = [20000, 10000, 5000, 2000, 1000, 500, 200, 100];

        foreach ($denominations as $denomination) {
            DB::table('cash_denominations')->insert([
                'cash_register_session_id' => 0, // Placeholder, update as needed
                'denomination' => $denomination,
                'count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
