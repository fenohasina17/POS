<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // false par défaut : une catégorie existante garde le comportement
    // actuel (prix imposé par le Central) tant que l'admin ne l'ouvre pas.
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('price_editable')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('price_editable');
        });
    }
};
