<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAvUserTables extends Migration
{
    public function up()
    {
        // 用戶喜好 tags
        Schema::create('ya_av_user_prefs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('bot_id');
            $table->string('tg_chat_id', 30);
            $table->json('fav_tags')->nullable()->comment('喜愛標籤');
            $table->boolean('push_enabled')->default(true)->comment('每日推播開關');
            $table->timestamps();
            $table->unique(['bot_id', 'tg_chat_id']);
            $table->index('push_enabled');
        });

        // 影片點擊紀錄
        Schema::create('ya_av_video_clicks', function (Blueprint $table) {
            $table->id();
            $table->string('video_code', 30);
            $table->string('tg_chat_id', 30);
            $table->unsignedInteger('bot_id');
            $table->timestamp('clicked_at')->useCurrent();
            $table->index(['video_code', 'clicked_at']);
            $table->index('clicked_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ya_av_user_prefs');
        Schema::dropIfExists('ya_av_video_clicks');
    }
}
