<?php

namespace Database\Factories;

use App\Models\PointOfSale;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Table>
 */
class TableFactory extends Factory
{
    /**
     * Le nom du modèle correspondant à cette factory.
     *
     * @var string
     */
    protected $model = Table::class;

    /**
     * Définir l'état par défaut du modèle.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'table_number' => $this->faker->unique()->numberBetween(1, 100),
            'name' => $this->faker->optional()->word(), // Parfois un nom, parfois null
            'capacity' => $this->faker->randomElement([2, 4, 6, 8, 10]),
            'status' => $this->faker->randomElement(['available', 'occupied', 'reserved', 'out_of_order']),
            'description' => $this->faker->sentence(),
            'point_of_sale_id' => PointOfSale::factory(), // Crée automatiquement un POS lié
            'location' => [
                'x' => $this->faker->numberBetween(0, 500),
                'y' => $this->faker->numberBetween(0, 500),
                'floor' => $this->faker->numberBetween(0, 2),
            ],
        ];
    }

    /**
     * État pour une table disponible.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'available',
        ]);
    }

    /**
     * État pour une table occupée.
     */
    public function occupied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'occupied',
        ]);
    }
}