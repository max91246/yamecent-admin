<?php

use App\AdminConfig;
use Illuminate\Database\Migrations\Migration;

class AddMissavUrlConfigs extends Migration
{
    public function up()
    {
        AdminConfig::firstOrCreate(
            ['config_key' => 'missav_base_url'],
            [
                'name'         => 'MissAV 基底 URL',
                'config_value' => 'https://missav.ai',
                'type'         => 'url',
            ]
        );

        AdminConfig::firstOrCreate(
            ['config_key' => 'missav_actress_list_url'],
            [
                'name'         => 'MissAV 女優列表 URL',
                'config_value' => 'https://missav.ai/actresses?sort=debut&page=',
                'type'         => 'url',
            ]
        );

        AdminConfig::firstOrCreate(
            ['config_key' => 'missav_video_list_url'],
            [
                'name'         => 'MissAV 新片列表 URL',
                'config_value' => 'https://missav.ai/new?page=',
                'type'         => 'url',
            ]
        );
    }

    public function down()
    {
        AdminConfig::whereIn('config_key', [
            'missav_base_url',
            'missav_actress_list_url',
            'missav_video_list_url',
        ])->delete();
    }
}
