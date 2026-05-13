<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\ShareholderService;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class StockQueryController extends Controller
{
    // Yahoo Finance TW 版 headers（與舊 admin 相同）
    private function twHeaders(string $symbol = ''): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Referer'    => $symbol ? "https://tw.stock.yahoo.com/quote/{$symbol}" : 'https://tw.stock.yahoo.com/',
            'Accept'     => 'application/json, text/plain, */*',
        ];
    }

    private function twQuery(): array
    {
        return ['intl' => 'tw', 'lang' => 'zh-Hant-TW', 'region' => 'TW', 'site' => 'finance'];
    }

    // ── 報價 + 日K ────────────────────────────────────────────────

    public function quote(Request $request)
    {
        $code = $this->validateCode($request);
        if (!$code) return $this->badCode();

        $cacheKey = "sq2_quote_{$code}_" . now()->format('Ymd_H');
        $data = Cache::remember($cacheKey, 3600, fn() => $this->fetchQuoteAndHistory($code));

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ── 三大法人 ──────────────────────────────────────────────────

    public function institutional(Request $request)
    {
        $code = $this->validateCode($request);
        if (!$code) return $this->badCode();

        $cacheKey = "sq2_inst_{$code}_" . now()->format('Ymd');
        $data = Cache::remember($cacheKey, 86400, fn() => $this->fetchInstitutional($code));

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ── 大戶持股分散表（FlareSolverr → norway.twsthr.info）────────

    public function distribution(Request $request)
    {
        $code = $this->validateCode($request);
        if (!$code) return $this->badCode();

        // ShareholderService 自帶 Redis cache（TTL 1天）
        $data = ShareholderService::fetch($code) ?? [];

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ── 月營收 ────────────────────────────────────────────────────

    public function revenue(Request $request)
    {
        $code = $this->validateCode($request);
        if (!$code) return $this->badCode();

        $cacheKey = "sq2_rev_{$code}_" . now()->format('Ym');
        $data = Cache::remember($cacheKey, 86400, fn() => $this->fetchRevenue($code));

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ── 相關新聞（Yahoo TW RSS）──────────────────────────────────

    public function news(Request $request)
    {
        $code = $this->validateCode($request);
        if (!$code) return $this->badCode();

        $cacheKey = "sq2_news_{$code}_" . now()->format('YmdH');
        $data = Cache::remember($cacheKey, 3600, fn() => $this->fetchNews($code));

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ── 私有：報價（Yahoo TW版）+ 日K（query2，同舊admin）──────────

    private function fetchQuoteAndHistory(string $code): array
    {
        $symbol    = $code . '.TW';
        $quote     = $this->fetchQuoteTw($code, $symbol);
        $history   = $this->fetchHistory($code);

        return [
            'ticker'  => $symbol,
            'quote'   => $quote,
            'history' => $history,
        ];
    }

    private function fetchQuoteTw(string $code, string $symbol): array
    {
        try {
            $symbolsEnc = rawurlencode(json_encode([$symbol]));
            $base       = 'https://tw.stock.yahoo.com/_td-stock/api/resource/FinanceChartService.ApacLibraCharts';
            $url        = "{$base};period=d;symbols={$symbolsEnc}";

            $res = Http::timeout(10)
                ->withHeaders($this->twHeaders($symbol))
                ->get($url, array_merge($this->twQuery(), ['returnMeta' => 'true']));

            if (!$res->ok()) return [];

            $chart = $res->json('data.0.chart', []);
            $q     = $chart['quote'] ?? [];
            $meta  = $chart['meta']  ?? [];

            $price     = (float)($q['price']                ?? 0);
            $prevClose = (float)($q['previousClose']        ?? 0);
            $change    = (float)($q['priceChange']          ?? $q['change'] ?? 0);
            $changePct = (float)($q['priceChangePercent']   ?? $q['changePercent'] ?? 0);

            return [
                'name'          => $meta['name'] ?? $meta['longName'] ?? $meta['shortName'] ?? $q['name'] ?? $code,
                'ticker'        => $symbol,
                'price'         => round($price, 2),
                'previousClose' => round($prevClose ?: ($price - $change), 2),
                'change'        => round($change, 2),
                'changePct'     => round($changePct, 2),
                'volume'        => (int)($q['volume'] ?? 0),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function fetchHistory(string $code): array
    {
        // 同舊admin：query2，先試 .TW，失敗才試 .TWO
        $headers = array_merge($this->twHeaders(), [
            'Accept' => 'application/json, text/plain, */*',
        ]);
        $query = ['interval' => '1d', 'range' => '3mo'];

        $symbol = null;
        foreach ([$code . '.TW', $code . '.TWO'] as $try) {
            try {
                $res  = Http::timeout(10)->withHeaders($headers)
                    ->get("https://query2.finance.yahoo.com/v8/finance/chart/{$try}", $query);
                $data = $res->json('chart.result.0', []);
                if (!empty($data['timestamp'])) {
                    $symbol = $try;
                    break;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        if (!$symbol) return [];

        try {
            $res        = Http::timeout(10)->withHeaders($headers)
                ->get("https://query2.finance.yahoo.com/v8/finance/chart/{$symbol}", $query);
            $result     = $res->json('chart.result.0', []);
            $timestamps = $result['timestamp']              ?? [];
            $ohlcv      = $result['indicators']['quote'][0] ?? [];
            $closes     = $ohlcv['close']  ?? [];

            $rows = [];
            foreach ($timestamps as $i => $ts) {
                $close = $closes[$i] ?? null;
                if (!$close) continue;

                $prevC     = $i > 0 ? ($closes[$i - 1] ?? null) : null;
                $change    = $prevC ? round($close - $prevC, 2) : null;
                $changePct = ($prevC && $prevC > 0) ? round(($close - $prevC) / $prevC * 100, 2) : null;

                $rows[] = [
                    'date'      => Carbon::createFromTimestamp($ts, 'Asia/Taipei')->format('Y-m-d'),
                    'open'      => round($ohlcv['open'][$i]   ?? 0, 2),
                    'high'      => round($ohlcv['high'][$i]   ?? 0, 2),
                    'low'       => round($ohlcv['low'][$i]    ?? 0, 2),
                    'close'     => round($close, 2),
                    'volume'    => (int)round(($ohlcv['volume'][$i] ?? 0) / 1000), // 張
                    'change'    => $change,
                    'changePct' => $changePct,
                ];
            }

            return array_reverse(array_slice($rows, -22));
        } catch (\Exception $e) {
            return [];
        }
    }

    // ── 私有：三大法人（Yahoo TW版）──────────────────────────────

    private function fetchInstitutional(string $code): array
    {
        try {
            $symbol = $code . '.TW';
            $base   = 'https://tw.stock.yahoo.com/_td-stock/api/resource/StockServices.tradesWithQuoteStats';
            $url    = "{$base};limit=10;period=day;symbol={$symbol}";

            $res = Http::timeout(10)
                ->withHeaders($this->twHeaders($symbol))
                ->get($url, $this->twQuery());

            if (!$res->ok()) return [];

            $list = $res->json('list', []);
            if (empty($list)) return [];

            return array_map(fn($row) => [
                'date'    => $row['formattedDate'] ?? substr($row['date'] ?? '', 0, 10),
                'foreign' => isset($row['foreignDiffVolK'])         ? (int)$row['foreignDiffVolK']         : 0,
                'trust'   => isset($row['investmentTrustDiffVolK']) ? (int)$row['investmentTrustDiffVolK'] : 0,
                'dealer'  => isset($row['dealerDiffVolK'])          ? (int)$row['dealerDiffVolK']          : 0,
            ], $list);
        } catch (\Exception $e) {
            return [];
        }
    }

    // ── 私有：月營收（Yahoo TW版）────────────────────────────────

    private function fetchRevenue(string $code): array
    {
        try {
            $symbol = $code . '.TW';
            $base   = 'https://tw.stock.yahoo.com/_td-stock/api/resource/StockServices.revenues';
            $url    = "{$base};period=month;symbol={$symbol}";

            $res = Http::timeout(10)
                ->withHeaders($this->twHeaders($symbol))
                ->get($url, $this->twQuery());

            if (!$res->ok()) return [];

            $revenues = $res->json('data.result.revenues', []);
            if (empty($revenues)) return [];

            return array_map(function ($r) {
                // date 是 ISO format: 2026-04-01T00:00:00+08:00
                $dateStr = $r['date'] ?? '';
                $month   = $dateStr ? date('y/m', strtotime($dateStr)) : '';
                // revenue 單位是元，÷1000 = 千元
                $revenue = (int)round((float)($r['revenue'] ?? 0) / 1000);
                return [
                    'month'   => $month,
                    'revenue' => $revenue,
                    'momPct'  => isset($r['revenueMoM']) ? round((float)$r['revenueMoM'], 1) . '%' : null,
                    'yoyPct'  => isset($r['revenueYoY']) ? round((float)$r['revenueYoY'], 1) . '%' : null,
                ];
            }, array_slice($revenues, 0, 6));
        } catch (\Exception $e) {
            return [];
        }
    }

    // ── 私有：新聞（Yahoo TW RSS）────────────────────────────────

    private function fetchNews(string $code): array
    {
        try {
            $res = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get("https://tw.stock.yahoo.com/rss", ['s' => $code]);

            if (!$res->ok()) return [];

            $xml = simplexml_load_string($res->body());
            if (!$xml || !isset($xml->channel->item)) return [];

            $items = [];
            foreach ($xml->channel->item as $item) {
                if (count($items) >= 8) break;
                $link     = !empty((string)$item->link) ? trim((string)$item->link) : trim((string)$item->guid);
                $items[] = [
                    'title'       => html_entity_decode(strip_tags((string)$item->title), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'url'         => $link,
                    'source'      => 'Yahoo 財經',
                    'publishedAt' => isset($item->pubDate) ? date('m/d H:i', strtotime((string)$item->pubDate)) : '',
                ];
            }
            return $items;
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
