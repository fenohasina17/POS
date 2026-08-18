<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_sales', function (Blueprint $table) {
            $table->id();
            $table->string('terminal_id');
            $table->string('restaurant_id');
            $table->unsignedBigInteger('remote_id');         // id original sur le POS
            $table->string('sale_number')->nullable();
            $table->unsignedInteger('ticket_number')->nullable();
            $table->string('status')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->decimal('final_amount', 12, 2)->nullable();
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->decimal('amount_received', 12, 2)->nullable();
            $table->decimal('change_amount', 12, 2)->nullable();
            $table->unsignedBigInteger('user_id_remote')->nullable();
            $table->unsignedBigInteger('point_of_sale_id_remote')->nullable();
            $table->unsignedBigInteger('session_id_remote')->nullable();
            $table->unsignedBigInteger('table_id_remote')->nullable();
            $table->timestamp('remote_created_at')->nullable();
            $table->timestamp('remote_completed_at')->nullable();
            $table->timestamp('received_at')->useCurrent();

            // Idempotence : un même (terminal, vente) ne peut pas être inséré deux fois
            $table->unique(['terminal_id', 'remote_id'], 'uq_remote_sales_terminal_remote');
            $table->index('terminal_id');
            $table->index('restaurant_id');
            $table->index('status');
            $table->index('remote_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_sales');
    }
};
