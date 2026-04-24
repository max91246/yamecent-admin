<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddAvActressMenu extends Migration
{
    public function up()
    {
        $now = now();

        $avParentId = DB::table('admin_menus')->where('name', 'AV 管理')->where('pid', 0)->value('id');

        if (!$avParentId) {
            $avParentId = DB::table('admin_menus')->insertGetId([
                'pid'        => 0,
                'name'       => 'AV 管理',
                'url'        => '',
                'icon'       => 'fa-film',
                'sort'       => 92,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('admin_menu_admin_role')->insert([
                ['admin_role_id' => 1, 'admin_menu_id' => $avParentId],
            ]);
        }

        $menuId = DB::table('admin_menus')->insertGetId([
            'pid'        => $avParentId,
            'name'       => '女優管理',
            'url'        => '/admin/av/actresses',
            'icon'       => 'fa-female',
            'sort'       => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('admin_menu_admin_role')->insert([
            ['admin_role_id' => 1, 'admin_menu_id' => $menuId],
        ]);
    }

    public function down()
    {
        DB::table('admin_menus')->where('name', '女優管理')->delete();
    }
}
