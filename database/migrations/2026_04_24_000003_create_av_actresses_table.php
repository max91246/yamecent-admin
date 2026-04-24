<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAvActressesTable extends Migration
{
    public function up()
    {
        Schema::create('ya_av_actresses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('姓名');
            $table->string('missav_slug', 200)->unique()->comment('MissAV URL slug');
            $table->string('image_url', 500)->nullable()->comment('頭像 URL');
            $table->unsignedSmallInteger('height')->nullable()->comment('身高 cm');
            $table->string('bust', 10)->nullable()->comment('胸圍');
            $table->unsignedTinyInteger('waist')->nullable()->comment('腰圍');
            $table->unsignedTinyInteger('hip')->nullable()->comment('臀圍');
            $table->date('birthday')->nullable()->comment('生日');
            $table->unsignedSmallInteger('debut_year')->nullable()->comment('出道年份');
            $table->string('notes', 500)->nullable()->comment('備註');
            $table->boolean('is_active')->default(true)->comment('在役');
            $table->timestamps();

            $table->index('name');
            $table->index('is_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ya_av_actresses');
    }
}
