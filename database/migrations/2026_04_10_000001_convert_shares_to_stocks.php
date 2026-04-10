<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ConvertSharesToStocks extends Migration
{
    private function tablePrefix(): string
    {
        // 優先取 config 前綴；若為空則嘗試偵測實際前綴
        $p = DB::getTablePrefix();
        if ($p !== '') return $p;

        // 偵測：若 ya_tg_holdings 存在則前綴為 ya_
        $tables = DB::select("SHOW TABLES LIKE 'ya_tg_holdings'");
        return count($tables) > 0 ? 'ya_' : '';
    }

    public function up()
    {
        $p = $this->tablePrefix();

        // 先將現有資料乘以 1000（張→股）
        DB::statement("UPDATE {$p}tg_holdings SET shares = shares * 1000");
        DB::statement("UPDATE {$p}tg_holding_trades SET sell_shares = sell_shares * 1000");
        DB::statement("UPDATE {$p}tg_settlements SET shares = shares * 1000");

        // 改欄位型別為 BIGINT 以容納更大數值
        DB::statement("ALTER TABLE {$p}tg_holdings MODIFY COLUMN shares BIGINT NOT NULL COMMENT '持有股數'");
        DB::statement("ALTER TABLE {$p}tg_holding_trades MODIFY COLUMN sell_shares BIGINT NOT NULL COMMENT '賣出股數'");
        DB::statement("ALTER TABLE {$p}tg_settlements MODIFY COLUMN shares BIGINT UNSIGNED NOT NULL");
    }

    public function down()
    {
        $p = $this->tablePrefix();

        DB::statement("UPDATE {$p}tg_holdings SET shares = shares / 1000");
        DB::statement("UPDATE {$p}tg_holding_trades SET sell_shares = sell_shares / 1000");
        DB::statement("UPDATE {$p}tg_settlements SET shares = shares / 1000");

        DB::statement("ALTER TABLE {$p}tg_holdings MODIFY COLUMN shares INT NOT NULL COMMENT '持有張數'");
        DB::statement("ALTER TABLE {$p}tg_holding_trades MODIFY COLUMN sell_shares INT NOT NULL COMMENT '賣出張數'");
        DB::statement("ALTER TABLE {$p}tg_settlements MODIFY COLUMN shares INT UNSIGNED NOT NULL");
    }
}
