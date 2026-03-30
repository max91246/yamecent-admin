<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSourceFieldsToArticlesTable extends Migration
{
    public function up()
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->unsignedBigInteger('source_post_id')->nullable()->unique()->after('is_active');
            $table->unsignedBigInteger('source_member_id')->nullable()->after('source_post_id');
            $table->timestamp('source_published_at')->nullable()->after('source_member_id');
        });
    }

    public function down()
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropUnique(['source_post_id']);
            $table->dropColumn(['source_post_id', 'source_member_id', 'source_published_at']);
        });
    }
}
