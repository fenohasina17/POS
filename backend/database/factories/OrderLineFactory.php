<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\OrderLine;
use App\Models\Sale;
use App\Models\Product;

class OrderLineFactory extends Factory
{
    protected $model = OrderLine::class;

    public function definition(): array
    {
        return [
            // Utilise la Factory plutôt que d'aller chercher dans la base
            'sale_id' => \App\Models\Sale::factory(),
            'product_id' => \App\Models\Product::factory(),
            'quantity' => $quantity = $this->faker->numberBetween(1, 10),
            'price' => $price = $this->faker->randomFloat(2, 5, 100),
            'total' => $quantity * $price, // Laravel gère très bien le calcul ici
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

