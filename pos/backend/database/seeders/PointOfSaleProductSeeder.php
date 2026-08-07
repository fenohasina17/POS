<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PointOfSaleProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Fetch 2 point_of_sale IDs
        $pointOfSaleIds = DB::table('point_of_sales')->limit(2)->pluck('id')->toArray();

        // Fetch 5 product IDs
        $productIds = DB::table('products')->limit(10)->pluck('id')->toArray();

        $data = [];

        foreach ($pointOfSaleIds as $pointOfSaleId) {
            foreach ($productIds as $productId) {
                $data[] = [
                    'point_of_sale_id' => $pointOfSaleId,
                    'product_id' => $productId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('point_of_sale_product')->insert($data);
    }
}
