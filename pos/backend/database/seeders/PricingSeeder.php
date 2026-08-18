<?php

namespace Database\Seeders;

use App\Models\PointOfSale;
use App\Models\Pricing;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class PricingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $articles = include database_path('data/articles.php');

        if (empty($articles) || !is_array($articles)) {
            return;
        }

        $pointOfSales = PointOfSale::all();
        $products = Product::all();

        if ($pointOfSales->isEmpty() || $products->isEmpty()) {
            return;
        }

        $pricesByRef = Collection::make($articles)
            ->reverse()
            ->unique('ref')
            ->mapWithKeys(fn (array $article) => [$article['ref'] => (float) $article['price']]);

        Pricing::whereIn('product_id', $products->pluck('id'))->delete();

        foreach ($pointOfSales as $pointOfSale) {
            $products->each(function (Product $product) use ($pointOfSale, $pricesByRef) {
                $price = $pricesByRef->get($product->ref);

                if ($price === null) {
                    return;
                }

                Pricing::updateOrCreate(
                    [
                        'point_of_sale_id' => 2,
                        'product_id' => $product->id,
                    ],
                    [
                        'price' => $price,
                    ]
                );
            });
        }
    }
}
