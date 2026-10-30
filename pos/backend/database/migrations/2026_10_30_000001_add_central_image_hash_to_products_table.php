<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Empreinte de la photo reçue du Central : la synchro ne retélécharge
    // la photo que lorsqu'elle change.
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('central_image_hash', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('central_image_hash');
        });
    }
};
