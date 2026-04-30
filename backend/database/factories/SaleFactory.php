<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Sale;
use App\Models\User;
use App\Models\PointOfSale;
use App\Models\Payment;
use App\Models\OrderLine;

class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
{
    return [
        'ticket_number' => (string) $this->faker->unique()->numberBetween(100000, 999999),
        // On crée les relations si elles n'existent pas
        'user_id' => User::factory(), 
        'point_of_sale_id' => PointOfSale::factory(),
        'cash_register_session_id' => \App\Models\CashRegisterSession::factory(),
        'total_amount' => 100,
        'discount_percentage' => 0,
        'final_amount' => 100,
    ];
}

    public function millionAriary(): static
    {
        return $this->state(fn () => [
            'total_amount' => 1_000_000,
            'discount_percentage' => 0,
            'final_amount' => 1_000_000,
        ]);
    }

    public function withOrderLines(int $multiplier = 10): static
    {
        return $this->afterCreating(function (Sale $sale) use ($multiplier) {
            OrderLine::factory()
                ->count($multiplier)
                ->create([
                    'sale_id' => $sale->id,
                ]);
        });
    }
}
