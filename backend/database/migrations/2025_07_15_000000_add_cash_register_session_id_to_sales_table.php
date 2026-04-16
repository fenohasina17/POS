<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCashRegisterSessionIdToSalesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('cash_register_sessions') || !Schema::hasTable('sales')) {
            return;
        }

        if (!Schema::hasColumn('sales', 'cash_register_session_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->bigInteger('cash_register_session_id')
                    ->nullable();
            });
        } else {
            DB::statement('ALTER TABLE sales ALTER COLUMN cash_register_session_id DROP NOT NULL');
        }

        $validSessionIds = DB::table('cash_register_sessions')->pluck('id');

        if ($validSessionIds->isNotEmpty()) {
            DB::table('sales')
                ->whereNotNull('cash_register_session_id')
                ->whereNotIn('cash_register_session_id', $validSessionIds)
                ->update(['cash_register_session_id' => null]);
        } else {
            DB::table('sales')
                ->whereNotNull('cash_register_session_id')
                ->update(['cash_register_session_id' => null]);
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->foreign('cash_register_session_id')
                ->references('id')
                ->on('cash_register_sessions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('sales')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'cash_register_session_id')) {
                $table->dropForeign(['cash_register_session_id']);
            }
        });

        if (Schema::hasColumn('sales', 'cash_register_session_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('cash_register_session_id');
            });
        }
    }
}
