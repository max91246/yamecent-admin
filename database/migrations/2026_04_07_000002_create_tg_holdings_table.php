<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTgHoldingsTable extends Migration
{
    public function up()
    {
        Schema::create('tg_holdings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('bot_id');
            $table->bigInteger('tg_chat_id');
            $table->string('tg_user_id', 50);
            $table->string('stock_code', 20)->comment('股票代號，如 2317');
            $table->string('stock_name', 100)->nullable()->comment('股票名稱');
            $table->integer('shares')->comment('持有張數');
            $table->tinyInteger('is_margin')->default(0)->comment('0=現股 1=融資');
            $table->decimal('total_cost', 15, 2)->comment('持有總成本（台幣）');
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('tg_bots')->onDelete('cascade');
            $table->index(['bot_id', 'tg_chat_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tg_holdings');
    }
}
