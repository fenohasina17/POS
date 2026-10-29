<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recopié du Central à chaque pos:sync-catalog. false par défaut =
        // comportement actuel (prix imposé) tant que le Central n'a rien dit.
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('price_editable')->default(false);
        });

        // central_price : dernier prix reçu du Central, pour pouvoir revenir
        // dessus hors ligne. is_local_override : le gérant a fixé son propre
        // prix, que la synchro ne doit plus écraser.
        Schema::table('pricing', function (Blueprint $table) {
            $table->decimal('central_price', 10, 2)->nullable();
            $table->boolean('is_local_override')->default(false);
        });

        DB::table('pricing')->update(['central_price' => DB::raw('price')]);
    }

    public function down(): void
    {
        Schema::table('pricing', function (Blueprint $table) {
            $table->dropColumn(['central_price', 'is_local_override']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('price_editable');
        });
    }
};
