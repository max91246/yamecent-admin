<?php

use App\AdminConfig;
use Illuminate\Database\Seeder;

class RevenueUrlConfigSeeder extends Seeder
{
    public function run()
    {
        AdminConfig::firstOrCreate(
            ['config_key' => 'yahoo_revenue_base'],
            [
                'name'         => '月營收 API 基底（Yahoo TW）',
                'config_value' => 'https://tw.stock.yahoo.com/_td-stock/api/resource/StockServices.revenues',
                'type'         => 'url',
            ]
        );

        echo "RevenueUrlConfigSeeder 完成。" . PHP_EOL;
    }
}
