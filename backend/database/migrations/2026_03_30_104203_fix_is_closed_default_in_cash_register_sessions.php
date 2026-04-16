<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cash_register_sessions', function (Blueprint $table) {
            $table->boolean('is_closed')->default(false)->change();
        });
    }

    public function down()
    {
        Schema::table('cash_register_sessions', function (Blueprint $table) {
            $table->boolean('is_closed')->default(null)->change();
        });
    }
};