<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTgHoldingTradesTable extends Migration
{
    public function up()
    {
        Schema::create('tg_holding_trades', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('bot_id');
            $table->bigInteger('tg_chat_id');
            $table->string('tg_user_id', 50);
            $table->string('stock_code', 20);
            $table->string('stock_name', 100)->nullable();
            $table->integer('sell_shares')->comment('賣出張數');
            $table->decimal('buy_price', 10, 2)->comment('買進均價（每股）');
            $table->decimal('sell_price', 10, 2)->comment('賣出價格（每股）');
            $table->tinyInteger('is_margin')->default(0)->comment('0=現股 1=融資');
            $table->decimal('profit', 15, 2)->comment('本次盈虧（台幣）');
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('tg_bots')->onDelete('cascade');
            $table->index(['bot_id', 'tg_chat_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tg_holding_trades');
    }
}
