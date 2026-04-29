<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class RenameAvTablesFixDoublePrefix extends Migration
{
    public function up()
    {
        // 修正雙重前綴問題：ya_ya_av_* → ya_av_*
        // Schema::rename 會自動套用 DB_PREFIX，所以 rename('ya_av_videos', 'av_videos')
        // 實際執行：RENAME TABLE ya_ya_av_videos TO ya_av_videos
        $tables = [
            'ya_av_videos'          => 'av_videos',
            'ya_av_actresses'       => 'av_actresses',
            'ya_av_user_prefs'      => 'av_user_prefs',
            'ya_av_video_actresses' => 'av_video_actresses',
            'ya_av_video_clicks'    => 'av_video_clicks',
        ];

        foreach ($tables as $from => $to) {
            if (Schema::hasTable($from)) {
                Schema::rename($from, $to);
            }
        }
    }

    public function down()
    {
        $tables = [
            'av_videos'          => 'ya_av_videos',
            'av_actresses'       => 'ya_av_actresses',
            'av_user_prefs'      => 'ya_av_user_prefs',
            'av_video_actresses' => 'ya_av_video_actresses',
            'av_video_clicks'    => 'ya_av_video_clicks',
        ];

        foreach ($tables as $from => $to) {
            if (Schema::hasTable($from)) {
                Schema::rename($from, $to);
            }
        }
    }
}
