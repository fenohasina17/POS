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
        Schema::table('cash_registers', function (Blueprint $table) {
            $columnsToDrop = [];

            if (Schema::hasColumn('cash_registers', 'registration_code')) {
                $columnsToDrop[] = 'registration_code';
            }

            if (Schema::hasColumn('cash_registers', 'machine_token')) {
                $columnsToDrop[] = 'machine_token';
            }

            if (Schema::hasColumn('cash_registers', 'activated_at')) {
                $columnsToDrop[] = 'activated_at';
            }

            if (Schema::hasColumn('cash_registers', 'last_seen_at')) {
                $columnsToDrop[] = 'last_seen_at';
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_registers', 'registration_code')) {
                $table->string('registration_code')->nullable()->unique();
            }

            if (!Schema::hasColumn('cash_registers', 'machine_token')) {
                $table->string('machine_token')->nullable()->unique();
            }

            if (!Schema::hasColumn('cash_registers', 'activated_at')) {
                $table->timestamp('activated_at')->nullable();
            }

            if (!Schema::hasColumn('cash_registers', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable();
            }
        });
    }
};
