<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddAvNewsMenu extends Migration
{
    public function up()
    {
        $now        = now();
        $avParentId = DB::table('admin_menus')->where('name', 'AV 管理')->where('pid', 0)->value('id');

        if (!$avParentId) {
            return;
        }

        $menuId = DB::table('admin_menus')->insertGetId([
            'pid'        => $avParentId,
            'name'       => 'AV 速報',
            'url'        => '/admin/av/news',
            'icon'       => 'fa-newspaper',
            'sort'       => 0, // 放在最前面
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('admin_menu_admin_role')->insert([
            ['admin_role_id' => 1, 'admin_menu_id' => $menuId],
        ]);
    }

    public function down()
    {
        DB::table('admin_menus')->where('name', 'AV 速報')->delete();
    }
}
