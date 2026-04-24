<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddStockQueryMenu extends Migration
{
    public function up()
    {
        $now = now();

        $tgParentId = DB::table('admin_menus')->where('name', 'TG 機器人')->value('id');

        $menuId = DB::table('admin_menus')->insertGetId([
            'pid'        => $tgParentId,
            'name'       => '台股查詢',
            'url'        => '/admin/stock-query',
            'icon'       => 'fa-search',
            'sort'       => 6,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('admin_menu_admin_role')->insert([
            ['admin_role_id' => 1, 'admin_menu_id' => $menuId],
        ]);
    }

    public function down()
    {
        DB::table('admin_menus')->where('name', '台股查詢')->delete();
    }
}
