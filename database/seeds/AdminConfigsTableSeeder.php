<?php

use App\AdminConfig;
use Illuminate\Database\Seeder;

class AdminConfigsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (AdminConfig::where('config_key', 'admin_logo')->exists()) {
            echo "AdminConfigsTableSeeder 已執行過，跳過。" . PHP_EOL;
            return;
        }

        $adminConfig = new AdminConfig();
        $adminConfig->fill([
            'name'         => '后台管理LOGO',
            'config_key'   => 'admin_logo',
            'config_value' => '/uploads/config/20181107/5be269ef937d1.png',
            'type'         => 'image',
        ]);
        $adminConfig->save();
    }
}
