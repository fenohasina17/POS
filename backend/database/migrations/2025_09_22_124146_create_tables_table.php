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
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->string('table_number'); // Numéro de la table (ex: "T01", "Table 1")
            $table->string('name')->nullable(); // Nom optionnel de la table
            $table->integer('capacity')->default(4); // Capacité d'accueil (nombre de personnes)
            $table->string('status')->default('available'); // available, occupied, reserved, out_of_order
            $table->text('description')->nullable(); // Description ou notes sur la table
            $table->foreignId('point_of_sale_id')->constrained()->onDelete('cascade'); // Point de vente associé
            $table->json('location')->nullable(); // Position dans le restaurant (coordonnées x,y ou zone)
            $table->timestamps();

            // Index pour optimiser les requêtes
            $table->index(['point_of_sale_id', 'status']);
            $table->unique(['point_of_sale_id', 'table_number']);
            $table->index('table_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
