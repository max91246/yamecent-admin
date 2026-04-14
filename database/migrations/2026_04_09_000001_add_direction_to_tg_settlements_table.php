<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDirectionToTgSettlementsTable extends Migration
{
    private function resolvedTable(): string
    {
        $p      = \DB::getTablePrefix();
        $tables = \DB::select("SHOW TABLES LIKE 'ya_tg_settlements'");
        $prefix = ($p === '' && count($tables) > 0) ? 'ya_' : $p;
        return $prefix . 'tg_settlements';
    }

    public function up()
    {
        $table = $this->resolvedTable();
        // 若欄位已存在則跳過（冪等）
        $cols = \DB::select("SHOW COLUMNS FROM `{$table}` LIKE 'direction'");
        if (count($cols) === 0) {
            \DB::statement("ALTER TABLE `{$table}` ADD COLUMN `direction` VARCHAR(10) NOT NULL DEFAULT 'buy' AFTER `is_settled`");
        }
    }

    public function down()
    {
        $table = $this->resolvedTable();
        $cols  = \DB::select("SHOW COLUMNS FROM `{$table}` LIKE 'direction'");
        if (count($cols) > 0) {
            \DB::statement("ALTER TABLE `{$table}` DROP COLUMN `direction`");
        }
    }
}
