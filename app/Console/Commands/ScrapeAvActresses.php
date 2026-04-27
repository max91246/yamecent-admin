<?php

namespace App\Console\Commands;

use App\AvActress;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScrapeAvActresses extends Command
{
    protected $signature   = 'scrape:av-actresses
                              {--pages=5   : 爬取頁數（每頁約 24 筆）}
                              {--delay=3   : 每頁請求間隔秒數}
                              {--new-only  : 只爬第 1 頁新人（排程用）}';
    protected $description = '從 MissAV 爬取 AV 女優資料（按出道日期排序）';

    private string $BASE;
    private string $LIST_URL;

    private Client $client;
    private string $flareSolverrUrl;

    public function handle()
    {
        $this->flareSolverrUrl = getConfig('flaresolverr_url');
        if (!$this->flareSolverrUrl) {
            $this->error('flaresolverr_url 未設定');
            return 1;
        }

        $this->BASE     = rtrim(getConfig('missav_base_url') ?: 'https://missav.ai', '/');
        $this->LIST_URL = getConfig('missav_actress_list_url') ?: ($this->BASE . '/actresses?sort=debut&page=');

        $this->client = new Client(['timeout' => 60]);
        $maxPages = $this->option('new-only') ? 1 : (int) $this->option('pages');
        $delay    = (int) $this->option('delay');
        $saved = $updated = $fail = 0;

        $this->info("開始爬取 MissAV 女優（按出道排序），共 {$maxPages} 頁...");
        Log::channel('tg_webhook')->info('[AV爬蟲] 開始', ['pages' => $maxPages]);

        for ($page = 1; $page <= $maxPages; $page++) {
            $this->line("── 第 {$page} 頁 ──");
            $html = $this->fetchHtml($this->LIST_URL . $page);
            if (!$html) {
                $this->warn("第 {$page} 頁取得失敗，略過");
                continue;
            }

            $cards = $this->parseActressList($html);
            $this->line("  找到 " . count($cards) . " 位女優");

            foreach ($cards as $card) {
                $detail = $this->fetchActressDetail($card['detail_url']) ?? [];
                if (empty($detail)) {
                    $fail++;
                    $this->warn("  [詳細失敗] {$card['name']}");
                }

                // 出道年從列表頁取得，detail 沒有就用列表的
                if (!empty($card['debut_year']) && empty($detail['debut_year'])) {
                    $detail['debut_year'] = $card['debut_year'];
                }

                $data    = array_merge($card, $detail);
                $payload = [
                    'name'       => $data['name'],
                    'image_url'  => $data['image_url'] ?? null,
                    'height'     => $data['height'] ?? null,
                    'bust'       => $data['bust'] ?? null,
                    'waist'      => $data['waist'] ?? null,
                    'hip'        => $data['hip'] ?? null,
                    'birthday'   => $data['birthday'] ?? null,
                    'debut_year' => $data['debut_year'] ?? null,
                    'is_active'  => true,
                ];

                $isNew = !AvActress::where('missav_slug', $data['slug'])->exists();
                AvActress::updateOrCreate(
                    ['missav_slug' => $data['slug']],
                    $payload
                );

                $this->line('  [' . ($isNew ? '新增' : '更新') . "] {$data['name']}" .
                    (!empty($data['debut_year']) ? " ({$data['debut_year']}出道)" : ''));

                $isNew ? $saved++ : $updated++;
                sleep(1);
            }

            if ($page < $maxPages) {
                sleep($delay);
            }
        }

        $this->info("完成。新增 {$saved}，更新 {$updated}，詳細失敗 {$fail}。");
        Log::channel('tg_webhook')->info('[AV爬蟲] 完成', compact('saved', 'updated', 'fail'));
        return 0;
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            $res  = $this->client->post($this->flareSolverrUrl, [
                'json' => ['cmd' => 'request.get', 'url' => $url, 'maxTimeout' => 30000],
            ]);
            $body   = json_decode((string) $res->getBody(), true);
            $status = $body['status'] ?? 'unknown';
            if ($status !== 'ok') {
                $this->warn("    FlareSolverr: {$status} | " . ($body['message'] ?? ''));
                return null;
            }
            if (($body['solution']['status'] ?? 0) !== 200) {
                $this->warn("    HTTP " . ($body['solution']['status'] ?? '?'));
                return null;
            }
            return $body['solution']['response'] ?? null;
        } catch (\Exception $e) {
            $this->warn("    請求異常：" . $e->getMessage());
            return null;
        }
    }

    private function parseActressList(string $html): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath   = new \DOMXPath($dom);
        $results = [];
        $seen    = [];

        // missav.ai 列表頁結構：li > div.space-y-4 > a[href=/actresses/slug]
        $items = $xpath->query('//li[.//a[contains(@href,"/actresses/")]]');

        foreach ($items as $li) {
            $a    = $xpath->query('.//a[contains(@href,"/actresses/")]', $li)->item(0);
            if (!$a) continue;

            $href = $a->getAttribute('href');
            if (!preg_match('#/actresses/([^/?]+)$#u', $href, $m)) continue;

            $slug = urldecode($m[1]);
            if (in_array($slug, ['ranking']) || isset($seen[$slug])) continue;
            $seen[$slug] = true;

            // 姓名：h4.text-nord13
            $h4   = $xpath->query('.//h4', $li)->item(0);
            $name = $h4 ? trim($h4->textContent) : '';
            if (!$name || mb_strlen($name) < 2) continue;

            // 圖片
            $imgNode  = $xpath->query('.//img', $li)->item(0);
            $imageUrl = ($imgNode instanceof \DOMElement) ? $imgNode->getAttribute('src') : null;

            // 出道年份：<p>2026 出道</p>
            $debutYear = null;
            $paras = $xpath->query('.//p', $li);
            foreach ($paras as $p) {
                $text = trim($p->textContent);
                if (preg_match('/^(\d{4})\s*出道/', $text, $dy)) {
                    $debutYear = (int) $dy[1];
                    break;
                }
            }

            $detailUrl = str_starts_with($href, 'http')
                ? $href
                : $this->BASE . $href;

            $results[] = [
                'name'       => $name,
                'slug'       => $slug,
                'image_url'  => $imageUrl,
                'debut_year' => $debutYear,
                'detail_url' => $detailUrl,
            ];
        }

        return $results;
    }

    private function fetchActressDetail(string $detailUrl): ?array
    {
        $html = $this->fetchHtml($detailUrl);
        if (!$html) return null;

        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath  = new \DOMXPath($dom);
        $result = [];

        // 資料在 class 含 text-nord9 的 div 裡的 <p> 標籤
        $paras = $xpath->query('//div[contains(@class,"text-nord9")]//p');
        $texts = [];
        foreach ($paras as $p) {
            $t = trim($p->textContent);
            if ($t) $texts[] = $t;
        }
        $combinedText = implode(' ', $texts);

        // 身高：163cm
        if (preg_match('/(\d{3})\s*cm/i', $combinedText, $m)) {
            $result['height'] = (int) $m[1];
        }

        // 三圍：35E - 23 - 33
        if (preg_match('/(\d{2,3}[A-Za-z]?)\s*[-–]\s*(\d{2,3})\s*[-–]\s*(\d{2,3})/', $combinedText, $m)) {
            $result['bust']  = $m[1];
            $result['waist'] = (int) $m[2];
            $result['hip']   = (int) $m[3];
        }

        // 生日：1988-05-24
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $combinedText, $m)) {
            $result['birthday'] = "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        // 圖片（detail 頁有更高解析度）
        $imgNode = $xpath->query('//div[contains(@class,"actor-avatar") or contains(@class,"overflow-hidden rounded-full")]//img')->item(0);
        if ($imgNode instanceof \DOMElement) {
            $result['image_url'] = $imgNode->getAttribute('src');
        }

        return $result;
    }
}
