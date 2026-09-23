<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('ref')->unique();
            $table->string('name');
            $table->foreignId('category_id')->constrained()->onDelete('restrict');
            // Même type que pricing.price côté POS (decimal 10,2) — évite tout
            // écart de cast entre le catalogue Central et la synchronisation POS.
            $table->decimal('price', 10, 2);
            // Métadonnée d'affichage Central uniquement (ex: "Petite (PM)",
            // "Grande (GM)") — jamais appliquée côté POS, qui n'a pas de colonne
            // équivalente sur products.
            $table->string('size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
