<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class StockQueryController extends Controller
{
    public function query(Request $request)
    {
        $code = strtoupper(trim($request->input('code', '')));
        if (!$code || !preg_match('/^\d{4,6}$/', $code)) {
            return response()->json(['success' => false, 'message' => '請輸入正確的股票代號（4~6位數字）']);
        }

        $cacheKey = "stock_query_{$code}_" . now()->format('Ymd_H');
        $data = Cache::remember($cacheKey, 3600, function () use ($code) {
            return $this->fetchAll($code);
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    private function fetchAll(string $code): array
    {
        // 先試上市(.TW)，若無資料再試上櫃(.TWO)
        $ticker  = $code . '.TW';
        $yData   = $this->fetchYahoo($ticker);
        if (empty($yData['quote']['price'])) {
            $ticker = $code . '.TWO';
            $yData  = $this->fetchYahoo($ticker);
        }

        return [
            'quote'         => $yData['quote'],
            'history'       => $yData['history'],
            'institutional' => $this->fetchInstitutional($code),
            'distribution'  => $this->fetchDistribution($code),
            'revenue'       => $this->fetchRevenue($code),
            'news'          => $this->fetchNews($ticker),
        ];
    }

    // ── Yahoo Finance：報價 + 近3個月日K ──────────────────────────

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
            $meta       = $result['meta']                     ?? [];
            $timestamps = $result['timestamp']                ?? [];
            $ohlcv      = $result['indicators']['quote'][0]   ?? [];

            $price     = (float)($meta['regularMarketPrice']  ?? 0);
            $prevClose = (float)($meta['chartPreviousClose']   ?? 0);

            $quote = [
                'name'          => $meta['longName'] ?? ($meta['shortName'] ?? $ticker),
                'ticker'        => $ticker,
                'price'         => round($price, 2),
                'previousClose' => round($prevClose, 2),
                'change'        => round($price - $prevClose, 2),
                'changePct'     => $prevClose > 0 ? round(($price - $prevClose) / $prevClose * 100, 2) : 0,
                'volume'        => (int)($meta['regularMarketVolume'] ?? 0),
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

            // 最近22個交易日，最新在前
            return ['quote' => $quote, 'history' => array_reverse(array_slice($rows, -22))];
        } catch (\Exception $e) {
            return ['quote' => [], 'history' => []];
        }
    }

    // ── TWSE T86：三大法人（近10個交易日）────────────────────────

    private function fetchInstitutional(string $code): array
    {
        $businessDays = [];
        $date = now('Asia/Taipei')->subDay();
        while (count($businessDays) < 14) {
            if ($date->isWeekday()) $businessDays[] = $date->format('Ymd');
            $date->subDay();
        }

        $rows = [];
        foreach ($businessDays as $day) {
            $row = Cache::remember("twse_t86_{$code}_{$day}", 86400, function () use ($code, $day) {
                try {
                    $res = Http::timeout(10)->withHeaders([
                        'User-Agent' => 'Mozilla/5.0',
                        'Referer'    => 'https://www.twse.com.tw/',
                    ])->get('https://www.twse.com.tw/rwd/zh/fund/T86', [
                        'date'     => $day,
                        'stockNo'  => $code,
                        'response' => 'json',
                    ]);

                    if (!$res->ok() || $res->json('stat') !== 'OK') return null;

                    $data = $res->json('data', []);
                    if (empty($data)) return null;

                    $row = collect($data)->first(fn($r) => isset($r[0]) && trim($r[0]) === $code);
                    if (!$row) $row = $data[0];
                    if (!$row || count($row) < 17) return null;

                    $clean = fn($v) => (int)str_replace([',', ' '], '', $v ?? '0');

                    return [
                        'date'    => Carbon::createFromFormat('Ymd', $day)->format('Y-m-d'),
                        // T86欄位：[2][3][4]=外資買賣超股數, [8][9][10]=投信, [11][12][13]=自營自行, [14][15][16]=自營避險
                        'foreign' => (int)round($clean($row[4])  / 1000),
                        'trust'   => (int)round($clean($row[10]) / 1000),
                        'dealer'  => (int)round(($clean($row[13]) + $clean($row[16])) / 1000),
                    ];
                } catch (\Exception $e) {
                    return null;
                }
            });

            if ($row) $rows[] = $row;
            if (count($rows) >= 10) break;
        }

        return $rows;
    }

    // ── TDCC：近10週大戶持股分散表 ───────────────────────────────

    private function fetchDistribution(string $code): array
    {
        try {
            return Cache::remember("tdcc_dist_{$code}_" . now()->format('YW'), 7200, function () use ($code) {
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
                        'totalHolders'  => (int)($item['STKHLDCNT']        ?? $item['totalHoldersCnt'] ?? 0),
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
            });
        } catch (\Exception $e) {
            return [];
        }
    }

    // ── MOPS：月營收（近6個月）────────────────────────────────────

    private function fetchRevenue(string $code): array
    {
        try {
            return Cache::remember("mops_rev_{$code}_" . now()->format('YmdH'), 3600, function () use ($code) {
                $now  = now('Asia/Taipei');
                $rows = [];

                for ($i = 1; $i <= 6; $i++) {
                    $target = $now->copy()->subMonths($i);
                    $year   = $target->year - 1911;
                    $month  = $target->month;

                    try {
                        $res = Http::timeout(10)->withHeaders([
                            'User-Agent' => 'Mozilla/5.0',
                        ])->asForm()->post('https://mops.twse.com.tw/mops/web/ajax_t05st10_1', [
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
                        // 抓取表格中所有數字格
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
            });
        } catch (\Exception $e) {
            return [];
        }
    }

    // ── Yahoo Finance：相關新聞 ───────────────────────────────────

    private function fetchNews(string $ticker): array
    {
        try {
            $res = Http::timeout(10)->withHeaders([
                'User-Agent' => 'Mozilla/5.0',
            ])->get('https://query2.finance.yahoo.com/v1/finance/search', [
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
}
