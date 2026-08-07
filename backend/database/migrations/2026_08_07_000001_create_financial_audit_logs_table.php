<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('action');                          // created, updated, deleted, cancelled, refunded
            $table->unsignedBigInteger('user_id')->nullable(); // qui a fait l'action
            $table->string('user_name')->nullable();           // snapshot du nom au moment de l'action
            $table->json('before')->nullable();                // état avant (null pour created)
            $table->json('after')->nullable();                 // état après (null pour deleted)
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id'], 'audit_auditable_index');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_audit_logs');
    }
};
