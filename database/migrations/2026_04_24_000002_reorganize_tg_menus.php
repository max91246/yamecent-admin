<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ReorganizeTgMenus extends Migration
{
    public function up()
    {
        $now   = now();
        $roleId = 1; // 超級管理員

        // ── 1. 取得目前各子項目 ID（依名稱查）──────────────────────
        $botListId    = DB::table('admin_menus')->where('name', '機器人管理')->value('id');
        $msgListId    = DB::table('admin_menus')->where('name', '訊息記錄')->value('id');
        $holdingId    = DB::table('admin_menus')->where('name', '持股管理')->value('id');
        $tradeId      = DB::table('admin_menus')->where('name', '交易記錄')->value('id');
        $disposalId   = DB::table('admin_menus')->where('name', '處置股查詢')->value('id');
        $stockQueryId = DB::table('admin_menus')->where('name', '台股查詢')->value('id');

        // ── 2. 把舊的「TG 機器人」父節點改名為「機器人管理」──────────
        DB::table('admin_menus')
            ->where('name', 'TG 機器人')
            ->update(['name' => '機器人管理', 'sort' => 91, 'updated_at' => $now]);

        $botParentId = DB::table('admin_menus')->where('name', '機器人管理')->where('pid', 0)->value('id');

        // ── 3. 建立「股票功能」父節點 ───────────────────────────────
        $stockParentId = DB::table('admin_menus')->insertGetId([
            'pid'        => 0,
            'name'       => '股票功能',
            'url'        => '',
            'icon'       => 'fa-chart-line',
            'sort'       => 90,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('admin_menu_admin_role')->insert([
            ['admin_role_id' => $roleId, 'admin_menu_id' => $stockParentId],
        ]);

        // ── 4. 建立「AV 管理」父節點 ────────────────────────────────
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
            ['admin_role_id' => $roleId, 'admin_menu_id' => $avParentId],
        ]);

        // ── 5. 把股票相關子項目移到「股票功能」 ──────────────────────
        $moves = [
            $holdingId    => ['sort' => 1],
            $tradeId      => ['sort' => 2],
            $disposalId   => ['sort' => 3],
            $stockQueryId => ['sort' => 4],
        ];
        foreach ($moves as $id => $extra) {
            if ($id) {
                DB::table('admin_menus')->where('id', $id)->update(array_merge(
                    ['pid' => $stockParentId, 'updated_at' => $now],
                    $extra
                ));
            }
        }

        // ── 6. 機器人管理子項目順序整理 ──────────────────────────────
        if ($botListId) DB::table('admin_menus')->where('id', $botListId)->update(['sort' => 1, 'updated_at' => $now]);
        if ($msgListId) DB::table('admin_menus')->where('id', $msgListId)->update(['sort' => 2, 'updated_at' => $now]);
    }

    public function down()
    {
        // 不實作 rollback，menu 結構異動風險高
    }
}
