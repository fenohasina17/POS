<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // « Sur place » ou « À emporter », demandé au client par le caissier.
    // Nullable : les ventes enregistrées avant cette colonne n'ont pas l'information.
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('service_type', ['sur_place', 'a_emporter'])->nullable()->after('table_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });
    }
};
