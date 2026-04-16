<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            try {
                $table->dropUnique('tables_table_number_unique');
            } catch (Throwable $e) {
                // Ignore if the legacy unique index does not exist.
            }

            try {
                $table->unique(['point_of_sale_id', 'table_number'], 'tables_point_of_sale_table_number_unique');
            } catch (Throwable $e) {
                // Ignore if the composite unique index already exists.
            }
        });
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            try {
                $table->dropUnique('tables_point_of_sale_table_number_unique');
            } catch (Throwable $e) {
                // Ignore if the composite unique index does not exist.
            }

            try {
                $table->unique('table_number', 'tables_table_number_unique');
            } catch (Throwable $e) {
                // Ignore if the legacy unique index already exists.
            }
        });
    }
};
