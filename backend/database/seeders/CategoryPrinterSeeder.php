<?php

// database/seeders/CategoryPrinterSeeder.php
namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategoryPrinterSeeder extends Seeder
{
    public function run()
    {
        $mapping = [
            'BOISSONS' => 'bar',
            'GLACE' => 'bar',
            'JUS' => 'bar',
            'CAFE' => 'bar',
            'THE' => 'bar',
            'PIZZA' => 'pizza',
            'PIZZAGM' => 'pizza',
            'PIZZAPM' => 'pizza',
            'CUISINE' => 'kitchen',
            'PLAT' => 'kitchen',
            'DIVERS' => 'kitchen',
            'PATISSERIE' => 'kitchen',
            'DESSERT' => 'kitchen',
            'SNACK' => 'kitchen',
        ];

        foreach ($mapping as $categoryName => $printerType) {
            Category::where('name', $categoryName)->update(['printer' => $printerType]);
        }
    }
}