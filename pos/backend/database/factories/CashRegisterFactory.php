<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\CashRegister;
use App\Models\PointOfSale;

class CashRegisterFactory extends Factory
{
    protected $model = CashRegister::class;

   public function definition(): array
{
    return [
        'name' => 'Caisse ' . $this->faker->unique()->numberBetween(1, 4),
        // Utilisation de la relation pour créer automatiquement le parent si nécessaire
        'point_of_sale_id' => PointOfSale::factory(), 
    ];
}

}
