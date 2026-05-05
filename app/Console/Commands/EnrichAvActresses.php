<?php

namespace App\Console\Commands;

use App\AvActress;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EnrichAvActresses extends Command
{
    protected $signature   = 'enrich:av-actresses
                              {--limit=0   : 最多處理幾筆（0=全部）}
                              {--missing   : 只處理缺資料的女優（height/birthday/birthplace 任一為空）}
                              {--delay=2   : 每筆請求間隔秒數}';
    protected $description = '從 avbase.net 批次補齊女優個人資料（出身地/身長/サイズ/趣味/生日）';

    public function handle()
    {
        $flareUrl = rtrim(getConfig('flaresolverr_url'), '/');
        $limit    = (int) $this->option('limit');
        $delay    = (int) $this->option('delay');
        $client   = new Client(['timeout' => 60]);

        $query = AvActress::orderBy('id');

        if ($this->option('missing')) {
            $query->where(function ($q) {
                $q->whereNull('height')
                  ->orWhereNull('birthday')
                  ->orWhereNull('birthplace');
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $actresses = $query->get();
        $total     = $actresses->count();

        $this->info("共 {$total} 筆女優需補齊資料...");

        $updated = 0;
        $notFound = 0;
        $failed   = 0;

        foreach ($actresses as $i => $actress) {
            $this->line("[" . ($i + 1) . "/{$total}] {$actress->name}");

            $url  = 'https://www.avbase.net/talents/' . urlencode($actress->name);
            $html = $this->fetchHtml($client, $flareUrl, $url);

            if ($html === null) {
                $this->warn("  → FlareSolverr 失敗，略過");
                $failed++;
                sleep($delay);
                continue;
            }

            $data = $this->parse($html);

            if (empty($data)) {
                $this->line("  → 查無資料");
                $notFound++;
                sleep($delay);
                continue;
            }

            // 只更新原本為空的欄位，不覆蓋已有資料
            $payload = [];
            if (!$actress->height     && !empty($data['height']))     $payload['height']     = $data['height'];
            if (!$actress->bust       && !empty($data['bust']))        $payload['bust']       = $data['bust'];
            if (!$actress->waist      && !empty($data['waist']))       $payload['waist']      = $data['waist'];
            if (!$actress->hip        && !empty($data['hip']))         $payload['hip']        = $data['hip'];
            if (!$actress->birthday   && !empty($data['birthday']))    $payload['birthday']   = $data['birthday'];
            if (!$actress->birthplace && !empty($data['birthplace']))  $payload['birthplace'] = $data['birthplace'];
            if (!$actress->hobbies    && !empty($data['hobbies']))     $payload['hobbies']    = $data['hobbies'];
            if (!empty($data['image_url']))                             $payload['image_url']  = $data['image_url'];

            if (!empty($payload)) {
                $actress->update($payload);
                $updated++;
                $this->info("  → 更新：" . implode(', ', array_keys($payload)));
            } else {
                $this->line("  → 資料已完整，略過");
            }

            sleep($delay);
        }

        $this->info("完成。更新 {$updated} 筆，查無 {$notFound} 筆，失敗 {$failed} 筆。");
        Log::channel('av_scraper')->info('[EnrichAvActresses] 完成', compact('updated', 'notFound', 'failed'));

        return 0;
    }

    private function fetchHtml(Client $client, string $flareUrl, string $targetUrl): ?string
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
                Log::channel('av_scraper')->warning('[EnrichAvActresses] FlareSolverr 非 ok', [
                    'url'     => $targetUrl,
                    'message' => $body['message'] ?? '',
                ]);
                return null;
            }
            return $body['solution']['response'] ?? null;
        } catch (\Exception $e) {
            Log::channel('av_scraper')->warning('[EnrichAvActresses] 請求失敗', [
                'url'   => $targetUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function parse(string $html): array
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath  = new \DOMXPath($doc);
        $result = [];

        // 大頭照（og:image）
        $og = $xpath->query('//meta[@property="og:image"]')->item(0);
        if ($og) {
            $src = $og->getAttribute('content');
            if ($src && !str_contains($src, 'default') && !str_contains($src, 'placeholder')) {
                $result['image_url'] = $src;
            }
        }

        // 基本資料區塊：class 含 flex-col gap-2 text-balance
        $infoBlock = $xpath->query(
            '//*[contains(@class,"flex-col") and contains(@class,"gap-2") and contains(@class,"text-balance")]'
        )->item(0);

        if (!$infoBlock) {
            return $result;
        }

        // 每個 flex justify-between items-start 是一列資料
        $rows = $xpath->query(
            './/*[contains(@class,"flex") and contains(@class,"justify-between") and contains(@class,"items-start")]',
            $infoBlock
        );

        $rawData = [];
        foreach ($rows as $row) {
            $spans = $xpath->query('.//span|.//div|.//p', $row);
            $texts = [];
            foreach ($spans as $span) {
                // 只取直接子文字，避免重複
                $t = '';
                foreach ($span->childNodes as $child) {
                    if ($child->nodeType === XML_TEXT_NODE) {
                        $t .= $child->textContent;
                    }
                }
                $t = trim($t);
                if ($t) $texts[] = $t;
            }
            $texts = array_values(array_unique(array_filter($texts)));
            if (count($texts) >= 2) {
                $rawData[$texts[0]] = $texts[1];
            }
        }

        // 對應欄位
        foreach ($rawData as $label => $value) {
            if (str_contains($label, '出身地') || str_contains($label, '出生地')) {
                $result['birthplace'] = $value;
            } elseif (str_contains($label, '身長') || str_contains($label, '身高')) {
                $result['height'] = (int) preg_replace('/[^0-9]/', '', $value) ?: null;
            } elseif (str_contains($label, 'サイズ') || str_contains($label, '尺寸')) {
                // 格式例：88B-58-85 或 B88-W58-H85
                if (preg_match('/(\d{2,3}[A-Za-z]?)[^\d]*(\d{2,3})[^\d]*(\d{2,3})/', $value, $m)) {
                    $result['bust']  = $m[1];
                    $result['waist'] = (int) $m[2];
                    $result['hip']   = (int) $m[3];
                }
            } elseif (str_contains($label, '趣味') || str_contains($label, '興趣')) {
                $result['hobbies'] = mb_substr($value, 0, 200);
            } elseif (str_contains($label, '生年月日') || str_contains($label, '生日') || str_contains($label, '誕生日')) {
                // 格式例：1995年03月14日 或 1995-03-14
                if (preg_match('/(\d{4})[\-年\/](\d{1,2})[\-月\/](\d{1,2})/', $value, $m)) {
                    $result['birthday'] = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
                }
            }
        }

        return array_filter($result, fn($v) => $v !== null && $v !== '');
    }
}
