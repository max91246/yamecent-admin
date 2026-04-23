<?php

use App\AdminConfig;
use Illuminate\Database\Migrations\Migration;

class AddGoldApiConfig extends Migration
{
    public function up()
    {
        AdminConfig::firstOrCreate(
            ['config_key' => 'gold_api_url'],
            [
                'name'         => '黃金期貨 API（Yahoo Finance GC=F）',
                'config_value' => 'https://query2.finance.yahoo.com/v8/finance/chart/GC%3DF',
                'type'         => 'url',
            ]
        );
    }

    public function down()
    {
        AdminConfig::where('config_key', 'gold_api_url')->delete();
    }
}
