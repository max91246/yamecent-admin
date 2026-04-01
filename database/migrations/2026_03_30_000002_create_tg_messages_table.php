<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTgMessagesTable extends Migration
{
    public function up()
    {
        Schema::create('tg_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('bot_id')->comment('所屬機器人');
            $table->string('tg_user_id', 50)->comment('TG 用戶 ID');
            $table->string('tg_username', 100)->nullable()->comment('TG 用戶名');
            $table->bigInteger('tg_chat_id')->comment('TG Chat ID');
            $table->text('content')->comment('訊息內容');
            $table->tinyInteger('direction')->default(1)->comment('1=收到用戶訊息 2=Bot 回覆');
            $table->string('message_type', 20)->default('text')->comment('text/callback/reply');
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('tg_bots')->onDelete('cascade');
            $table->index(['bot_id', 'tg_chat_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tg_messages');
    }
}
