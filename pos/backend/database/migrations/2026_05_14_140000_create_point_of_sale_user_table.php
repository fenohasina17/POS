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
        Schema::create('point_of_sale_user', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('point_of_sale_id')->constrained('point_of_sales')->onDelete('cascade');
            $blueprint->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $blueprint->timestamps();

            $blueprint->unique(['point_of_sale_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_of_sale_user');
    }
};
