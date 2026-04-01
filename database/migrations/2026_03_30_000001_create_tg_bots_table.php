<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTgBotsTable extends Migration
{
    public function up()
    {
        Schema::create('tg_bots', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100)->comment('機器人名稱');
            $table->string('token', 200)->unique()->comment('Bot Token');
            $table->tinyInteger('type')->default(1)->comment('1=指數查詢');
            $table->tinyInteger('is_active')->default(1)->comment('0=停用 1=啟用');
            $table->string('remark', 500)->nullable()->comment('備註');
            $table->timestamp('webhook_set_at')->nullable()->comment('最後設定webhook時間');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tg_bots');
    }
}
