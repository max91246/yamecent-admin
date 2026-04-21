<?php

namespace Database\Seeders;

use App\AdminConfig;
use Illuminate\Database\Seeder;

class AlertThresholdConfigSeeder extends Seeder
{
    public function run()
    {
        AdminConfig::firstOrCreate(
            ['config_key' => 'wtx_alert_points'],
            [
                'name'         => '台指1分K告警閾值（點）',
                'config_value' => '50',
                'type'         => 'number',
            ]
        );

        AdminConfig::firstOrCreate(
            ['config_key' => 'oil_alert_5m_pct'],
            [
                'name'         => '原油5分K告警振幅（%）',
                'config_value' => '1.0',
                'type'         => 'number',
            ]
        );

        AdminConfig::firstOrCreate(
            ['config_key' => 'holding_alert_pct'],
            [
                'name'         => '持股告警漲跌幅（%）',
                'config_value' => '10',
                'type'         => 'number',
            ]
        );

        echo "AlertThresholdConfigSeeder 完成。" . PHP_EOL;
    }
}
