<?php
// database/migrations/2024_01_01_000000_add_columns_to_sales_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'notes')) {
                $table->text('notes')->nullable()->after('change_amount');
            }
            
            if (!Schema::hasColumn('sales', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('status');
            }
            
            if (!Schema::hasColumn('sales', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['notes', 'cancelled_at', 'cancellation_reason']);
        });
    }
};