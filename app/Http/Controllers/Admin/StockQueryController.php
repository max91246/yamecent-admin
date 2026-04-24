<?php

namespace App\Http\Controllers\Admin;

use App\DisposalStock;
use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class StockQueryController extends Controller
{
    public function index()
    {
        return view('admin.stock_query');
    }

    public function query(Request $request)
    {
        $code = strtoupper(trim($request->input('code', '')));

        if (!$code || !preg_match('/^\d{4,6}$/', $code)) {
            return response()->json(['error' => '請輸入有效的股票代號（4-6位數字）'], 422);
        }

        $quote = $this->fetchQuote($code);
        if (!$quote) {
            return response()->json(['error' => "查無股票代號 {$code}"], 404);
        }

        $institutional = $this->fetchInstitutional($code);
        $revenues      = $this->fetchRevenue($code);
        $news          = $this->fetchNews($code);
        $disposal      = $this->getDisposal($code);
        $history       = $this->fetchPriceHistory($code);

        return response()->json([
            'code'          => $code,
            'quote'         => $quote,
            'institutional' => $institutional,
            'revenues'      => $revenues,
            'news'          => $news,
            'disposal'      => $disposal,
            'history'       => $history,
        ]);
    }

    private function fetchQuote(string $code): ?array
    {
        $cacheKey = 'tw-' . $code;
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $symbolsEnc = rawurlencode(json_encode([$code . '.TW']));
        $base       = rtrim(getConfig('yahoo_stock_chart_base'), '/');
        $url        = "{$base};period=d;symbols={$symbolsEnc}";

        try {
            $client = new Client(['timeout' => 10]);
            $res    = $client->get($url, [
                'query' => ['intl' => 'tw', 'lang' => 'zh-Hant-TW', 'region' => 'TW', 'site' => 'finance', 'returnMeta' => 'true'],
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Referer'    => 'https://tw.stock.yahoo.com/',
                    'Accept'     => 'application/json, text/plain, */*',
                ],
            ]);

            $data  = json_decode((string) $res->getBody(), true);
            $chart = $data['data'][0]['chart'] ?? null;
            if (!$chart) return null;

            $quote = $chart['quote'] ?? [];
            $meta  = $chart['meta']  ?? [];
            $price = $quote['price'] ?? null;
            if ($price === null) return null;

            $name   = $meta['name'] ?? $meta['longName'] ?? $meta['shortName'] ?? $quote['name'] ?? $code;
            $result = [
                'name'           => $name,
                'price'          => $price,
                'priceChange'    => $quote['priceChange']        ?? $quote['change']         ?? null,
                'priceChangePct' => $quote['priceChangePercent'] ?? $quote['changePercent']  ?? null,
                'volume'         => $quote['volume'] ?? null,
            ];

            Cache::put($cacheKey, $result, now()->addMinutes(5));
            return $result;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function fetchInstitutional(string $code): ?array
    {
        $symbol = $code . '.TW';
        $base   = rtrim(getConfig('yahoo_institutional_base'), '/');
        $url    = "{$base};limit=10;period=day;symbol={$symbol}";

        try {
            $client = new Client(['timeout' => 10]);
            $res    = $client->get($url, [
                'query' => ['intl' => 'tw', 'lang' => 'zh-Hant-TW', 'region' => 'TW', 'site' => 'finance'],
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Referer'    => "https://tw.stock.yahoo.com/quote/{$symbol}",
                    'Accept'     => 'application/json, text/plain, */*',
                ],
            ]);

            $data = json_decode((string) $res->getBody(), true);
            $list = $data['list'] ?? [];
            if (empty($list)) return null;

            return array_map(fn($row) => [
                'date'    => $row['formattedDate'] ?? substr($row['date'] ?? '', 0, 10),
                'foreign' => isset($row['foreignDiffVolK'])         ? (int) $row['foreignDiffVolK']         : null,
                'trust'   => isset($row['investmentTrustDiffVolK']) ? (int) $row['investmentTrustDiffVolK'] : null,
                'dealer'  => isset($row['dealerDiffVolK'])          ? (int) $row['dealerDiffVolK']          : null,
            ], $list);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function fetchRevenue(string $code): ?array
    {
        $symbol = $code . '.TW';
        $base   = rtrim(getConfig('yahoo_revenue_base'), '/');
        $url    = "{$base};period=month;symbol={$symbol}";

        try {
            $client = new Client(['timeout' => 10]);
            $res    = $client->get($url, [
                'query' => ['intl' => 'tw', 'lang' => 'zh-Hant-TW', 'region' => 'TW', 'site' => 'finance'],
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Referer'    => "https://tw.stock.yahoo.com/quote/{$symbol}",
                    'Accept'     => 'application/json, text/plain, */*',
                ],
            ]);

            $data     = json_decode((string) $res->getBody(), true);
            $revenues = $data['data']['result']['revenues'] ?? [];
            return !empty($revenues) ? array_slice($revenues, 0, 6) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function fetchPriceHistory(string $code): array
    {
        $symbol = $code . '.TW';
        $url    = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}";

        try {
            $client = new Client(['timeout' => 10]);
            $res    = $client->get($url, [
                'query' => [
                    'interval'       => '1d',
                    'range'          => '1mo',
                    'region'         => 'TW',
                    'lang'           => 'zh-Hant-TW',
                    'includePrePost' => 'false',
                ],
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Referer'    => "https://tw.stock.yahoo.com/quote/{$symbol}",
                    'Accept'     => 'application/json, text/plain, */*',
                ],
            ]);

            $data   = json_decode((string) $res->getBody(), true);
            $result = $data['chart']['result'][0] ?? null;
            if (!$result) return [];

            $timestamps = $result['timestamp'] ?? [];
            $ohlcv      = $result['indicators']['quote'][0] ?? [];
            $opens      = $ohlcv['open']   ?? [];
            $highs      = $ohlcv['high']   ?? [];
            $lows       = $ohlcv['low']    ?? [];
            $closes     = $ohlcv['close']  ?? [];
            $volumes    = $ohlcv['volume'] ?? [];

            $candles = [];
            foreach ($timestamps as $i => $ts) {
                $close = $closes[$i] ?? null;
                if ($close === null) continue;
                $candles[] = [
                    'date'   => date('Y-m-d', $ts),
                    'open'   => round($opens[$i]  ?? 0, 2),
                    'high'   => round($highs[$i]  ?? 0, 2),
                    'low'    => round($lows[$i]   ?? 0, 2),
                    'close'  => round($close,          2),
                    'volume' => (int) round(($volumes[$i] ?? 0) / 1000),
                ];
            }

            return array_slice(array_reverse($candles), 0, 10);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getDisposal(string $code): ?array
    {
        $key = 'disposal:' . $code;
        if (Cache::has('disposal:cache_ready')) {
            $raw = Cache::get($key);
            return $raw ? json_decode($raw, true) : null;
        }

        $record = DisposalStock::where('stock_code', $code)
            ->where('end_date', '>=', now()->toDateString())
            ->first();

        if (!$record) return null;

        return [
            'start_date' => $record->start_date->toDateString(),
            'end_date'   => $record->end_date->toDateString(),
            'reason'     => $record->reason,
            'market'     => $record->market,
        ];
    }

    private function fetchNews(string $code): array
    {
        return Cache::remember("tw-news-{$code}", 600, function () use ($code) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                    ->get("https://tw.stock.yahoo.com/rss?s={$code}");

                if (!$response->ok()) return [];

                $xml = simplexml_load_string($response->body());
                if (!$xml) return [];

                $items = [];
                $count = 0;
                foreach ($xml->channel->item as $item) {
                    if ($count >= 8) break;
                    $link = !empty((string) $item->link) ? trim((string) $item->link) : trim((string) $item->guid);
                    $items[] = [
                        'title' => html_entity_decode(strip_tags((string) $item->title), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                        'link'  => $link,
                        'date'  => isset($item->pubDate) ? date('m/d H:i', strtotime((string) $item->pubDate)) : '',
                    ];
                    $count++;
                }
                return $items;
            } catch (\Exception $e) {
                return [];
            }
        });
    }
}
