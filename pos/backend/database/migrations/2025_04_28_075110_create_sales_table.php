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
            $table->string('ticket_number');
            $table->string('sale_number')->unique()
                ->comment('Préfixé par le code/nom du point de vente (ex: CENTRE_V-20250001)');

            // Relations principales
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('point_of_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_id')->nullable(); // Optionnel (pour le service en salle)

            // Montants
            $table->decimal('total_amount', 10, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('final_amount', 10, 2);
            $table->decimal('amount_received', 10, 2)->nullable();
            $table->decimal('change_amount', 10, 2)->nullable();

            // État et Paiement
            $table->string('status')->default('pending'); // pending, completed, cancelled
            $table->foreignId('payment_id')->nullable()->constrained('payments');

            $table->softDeletes();

            $table->foreignId('deleted_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->string('deletion_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales');
    }
}
