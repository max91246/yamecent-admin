<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;

class TestDisposalApis extends Command
{
    protected $signature   = 'test:disposal-apis {stock=2330}';
    protected $description = '測試大戶持股人數相關 API 端點';

    public function handle()
    {
        $stock  = $this->argument('stock');
        $client = new Client(['timeout' => 15]);
        $today  = now()->subDay()->format('Ymd'); // 抓昨日（今日可能未更新）

        // ── 1. TWSE 集保戶股權分散表（TWTB4U）────────────────────
        $this->info("=== [1] TWSE 集保戶股權分散表 TWTB4U (股票:{$stock}, 日期:{$today}) ===");
        try {
            $res  = $client->get('https://www.twse.com.tw/rwd/zh/fund/TWTB4U', [
                'query'   => ['response' => 'json', 'strDate' => $today, 'stockNo' => $stock],
                'headers' => ['User-Agent' => 'Mozilla/5.0', 'Referer' => 'https://www.twse.com.tw/'],
            ]);
            $data = json_decode((string) $res->getBody(), true);
            $this->line('stat: ' . ($data['stat'] ?? 'N/A'));
            $this->line('title: ' . ($data['title'] ?? 'N/A'));
            if (!empty($data['fields'])) {
                $this->line('欄位：' . implode(', ', $data['fields']));
            }
            if (!empty($data['data'][0])) {
                $this->line('第一筆：' . implode(' | ', $data['data'][0]));
            }
        } catch (\Exception $e) {
            $this->error('失敗：' . $e->getMessage());
        }

        $this->newLine();

        // ── 2. TWSE OpenAPI 集保戶股權分散表 ─────────────────────
        $this->info("=== [2] TWSE OpenAPI 集保戶股權分散表 ===");
        try {
            $res  = $client->get('https://openapi.twse.com.tw/v1/fund/TWTB4U', [
                'headers' => ['Accept' => 'application/json', 'User-Agent' => 'Mozilla/5.0'],
            ]);
            $data = json_decode((string) $res->getBody(), true);
            $this->line('筆數：' . count($data));
            if (!empty($data[0])) {
                $this->line('欄位：' . implode(', ', array_keys($data[0])));
                $this->line('第一筆：');
                foreach ($data[0] as $k => $v) {
                    $this->line("  [{$k}] => {$v}");
                }
            }
        } catch (\Exception $e) {
            $this->error('失敗：' . $e->getMessage());
        }

        $this->newLine();

        // ── 3. TDCC 集保結算所 API ────────────────────────────────
        $this->info("=== [3] TDCC 集保結算所 smApi (股票:{$stock}) ===");
        try {
            $res  = $client->get('https://www.tdcc.com.tw/smApi/smViewer', [
                'query'   => ['searchDate' => $today, 'searchType' => '02', 'id' => $stock],
                'headers' => ['User-Agent' => 'Mozilla/5.0', 'Referer' => 'https://www.tdcc.com.tw/'],
            ]);
            $body = (string) $res->getBody();
            $data = json_decode($body, true);
            if ($data) {
                $this->line('筆數：' . count($data));
                if (!empty($data[0])) {
                    $this->line('欄位：' . implode(', ', array_keys($data[0])));
                    $this->line('第一筆：');
                    foreach ($data[0] as $k => $v) {
                        $this->line("  [{$k}] => {$v}");
                    }
                }
            } else {
                $this->line('回傳非 JSON，前 300 字：' . substr($body, 0, 300));
            }
        } catch (\Exception $e) {
            $this->error('失敗：' . $e->getMessage());
        }

        return 0;
    }
}
