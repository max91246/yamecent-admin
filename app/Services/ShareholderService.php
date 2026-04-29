<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ShareholderService
{
    /**
     * 取得股票近10週大戶持股分散表。
     * 先查 Redis（TTL 1天），無則透過 FlareSolverr 抓 norway.twsthr.info。
     *
     * @return array|null  10筆資料陣列，或 null（抓取失敗）
     */
    public static function fetch(string $code): ?array
    {
        $cacheKey = 'shareholder_dist:' . $code;

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return json_decode($cached, true);
        }

        $rows = self::scrape($code);
        if ($rows === null) {
            return null;
        }

        Cache::put($cacheKey, json_encode($rows, JSON_UNESCAPED_UNICODE), 86400);
        return $rows;
    }

    private static function scrape(string $code): ?array
    {
        $flareUrl  = rtrim(getConfig('flaresolverr_url'), '/');
        $targetUrl = "https://norway.twsthr.info/StockHolders.aspx?stock={$code}";

        try {
            $client = new Client(['timeout' => 60]);
            $res    = $client->post($flareUrl, [
                'json' => [
                    'cmd'        => 'request.get',
                    'url'        => $targetUrl,
                    'maxTimeout' => 30000,
                ],
            ]);
            $body = json_decode((string) $res->getBody(), true);
        } catch (\Exception $e) {
            Log::warning('[ShareholderService] FlareSolverr 失敗', ['code' => $code, 'error' => $e->getMessage()]);
            return null;
        }

        if (($body['status'] ?? '') !== 'ok') {
            Log::warning('[ShareholderService] FlareSolverr 非 ok', ['code' => $code, 'status' => $body['status'] ?? '']);
            return null;
        }

        $html = $body['solution']['response'] ?? '';
        if (!$html) {
            return null;
        }

        return self::parseTable($html);
    }

    private static function parseTable(string $html): ?array
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($doc);

        $tables = $xpath->query('//div[@id="D1"]//table[@id="Details"]');
        if (!$tables || $tables->length === 0) {
            $tables = $xpath->query('//table[@id="Details"]');
        }
        if (!$tables || $tables->length === 0) {
            return null;
        }

        $table   = $tables->item(0);
        $headers = [];
        $rows    = [];

        foreach ($xpath->query('.//tr', $table) as $tr) {
            $cells = $xpath->query('.//th|.//td', $tr);
            $vals  = [];
            foreach ($cells as $cell) {
                $vals[] = trim($cell->textContent);
            }
            if (empty(array_filter($vals))) continue;

            if (empty($headers)) {
                $headers = $vals;
                continue;
            }

            if (count($vals) !== count($headers)) continue;

            $raw = array_combine($headers, $vals);

            // 標準化為英文 key，方便前後端使用
            $rows[] = [
                'date'             => $raw['資料日期']                  ?? '',
                'total_lots'       => $raw['集保總張數']                 ?? '',
                'total_holders'    => $raw['總股東人數']                 ?? '',
                'avg_lots'         => $raw['平均張數/人']                ?? '',
                'over400_lots'     => $raw['>400張大股東持有張數']        ?? '',
                'over400_pct'      => $raw['>400張大股東持有百分比']      ?? '',
                'over400_count'    => $raw['>400張大股東人數']            ?? '',
                'range_400_600'    => $raw['400~600張人數']              ?? '',
                'range_600_800'    => $raw['600~800張人數']              ?? '',
                'range_800_1000'   => $raw['800~1000張人數']             ?? '',
                'over1000_count'   => $raw['>1000張人數']                ?? '',
                'over1000_pct'     => $raw['>1000張大股東持有百分比']     ?? '',
                'close_price'      => $raw['收盤價']                     ?? '',
            ];

            if (count($rows) >= 10) break;
        }

        return !empty($rows) ? $rows : null;
    }
}
