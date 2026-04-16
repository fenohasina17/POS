<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->integer('port')->nullable();
            $table->string('usb_identifier')->nullable();
        });

        DB::statement("ALTER TABLE printers DROP CONSTRAINT IF EXISTS printers_connection_type_check");
        DB::statement("UPDATE printers SET connection_type = 'network' WHERE connection_type IS NULL OR connection_type = '' OR connection_type = 'cups'");
        DB::statement("ALTER TABLE printers ADD CONSTRAINT printers_connection_type_check CHECK (connection_type in ('network','usb','cups'))");
        DB::statement("ALTER TABLE printers ALTER COLUMN connection_type SET DEFAULT 'network'");
        DB::statement("ALTER TABLE printers ALTER COLUMN connection_type SET NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->dropColumn(['port', 'usb_identifier']);
        });

        DB::statement("ALTER TABLE printers DROP CONSTRAINT IF EXISTS printers_connection_type_check");
        DB::statement("UPDATE printers SET connection_type = 'cups' WHERE connection_type <> 'cups'");
        DB::statement("ALTER TABLE printers ADD CONSTRAINT printers_connection_type_check CHECK (connection_type in ('cups'))");
        DB::statement("ALTER TABLE printers ALTER COLUMN connection_type SET DEFAULT 'cups'");
        DB::statement("ALTER TABLE printers ALTER COLUMN connection_type SET NOT NULL");
    }
};
