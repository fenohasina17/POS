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
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // ex: "Cuisine", "Tickets caisse"
            $table->foreignId('cash_register_id')->constrained()->onDelete('cascade');

            $table->enum('connection_type', ['cups'])->default('cups');
            $table->string('ip_address')->nullable();  // Conservé pour compatibilité mais inutilisé
            $table->integer('timeout')->default(30); // Connection timeout in seconds

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
