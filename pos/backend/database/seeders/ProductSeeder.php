<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;
use Database\Factories\ArticleFactory;
use Illuminate\Support\Collection;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $articles = include database_path('data/articles.php');

        if (empty($articles) || !is_array($articles)) {
            return;
        }

        $articlesCollection = Collection::make($articles);

        // Keep the latest occurrence when references are duplicated
        $articlesByRef = $articlesCollection
            ->reverse()
            ->unique('ref')
            ->values()
            ->reverse();

        $categoryIds = Category::pluck('id', 'name');

        $preparedArticles = $articlesByRef
            ->map(function (array $article) use ($categoryIds) {
                $categoryId = $categoryIds[$article['category']] ?? null;

                if (!$categoryId) {
                    return null;
                }

                return [
                    'ref' => $article['ref'],
                    'name' => $article['name'],
                    'category_id' => $categoryId,
                    'status' => true,
                    'image' => 'default-product-image.jpg',
                ];
            })
            ->filter()
            ->values();

        $refsToKeep = $preparedArticles->pluck('ref');

        Product::whereNotIn('ref', $refsToKeep)->delete();

        $existingRefs = Product::whereIn('ref', $refsToKeep)->pluck('ref');

        $preparedArticles->each(function (array $attributes) use ($existingRefs) {
            if ($existingRefs->contains($attributes['ref'])) {
                Product::where('ref', $attributes['ref'])->update($attributes);
            }
        });

        $articlesToCreate = $preparedArticles
            ->reject(fn (array $attributes) => $existingRefs->contains($attributes['ref']))
            ->values();

        if ($articlesToCreate->isNotEmpty()) {
            ArticleFactory::new()
                ->withDataset($articlesToCreate->all())
                ->create();
        }
    }
}
