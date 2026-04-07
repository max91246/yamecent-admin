<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBuyPriceToTgHoldings extends Migration
{
    public function up()
    {
        Schema::table('tg_holdings', function (Blueprint $table) {
            $table->decimal('buy_price', 10, 2)->default(0)->comment('每股買進價格')->after('total_cost');
        });
    }

    public function down()
    {
        Schema::table('tg_holdings', function (Blueprint $table) {
            $table->dropColumn('buy_price');
        });
    }
}
