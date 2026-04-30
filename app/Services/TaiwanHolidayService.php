<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TaiwanHolidayService
{
    /**
     * 判斷某日是否為台股休市日（國定假日，不含週末）。
     */
    public static function isHoliday(string $date): bool
    {
        $year     = (int) substr($date, 0, 4);
        $holidays = self::getHolidays($year);
        return in_array($date, $holidays);
    }

    /**
     * 計算 T+N 台股交割日（跳過週末與國定假日）。
     * 若目標年份無假日資料，fallback 只跳週末。
     */
    public static function calcSettleDate(\Carbon\Carbon $tradeDate, int $n = 2): \Carbon\Carbon
    {
        $d    = $tradeDate->copy()->startOfDay();
        $days = 0;

        while ($days < $n) {
            $d->addDay();
            if ($d->isWeekend()) continue;
            if (self::isHoliday($d->toDateString())) continue;
            $days++;
        }

        return $d;
    }

    /**
     * 取得某年所有休市日期陣列（Redis cache 1天）。
     */
    public static function getHolidays(int $year): array
    {
        return Cache::remember("tw_holidays:{$year}", 86400, function () use ($year) {
            return DB::table('tw_market_holidays')
                ->whereYear('date', $year)
                ->pluck('date')
                ->map(fn($d) => substr($d, 0, 10))
                ->toArray();
        });
    }
}
