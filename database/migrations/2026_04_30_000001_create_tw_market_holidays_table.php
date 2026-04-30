<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateTwMarketHolidaysTable extends Migration
{
    public function up()
    {
        Schema::create('tw_market_holidays', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->string('reason', 50);
        });

        // 2026 年台股休市日（來源：TWSE OpenAPI）
        $holidays2026 = [
            ['date' => '2026-01-01', 'reason' => '元旦'],
            ['date' => '2026-02-12', 'reason' => '農曆春節前結算交割作業'],
            ['date' => '2026-02-13', 'reason' => '農曆春節前結算交割作業'],
            ['date' => '2026-02-16', 'reason' => '農曆春節'],
            ['date' => '2026-02-17', 'reason' => '農曆春節'],
            ['date' => '2026-02-18', 'reason' => '農曆春節'],
            ['date' => '2026-02-19', 'reason' => '農曆春節'],
            ['date' => '2026-02-20', 'reason' => '農曆春節'],
            ['date' => '2026-02-27', 'reason' => '和平紀念日補假'],
            ['date' => '2026-04-03', 'reason' => '兒童節補假'],
            ['date' => '2026-04-06', 'reason' => '民族掃墓節'],
            ['date' => '2026-05-01', 'reason' => '勞動節'],
            ['date' => '2026-06-19', 'reason' => '端午節'],
            ['date' => '2026-09-25', 'reason' => '中秋節'],
            ['date' => '2026-09-28', 'reason' => '教師節'],
            ['date' => '2026-10-09', 'reason' => '國慶日補假'],
            ['date' => '2026-10-26', 'reason' => '臺灣光復紀念日補假'],
            ['date' => '2026-12-25', 'reason' => '行憲紀念日'],
        ];

        DB::table('tw_market_holidays')->insert($holidays2026);
    }

    public function down()
    {
        Schema::dropIfExists('tw_market_holidays');
    }
}
