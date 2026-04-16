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
        Schema::table('categories', function (Blueprint $table) {
            $table->bigInteger('printer_type_id')->nullable();
            $table->foreign('printer_type_id')->references('id')->on('printer_types')->onDelete('set null');
            $table->dropColumn('printer_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->enum('printer_type', ['cash', 'kitchen', 'pizza', 'bar'])->default('cash');
            $table->dropForeign(['printer_type_id']);
            $table->dropColumn('printer_type_id');
        });
    }
};
