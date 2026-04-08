<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTgSettlementsTable extends Migration
{
    public function up()
    {
        Schema::create('tg_settlements', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('bot_id');
            $table->bigInteger('tg_chat_id');
            $table->string('tg_user_id', 50);
            $table->string('stock_code', 20);
            $table->string('stock_name', 50);
            $table->unsignedInteger('shares');
            $table->decimal('buy_price', 10, 2);
            $table->decimal('settlement_amount', 15, 2)->comment('交割款項（自備+手續費）');
            $table->date('settle_date')->comment('T+2 交割日');
            $table->tinyInteger('is_settled')->default(0)->comment('0=待交割 1=已交割');
            $table->timestamps();

            $table->index(['bot_id', 'tg_chat_id', 'is_settled', 'settle_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tg_settlements');
    }
}
