<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->string('terminal_id')->unique();
            $table->string('restaurant_id');
            $table->string('app_version')->nullable();
            $table->enum('status', ['online', 'offline', 'unknown'])->default('unknown');
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('pending_sync_count')->default(0);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->index('restaurant_id');
            $table->index('status');
            $table->index('last_heartbeat_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminals');
    }
};
