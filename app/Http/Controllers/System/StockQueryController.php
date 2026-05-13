<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class StockQueryController extends Controller
{
    // ── 報價 + 日K（快速，約 1~2s）────────────────────────────────

    public function quote(Request $request)
    {
        $code = $this->validateCode($request);
        if (!$code) return $this->badCode();

        $cacheKey = "sq_quote_{$code}_" . now()->format('Ymd_H');
        $data = Cache::remember($cacheKey, 3600, function () use ($code) {
            // 先試上市(.TW)，只有完全找不到股票名稱才 fallback 到 .TWO（上櫃）
            $ticker = $code . '.TW';
            $yData  = $this->fetchYahoo($ticker);

            $hasData = !empty($yData['quote']['name']) && $yData['quote']['name'] !== $ticker;
            if (!$hasData) {
                $ticker = $code . '.TWO';
                $yData  = $this->fetchYahoo($ticker);
            }

            // 強制用我們實際查的後綴，不信任 Yahoo meta.symbol（可能回錯後綴）
            if (!empty($yData['quote'])) {
                $yData['quote']['ticker'] = $ticker;
            }

            return ['ticker' => $ticker] + $yData;
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ── 三大法人（慢，TWSE 多日並行，約 3~5s）────────────────────

    public function institutional(Request $request)
    {
        $code = $this->validateCode($request);
        if (!$code) return $this->badCode();

        $cacheKey = "sq_inst_{$code}_" . now()->format('Ymd');
        $data = Cache::remember($cacheKey, 86400, fn() => $this->fetchInstitutional($code));

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ── 大戶持股分散表（TDCC）────────────────────────────────────

    public function distribution(Request $request)
    {
        $code = $this->validateCode($request);
        if (!$code) return $this->badCode();

        $cacheKey = "sq_dist_{$code}_" . now()->format('YW');
        $data = Cache::remember($cacheKey, 7200, fn() => $this->fetchDistribution($code));

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ── 月營收（MOPS）────────────────────────────────────────────

    public function revenue(Request $request)
    {
        $code = $this->validateCode($request);
        if (!$code) return $this->badCode();

        $cacheKey = "sq_rev_{$code}_" . now()->format('YmdH');
        $data = Cache::remember($cacheKey, 3600, fn() => $this->fetchRevenue($code));

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ── 相關新聞（Yahoo Finance）──────────────────────────────────

    public function news(Request $request)
    {
        $code = $this->validateCode($request);
        if (!$code) return $this->badCode();

        // ticker 由前端傳入（避免重複查一次 .TW/.TWO）
        $ticker   = $request->input('ticker', $code . '.TW');
        $cacheKey = "sq_news_{$code}_" . now()->format('YmdH');
        $data = Cache::remember($cacheKey, 3600, fn() => $this->fetchNews($ticker));

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ── 私有：Yahoo Finance 報價 + 日K ───────────────────────────

    private function fetchYahoo(string $ticker): array
    {
        try {
            $res = Http::timeout(15)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ])->get("https://query1.finance.yahoo.com/v8/finance/chart/{$ticker}", [
                'interval' => '1d',
                'range'    => '3mo',
            ]);

            if (!$res->ok()) return ['quote' => [], 'history' => []];

            $result     = $res->json('chart.result.0', []);
            $meta       = $result['meta']                   ?? [];
            $timestamps = $result['timestamp']              ?? [];
            $ohlcv      = $result['indicators']['quote'][0] ?? [];

            $price = (float)($meta['regularMarketPrice'] ?? 0);

            $quote = [
                'name'          => $meta['longName'] ?? ($meta['shortName'] ?? $ticker),
                'ticker'        => $ticker,  // 用傳入的 ticker，不信任 meta.symbol
                'price'         => round($price, 2),
                'volume'        => (int)($meta['regularMarketVolume'] ?? 0),
                'previousClose' => 0,
                'change'        => 0,
                'changePct'     => 0,
            ];

            $rows = [];
            foreach ($timestamps as $i => $ts) {
                $close = $ohlcv['close'][$i] ?? null;
                if (!$close) continue;

                $prevC     = $i > 0 ? ($ohlcv['close'][$i - 1] ?? null) : null;
                $change    = $prevC ? round($close - $prevC, 2) : null;
                $changePct = ($prevC && $prevC > 0) ? round(($close - $prevC) / $prevC * 100, 2) : null;

                $rows[] = [
                    'date'      => Carbon::createFromTimestamp($ts, 'Asia/Taipei')->format('Y-m-d'),
                    'open'      => round($ohlcv['open'][$i]   ?? 0, 2),
                    'high'      => round($ohlcv['high'][$i]   ?? 0, 2),
                    'low'       => round($ohlcv['low'][$i]    ?? 0, 2),
                    'close'     => round($close, 2),
                    'volume'    => (int)($ohlcv['volume'][$i] ?? 0),
                    'change'    => $change,
                    'changePct' => $changePct,
                ];
            }

            // 用最後兩筆計算正確漲跌（避免用 chartPreviousClose 這個圖表起點價）
            $n = count($rows);
            if ($n >= 2) {
                $latestPrice    = $price > 0 ? $price : $rows[$n - 1]['close'];
                $yesterdayClose = $rows[$n - 2]['close'];
                $quote['price']         = round($latestPrice, 2);
                $quote['previousClose'] = round($yesterdayClose, 2);
                $quote['change']        = round($latestPrice - $yesterdayClose, 2);
                $quote['changePct']     = $yesterdayClose > 0
                    ? round(($latestPrice - $yesterdayClose) / $yesterdayClose * 100, 2) : 0;
            }

            return ['quote' => $quote, 'history' => array_reverse(array_slice($rows, -22))];
        } catch (\Exception $e) {
            return ['quote' => [], 'history' => []];
        }
    }

    // ── 私有：TWSE T86 並行（Http::pool）────────────────────────

    private function fetchInstitutional(string $code): array
    {
        $businessDays = [];
        $date = now('Asia/Taipei')->subDay();
        while (count($businessDays) < 14) {
            if ($date->isWeekday()) $businessDays[] = $date->format('Ymd');
            $date->subDay();
        }

        // 過濾掉已有快取的日期，只對缺失的發請求
        $missing = [];
        $cached  = [];
        foreach ($businessDays as $day) {
            $key = "twse_t86_{$code}_{$day}";
            $val = Cache::get($key);
            if ($val !== null) {
                if ($val) $cached[$day] = $val;
            } else {
                $missing[] = $day;
            }
        }

        // Http::pool 並行取所有缺失日期
        if (!empty($missing)) {
            $responses = Http::pool(function (Pool $pool) use ($missing, $code) {
                return collect($missing)->map(fn($day) =>
                    $pool->as($day)
                        ->timeout(8)
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0', 'Referer' => 'https://www.twse.com.tw/'])
                        ->get('https://www.twse.com.tw/rwd/zh/fund/T86', [
                            'date'     => $day,
                            'stockNo'  => $code,
                            'response' => 'json',
                        ])
                )->all();
            });

            foreach ($missing as $day) {
                $res = $responses[$day] ?? null;
                $row = null;

                try {
                    if ($res && $res->ok() && $res->json('stat') === 'OK') {
                        $data = $res->json('data', []);
                        if (!empty($data)) {
                            $r     = collect($data)->first(fn($r) => isset($r[0]) && trim($r[0]) === $code) ?? $data[0];
                            $clean = fn($v) => (int)str_replace([',', ' '], '', $v ?? '0');
                            if ($r && count($r) >= 17) {
                                $row = [
                                    'date'    => Carbon::createFromFormat('Ymd', $day)->format('Y-m-d'),
                                    'foreign' => (int)round($clean($r[4])  / 1000),
                                    'trust'   => (int)round($clean($r[10]) / 1000),
                                    'dealer'  => (int)round(($clean($r[13]) + $clean($r[16])) / 1000),
                                ];
                            }
                        }
                    }
                } catch (\Exception $e) {}

                Cache::put("twse_t86_{$code}_{$day}", $row, 86400);
                if ($row) $cached[$day] = $row;
            }
        }

        // 依日期排序（最新在前），最多取10筆
        krsort($cached);
        return array_values(array_slice($cached, 0, 10));
    }

    // ── 私有：TDCC 大戶持股 ────────────────────────────────────

    private function fetchDistribution(string $code): array
    {
        try {
            $res = Http::timeout(15)->withHeaders([
                'User-Agent' => 'Mozilla/5.0',
                'Referer'    => 'https://www.tdcc.com.tw/',
            ])->asForm()->post('https://www.tdcc.com.tw/smjh/getS04.do', [
                'selectedJCEStkNo' => $code,
                'scaDate'          => '',
            ]);

            if (!$res->ok()) return [];

            $json = $res->json();
            if (!is_array($json) || empty($json)) return [];

            return collect($json)->take(10)->map(function ($item) {
                $date = $item['SCADATE'] ?? $item['scaDate'] ?? ($item['DISTDATE'] ?? '');
                if (strlen($date) === 8) {
                    $date = substr($date, 0, 4) . '/' . substr($date, 4, 2) . '/' . substr($date, 6, 2);
                }
                return [
                    'date'          => $date,
                    'totalHolders'  => (int)($item['STKHLDCNT']        ?? 0),
                    'avgShares'     => (float)($item['STKHLDAVGSHRCNT'] ?? 0),
                    'over400count'  => (int)($item['OVR400CNT']         ?? 0),
                    'over400pct'    => (float)($item['OVR400SHRCNT']    ?? 0),
                    'b400to600'     => (int)($item['B400T600']           ?? $item['BAND400TO600'] ?? 0),
                    'b600to800'     => (int)($item['B600T800']           ?? $item['BAND600TO800'] ?? 0),
                    'b800to1000'    => (int)($item['B800T1000']          ?? $item['BAND800TO1000'] ?? 0),
                    'over1000count' => (int)($item['OVR1000CNT']         ?? 0),
                    'over1000pct'   => (float)($item['OVR1000SHRCNT']   ?? 0),
                    'closePrice'    => (float)($item['CLOSEPRICE']       ?? 0),
                ];
            })->values()->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    // ── 私有：MOPS 月營收 ─────────────────────────────────────────

    private function fetchRevenue(string $code): array
    {
        $now  = now('Asia/Taipei');
        $rows = [];

        for ($i = 1; $i <= 6; $i++) {
            $target = $now->copy()->subMonths($i);
            $year   = $target->year - 1911;
            $month  = $target->month;

            try {
                $res = Http::timeout(10)->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                    ->asForm()->post('https://mops.twse.com.tw/mops/web/ajax_t05st10_1', [
                        'encodeURIComponent' => '1',
                        'step'               => '1',
                        'firstin'            => '1',
                        'off'                => '1',
                        'TYPEK'              => 'sii',
                        'co_id'              => $code,
                        'year'               => $year,
                        'month'              => str_pad($month, 2, '0', STR_PAD_LEFT),
                    ]);

                if (!$res->ok()) continue;

                $html = $res->body();
                if (preg_match_all('/<td[^>]*align="right"[^>]*>\s*([-\d,\.]+%?)\s*<\/td>/i', $html, $m)) {
                    $vals = $m[1];
                    if (count($vals) >= 5) {
                        $rows[] = [
                            'month'   => sprintf('%02d/%02d', $target->year % 100, $month),
                            'revenue' => (int)str_replace(',', '', $vals[0] ?? '0'),
                            'momPct'  => $vals[3] ?? null,
                            'yoyPct'  => $vals[4] ?? null,
                        ];
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $rows;
    }

    // ── 私有：Yahoo Finance 新聞 ──────────────────────────────────

    private function fetchNews(string $ticker): array
    {
        try {
            $res = Http::timeout(10)->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get('https://query2.finance.yahoo.com/v1/finance/search', [
                    'q'                => $ticker,
                    'quotesCount'      => 0,
                    'newsCount'        => 10,
                    'enableFuzzyQuery' => 'false',
                    'newsQueryId'      => 'news_cie_vespa',
                ]);

            if (!$res->ok()) return [];

            return collect($res->json('news', []))->take(8)->map(fn($n) => [
                'title'       => $n['title']     ?? '',
                'url'         => $n['link']      ?? '',
                'source'      => $n['publisher'] ?? '',
                'publishedAt' => isset($n['providerPublishTime'])
                    ? Carbon::createFromTimestamp($n['providerPublishTime'], 'Asia/Taipei')->format('m/d H:i')
                    : '',
            ])->values()->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function validateCode(Request $request): ?string
    {
        $code = strtoupper(trim($request->input('code', '')));
        return ($code && preg_match('/^\d{4,6}$/', $code)) ? $code : null;
    }

    private function badCode()
    {
        return response()->json(['success' => false, 'message' => '請輸入正確的股票代號']);
    }
}
