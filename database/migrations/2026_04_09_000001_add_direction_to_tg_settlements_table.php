<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDirectionToTgSettlementsTable extends Migration
{
    public function up()
    {
        Schema::table('tg_settlements', function (Blueprint $table) {
            // 'buy' = 待付款（買入交割）, 'sell' = 待收款（賣出交割）
            $table->string('direction', 10)->default('buy')->after('is_settled');
        });
    }

    public function down()
    {
        Schema::table('tg_settlements', function (Blueprint $table) {
            $table->dropColumn('direction');
        });
    }
}
