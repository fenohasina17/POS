<?php

namespace Database\Factories;

use App\Models\SalePayment;
use App\Models\Sale;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalePaymentFactory extends Factory
{
    /**
     * Le nom du modèle correspondant à la factory.
     *
     * @var string
     */
    protected $model = SalePayment::class;

    /**
     * Définition de l'état par défaut du modèle.
     *
     * @return array
     */
    public function definition()
    {
        return [
            // Crée automatiquement une vente si aucune n'est fournie
            'sale_id' => Sale::factory(),
            
            // Crée automatiquement un type de paiement (ex: Orange Money) si aucun n'est fourni
            'payment_id' => Payment::factory(),
            
            // Un montant aléatoire pour le test
            'amount' => $this->faker->randomFloat(2, 500, 50000),
            
            // Référence optionnelle (ex: numéro de transaction)
            'reference' => $this->faker->bothify('TXN-#####-??'),
            
            // Notes optionnelles
            'notes' => $this->faker->sentence(),
        ];
    }
}