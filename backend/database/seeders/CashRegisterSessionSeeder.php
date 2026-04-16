<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CashRegisterSession;

class CashRegisterSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Utilisation de la méthode factory() liée au modèle
        CashRegisterSession::factory()->count(10)->create();
    }
}