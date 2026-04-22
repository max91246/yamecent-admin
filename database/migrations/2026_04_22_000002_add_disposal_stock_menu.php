<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddDisposalStockMenu extends Migration
{
    public function up()
    {
        $now = now();

        // 掛在「TG 機器人」(pid=0 父選單 id=10) 下
        $tgParentId = DB::table('admin_menus')->where('name', 'TG 機器人')->value('id');

        $menuId = DB::table('admin_menus')->insertGetId([
            'pid'        => $tgParentId,
            'name'       => '處置股查詢',
            'url'        => '/admin/disposal-stock/list',
            'icon'       => 'fa-exclamation-triangle',
            'sort'       => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 授權給超級管理員 role (id=1)
        DB::table('admin_menu_admin_role')->insert([
            ['admin_role_id' => 1, 'admin_menu_id' => $menuId],
        ]);
    }

    public function down()
    {
        DB::table('admin_menus')->where('name', '處置股查詢')->delete();
    }
}
