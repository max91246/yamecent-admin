<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterArticleCommentsAddColumns extends Migration
{
    public function up()
    {
        Schema::table('article_comments', function (Blueprint $table) {
            $table->tinyInteger('is_visible')->default(1)->after('content')
                  ->comment('0:隱藏 1:顯示');
            $table->text('admin_reply')->nullable()->after('is_visible')
                  ->comment('管理員回復內容');
            $table->timestamp('admin_replied_at')->nullable()->after('admin_reply');
        });
    }

    public function down()
    {
        Schema::table('article_comments', function (Blueprint $table) {
            $table->dropColumn(['is_visible', 'admin_reply', 'admin_replied_at']);
        });
    }
}
