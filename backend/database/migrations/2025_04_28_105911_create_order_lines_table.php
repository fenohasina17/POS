<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderLinesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_lines', function (Blueprint $table) {
            $table->id(); // Clé primaire
            $table->foreignId('sale_id')->constrained()->onDelete('cascade'); // Clé étrangère vers sales
            $table->foreignId('product_id')->constrained()->onDelete('cascade'); // Clé étrangère vers products
            $table->integer('quantity'); // Quantité de produit
            $table->decimal('price', 10); // Prix unitaire
            $table->decimal('total', 10); // Montant total pour cette ligne
            $table->timestamps(); // Champs created_at et updated_at
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_lines');
    }
}
