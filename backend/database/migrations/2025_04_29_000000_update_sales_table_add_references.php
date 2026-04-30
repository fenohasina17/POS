<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'table_id')) {
                $table->bigInteger('table_id')
                    ->nullable();
            }

            if (!Schema::hasColumn('sales', 'cash_register_session_id')) {
                $table->bigInteger('cash_register_session_id')
                    ->nullable();
            }



            if (!Schema::hasColumn('sales', 'amount_received')) {
                $table->decimal('amount_received', 10, 2)
                    ->nullable();
            }

            if (!Schema::hasColumn('sales', 'change_amount')) {
                $table->decimal('change_amount', 10, 2)
                    ->nullable();
            }
        });

        if (Schema::hasColumn('sales', 'payment_id')) {
            DB::statement('ALTER TABLE sales ALTER COLUMN payment_id DROP NOT NULL');
        }
    }

    public function down()
    {
        if (Schema::hasColumn('sales', 'change_amount')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('change_amount');
            });
        }

        if (Schema::hasColumn('sales', 'amount_received')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('amount_received');
            });
        }

        if (Schema::hasColumn('sales', 'cash_register_session_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('cash_register_session_id');
            });
        }

        if (Schema::hasColumn('sales', 'table_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('table_id');
            });
        }

        if (Schema::hasColumn('sales', 'payment_id')) {
            DB::statement('ALTER TABLE sales ALTER COLUMN payment_id SET NOT NULL');
        }
    }
};
