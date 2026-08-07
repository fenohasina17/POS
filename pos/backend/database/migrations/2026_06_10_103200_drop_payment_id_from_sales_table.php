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
        // Only attempt to drop if the column exists
        if (Schema::hasColumn('sales', 'payment_id')) {
            Schema::table('sales', function (Blueprint $table) {
                // Drop foreign key constraint first (Laravel generates a name like sales_payment_id_foreign)
                $table->dropForeign(['payment_id']);
                $table->dropColumn('payment_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Recreate the payment_id column as nullable foreign key to payments table
            $table->foreignId('payment_id')
                  ->nullable()
                  ->constrained('payments')
                  ->cascadeOnDelete();
        });
    }
};
