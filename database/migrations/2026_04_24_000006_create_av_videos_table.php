<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAvVideosTable extends Migration
{
    public function up()
    {
        Schema::create('ya_av_videos', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('番號 如 SSIS-001');
            $table->string('title', 500)->comment('片名');
            $table->string('cover_url', 500)->nullable()->comment('封面圖');
            $table->string('thumb_url', 500)->nullable()->comment('縮圖');
            $table->date('release_date')->nullable()->comment('發行日');
            $table->string('studio', 100)->nullable()->comment('片商');
            $table->string('series', 200)->nullable()->comment('系列');
            $table->unsignedSmallInteger('duration_min')->nullable()->comment('時長（分鐘）');
            $table->json('actresses')->nullable()->comment('演員名單 JSON');
            $table->json('tags')->nullable()->comment('標籤 JSON');
            $table->string('source', 20)->default('missav')->comment('資料來源');
            $table->string('source_url', 500)->nullable()->comment('來源 URL');
            $table->boolean('is_uncensored')->default(false)->comment('無碼');
            $table->boolean('is_leaked')->default(false)->comment('流出');
            $table->timestamps();

            $table->index('release_date');
            $table->index('studio');
            $table->index(['is_uncensored', 'release_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ya_av_videos');
    }
}
