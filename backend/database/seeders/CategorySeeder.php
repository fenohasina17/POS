<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Tableau des catégories directement dans le seeder
        $categories = [
            'BOISSONS',
            'GLACE',
            'PIZZA',
            'PIZZAGM',
            'PIZZAPM',
            'CUISINE',
            'DIVERS',
            'PATISSERIE',
            'SNACK',
        ];

        // Créer les catégories
        foreach ($categories as $categoryName) {
            Category::updateOrCreate(
                ['name' => $categoryName],
             
            );
        }
    }
}