<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBirthplaceToAvActresses extends Migration
{
    public function up()
    {
        Schema::table('av_actresses', function (Blueprint $table) {
            $table->string('birthplace', 50)->nullable()->after('hip');
            $table->string('hobbies', 200)->nullable()->after('birthplace');
        });
    }

    public function down()
    {
        Schema::table('av_actresses', function (Blueprint $table) {
            $table->dropColumn(['birthplace', 'hobbies']);
        });
    }
}
