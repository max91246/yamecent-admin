<?php

namespace App\Console\Commands;

use App\AvVideo;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScrapeAvVideos extends Command
{
    protected $signature   = 'scrape:av-videos
                              {--pages=3 : 爬取頁數}
                              {--list=new : new / today / uncensored / release}
                              {--delay=2 : 每頁間隔秒數}';
    protected $description = '從 MissAV 爬取 AV 新片資料存入 ya_av_videos';

    private const BASE = 'https://missav.ai';

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
        $listType = $this->option('list');
        $maxPages = (int) $this->option('pages');
        $delay    = (int) $this->option('delay');
        $saved = $updated = $fail = 0;

        $this->info("開始爬取 MissAV 新片（{$listType}）共 {$maxPages} 頁...");
        Log::channel('tg_webhook')->info('[AV影片爬蟲] 開始', ['type' => $listType, 'pages' => $maxPages]);

        for ($page = 1; $page <= $maxPages; $page++) {
            $this->line("── 第 {$page} 頁 ──");
            $listUrl = $this->buildListUrl($listType, $page);
            $html    = $this->fetchHtml($listUrl);
            if (!$html) {
                $this->warn("  取得失敗，略過");
                continue;
            }

            $codes = $this->parseVideoList($html);
            $this->line("  找到 " . count($codes) . " 部影片");

            foreach ($codes as $item) {
                $detail = $this->fetchVideoDetail($item['url']);
                if (!$detail || empty($detail['code'])) {
                    $fail++;
                    $this->warn("  [詳細失敗] {$item['code']}");
                    continue;
                }

                $payload = [
                    'code'         => $detail['code'],
                    'title'        => $detail['title']         ?? '',
                    'cover_url'    => $detail['cover_url']     ?? $item['cover'] ?? null,
                    'thumb_url'    => $item['cover']           ?? null,
                    'release_date' => $detail['release_date']  ?? null,
                    'studio'       => $detail['studio']        ?? null,
                    'series'       => $detail['series']        ?? null,
                    'actresses'    => $detail['actresses']     ?? null,
                    'tags'         => $detail['tags']          ?? null,
                    'source'       => 'missav',
                    'source_url'   => $item['url'],
                    'is_uncensored'=> (bool) ($detail['is_uncensored'] ?? false),
                    'is_leaked'    => str_contains($listType, 'leak'),
                ];

                $isNew = !AvVideo::where('code', $detail['code'])->exists();
                AvVideo::updateOrCreate(['code' => $detail['code']], $payload);

                $tag = $isNew ? '新增' : '更新';
                $actress = $payload['actresses'] ? implode(',', $payload['actresses']) : '-';
                $this->line("  [{$tag}] {$detail['code']} | {$actress} | {$payload['release_date']}");

                $isNew ? $saved++ : $updated++;
                usleep(800000); // 0.8 秒間隔
            }

            if ($page < $maxPages) sleep($delay);
        }

        $this->info("完成。新增 {$saved}，更新 {$updated}，失敗 {$fail}。");
        Log::channel('tg_webhook')->info('[AV影片爬蟲] 完成', compact('saved', 'updated', 'fail'));
        return 0;
    }

    private function buildListUrl(string $type, int $page): string
    {
        return match ($type) {
            'today'      => self::BASE . "/today-hot?page={$page}",
            'uncensored' => self::BASE . "/uncensored-leak?page={$page}",
            'release'    => self::BASE . "/release?page={$page}",
            default      => self::BASE . "/new?page={$page}",
        };
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            $res  = $this->client->post($this->flareSolverrUrl, [
                'json' => ['cmd' => 'request.get', 'url' => $url, 'maxTimeout' => 30000],
            ]);
            $body = json_decode((string) $res->getBody(), true);
            if (($body['status'] ?? '') !== 'ok' || ($body['solution']['status'] ?? 0) !== 200) {
                return null;
            }
            return $body['solution']['response'] ?? null;
        } catch (\Exception $e) {
            $this->warn("    請求異常：" . $e->getMessage());
            return null;
        }
    }

    private function parseVideoList(string $html): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $links = $xpath->query('//a[contains(@href,"missav.ai/")]');
        $seen  = [];
        $out   = [];

        foreach ($links as $a) {
            if (!$a instanceof \DOMElement) continue;
            $href = $a->getAttribute('href');

            // 番號匹配：xxx-123 或 xxxxx-1234
            if (!preg_match('~missav\.ai/([a-z0-9]+-[0-9]+)(?:[/?#]|$)~i', $href, $m)) {
                continue;
            }
            $code = strtoupper($m[1]);
            if (isset($seen[$code])) continue;
            $seen[$code] = true;

            // 縮圖
            $imgNode = $xpath->query('.//img', $a)->item(0);
            $cover   = ($imgNode instanceof \DOMElement) ? $imgNode->getAttribute('src') : null;

            $out[] = [
                'code'  => $code,
                'url'   => $href,
                'cover' => $cover,
            ];
        }

        return $out;
    }

    private function fetchVideoDetail(string $url): ?array
    {
        $html = $this->fetchHtml($url);
        if (!$html) return null;

        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath  = new \DOMXPath($dom);
        $result = [];

        // og:image 作為封面
        $og = $xpath->query('//meta[@property="og:image"]')->item(0);
        if ($og instanceof \DOMElement) {
            $result['cover_url'] = $og->getAttribute('content');
        }

        // 詳細資料區：找所有含 : 或 ： 的 div
        $divs = $xpath->query('//div[contains(@class,"space-y-2") or contains(@class,"text-secondary") or contains(@class,"text-nord")]');
        $info = [];
        foreach ($divs as $d) {
            $t = trim($d->textContent);
            if (strlen($t) > 5 && strlen($t) < 500 && preg_match('/[:：]/', $t)) {
                $info[] = $t;
            }
        }

        $combined = implode("\n", $info);

        // 番號
        if (preg_match('/番號[:：]\s*([A-Z0-9\-]+)/u', $combined, $m)) {
            $result['code'] = trim($m[1]);
        }

        // 發行日
        if (preg_match('/發行日期[:：]\s*(\d{4}-\d{2}-\d{2})/u', $combined, $m)) {
            $result['release_date'] = $m[1];
        }

        // 標題（日文原名）
        if (preg_match('/標題[:：]\s*([^\n]+)/u', $combined, $m)) {
            $result['title'] = trim($m[1]);
        }

        // 女優
        if (preg_match('/女優[:：]\s*([^\n]+)/u', $combined, $m)) {
            $names = preg_split('/[,、，\s]+/u', trim($m[1]));
            $result['actresses'] = array_values(array_filter(array_map('trim', $names)));
        }

        // 類型 / 標籤
        if (preg_match('/類型[:：]\s*([^\n]+)/u', $combined, $m)) {
            $tags = preg_split('/[,、，]\s*/u', trim($m[1]));
            $result['tags'] = array_values(array_filter(array_map('trim', $tags)));
        }

        // 發行商
        if (preg_match('/發行商[:：]\s*([^\n]+)/u', $combined, $m)) {
            $result['studio'] = trim($m[1]);
        }

        // 系列
        if (preg_match('/系列[:：]\s*([^\n]+)/u', $combined, $m)) {
            $result['series'] = trim($m[1]);
        }

        // 無碼標記
        $result['is_uncensored'] = str_contains($url, 'uncensored') || str_contains($url, 'leak');

        return $result;
    }
}
