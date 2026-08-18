<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Colonnes de synchronisation vers le serveur central.
        // synced_at = null  → enregistrement à envoyer
        // synced_at = date  → enregistrement déjà transmis

        $tables = ['sales', 'cash_register_sessions', 'cash_transactions', 'order_lines', 'sale_payments'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->timestamp('synced_at')->nullable()->after('updated_at');
                $blueprint->index('synced_at', "idx_{$blueprint->getTable()}_synced_at");
            });
        }
    }

    public function down(): void
    {
        $tables = ['sales', 'cash_register_sessions', 'cash_transactions', 'order_lines', 'sale_payments'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex("idx_{$blueprint->getTable()}_synced_at");
                $blueprint->dropColumn('synced_at');
            });
        }
    }
};
