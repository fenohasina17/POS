<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PointOfSale>
 */
class PointOfSaleFactory extends Factory
{
    public function definition(): array
    {
        static $counter = 101;

        return [
            'name' => 'Pv' . $counter++,
        ];
    }
}

