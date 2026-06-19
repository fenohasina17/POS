<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PointOfSale;
use App\Models\Category;
use App\Models\Product;
use App\Models\Pricing;
use Illuminate\Support\Facades\DB;

class CategoryProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pointsOfSale = PointOfSale::all();

        if ($pointsOfSale->isEmpty()) {
            $this->command->error('No Points of Sale found. Run PointOfSaleSeeder first.');
            return;
        }

        // 1. Créer 10 catégories globales
        for ($i = 1; $i <= 10; $i++) {
            $category = Category::firstOrCreate(
                ['name' => "Catégorie $i"],
                ['printer' => 'kitchen']
            );

            // 2. Créer 10 produits pour cette catégorie
            for ($j = 1; $j <= 10; $j++) {
                $product = Product::firstOrCreate(
                    ['name' => "Produit $j (Cat $i)", 'category_id' => $category->id],
                    ['ref' => "REF-" . $i . $j]
                );

                // 3. Associer chaque produit à chaque POS
                foreach ($pointsOfSale as $pos) {
                    // Ajouter au pivot point_of_sale_product
                    DB::table('point_of_sale_product')->updateOrInsert(
                        [
                            'point_of_sale_id' => $pos->id,
                            'product_id' => $product->id
                        ]
                    );

                    // Ajouter le prix dans Pricing
                    Pricing::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'point_of_sale_id' => $pos->id
                        ],
                        [
                            'price' => rand(1000, 5000)
                        ]
                    );
                }
            }
        }

        $this->command->info('10 Categories and 100 Products created globally, with pricing/availability for each POS.');
    }
}
