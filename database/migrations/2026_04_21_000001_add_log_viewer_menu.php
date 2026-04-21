<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddLogViewerMenu extends Migration
{
    public function up()
    {
        $now = now();

        // 新增「系統日誌」父選單（系統設置層級）
        $parentId = DB::table('admin_menus')->insertGetId([
            'pid'        => 0,
            'name'       => '系統日誌',
            'url'        => '#',
            'icon'       => 'fa-file-text-o',
            'sort'       => 99,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 新增「Log 查看」子選單（外部連結）
        $childId = DB::table('admin_menus')->insertGetId([
            'pid'        => $parentId,
            'name'       => 'Log 查看',
            'url'        => '/log-viewer',
            'icon'       => 'fa-search',
            'sort'       => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 授權給超級管理員 role (id=1)
        DB::table('admin_menu_admin_role')->insert([
            ['admin_role_id' => 1, 'admin_menu_id' => $parentId],
            ['admin_role_id' => 1, 'admin_menu_id' => $childId],
        ]);
    }

    public function down()
    {
        DB::table('admin_menus')
            ->whereIn('name', ['系統日誌', 'Log 查看'])
            ->delete();
    }
}
