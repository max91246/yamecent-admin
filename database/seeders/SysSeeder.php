<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SysSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $menus = [
            ['id' => 1,  'parent_id' => 0, 'menu_type' => 0, 'title' => '系統管理', 'name' => 'System',     'path' => '/system',            'component' => '',                  'icon' => 'ri:settings-3-line', 'rank' => 1, 'show_link' => 1],
            ['id' => 2,  'parent_id' => 1, 'menu_type' => 0, 'title' => '用戶管理', 'name' => 'SystemUser', 'path' => '/system/user/index', 'component' => 'system/user/index', 'icon' => 'ri:admin-line',      'rank' => 1, 'show_link' => 1],
            ['id' => 3,  'parent_id' => 1, 'menu_type' => 0, 'title' => '角色管理', 'name' => 'SystemRole', 'path' => '/system/role/index', 'component' => 'system/role/index', 'icon' => 'ri:user-star-line',  'rank' => 2, 'show_link' => 1],
            ['id' => 4,  'parent_id' => 1, 'menu_type' => 0, 'title' => '菜單管理', 'name' => 'SystemMenu', 'path' => '/system/menu/index', 'component' => 'system/menu/index', 'icon' => 'ep:menu',            'rank' => 3, 'show_link' => 1],
            ['id' => 10, 'parent_id' => 2, 'menu_type' => 3, 'title' => '新增用戶', 'name' => '', 'path' => '', 'component' => '', 'icon' => '', 'rank' => 1, 'show_link' => 0, 'auths' => 'system:user:add'],
            ['id' => 11, 'parent_id' => 2, 'menu_type' => 3, 'title' => '修改用戶', 'name' => '', 'path' => '', 'component' => '', 'icon' => '', 'rank' => 2, 'show_link' => 0, 'auths' => 'system:user:edit'],
            ['id' => 12, 'parent_id' => 2, 'menu_type' => 3, 'title' => '刪除用戶', 'name' => '', 'path' => '', 'component' => '', 'icon' => '', 'rank' => 3, 'show_link' => 0, 'auths' => 'system:user:delete'],
            ['id' => 13, 'parent_id' => 3, 'menu_type' => 3, 'title' => '新增角色', 'name' => '', 'path' => '', 'component' => '', 'icon' => '', 'rank' => 1, 'show_link' => 0, 'auths' => 'system:role:add'],
            ['id' => 14, 'parent_id' => 3, 'menu_type' => 3, 'title' => '修改角色', 'name' => '', 'path' => '', 'component' => '', 'icon' => '', 'rank' => 2, 'show_link' => 0, 'auths' => 'system:role:edit'],
            ['id' => 15, 'parent_id' => 3, 'menu_type' => 3, 'title' => '刪除角色', 'name' => '', 'path' => '', 'component' => '', 'icon' => '', 'rank' => 3, 'show_link' => 0, 'auths' => 'system:role:delete'],
        ];

        $menuDefaults = [
            'extra_icon' => '', 'auths' => '', 'frame_src' => '', 'redirect' => '',
            'enter_transition' => '', 'leave_transition' => '', 'active_path' => '',
            'frame_loading' => 1, 'keep_alive' => 0, 'hidden_tag' => 0,
            'fixed_tag' => 0, 'show_parent' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ];

        foreach ($menus as $menu) {
            DB::table('sys_menus')->insertOrIgnore(array_merge($menuDefaults, $menu));
        }

        DB::table('sys_roles')->insertOrIgnore([
            ['id' => 1, 'name' => '超級管理員', 'code' => 'admin',  'status' => 1, 'remark' => '', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => '一般用戶',   'code' => 'common', 'status' => 1, 'remark' => '', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 超管角色擁有所有菜單
        $allMenuIds = DB::table('sys_menus')->pluck('id');
        foreach ($allMenuIds as $menuId) {
            DB::table('sys_role_menus')->insertOrIgnore(['role_id' => 1, 'menu_id' => $menuId]);
        }

        // 超管用戶（同步 admin_users 的 admin 帳號密碼）
        $adminUser = DB::table('admin_users')->where('account', 'admin')->first();
        DB::table('sys_users')->insertOrIgnore([
            'id'         => 1,
            'username'   => 'admin',
            'password'   => $adminUser ? $adminUser->password : Hash::make('admin123'),
            'nickname'   => '超級管理員',
            'status'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('sys_user_roles')->insertOrIgnore(['user_id' => 1, 'role_id' => 1]);
    }
}
