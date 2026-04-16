<?php

namespace Database\Factories;

use App\Models\PrinterType;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrinterTypeFactory extends Factory
{
    protected $model = PrinterType::class;

    public function definition(): array
    {
        return [
            // Utilise faker pour varier les noms, ou une liste fixe pour tes besoins spécifiques
            'name' => $this->faker->randomElement(['Kitchen', 'Bar', 'Cash', 'Receipt']),
        ];
    }
}