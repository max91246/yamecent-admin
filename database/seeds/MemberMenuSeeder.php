<?php

use App\AdminMenu;
use App\AdminRole;
use Illuminate\Database\Seeder;

class MemberMenuSeeder extends Seeder
{
    public function run()
    {
        $menu = new AdminMenu();
        $menu->fill([
            'pid'  => 0,
            'name' => '會員管理',
            'url'  => '/admin/member/list',
            'icon' => 'mdi mdi-account-multiple',
            'sort' => 20,
        ]);
        $menu->save();

        $role = AdminRole::find(1);
        $menu->roles()->attach($role);

        echo "選單新增完成 id={$menu->id}" . PHP_EOL;
    }
}
