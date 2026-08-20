<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remote_order_lines', function (Blueprint $table) {
            $table->string('category_name')->nullable()->after('product_name');
            $table->index('product_name');
            $table->index('category_name');
        });
    }

    public function down(): void
    {
        Schema::table('remote_order_lines', function (Blueprint $table) {
            $table->dropIndex(['product_name']);
            $table->dropIndex(['category_name']);
            $table->dropColumn('category_name');
        });
    }
};
