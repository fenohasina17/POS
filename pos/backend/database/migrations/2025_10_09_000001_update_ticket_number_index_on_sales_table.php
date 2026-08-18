<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Vérifier si la contrainte existe avant de la supprimer
        $exists = DB::select("SELECT constraint_name FROM information_schema.constraint_column_usage WHERE constraint_name = 'sales_ticket_number_unique'");

        if (!empty($exists)) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropUnique('sales_ticket_number_unique');
            });
        }

        Schema::table('sales', function (Blueprint $table) {
            // Vérifier si la contrainte session_ticket existe avant de l'ajouter
            $existsSession = DB::select("SELECT constraint_name FROM information_schema.constraint_column_usage WHERE constraint_name = 'sales_session_ticket_unique'");
            if (empty($existsSession)) {
                $table->unique(['cash_register_session_id', 'ticket_number'], 'sales_session_ticket_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $existsSession = DB::select("SELECT constraint_name FROM information_schema.constraint_column_usage WHERE constraint_name = 'sales_session_ticket_unique'");
            if (!empty($existsSession)) {
                $table->dropUnique('sales_session_ticket_unique');
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            $exists = DB::select("SELECT constraint_name FROM information_schema.constraint_column_usage WHERE constraint_name = 'sales_ticket_number_unique'");
            if (empty($exists)) {
                $table->unique('ticket_number', 'sales_ticket_number_unique');
            }
        });
    }
};
