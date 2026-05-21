<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTgFuturesPositionsTable extends Migration
{
    public function up()
    {
        Schema::create('tg_futures_positions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('bot_id');
            $table->bigInteger('tg_chat_id');
            $table->string('tg_user_id', 50);
            $table->integer('entry_point')->comment('建倉點位');
            $table->tinyInteger('contracts')->default(1)->comment('口數（小台）');
            $table->tinyInteger('is_open')->default(1)->comment('1=持倉中 0=已平倉');
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('tg_bots')->onDelete('cascade');
            $table->index(['bot_id', 'tg_chat_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tg_futures_positions');
    }
}
