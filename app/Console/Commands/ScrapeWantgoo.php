<?php

namespace App\Console\Commands;

use App\Article;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Console\Command;

class ScrapeWantgoo extends Command
{
    protected $signature = 'scrape:wantgoo
                            {--page=1      : 要爬取的頁碼}
                            {--fix-images  : 補下載已存在但圖片仍為外部 URL 的記錄}';

    protected $description = '透過 FlareSolverr 爬取 Wantgoo 精選文章並存入 DB';

    // FlareSolverr 在 Laradock backend 網路內的位址
    const FLARE_URL  = 'http://flaresolverr:8191/v1';
    const LIST_TPL   = 'https://www.wantgoo.com/blog/daily-featured-data?page=%d';
    const DETAIL_TPL = 'https://www.wantgoo.com/blog/%s/post/%s/detail';

    // 爬蟲圖片存放目錄（與後台上傳的 uploads/article/ 區隔）
    const IMG_DIR = 'uploads/wantgoo';

    public function handle()
    {
        $client = new Client(['timeout' => 90]);

        // ── 補圖模式 ────────────────────────────────────────────────
        if ($this->option('fix-images')) {
            return $this->fixImages($client);
        }

        // ── 1. 取得列表 ──────────────────────────────────────────────
        $page    = (int) $this->option('page');
        $listUrl = sprintf(self::LIST_TPL, $page);
        $this->info("正在透過 FlareSolverr 爬取第 {$page} 頁列表...");

        $articles = $this->flare($client, $listUrl);

        if ($articles === null || !is_array($articles) || empty($articles)) {
            $this->error('列表取得失敗或為空，請確認 FlareSolverr 容器正在執行。');
            return 1;
        }

        $this->info('共取得 ' . count($articles) . " 篇文章，開始處理...\n");

        $saved   = 0;
        $skipped = 0;

        // ── 2. 逐篇處理 ──────────────────────────────────────────────
        foreach ($articles as $i => $a) {
            $postId   = $a['postId']   ?? null;
            $memberId = $a['memberId'] ?? null;
            $title    = $a['title']    ?? '（無標題）';

            $this->line(str_repeat('─', 62));
            $this->line('[' . ($i + 1) . '] ' . $title);

            // 去重：source_post_id 已存在則略過
            if ($postId && Article::where('source_post_id', $postId)->exists()) {
                $this->line('    → [略過] 已存在');
                $skipped++;
                continue;
            }

            // ── 3. 下載封面圖片到本地 ────────────────────────────────
            $coverUrl   = $a['cover'] ?? null;
            $localImage = null;

            if ($coverUrl) {
                $this->line("    → 下載圖片：{$coverUrl}");
                $localImage = $this->downloadImage($client, $coverUrl);
                if ($localImage) {
                    $this->line("    → 圖片已存：{$localImage}");
                } else {
                    $this->warn('    → 圖片下載失敗，image 欄位留空');
                }
            }

            // ── 4. 取得 detail 內容 ──────────────────────────────────
            $content = $a['summary'] ?? '';

            if ($postId && $memberId) {
                $detailUrl = sprintf(self::DETAIL_TPL, $memberId, $postId);
                $this->line("    → 取得 detail：{$detailUrl}");

                $detail = $this->flare($client, $detailUrl);

                if (is_array($detail)) {
                    $content = $detail['content']
                        ?? $detail['body']
                        ?? $detail['htmlContent']
                        ?? $detail['html']
                        ?? $detail['postContent']
                        ?? ($a['summary'] ?? '');
                } else {
                    $this->warn('    → detail 取得失敗，以摘要代替內容');
                }
            }

            // ── 5. 存入 DB ───────────────────────────────────────────
            $publishedAt = isset($a['publishTime'])
                ? date('Y-m-d H:i:s', (int) ($a['publishTime'] / 1000))
                : null;

            Article::create([
                'title'               => $title,
                'image'               => $localImage,
                'content'             => $content,
                'type'                => 4,
                'is_active'           => 1,
                'source_post_id'      => $postId,
                'source_member_id'    => $memberId,
                'source_published_at' => $publishedAt,
            ]);

            $this->info('    → [新增] 成功');
            $saved++;
        }

        $this->line(str_repeat('─', 62));
        $this->info("爬取完成。已新增 {$saved} 篇，略過 {$skipped} 篇。");

        return 0;
    }

