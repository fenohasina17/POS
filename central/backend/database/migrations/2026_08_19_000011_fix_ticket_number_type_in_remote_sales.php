<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ticket_number était unsignedInteger mais le POS l'envoie en string (ex: "POS-20260813-0001")
        DB::statement('ALTER TABLE remote_sales ALTER COLUMN ticket_number TYPE VARCHAR(100) USING ticket_number::VARCHAR');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE remote_sales ALTER COLUMN ticket_number TYPE INTEGER USING NULL');
    }
};
