<?php

namespace Database\Seeders;

use App\AdminConfig;
use Illuminate\Database\Seeder;

class DisposalUrlConfigSeeder extends Seeder
{
    public function run()
    {
        AdminConfig::firstOrCreate(
            ['config_key' => 'tpex_disposal_url'],
            [
                'name'         => 'TPEX 上櫃處置股資訊 API',
                'config_value' => 'https://www.tpex.org.tw/openapi/v1/tpex_disposal_information',
                'type'         => 'url',
            ]
        );

        AdminConfig::firstOrCreate(
            ['config_key' => 'twse_disposal_url'],
            [
                'name'         => 'TWSE 上市處置股資訊 API',
                'config_value' => 'https://www.twse.com.tw/rwd/zh/announcement/punish',
                'type'         => 'url',
            ]
        );

        echo "DisposalUrlConfigSeeder 完成。" . PHP_EOL;
    }
}
