<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Le modèle associé à cette factory.
     *
     * @var string
     */
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Un "Payment" est juste un nom de mode de paiement (ex: Espèces, Carte)
            // Il ne doit PAS contenir de sale_id ou de montant.
            'name' => $this->faker->unique()->randomElement([
                'Espèces', 
                'Carte Bancaire', 
                'Virement', 
                'Orange Money', 
                'Mvola'
            ]),
        ];
    }
}
