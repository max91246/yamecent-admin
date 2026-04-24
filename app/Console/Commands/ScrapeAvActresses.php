<?php

namespace App\Console\Commands;

use App\AvActress;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScrapeAvActresses extends Command
{
    protected $signature   = 'scrape:av-actresses
                              {--pages=5 : 爬取頁數（每頁約 20 筆）}
                              {--delay=3 : 每頁請求間隔秒數}';
    protected $description = '從 MissAV 爬取 AV 女優資料存入 ya_av_actresses';

    private Client $client;
    private string $flareSolverrUrl;

    public function handle()
    {
        $this->flareSolverrUrl = getConfig('flaresolverr_url');
        if (!$this->flareSolverrUrl) {
            $this->error('flaresolverr_url 未設定');
            return 1;
        }

        $this->client = new Client(['timeout' => 60]);
        $maxPages     = (int) $this->option('pages');
        $delay        = (int) $this->option('delay');
        $saved = $skip = $fail = 0;

        $this->info("開始爬取 MissAV 女優，共 {$maxPages} 頁...");
        Log::channel('tg_webhook')->info('[AV爬蟲] 開始', ['pages' => $maxPages]);

        for ($page = 1; $page <= $maxPages; $page++) {
            $this->line("── 第 {$page} 頁 ──");
            $url      = "https://missav.ws/actresses?page={$page}";
            $html     = $this->fetchHtml($url);
            if (!$html) {
                $this->warn("第 {$page} 頁取得失敗，略過");
                continue;
            }

            $cards = $this->parseActressList($html);
            $this->line("  找到 " . count($cards) . " 位女優");

            foreach ($cards as $card) {
                // 嘗試取詳細資料
                $detail = $this->fetchActressDetail($card['detail_url']) ?? [];
                if (empty($detail)) {
                    $fail++;
                    $this->warn("  [詳細取得失敗] {$card['name']}");
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

                $this->line('  [' . ($isNew ? '新增' : '更新') . "] {$data['name']}");
                $saved++;

                sleep(1);
            }

            if ($page < $maxPages) {
                sleep($delay);
            }
        }

        $this->info("完成。新增 {$saved}，略過 {$skip}，失敗 {$fail}。");
        Log::channel('tg_webhook')->info('[AV爬蟲] 完成', compact('saved', 'skip', 'fail'));
        return 0;
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            $res  = $this->client->post($this->flareSolverrUrl, [
                'json' => ['cmd' => 'request.get', 'url' => $url, 'maxTimeout' => 30000],
            ]);
            $body = json_decode((string) $res->getBody(), true);
            $status = $body['status'] ?? 'unknown';
            if ($status !== 'ok') {
                $this->warn("    FlareSolverr status: {$status} | " . ($body['message'] ?? ''));
                return null;
            }
            $code = $body['solution']['status'] ?? 0;
            if ($code !== 200) {
                $this->warn("    HTTP {$code}");
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
        $links   = $xpath->query('//a[contains(@href,"/actresses/")]');
        $results = [];
        $seen    = [];

        foreach ($links as $a) {
            $href = $a->getAttribute('href');
            // 只要女優個人頁，排除 ranking / 純列表
            if (!preg_match('#/actresses/([^/?]+)$#u', $href, $m)) {
                continue;
            }
            $slug = urldecode($m[1]);
            if (in_array($slug, ['ranking']) || isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;

            // 取名字（img alt 或 a title）
            $img  = $xpath->query('.//img', $a);
            $name = $img->length ? $img->item(0)->getAttribute('alt') : '';
            if (!$name) {
                $name = $a->getAttribute('title');
            }
            if (!$name || mb_strlen($name) < 2) {
                continue;
            }

            // 頭像
            $imageUrl = $img->length ? $img->item(0)->getAttribute('src') : null;

            // 保留完整 detail URL（含語言前綴，如 /dm248/actresses/波多野結衣）
            $detailUrl = str_starts_with($href, 'http')
                ? $href
                : 'https://missav.ws' . $href;

            $results[] = [
                'name'       => $name,
                'slug'       => $slug,
                'image_url'  => $imageUrl,
                'detail_url' => $detailUrl,
            ];
        }

        return $results;
    }

    private function fetchActressDetail(string $detailUrl): ?array
    {
        $url  = $detailUrl;
        $this->line("    → 詳細 URL：{$url}");
        $html = $this->fetchHtml($url);
        $this->line("    → HTML 長度：" . ($html ? strlen($html) : 'NULL'));
        if (!$html) {
            return null;
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $result = [];

        // 資料在 class="text-nord9" 的 div 內的 <p> 標籤
        // 格式：<p>163cm / 35E - 23 - 33</p><p>1988-05-24 (37歲)</p>
        $infoDiv = $xpath->query('//div[contains(@class,"text-nord9")]//p');
        $texts   = [];
        foreach ($infoDiv as $p) {
            $t = trim($p->textContent);
            if ($t) $texts[] = $t;
        }
        $combinedText = implode(' ', $texts);

        // 身高：163cm
        if (preg_match('/(\d{3})\s*cm/i', $combinedText, $m)) {
            $result['height'] = (int) $m[1];
        }

        // 三圍：35E - 23 - 33（用 / 或 - 分隔）
        // 格式：163cm / 35E - 23 - 33
        if (preg_match('/(\d{2,3}[A-Za-z]?)\s*[-–]\s*(\d{2,3})\s*[-–]\s*(\d{2,3})/', $combinedText, $m)) {
            $result['bust']  = $m[1];
            $result['waist'] = (int) $m[2];
            $result['hip']   = (int) $m[3];
        }

        // 生日：1988-05-24
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $combinedText, $m)) {
            $result['birthday'] = "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        return $result;
    }
}
