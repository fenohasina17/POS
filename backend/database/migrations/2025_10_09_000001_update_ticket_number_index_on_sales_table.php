<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Supprimer l'unicité globale sur ticket_number
            $table->dropUnique('sales_ticket_number_unique');
        });

        Schema::table('sales', function (Blueprint $table) {
            // Garantir l'unicité par session de caisse
            $table->unique(['cash_register_session_id', 'ticket_number'], 'sales_session_ticket_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique('sales_session_ticket_unique');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->unique('ticket_number', 'sales_ticket_number_unique');
        });
    }
};
