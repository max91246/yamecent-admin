<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTgWalletsTable extends Migration
{
    public function up()
    {
        Schema::create('tg_wallets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('bot_id');
            $table->bigInteger('tg_chat_id');
            $table->string('tg_user_id', 50);
            $table->decimal('capital', 15, 2)->default(0)->comment('帳戶資金（台幣）');
            $table->timestamps();

            $table->unique(['bot_id', 'tg_chat_id', 'tg_user_id'], 'tg_wallets_unique');
            $table->index(['bot_id', 'tg_chat_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tg_wallets');
    }
}
