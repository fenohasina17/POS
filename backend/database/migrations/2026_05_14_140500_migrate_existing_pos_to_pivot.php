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
        // Transférer les données existantes de users.point_of_sale_id vers la table pivot
        $users = DB::table('users')->whereNotNull('point_of_sale_id')->get();
        
        foreach ($users as $user) {
            DB::table('point_of_sale_user')->insert([
                'user_id' => $user->id,
                'point_of_sale_id' => $user->point_of_sale_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas besoin de faire grand chose ici car la table pivot sera supprimée par la migration précédente si on fait un rollback complet
    }
};
