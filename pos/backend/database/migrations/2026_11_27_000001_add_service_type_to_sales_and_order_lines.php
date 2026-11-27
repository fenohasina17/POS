<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // « Sur place » / « À emporter », demandé au client par le caissier.
    // Le choix est fait par article (une même commande peut mélanger les deux) :
    //  - order_lines.service_type : sur_place | a_emporter
    //  - sales.service_type       : résumé de la commande, sur_place | a_emporter | mixte
    // Nullable : les ventes enregistrées avant ce choix n'ont pas l'information.
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('service_type', 20)->nullable()->after('table_id');
        });
        Schema::table('order_lines', function (Blueprint $table) {
            $table->string('service_type', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_lines', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });
    }
};
