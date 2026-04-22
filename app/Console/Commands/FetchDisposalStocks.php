<?php

namespace App\Console\Commands;

use App\DisposalStock;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FetchDisposalStocks extends Command
{
    protected $signature   = 'fetch:disposal-stocks';
    protected $description = '每日抓取 TPEX 上櫃 + TWSE 上市處置股資訊，寫入 ya_disposal_stocks';

    public function handle()
    {
        $client = new Client(['timeout' => 15]);
        $saved  = 0;
        $skip   = 0;

        Log::channel('tg_webhook')->info('[處置股] 開始抓取處置股資訊');

        // ── 0. 清除已到期的舊處置記錄 ───────────────────────────
        $today   = Carbon::now('Asia/Taipei')->toDateString();
        $deleted = DisposalStock::where('end_date', '<', $today)->delete();
        if ($deleted > 0) {
            $this->info("已清除 {$deleted} 筆過期處置記錄。");
            Log::channel('tg_webhook')->info('[處置股] 清除過期記錄', ['deleted' => $deleted]);
        }

        // ── 1. TPEX 上櫃 ────────────────────────────────────────
        $tpexUrl = rtrim(getConfig('tpex_disposal_url'), '/');
        $this->info('抓取 TPEX 上櫃處置股...');

        try {
            $res  = $client->get($tpexUrl, [
                'headers' => ['User-Agent' => 'Mozilla/5.0'],
            ]);
            $list = json_decode((string) $res->getBody(), true);

            if (is_array($list)) {
                foreach ($list as $row) {
                    $result = $this->saveTpex($row);
                    $result === 'saved' ? $saved++ : $skip++;
                }
                $this->info('TPEX 處理完，共 ' . count($list) . ' 筆');
            }
        } catch (RequestException $e) {
            $this->error('TPEX 請求失敗：' . $e->getMessage());
            Log::channel('tg_webhook')->error('[處置股] TPEX 請求失敗', ['error' => $e->getMessage()]);
        }

        // ── 2. TWSE 上市 ────────────────────────────────────────
        $twseUrl = rtrim(getConfig('twse_disposal_url'), '/');
        $this->info('抓取 TWSE 上市處置股...');

        try {
            $res  = $client->get($twseUrl, [
                'query'   => ['response' => 'json'],
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0',
                    'Referer'    => 'https://www.twse.com.tw/',
                ],
            ]);
            $data = json_decode((string) $res->getBody(), true);

            if (($data['stat'] ?? '') === 'OK' && !empty($data['data'])) {
                foreach ($data['data'] as $row) {
                    $result = $this->saveTwse($row);
                    $result === 'saved' ? $saved++ : $skip++;
                }
                $this->info('TWSE 處理完，共 ' . count($data['data']) . ' 筆');
            }
        } catch (RequestException $e) {
            $this->error('TWSE 請求失敗：' . $e->getMessage());
            Log::channel('tg_webhook')->error('[處置股] TWSE 請求失敗', ['error' => $e->getMessage()]);
        }

        $this->info("完成。新增 {$saved} 筆，略過 {$skip} 筆（已存在）。");
        Log::channel('tg_webhook')->info('[處置股] 抓取完成', ['saved' => $saved, 'skip' => $skip]);

        // ── 寫入 Redis Cache ─────────────────────────────────────
        $this->populateRedisCache();

        return 0;
    }

    /**
     * 把今日有效的處置股全部寫入 Redis。
     * key: disposal:{stock_code}  value: JSON 資料
     * key: disposal:cache_ready   value: 1（旗標）
     * TTL: 到今天 07:59（因為 08:00 會重新抓取）
     */
    private function populateRedisCache(): void
    {
        $now    = Carbon::now('Asia/Taipei');
        $expire = $now->copy()->startOfDay()->setTime(7, 59, 0);
        if ($expire->lte($now)) {
            $expire->addDay();
        }
        $ttl = max(60, (int) $now->diffInSeconds($expire));

        $actives = DisposalStock::where('end_date', '>=', $now->toDateString())->get();

        // 先清掉舊有的旗標，再重寫
        Cache::forget('disposal:cache_ready');

        foreach ($actives as $d) {
            Cache::put('disposal:' . $d->stock_code, json_encode([
                'start_date' => $d->start_date->toDateString(),
                'end_date'   => $d->end_date->toDateString(),
                'reason'     => $d->reason,
                'market'     => $d->market,
            ]), $ttl);
        }

        Cache::put('disposal:cache_ready', 1, $ttl);

        $this->info("Redis Cache 已更新，共 {$actives->count()} 筆有效處置股，TTL {$ttl}s（到 {$expire->format('Y-m-d H:i')}）。");
        Log::channel('tg_webhook')->info('[處置股] Redis cache 更新完成', ['count' => $actives->count(), 'ttl' => $ttl]);
    }

    /**
     * 儲存 TPEX 一筆資料
     * 日期格式：Date=1150413（ROC YYYMMDD），DispositionPeriod=1150414~1150427
     */
    private function saveTpex(array $row): string
    {
        $code   = $row['SecuritiesCompanyCode'] ?? null;
        $name   = $row['CompanyName']           ?? '';
        $period = $row['DispositionPeriod']     ?? '';
        $reason = $row['DispositionReasons']    ?? '';
        $cond   = $row['DisposalCondition']     ?? '';

        if (!$code || !$period) {
            return 'skip';
        }

        // 公告日期
        $announcedDate = $this->rocToDate($row['Date'] ?? '');

        // 處置期間 "1150414~1150427"
        [$startRoc, $endRoc] = array_pad(explode('~', $period), 2, '');
        $startDate = $this->rocToDate(trim($startRoc));
        $endDate   = $this->rocToDate(trim($endRoc));

        if (!$startDate || !$endDate) {
            return 'skip';
        }

        $exists = DisposalStock::where('market', 'tpex')
            ->where('stock_code', $code)
            ->where('start_date', $startDate)
            ->exists();

        if ($exists) {
            return 'skip';
        }

        DisposalStock::create([
            'market'         => 'tpex',
            'stock_code'     => $code,
            'stock_name'     => $name,
            'announced_date' => $announcedDate,
            'start_date'     => $startDate,
            'end_date'       => $endDate,
            'reason'         => mb_substr($reason, 0, 255),
            'condition'      => $cond,
        ]);

        $this->line("  [TPEX 新增] {$code} {$name} {$startDate}~{$endDate}");

        return 'saved';
    }

    /**
     * 儲存 TWSE 一筆資料
     * fields: [0]編號 [1]公告日期"115/04/16" [2]代號 [3]名稱 [4]累積次數
     *         [5]原因 [6]處置起迄"115/04/17至115/04/30" [7]措施 [8]內容 [9]備註
     */
    private function saveTwse(array $row): string
    {
        $code   = $row[2] ?? null;
        $name   = $row[3] ?? '';
        $period = $row[6] ?? '';
        $reason = $row[5] ?? '';
        $cond   = ($row[7] ?? '') . "\n" . ($row[8] ?? '');

        if (!$code || !$period) {
            return 'skip';
        }

        $announcedDate = $this->rocSlashToDate($row[1] ?? '');

        // 期間格式："115/04/17至115/04/30"
        $parts     = preg_split('/至|~/', $period);
        $startDate = $this->rocSlashToDate(trim($parts[0] ?? ''));
        $endDate   = $this->rocSlashToDate(trim($parts[1] ?? ''));

        if (!$startDate || !$endDate) {
            return 'skip';
        }

        $exists = DisposalStock::where('market', 'twse')
            ->where('stock_code', $code)
            ->where('start_date', $startDate)
            ->exists();

        if ($exists) {
            return 'skip';
        }

        DisposalStock::create([
            'market'         => 'twse',
            'stock_code'     => $code,
            'stock_name'     => $name,
            'announced_date' => $announcedDate,
            'start_date'     => $startDate,
            'end_date'       => $endDate,
            'reason'         => mb_substr($reason, 0, 255),
            'condition'      => trim($cond),
        ]);

        $this->line("  [TWSE 新增] {$code} {$name} {$startDate}~{$endDate}");

        return 'saved';
    }

    /**
     * ROC 日期 YYYMMDD → Y-m-d
     * 例：1150414 → 2026-04-14
     */
    private function rocToDate(string $roc): ?string
    {
        $roc = trim($roc);
        if (strlen($roc) !== 7 || !ctype_digit($roc)) {
            return null;
        }
        $year  = (int) substr($roc, 0, 3) + 1911;
        $month = substr($roc, 3, 2);
        $day   = substr($roc, 5, 2);

        return "{$year}-{$month}-{$day}";
    }

    /**
     * ROC 日期 YYY/MM/DD → Y-m-d
     * 例：115/04/14 → 2026-04-14
     */
    private function rocSlashToDate(string $roc): ?string
    {
        $roc = trim($roc);
        if (!preg_match('/^(\d{3})\/(\d{2})\/(\d{2})$/', $roc, $m)) {
            return null;
        }
        $year = (int) $m[1] + 1911;

        return "{$year}-{$m[2]}-{$m[3]}";
    }
}
