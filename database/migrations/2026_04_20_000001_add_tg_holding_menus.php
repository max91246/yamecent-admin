<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddTgHoldingMenus extends Migration
{
    public function up()
    {
        $now = now();

        // 新增「持股管理」menu
        $holdingMenuId = DB::table('admin_menus')->insertGetId([
            'pid'        => 10,
            'name'       => '持股管理',
            'url'        => '/admin/tg-holding/list',
            'icon'       => 'fa-bar-chart',
            'sort'       => 3,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 新增「交易記錄」menu
        $tradeMenuId = DB::table('admin_menus')->insertGetId([
            'pid'        => 10,
            'name'       => '交易記錄',
            'url'        => '/admin/tg-holding/trade-list',
            'icon'       => 'fa-list',
            'sort'       => 4,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 授權給超级管理員 role (id=1)
        DB::table('admin_menu_admin_role')->insert([
            ['admin_role_id' => 1, 'admin_menu_id' => $holdingMenuId],
            ['admin_role_id' => 1, 'admin_menu_id' => $tradeMenuId],
        ]);
    }

    public function down()
    {
        DB::table('admin_menus')
            ->whereIn('url', ['/admin/tg-holding/list', '/admin/tg-holding/trade-list'])
            ->delete();
    }
}
