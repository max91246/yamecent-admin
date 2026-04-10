<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ConvertSharesToStocks extends Migration
{
    public function up()
    {
        // 先將現有資料乘以 1000（張→股）
        DB::statement('UPDATE tg_holdings SET shares = shares * 1000');
        DB::statement('UPDATE tg_holding_trades SET sell_shares = sell_shares * 1000');
        DB::statement('UPDATE tg_settlements SET shares = shares * 1000');

        // 改欄位型別為 BIGINT 以容納更大數值
        DB::statement('ALTER TABLE tg_holdings MODIFY COLUMN shares BIGINT NOT NULL COMMENT "持有股數"');
        DB::statement('ALTER TABLE tg_holding_trades MODIFY COLUMN sell_shares BIGINT NOT NULL COMMENT "賣出股數"');
        DB::statement('ALTER TABLE tg_settlements MODIFY COLUMN shares BIGINT UNSIGNED NOT NULL');
    }

    public function down()
    {
        DB::statement('UPDATE tg_holdings SET shares = shares / 1000');
        DB::statement('UPDATE tg_holding_trades SET sell_shares = sell_shares / 1000');
        DB::statement('UPDATE tg_settlements SET shares = shares / 1000');

        DB::statement('ALTER TABLE tg_holdings MODIFY COLUMN shares INT NOT NULL COMMENT "持有張數"');
        DB::statement('ALTER TABLE tg_holding_trades MODIFY COLUMN sell_shares INT NOT NULL COMMENT "賣出張數"');
        DB::statement('ALTER TABLE tg_settlements MODIFY COLUMN shares INT UNSIGNED NOT NULL');
    }
}
