<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalesTable extends Migration
{
    public function up()
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('point_of_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_id')->nullable();
            $table->bigInteger('cash_register_session_id')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0);

            // Correction de la colonne générée
            $table->decimal('final_amount', 10, 2);

            $table->string('status')->default('pending');
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_reference')->nullable();
            $table->decimal('amount_received', 10, 2)->nullable();
            $table->decimal('change_amount', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales');
    }
}
