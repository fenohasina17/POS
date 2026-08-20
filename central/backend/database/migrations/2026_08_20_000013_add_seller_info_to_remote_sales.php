<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remote_sales', function (Blueprint $table) {
            $table->string('seller_name')->nullable()->after('user_id_remote');
            $table->string('point_of_sale_name')->nullable()->after('point_of_sale_id_remote');
            $table->index('seller_name');
            $table->index('point_of_sale_name');
        });
    }

    public function down(): void
    {
        Schema::table('remote_sales', function (Blueprint $table) {
            $table->dropIndex(['seller_name']);
            $table->dropIndex(['point_of_sale_name']);
            $table->dropColumn(['seller_name', 'point_of_sale_name']);
        });
    }
};
