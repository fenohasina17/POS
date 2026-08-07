<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_cash_register_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('terminal_id');
            $table->string('restaurant_id');
            $table->unsignedBigInteger('remote_id');
            $table->decimal('starting_amount', 12, 2)->nullable();
            $table->decimal('actual_cash_amount', 12, 2)->nullable();
            $table->decimal('expected_cash_amount', 12, 2)->nullable();
            $table->decimal('total_sales', 12, 2)->nullable();
            $table->decimal('total_refunds', 12, 2)->nullable();
            $table->boolean('is_closed')->default(false);
            $table->boolean('has_discrepancy')->default(false);
            $table->unsignedBigInteger('user_id_remote')->nullable();
            $table->timestamp('remote_opened_at')->nullable();
            $table->timestamp('remote_closed_at')->nullable();
            $table->timestamp('received_at')->useCurrent();

            $table->unique(['terminal_id', 'remote_id'], 'uq_remote_sessions_terminal_remote');
            $table->index('terminal_id');
            $table->index('restaurant_id');
            $table->index('is_closed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_cash_register_sessions');
    }
};
