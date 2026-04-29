<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;

class TestDisposalApis extends Command
{
    protected $signature   = 'test:disposal-apis {stock=2376}';
    protected $description = '透過 FlareSolverr 抓取大戶持股分散表（norway.twsthr.info）';

    public function handle()
    {
        $stock          = $this->argument('stock');
        $targetUrl      = "https://norway.twsthr.info/StockHolders.aspx?stock={$stock}";
        $flaresolverrUrl = rtrim(getConfig('flaresolverr_url'), '/');

        $this->info("FlareSolverr: {$flaresolverrUrl}");
        $this->info("目標: {$targetUrl}");

        $client = new Client(['timeout' => 60]);

        try {
            $res  = $client->post($flaresolverrUrl, [
                'json' => [
                    'cmd'     => 'request.get',
                    'url'     => $targetUrl,
                    'maxTimeout' => 30000,
                ],
            ]);
            $body = json_decode((string) $res->getBody(), true);
        } catch (\Exception $e) {
            $this->error('FlareSolverr 請求失敗：' . $e->getMessage());
            return 1;
        }

        $status = $body['status'] ?? 'unknown';
        $this->info("FlareSolverr status: {$status}");

        if ($status !== 'ok') {
            $this->error('FlareSolverr 回傳非 ok：' . json_encode($body));
            return 1;
        }

        $html = $body['solution']['response'] ?? '';
        $this->info('取得 HTML 長度：' . strlen($html));

        // 解析 table#myTable1（明細頁籤的主表格）
        $rows = $this->parseTable($html);

        if (empty($rows)) {
            $this->error('找不到資料表格，印出 HTML 前 1000 字：');
            $this->line(substr(strip_tags($html), 0, 1000));
            return 1;
        }

        $this->info('解析到 ' . count($rows) . ' 筆資料，顯示前 3 筆：');
        foreach (array_slice($rows, 0, 3) as $i => $row) {
            $this->line("── 第 " . ($i + 1) . " 筆 ──");
            foreach ($row as $k => $v) {
                $this->line("  [{$k}] => {$v}");
            }
        }

        return 0;
    }

    private function parseTable(string $html): array
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($doc);

        // 找 id="myTable1" 的 table（明細頁籤）
        $tables = $xpath->query('//table[@id="myTable1"]');
        if (!$tables || $tables->length === 0) {
            // fallback：找第一個有 20260 開頭日期的 table
            $tables = $xpath->query('//table[contains(@class,"table")]');
        }
        if (!$tables || $tables->length === 0) {
            return [];
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
            } else {
                $rows[] = array_combine(
                    array_slice($headers, 0, count($vals)),
                    $vals
                );
            }
        }

        return $rows;
    }
}
