<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Aucune validation d'unicité n'a jamais existé sur "ref" — neutralise les
        // éventuels doublons avant de poser la contrainte, pour ne pas faire
        // échouer la migration sur des données de production existantes.
        $duplicates = DB::table('products')
            ->select('ref')
            ->groupBy('ref')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('ref');

        foreach ($duplicates as $ref) {
            $ids = DB::table('products')->where('ref', $ref)->orderBy('id')->pluck('id');
            foreach ($ids->slice(1) as $id) {
                DB::table('products')->where('id', $id)->update(['ref' => "{$ref}-dup-{$id}"]);
            }
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unique('ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['ref']);
        });
    }
};
