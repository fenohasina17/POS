<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            // Clé étrangère vers la vente
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');

            // Clé étrangère vers le TYPE de paiement (votre table 'payments')
            $table->foreignId('payment_id')->constrained('payments');

            $table->decimal('amount', 10, 2);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salepayments');
    }
};
