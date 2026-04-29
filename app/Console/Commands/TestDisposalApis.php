<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;

class TestDisposalApis extends Command
{
    protected $signature   = 'test:disposal-apis';
    protected $description = '測試 TPEX / TWSE OpenAPI 處置股端點，印出欄位與第一筆資料';

    public function handle()
    {
        $client = new Client(['timeout' => 15]);

        // ── TPEX ─────────────────────────────────────────────────
        $this->info('=== TPEX 上櫃 ===');
        try {
            $res  = $client->get('https://www.tpex.org.tw/openapi/v1/tpex_disposal_information', [
                'headers' => ['User-Agent' => 'Mozilla/5.0'],
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
            $this->error('TPEX 失敗：' . $e->getMessage());
        }

        $this->newLine();

        // ── TWSE OpenAPI ──────────────────────────────────────────
        $this->info('=== TWSE 上市 (openapi.twse.com.tw) ===');
        try {
            $res  = $client->get('https://openapi.twse.com.tw/v1/announcement/punish', [
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
            $this->error('TWSE OpenAPI 失敗：' . $e->getMessage());
        }

        return 0;
    }
}
