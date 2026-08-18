<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\CashRegisterSession;
use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Support\Carbon;

class CashRegisterSessionFactory extends Factory
{
    protected $model = CashRegisterSession::class;

    public function definition(): array
    {
        $startingAmount = $this->faker->randomFloat(2, 50, 200);
        
        return [
            // Utilise un ID existant ou en crée un nouveau automatiquement
            'cash_register_id' => CashRegister::exists() ? CashRegister::inRandomOrder()->first()->id : CashRegister::factory(),
            'user_id' => User::exists() ? User::inRandomOrder()->first()->id : User::factory(),
            
            'starting_amount' => $startingAmount,
            'expected_cash_amount' => $startingAmount, // Par défaut, au début c'est égal
            'actual_cash_amount' => null,
            'difference_amount' => 0,
            
            'total_sales' => 0,
            'total_refunds' => 0,
            
            'is_closed' => false,
            'is_bill_checked' => false,
            'has_discrepancy' => false,
            
            'start_ticket_number' => $this->faker->numberBetween(1, 1000),
            'opened_at' => Carbon::now()->subHours(rand(1, 8)),
            'closed_at' => null,
            'closed_by_user_id' => null,
            
            'notes' => $this->faker->optional(0.3)->sentence(),
            'closing_notes' => null,
            'discrepancy_explanation' => null,
        ];
    }

    /**
     * État pour une session clôturée avec des calculs logiques.
     */
    public function closed(): self
    {
        return $this->state(function (array $attributes) {
            $sales = $this->faker->randomFloat(2, 100, 2000);
            $refunds = $this->faker->randomFloat(2, 0, 50);
            $expected = $attributes['starting_amount'] + $sales - $refunds;
            $actual = $expected + $this->faker->randomElement([0, 0, 0, -5, 10]); // 3 chances sur 5 d'être juste
            
            return [
                'is_closed' => true,
                'total_sales' => $sales,
                'total_refunds' => $refunds,
                'expected_cash_amount' => $expected,
                'actual_cash_amount' => $actual,
                'difference_amount' => $actual - $expected,
                'has_discrepancy' => ($actual !== $expected),
                'closed_at' => Carbon::now(),
                'closed_by_user_id' => User::exists() ? User::inRandomOrder()->first()->id : User::factory(),
                'is_bill_checked' => true,
            ];
        });
    }
}