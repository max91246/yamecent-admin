<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;

class Test18Porn extends Command
{
    protected $signature   = 'test:18porn';
    protected $description = '測試抓取 18porn.cc 影片列表與詳細頁';

    public function handle()
    {
        $flareUrl  = rtrim(getConfig('flaresolverr_url'), '/');
        $listUrl   = 'https://18porn.cc/videos/?sortby=newest';
        $client    = new Client(['timeout' => 60]);

        // ── 1. 抓列表頁 ────────────────────────────────────────────
        $this->info("=== 列表頁 ===");
        $html = $this->fetch($client, $flareUrl, $listUrl);
        if (!$html) {
            $this->error('列表頁抓取失敗');
            return 1;
        }
        $this->line('HTML 長度：' . strlen($html));

        $items = $this->parseList($html);
        $this->line('找到影片數：' . count($items));

        if (empty($items)) {
            $this->error('找不到任何影片卡片，印出 HTML 前 500 字：');
            $this->line(substr(strip_tags($html), 0, 500));
            return 1;
        }

        foreach (array_slice($items, 0, 3) as $i => $item) {
            $this->line("── 第 " . ($i + 1) . " 筆 ──");
            foreach ($item as $k => $v) {
                $this->line("  [{$k}] => {$v}");
            }
        }

        // ── 2. 抓第一筆詳細頁 ──────────────────────────────────────
        $firstUrl = $items[0]['url'] ?? null;
        if (!$firstUrl) {
            $this->error('無法取得第一筆詳細頁 URL');
            return 1;
        }

        $this->newLine();
        $this->info("=== 詳細頁（{$firstUrl}）===");
        $detailHtml = $this->fetch($client, $flareUrl, $firstUrl);
        if (!$detailHtml) {
            $this->error('詳細頁抓取失敗');
            return 1;
        }
        $this->line('HTML 長度：' . strlen($detailHtml));

        $detail = $this->parseDetail($detailHtml);
        $this->line('解析結果：');
        foreach ($detail as $k => $v) {
            $this->line("  [{$k}] => " . (is_array($v) ? implode(', ', $v) : $v));
        }

        return 0;
    }

    private function fetch(Client $client, string $flareUrl, string $targetUrl): ?string
    {
        try {
            $res  = $client->post($flareUrl, [
                'json' => [
                    'cmd'        => 'request.get',
                    'url'        => $targetUrl,
                    'maxTimeout' => 30000,
                ],
            ]);
            $body = json_decode((string) $res->getBody(), true);
            if (($body['status'] ?? '') !== 'ok') {
                $this->warn('FlareSolverr 非 ok：' . ($body['message'] ?? ''));
                return null;
            }
            return $body['solution']['response'] ?? null;
        } catch (\Exception $e) {
            $this->error('請求失敗：' . $e->getMessage());
            return null;
        }
    }

    private function parseList(string $html): array
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($doc);

        // class="col-sm-6 col-md-4 col-lg-4"
        $cards = $xpath->query('//div[contains(@class,"col-sm-6") and contains(@class,"col-md-4") and contains(@class,"col-lg-4")]');
        $items = [];

        foreach ($cards as $card) {
            // 連結
            $a   = $xpath->query('.//a[@href]', $card)->item(0);
            $url = $a ? $a->getAttribute('href') : null;
            if (!$url) continue;
            if (!str_starts_with($url, 'http')) {
                $url = 'https://18porn.cc' . $url;
            }

            // 標題
            $title = '';
            $titleNode = $xpath->query('.//a[@title]', $card)->item(0)
                      ?? $xpath->query('.//h3|.//h2|.//p', $card)->item(0);
            if ($titleNode) {
                $title = trim($titleNode->getAttribute('title') ?: $titleNode->textContent);
            }

            // 縮圖
            $img     = $xpath->query('.//img', $card)->item(0);
            $thumb   = $img ? ($img->getAttribute('data-src') ?: $img->getAttribute('src')) : null;

            // 時長
            $durationNode = $xpath->query('.//*[contains(@class,"duration") or contains(@class,"time")]', $card)->item(0);
            $duration = $durationNode ? trim($durationNode->textContent) : null;

            $items[] = [
                'url'      => $url,
                'title'    => mb_substr($title, 0, 80),
                'thumb'    => $thumb,
                'duration' => $duration,
            ];
        }

        return $items;
    }

    private function parseDetail(string $html): array
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($doc);

        $result = [];

        // 標題
        $h1 = $xpath->query('//h1')->item(0);
        if ($h1) $result['title'] = trim($h1->textContent);

        // Tags（id="TagsLocation"）
        $tagContainer = $xpath->query('//*[@id="TagsLocation"]')->item(0);
        $tags = [];
        if ($tagContainer) {
            foreach ($xpath->query('.//a', $tagContainer) as $a) {
                $t = trim($a->textContent);
                if ($t) $tags[] = $t;
            }
        }
        $result['tags'] = $tags;

        // 封面圖（og:image）
        $og = $xpath->query('//meta[@property="og:image"]')->item(0);
        if ($og) $result['cover'] = $og->getAttribute('content');

        // 嘗試找影片來源 URL
        $source = $xpath->query('//source[@src]')->item(0)
               ?? $xpath->query('//video[@src]')->item(0);
        if ($source) $result['video_src'] = $source->getAttribute('src');

        return $result;
    }
}
