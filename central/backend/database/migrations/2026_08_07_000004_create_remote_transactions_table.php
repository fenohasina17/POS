<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('terminal_id');
            $table->string('restaurant_id');
            $table->unsignedBigInteger('remote_id');
            $table->string('type');
            $table->decimal('amount', 12, 2);
            $table->string('label')->nullable();
            $table->unsignedBigInteger('session_id_remote')->nullable();
            $table->timestamp('remote_created_at')->nullable();
            $table->timestamp('received_at')->useCurrent();

            $table->unique(['terminal_id', 'remote_id'], 'uq_remote_transactions_terminal_remote');
            $table->index('terminal_id');
            $table->index('restaurant_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_cash_transactions');
    }
};
