<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;

class TestAvBase extends Command
{
    protected $signature   = 'test:avbase {name=雛形みくる : 女優名稱}';
    protected $description = '測試從 avbase.net 查詢女優個人資料';

    public function handle()
    {
        $name      = $this->argument('name');
        $flareUrl  = rtrim(getConfig('flaresolverr_url'), '/');
        $targetUrl = 'https://www.avbase.net/talents/' . urlencode($name);
        $client    = new Client(['timeout' => 60]);

        $this->info("查詢：{$targetUrl}");

        // ── 抓頁面 ──────────────────────────────────────────────────
        try {
            $res  = $client->post($flareUrl, [
                'json' => [
                    'cmd'        => 'request.get',
                    'url'        => $targetUrl,
                    'maxTimeout' => 30000,
                ],
            ]);
            $body = json_decode((string) $res->getBody(), true);
        } catch (\Exception $e) {
            $this->error('FlareSolverr 請求失敗：' . $e->getMessage());
            return 1;
        }

        $status = $body['status'] ?? 'unknown';
        $this->line("FlareSolverr status：{$status}");

        if ($status !== 'ok') {
            $this->error('FlareSolverr 非 ok：' . ($body['message'] ?? ''));
            return 1;
        }

        $html = $body['solution']['response'] ?? '';
        $this->line('HTML 長度：' . strlen($html));

        if (strlen($html) < 500) {
            $this->error('HTML 太短，可能無內容：');
            $this->line(strip_tags($html));
            return 1;
        }

        // ── 解析 ────────────────────────────────────────────────────
        $result = $this->parse($html, $name);

        if (empty(array_filter($result))) {
            $this->warn("查無 [{$name}] 的資料，印出頁面關鍵文字：");
            $this->line(substr(strip_tags($html), 0, 800));
            return 0;
        }

        $this->info("── 解析結果 ──");
        foreach ($result as $k => $v) {
            $this->line("  [{$k}] => " . (is_array($v) ? implode(', ', $v) : ($v ?? '-')));
        }

        return 0;
    }

    private function parse(string $html, string $name): array
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($doc);

        $result = ['name' => $name];

        // ── 姓名（頁面 h1）────────────────────────────────────────
        $h1 = $xpath->query('//h1')->item(0);
        if ($h1) $result['page_name'] = trim($h1->textContent);

        // ── 大頭照 ────────────────────────────────────────────────
        $img = $xpath->query('//img[contains(@class,"rounded") or contains(@alt,"' . $name . '")]')->item(0);
        if ($img) $result['image'] = $img->getAttribute('src');

        // ── 基本資料區塊：class 含 flex-col gap-2 text-balance ──────
        $infoBlock = $xpath->query(
            '//*[contains(@class,"flex") and contains(@class,"flex-col") and contains(@class,"gap-2") and contains(@class,"text-balance")]'
        )->item(0);

        if ($infoBlock) {
            // 每個 flex justify-between 的 div 是一個資料列
            $rows = $xpath->query(
                './/*[contains(@class,"flex") and contains(@class,"justify-between") and contains(@class,"items-start")]',
                $infoBlock
            );

            foreach ($rows as $row) {
                $cells = $xpath->query('.//*[self::span or self::div or self::p]', $row);
                $texts = [];
                foreach ($cells as $cell) {
                    $t = trim($cell->textContent);
                    if ($t && strlen($t) < 100) {
                        $texts[] = $t;
                    }
                }
                // 去重複後取前兩個（label + value）
                $texts = array_values(array_unique($texts));
                if (count($texts) >= 2) {
                    $label = $texts[0];
                    $value = $texts[1];
                    $result[$label] = $value;
                }
            }
        } else {
            $result['_block_found'] = false;
        }

        return $result;
    }
}
