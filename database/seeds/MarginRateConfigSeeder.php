<?php

use App\AdminConfig;
use Illuminate\Database\Seeder;

class MarginRateConfigSeeder extends Seeder
{
    public function run()
    {
        AdminConfig::firstOrCreate(
            ['config_key' => 'margin_interest_rate'],
            [
                'name'         => '融資年利率（%）',
                'config_value' => '6.5',
                'type'         => 'number',
            ]
        );

        echo "MarginRateConfigSeeder 完成。" . PHP_EOL;
    }
}
