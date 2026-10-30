<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // image : chemin sur le disque local du Central. image_hash : empreinte
    // du fichier, comparée par chaque caisse pour ne retélécharger la photo
    // que lorsqu'elle change.
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image')->nullable();
            $table->string('image_hash', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['image', 'image_hash']);
        });
    }
};
