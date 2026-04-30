<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentSeeder extends Seeder
{
    public function run()
    {
        // Insérer les types de paiement dans la table payment_types
        $payments = [
            ['name' => 'TPE'],
            ['name' => 'Orange Money'],
            ['name' => 'MVola'],
            ['name' => 'Espèce'],
            ['name' => 'Airtel Money'],
            ['name' => 'En compte'],
        ];

        foreach ($payments as $payment) {
            DB::table('payments')->updateOrInsert(['name' => $payment['name']], $payment);
        }
    }
}
