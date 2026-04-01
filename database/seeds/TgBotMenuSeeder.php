<?php

use App\AdminMenu;
use App\AdminRole;
use Illuminate\Database\Seeder;

class TgBotMenuSeeder extends Seeder
{
    public function run()
    {
        // 防重複執行
        if (AdminMenu::where('name', 'TG 機器人')->exists()) {
            echo "TgBotMenuSeeder 已執行過，跳過。" . PHP_EOL;
            return;
        }

        $role = AdminRole::findOrFail(1);

        // 1. 新增父選單「TG 機器人」
        $parent = new AdminMenu();
        $parent->fill([
            'pid'  => 0,
            'name' => 'TG 機器人',
            'url'  => '',
            'icon' => 'mdi mdi-robot',
            'sort' => 90,
        ]);
        $parent->save();
        $parent->roles()->attach($role);

        // 2. 新增子選單「機器人管理」
        $child1 = new AdminMenu();
        $child1->fill([
            'pid'  => $parent->id,
            'name' => '機器人管理',
            'url'  => '/admin/tg-bot/list',
            'icon' => null,
            'sort' => 1,
        ]);
        $child1->save();
        $child1->roles()->attach($role);

        // 3. 新增子選單「訊息記錄」
        $child2 = new AdminMenu();
        $child2->fill([
            'pid'  => $parent->id,
            'name' => '訊息記錄',
            'url'  => '/admin/tg-message/list',
            'icon' => null,
            'sort' => 2,
        ]);
        $child2->save();
        $child2->roles()->attach($role);

        echo "TG 機器人選單新增完成 parent.id={$parent->id} child1.id={$child1->id} child2.id={$child2->id}" . PHP_EOL;
    }
}
