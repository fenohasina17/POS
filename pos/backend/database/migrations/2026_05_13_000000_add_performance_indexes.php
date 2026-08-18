<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Indexes pour optimiser les requêtes fréquentes
            $table->index('user_id');
            $table->index('point_of_sale_id');
            $table->index(['status', 'created_at']); // Pour filtrage + tri
            $table->index(['point_of_sale_id', 'status', 'created_at']);
            $table->index('cash_register_session_id');
        });

        Schema::table('order_lines', function (Blueprint $table) {
            $table->index(['sale_id', 'created_at']);
            $table->index('product_id');
        });

        Schema::table('cash_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('cash_transactions', 'cash_register_session_id')) {
                $table->index(['cash_register_session_id', 'created_at']);
            }
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['point_of_sale_id']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['point_of_sale_id', 'status', 'created_at']);
            $table->dropIndex(['cash_register_session_id']);
        });

        Schema::table('order_lines', function (Blueprint $table) {
            $table->dropIndex(['sale_id', 'created_at']);
            $table->dropIndex(['product_id']);
        });

        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropIndex(['cash_register_session_id', 'created_at']);
            $table->dropIndex(['type']);
        });
    }
};