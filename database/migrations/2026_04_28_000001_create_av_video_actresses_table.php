<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAvVideoActressesTable extends Migration
{
    public function up()
    {
        Schema::create('ya_av_video_actresses', function (Blueprint $table) {
            $table->unsignedBigInteger('video_id');
            $table->unsignedBigInteger('actress_id');
            $table->primary(['video_id', 'actress_id']);
            $table->index('actress_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ya_av_video_actresses');
    }
}
