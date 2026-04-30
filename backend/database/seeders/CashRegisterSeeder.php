<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CashRegister;

class CashRegisterSeeder extends Seeder
{
    public function run(): void
    {
        CashRegister::factory()->count(2)->create();
    }
}
