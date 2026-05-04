<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTgUsernameToAvUserPrefs extends Migration
{
    public function up()
    {
        Schema::table('av_user_prefs', function (Blueprint $table) {
            $table->string('tg_username', 64)->nullable()->after('tg_chat_id');
        });
    }

    public function down()
    {
        Schema::table('av_user_prefs', function (Blueprint $table) {
            $table->dropColumn('tg_username');
        });
    }
}