    /**
     * 補圖模式：找出 type=4 且 image 仍為外部 URL 的記錄，重新下載並更新。
     */
    private function fixImages(Client $client): int
    {
        $this->info('補圖模式：搜尋 image 仍為外部 URL 的玩股網文章...');

        $rows = Article::where('type', 4)
            ->where(function ($q) {
                $q->where('image', 'like', 'http://%')
                  ->orWhere('image', 'like', 'https://%');
            })
            ->get();

        if ($rows->isEmpty()) {
            $this->info('沒有需要補圖的記錄。');
            return 0;
        }

        $this->info("共找到 {$rows->count()} 筆需要補圖。\n");

        $fixed  = 0;
        $failed = 0;

        foreach ($rows as $article) {
            $this->line(str_repeat('─', 62));
            $this->line("[{$article->id}] {$article->title}");
            $this->line("    → 原始 URL：{$article->image}");

            $localImage = $this->downloadImage($client, $article->image);

            if ($localImage) {
                $article->image = $localImage;
                $article->save();
                $this->info("    → [已更新] {$localImage}");
                $fixed++;
            } else {
                $this->warn('    → [失敗] 圖片下載失敗，跳過');
                $failed++;
            }
        }

        $this->line(str_repeat('─', 62));
        $this->info("補圖完成。已更新 {$fixed} 筆，失敗 {$failed} 筆。");

        return 0;
    }

    /**
     * 下載外部圖片到本地 uploads/wantgoo/YYYY/MM/ 目錄。
     * 以 URL 的 md5 為檔名（保留原始副檔名），避免重複下載。
     * 回傳相對路徑（如 /uploads/wantgoo/2026/03/abc123.jpg），失敗回傳 null。
     */
    private function downloadImage(Client $client, string $url): ?string
    {
        try {
            // 取副檔名，預設 jpg
            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $ext = 'jpg';
            }

            $filename  = md5($url) . '.' . $ext;
            $dateDir   = date('Y/m');
            $relDir    = self::IMG_DIR . '/' . $dateDir;
            $absDir    = public_path($relDir);
            $absPath   = $absDir . '/' . $filename;
            $relPath   = '/' . $relDir . '/' . $filename;

            // 已存在則直接回傳路徑，不重複下載
            if (file_exists($absPath)) {
                return $relPath;
            }

            if (!is_dir($absDir)) {
                mkdir($absDir, 0755, true);
            }

            $client->get($url, ['sink' => $absPath, 'timeout' => 30]);

            return file_exists($absPath) ? $relPath : null;
        } catch (\Exception $e) {
            $this->warn('    → 圖片下載例外：' . $e->getMessage());
            return null;
        }
    }

    /**
     * 透過 FlareSolverr 取得 URL 並解析 JSON 回應。
     * 回傳解析後的陣列，失敗回傳 null。
     */
    private function flare(Client $client, string $url): ?array
    {
        try {
            $res = $client->post(self::FLARE_URL, [
                'json' => [
                    'cmd'        => 'request.get',
                    'url'        => $url,
                    'maxTimeout' => 60000,
                ],
            ]);
        } catch (RequestException $e) {
            $this->error('FlareSolverr 請求失敗：' . $e->getMessage());
            return null;
        }

        $body     = json_decode((string) $res->getBody(), true);
        $solution = $body['solution'] ?? null;

        if (!$solution || ($body['status'] ?? '') !== 'ok') {
            $this->error('FlareSolverr 回傳錯誤：' . ($body['message'] ?? json_encode($body)));
            return null;
        }

        $text = $solution['response'] ?? '';

        // FlareSolverr 有時會把 JSON 包在 <html><body><pre>...</pre></body></html> 裡
        if (preg_match('/<pre[^>]*>([\s\S]*?)<\/pre>/i', $text, $m)) {
            $text = html_entity_decode(trim($m[1]));
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }
}
