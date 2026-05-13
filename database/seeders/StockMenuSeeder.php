<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $defaults = [
            'extra_icon' => '', 'auths' => '', 'frame_src' => '', 'redirect' => '',
            'enter_transition' => '', 'leave_transition' => '', 'active_path' => '',
            'frame_loading' => 1, 'keep_alive' => 0, 'hidden_tag' => 0,
            'fixed_tag' => 0, 'show_parent' => 0, 'menu_type' => 0, 'show_link' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ];

        $menus = [
            // 個股查詢
            ['id' => 63, 'parent_id' => 60, 'title' => '個股查詢', 'name' => 'StockQuery',
             'path' => '/stock/query', 'component' => 'stock/query',
             'icon' => 'ri:search-line', 'rank' => 3],
            // 交易記錄（重用 tg/trade 元件）
            ['id' => 64, 'parent_id' => 60, 'title' => '交易記錄', 'name' => 'StockTrade',
             'path' => '/stock/trade', 'component' => 'tg/trade',
             'icon' => 'ri:exchange-line', 'rank' => 4],
            // 持股管理（重用 tg/holding 元件）
            ['id' => 65, 'parent_id' => 60, 'title' => '持股管理', 'name' => 'StockHolding',
             'path' => '/stock/holding', 'component' => 'tg/holding',
             'icon' => 'ri:funds-line', 'rank' => 5],
        ];

        foreach ($menus as $menu) {
            DB::table('sys_menus')->insertOrIgnore(array_merge($defaults, $menu));
        }

        // 超管角色自動取得新選單權限
        $newIds = [63, 64, 65];
        foreach ($newIds as $menuId) {
            DB::table('sys_role_menus')->insertOrIgnore(['role_id' => 1, 'menu_id' => $menuId]);
        }
    }
}
