<?php

use App\AdminMenu;
use App\AdminRole;
use Illuminate\Database\Seeder;

class TgHoldingMenuSeeder extends Seeder
{
    public function run()
    {
        if (AdminMenu::where('name', '持股管理')->exists()) {
            echo "TgHoldingMenuSeeder 已執行過，跳過。" . PHP_EOL;
            return;
        }

        $role   = AdminRole::findOrFail(1);
        $parent = AdminMenu::where('name', 'TG 機器人')->first();

        if (!$parent) {
            echo "找不到 TG 機器人父選單，請先執行 TgBotMenuSeeder。" . PHP_EOL;
            return;
        }

        $child1 = new AdminMenu();
        $child1->fill([
            'pid'  => $parent->id,
            'name' => '持股管理',
            'url'  => '/admin/tg-holding/list',
            'icon' => null,
            'sort' => 3,
        ]);
        $child1->save();
        $child1->roles()->attach($role);

        $child2 = new AdminMenu();
        $child2->fill([
            'pid'  => $parent->id,
            'name' => '交易記錄',
            'url'  => '/admin/tg-holding/trade-list',
            'icon' => null,
            'sort' => 4,
        ]);
        $child2->save();
        $child2->roles()->attach($role);

        echo "持股/交易選單新增完成" . PHP_EOL;
    }
}
