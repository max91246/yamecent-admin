<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTgStatesTable extends Migration
{
    public function up()
    {
        Schema::create('tg_states', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('bot_id');
            $table->bigInteger('tg_chat_id');
            $table->string('state', 50)->comment('當前對話狀態');
            $table->json('state_data')->nullable()->comment('暫存中間資料');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['bot_id', 'tg_chat_id']);
            $table->foreign('bot_id')->references('id')->on('tg_bots')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tg_states');
    }
}
