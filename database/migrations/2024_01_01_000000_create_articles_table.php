<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticlesTable extends Migration
{
    public function up()
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title', 255)->default('');
            $table->string('image', 255)->nullable();
            $table->longText('content')->nullable();
            $table->tinyInteger('type')->default(1)->comment('1:一般文章 2:高級文章 3:特級文章');
            $table->tinyInteger('is_active')->default(0)->comment('0:下架 1:上架');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('articles');
    }
}
