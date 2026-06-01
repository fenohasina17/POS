<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->unsignedBigInteger('locked_by_session_id')->nullable();
            $table->timestamp('locked_at')->nullable();

            $table->foreign('locked_by_session_id')
                  ->references('id')
                  ->on('cash_register_sessions')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropForeign(['locked_by_session_id']);
            $table->dropColumn(['locked_by_session_id', 'locked_at']);
        });
    }
};
