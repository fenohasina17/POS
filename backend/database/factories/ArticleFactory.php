<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ArticleFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return Product::factory()->definition();
    }

    /**
     * Build a factory configured to replay a prepared dataset.
     */
    public function withDataset(array $articles): Factory
    {
        return $this->count(count($articles))->sequence(...array_map(function (array $article) {
            return array_merge([
                'status' => true,
                'image' => 'default-product-image.jpg',
            ], $article);
        }, $articles));
    }
}
