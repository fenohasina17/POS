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
            if (!Schema::hasColumn('sales', 'amount_received')) {
                $table->decimal('amount_received', 12, 2)->nullable();
            }

            if (!Schema::hasColumn('sales', 'change_amount')) {
                $table->decimal('change_amount', 12, 2)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'change_amount')) {
                $table->dropColumn('change_amount');
            }

            if (Schema::hasColumn('sales', 'amount_received')) {
                $table->dropColumn('amount_received');
            }
        });
    }
};
