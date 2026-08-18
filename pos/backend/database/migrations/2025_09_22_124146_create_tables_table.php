<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->string('table_number');
            $table->string('name')->nullable();
            $table->integer('capacity')->default(4);
            $table->string('status')->default('available');
            $table->text('description')->nullable();
            $table->foreignId('point_of_sale_id')->constrained()->onDelete('cascade');
            $table->jsonb('location')->nullable(); 
            $table->timestamps();

            // Index standard sur les colonnes simples
            $table->index(['point_of_sale_id', 'status']);
        });

        // Index spécifique pour PostgreSQL sur la clé 'zone' à l'intérieur du JSON
        // On utilise un DB::statement car Blueprint ne gère pas les index JSON complexes nativement
        DB::statement("CREATE INDEX tables_location_zone_index ON tables ((location->>'zone'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};