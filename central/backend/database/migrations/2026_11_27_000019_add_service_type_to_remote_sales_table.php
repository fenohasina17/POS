<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // « sur_place » / « a_emporter », reçu des caisses avec chaque vente.
    // Nullable : ventes antérieures et caisses pas encore mises à jour.
    public function up(): void
    {
        Schema::table('remote_sales', function (Blueprint $table) {
            $table->string('service_type', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('remote_sales', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });
    }
};
