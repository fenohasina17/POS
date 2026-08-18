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
        Schema::create('session_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('cash_register_sessions')->onDelete('cascade');
            $table->decimal('difference_amount', 10, 2);
            $table->text('explanation')->nullable();
            $table->boolean('is_checked')->default(false);
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_discrepancies');
    }
};
