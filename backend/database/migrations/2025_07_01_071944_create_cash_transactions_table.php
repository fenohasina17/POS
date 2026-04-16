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
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            
            // Relation avec la session de caisse (obligatoire)
            $table->foreignId('session_id')
                ->constrained('cash_register_sessions')
                ->onDelete('cascade');
            
            // Relation avec la vente (optionnelle - uniquement pour les ventes/remboursements)
            $table->foreignId('sale_id')
                ->nullable()
                ->constrained('sales')
                ->onDelete('set null');
            
            // Types de transactions
            // sale: vente en espèces
            // refund: remboursement en espèces
            // in: dépôt / ajout d'argent (ex: fond de caisse)
            // out: retrait d'argent
            $table->enum('type', ['sale', 'refund', 'in', 'out'])
                ->default('sale');
            
            // Montant de la transaction
            $table->decimal('amount', 10, 2);
            
            // Description de la transaction
            $table->string('description')->nullable();
            
            // Métadonnées additionnelles (optionnel)
            $table->string('reference')->nullable(); // Référence externe
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Index pour optimiser les requêtes
            $table->index(['session_id', 'type']);
            $table->index(['sale_id']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
    }
};