<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // « Sur place / à emporter », reçu des caisses : par article
    // (sur_place | a_emporter) et résumé par vente (… | mixte).
    // Nullable : ventes antérieures et caisses pas encore mises à jour.
    public function up(): void
    {
        Schema::table('remote_sales', function (Blueprint $table) {
            $table->string('service_type', 20)->nullable();
        });
        Schema::table('remote_order_lines', function (Blueprint $table) {
            $table->string('service_type', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('remote_order_lines', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });
        Schema::table('remote_sales', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });
    }
};
