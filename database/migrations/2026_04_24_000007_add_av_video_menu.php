<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddAvVideoMenu extends Migration
{
    public function up()
    {
        $now        = now();
        $avParentId = DB::table('admin_menus')->where('name', 'AV 管理')->where('pid', 0)->value('id');
        if (!$avParentId) return;

        $menuId = DB::table('admin_menus')->insertGetId([
            'pid'        => $avParentId,
            'name'       => '新片速報',
            'url'        => '/admin/av/videos',
            'icon'       => 'fa-film',
            'sort'       => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('admin_menu_admin_role')->insert([
            ['admin_role_id' => 1, 'admin_menu_id' => $menuId],
        ]);

        // 原 AV 速報 改名為「女優速報」避免混淆
        DB::table('admin_menus')->where('name', 'AV 速報')->update([
            'name' => '女優速報',
            'sort' => 2,
            'updated_at' => $now,
        ]);

        // 女優管理 sort 後推
        DB::table('admin_menus')->where('name', '女優管理')->update([
            'sort' => 3,
            'updated_at' => $now,
        ]);
    }

    public function down()
    {
        DB::table('admin_menus')->where('name', '新片速報')->delete();
    }
}
