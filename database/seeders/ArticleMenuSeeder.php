<?php

namespace Database\Seeders;

use App\AdminMenu;
use App\AdminRole;
use App\AdminUser;
use Illuminate\Database\Seeder;

class ArticleMenuSeeder extends Seeder
{
    public function run()
    {
        if (AdminMenu::where('name', '文章管理')->exists()) {
            echo "ArticleMenuSeeder 已執行過，跳過。" . PHP_EOL;
            return;
        }

        // 新增文章管理選單
        $menu = new AdminMenu();
        $menu->fill([
            'pid'  => 0,
            'name' => '文章管理',
            'url'  => '/admin/article/list',
            'icon' => 'mdi-newspaper',
            'sort' => 10,
        ]);
        $menu->save();

        // 綁定到超級管理員角色
        $role = AdminRole::find(1);
        $menu->roles()->attach($role);

        echo "選單新增完成 id={$menu->id}" . PHP_EOL;

        // 給 max 指派超級管理員角色（若尚未指派）
        $max = AdminUser::where('account', 'max')->first();
        if ($max && $max->roles->isEmpty()) {
            $max->roles()->attach($role);
            echo "max 角色指派完成" . PHP_EOL;
        }
    }
}
