<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('tables')) {
            return;
        }

        if (!Schema::hasColumn('sales', 'table_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->bigInteger('table_id')->nullable();
            });
        } else {
            DB::statement('ALTER TABLE sales ALTER COLUMN table_id DROP NOT NULL');
        }

        DB::table('sales')
            ->whereNotNull('table_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('tables')
                    ->whereColumn('tables.id', 'sales.table_id');
            })
            ->update(['table_id' => null]);

        Schema::table('sales', function (Blueprint $table) {
            $table->foreign('table_id')
                ->references('id')
                ->on('tables')
                ->nullOnDelete();
            $table->index('table_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'table_id')) {
                $table->dropForeign(['table_id']);
            }
        });

        if (Schema::hasColumn('sales', 'table_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('table_id');
            });
        }
    }
};
