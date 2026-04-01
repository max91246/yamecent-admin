<?php

use App\AdminMenu;
use App\AdminRole;
use Illuminate\Database\Seeder;

class MemberSubmenuSeeder extends Seeder
{
    public function run()
    {
        if (AdminMenu::where('name', '會員列表')->exists()) {
            echo "MemberSubmenuSeeder 已執行過，跳過。" . PHP_EOL;
            return;
        }

        // 1. 找到現有「會員管理」父選單，改為下拉（url='#'）
        $parent = AdminMenu::where('name', '會員管理')->firstOrFail();
        $parent->url = '#';
        $parent->save();

        $role = AdminRole::findOrFail(1);

        // 2. 新增子選單：會員列表
        $child1 = new AdminMenu();
        $child1->fill([
            'pid'  => $parent->id,
            'name' => '會員列表',
            'url'  => '/admin/member/list',
            'icon' => null,
            'sort' => 0,
        ]);
        $child1->save();
        $child1->roles()->attach($role);

        // 3. 新增子選單：資產管理
        $child2 = new AdminMenu();
        $child2->fill([
            'pid'  => $parent->id,
            'name' => '資產管理',
            'url'  => '/admin/member/balance/list',
            'icon' => null,
            'sort' => 0,
        ]);
        $child2->save();
        $child2->roles()->attach($role);

        echo "子選單新增完成 child1.id={$child1->id} child2.id={$child2->id}" . PHP_EOL;
    }
}
