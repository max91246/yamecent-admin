<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportTwHolidays extends Command
{
    protected $signature   = 'holiday:import {year? : 要匯入的年份，預設為下一年}';
    protected $description = '從 TWSE OpenAPI 匯入台股休市日，並清除兩年前舊資料';

    public function handle()
    {
        $year    = (int) ($this->argument('year') ?: now()->addYear()->year);
        $prevYear = $year - 2;

        $this->info("匯入 {$year} 年台股休市日...");

        $holidays = $this->fetchFromTwse($year);

        if (empty($holidays)) {
            $this->error("TWSE OpenAPI 無 {$year} 年資料，請稍後再試或手動匯入。");
            return 1;
        }

        // upsert 寫入（重複日期直接更新 reason）
        foreach ($holidays as $h) {
            DB::table('tw_market_holidays')->upsert(
                [['date' => $h['date'], 'reason' => $h['reason']]],
                ['date'],
                ['reason']
            );
        }

        $this->info("✓ 寫入 " . count($holidays) . " 筆 {$year} 年休市日");

        // 清除兩年前的舊資料
        $deleted = DB::table('tw_market_holidays')
            ->whereYear('date', $prevYear)
            ->delete();

        if ($deleted > 0) {
            $this->info("✓ 已清除 {$prevYear} 年舊資料（{$deleted} 筆）");
        }

        // 清除相關 Redis cache
        Cache::forget("tw_holidays:{$year}");
        Cache::forget("tw_holidays:{$prevYear}");

        Log::info("[holiday:import] 完成", ['year' => $year, 'imported' => count($holidays), 'deleted_year' => $prevYear, 'deleted' => $deleted]);

        return 0;
    }

    private function fetchFromTwse(int $year): array
    {
        try {
            $client = new Client(['timeout' => 15]);
            $res    = $client->get('https://openapi.twse.com.tw/v1/holidaySchedule/holidaySchedule', [
                'headers' => ['Accept' => 'application/json'],
            ]);
            $list = json_decode((string) $res->getBody(), true);
        } catch (\Exception $e) {
            $this->error('TWSE API 請求失敗：' . $e->getMessage());
            return [];
        }

        if (!is_array($list)) {
            return [];
        }

        $holidays = [];
        foreach ($list as $row) {
            // 欄位：Date(民國YYYMMDD 或 YYYY-MM-DD), Name
            $dateRaw = $row['Date'] ?? $row['date'] ?? '';
            $reason  = $row['Name'] ?? $row['name'] ?? '';

            $date = $this->parseDate($dateRaw);
            if (!$date) continue;

            // 只取目標年份、且只取平日（假日清單本身應只含休市日，但保險起見過濾週末）
            if ((int) substr($date, 0, 4) !== $year) continue;

            $dow = date('N', strtotime($date)); // 1=Mon ... 7=Sun
            if ($dow >= 6) continue;            // 跳過週末

            $holidays[] = ['date' => $date, 'reason' => mb_substr($reason, 0, 50)];
        }

        return $holidays;
    }

    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);

        // YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
            return $raw;
        }

        // 民國 YYYMMDD（例：1150101）
        if (preg_match('/^(\d{3})(\d{2})(\d{2})$/', $raw, $m)) {
            $year = (int) $m[1] + 1911;
            return "{$year}-{$m[2]}-{$m[3]}";
        }

        return null;
    }
}
