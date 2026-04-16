<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PrinterTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = ['cash', 'kitchen', 'pizza', 'bar'];

        foreach ($types as $type) {
            \App\Models\PrinterType::firstOrCreate(['name' => $type]);
        }
    }
}
