<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_sale_payments', function (Blueprint $table) {
            $table->id();
            $table->string('terminal_id');
            $table->string('restaurant_id');
            $table->unsignedBigInteger('remote_id');
            $table->unsignedBigInteger('sale_id_remote');
            $table->string('payment_method_name')->nullable();
            $table->decimal('amount', 12, 2);
            $table->timestamp('remote_created_at')->nullable();
            $table->timestamp('received_at')->useCurrent();

            $table->unique(['terminal_id', 'remote_id'], 'uq_remote_payments_terminal_remote');
            $table->index('terminal_id');
            $table->index('sale_id_remote');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_sale_payments');
    }
};
