<?php

namespace Database\Seeders;

use App\AdminMenu;
use App\AdminRole;
use Illuminate\Database\Seeder;

class CommentMenuSeeder extends Seeder
{
    public function run()
    {
        // 防重複執行
        if (AdminMenu::where('name', '文章列表')->exists()) {
            echo "CommentMenuSeeder 已執行過，跳過。" . PHP_EOL;
            return;
        }

        // 1. 找到現有「文章管理」父選單，改為下拉（url='#'）
        $parent = AdminMenu::where('name', '文章管理')->firstOrFail();
        $parent->url = '#';
        $parent->save();

        $role = AdminRole::findOrFail(1);

        // 2. 新增子選單：文章列表
        $child1 = new AdminMenu();
        $child1->fill([
            'pid'  => $parent->id,
            'name' => '文章列表',
            'url'  => '/admin/article/list',
            'icon' => null,
            'sort' => 0,
        ]);
        $child1->save();
        $child1->roles()->attach($role);

        // 3. 新增子選單：留言內容
        $child2 = new AdminMenu();
        $child2->fill([
            'pid'  => $parent->id,
            'name' => '留言內容',
            'url'  => '/admin/comment/list',
            'icon' => null,
            'sort' => 1,
        ]);
        $child2->save();
        $child2->roles()->attach($role);

        echo "文章管理子選單新增完成 child1.id={$child1->id} child2.id={$child2->id}" . PHP_EOL;
    }
}
