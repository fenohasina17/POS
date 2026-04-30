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
        Schema::create('cash_register_sessions', function (Blueprint $table) {
            $table->id();
            
            // Relations et Clés Étrangères
            $table->foreignId('cash_register_id')->constrained()->onDelete('restrict');
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->onDelete('restrict');

            // Montants Financiers (Précision 12,2 pour la sécurité)
            $table->decimal('starting_amount', 12, 2)->default(0);
            $table->decimal('expected_cash_amount', 12, 2)->default(0);
            $table->decimal('actual_cash_amount', 12, 2)->nullable();
            $table->decimal('difference_amount', 12, 2)->default(0);
            $table->decimal('total_sales', 12, 2)->default(0);
            $table->decimal('total_refunds', 12, 2)->default(0);

            // Suivi des Tickets et État
            $table->integer('start_ticket_number')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->boolean('is_bill_checked')->default(false);
            $table->boolean('has_discrepancy')->default(false);

            // Notes et explications
            $table->text('closing_notes')->nullable();
            $table->text('discrepancy_explanation')->nullable();
            $table->text('notes')->nullable();

            // Dates et Timestamps
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            // Index pour la performance
            $table->index(['cash_register_id', 'is_closed']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_register_sessions');
    }
};