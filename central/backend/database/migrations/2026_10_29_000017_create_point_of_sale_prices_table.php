<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dernier état des prix remonté par chaque caisse (pos:sync-catalog).
     * Instantané remplacé à chaque envoi : sert au tableau « Prix par point
     * de vente », pas d'historique.
     */
    public function up(): void
    {
        Schema::create('point_of_sale_prices', function (Blueprint $table) {
            $table->id();
            $table->string('restaurant_id', 100);
            $table->string('terminal_id', 100);
            $table->string('product_ref');
            $table->decimal('price', 10, 2);
            $table->boolean('is_local_override')->default(false);
            $table->timestamp('reported_at');
            $table->timestamps();

            $table->unique(['restaurant_id', 'product_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_of_sale_prices');
    }
};
