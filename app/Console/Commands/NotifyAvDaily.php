<?php

namespace App\Console\Commands;

use App\AvUserPref;
use App\AvVideo;
use App\TgBot;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyAvDaily extends Command
{
    protected $signature   = 'notify:av-daily';
    protected $description = '每日推送 AV 新片給訂閱用戶（依喜好 tag 篩選）';

    public function handle()
    {
        $today  = now()->toDateString();
        $client = new Client(['timeout' => 10]);
        $pushed = 0;
        $skip   = 0;

        Log::channel('av_scraper')->info('[AV推播] 開始每日推播', ['date' => $today]);

        // 取所有開啟推播的用戶偏好
        $prefs = AvUserPref::where('push_enabled', true)
            ->whereNotNull('fav_tags')
            ->get();

        foreach ($prefs as $pref) {
            $tags = $pref->fav_tags;
            if (empty($tags)) { $skip++; continue; }

            $bot = TgBot::find($pref->bot_id);
            if (!$bot || !$bot->is_active) { $skip++; continue; }

            // 找今日有匹配 tag 的新片（最多 5 部）
            $query = AvVideo::whereDate('release_date', $today);
            foreach ($tags as $tag) {
                $query->orWhere(function ($q) use ($tag) {
                    $q->whereJsonContains('tags', $tag)
                      ->whereDate('release_date', now()->toDateString());
                });
            }
            $videos = $query->inRandomOrder()->limit(5)->get();

            if ($videos->isEmpty()) { $skip++; continue; }

            // 組訊息
            $tagStr = implode('、', $tags);
            $lines  = ["🔔 <b>今日新片推播</b>\n📌 喜好標籤：{$tagStr}\n"];

            foreach ($videos as $v) {
                $actress = $v->actresses ? implode(' / ', $v->actresses) : '-';
                $vTags   = $v->tags ? implode(' ｜ ', array_slice($v->tags, 0, 5)) : '';
                $lines[] = "📀 <b>{$v->code}</b>";
                if ($v->title) $lines[] = "📝 " . mb_substr($v->title, 0, 50) . (mb_strlen($v->title) > 50 ? '…' : '');
                $lines[] = "👤 {$actress}";
                if ($vTags) $lines[] = "🏷 {$vTags}";
                if ($v->studio) $lines[] = "🏢 {$v->studio}";
                if ($v->source_url) $lines[] = "🔗 {$v->source_url}";
                $lines[] = '';
            }

            try {
                $client->post("https://api.telegram.org/bot{$bot->token}/sendMessage", [
                    'json' => [
                        'chat_id'    => $pref->tg_chat_id,
                        'text'       => implode("\n", $lines),
                        'parse_mode' => 'HTML',
                        'disable_web_page_preview' => false,
                    ],
                ]);
                $pushed++;
            } catch (\Exception $e) {
                Log::channel('av_scraper')->warning('[AV推播] 推送失敗', [
                    'chat_id' => $pref->tg_chat_id,
                    'error'   => $e->getMessage(),
                ]);
            }

            usleep(300000); // 0.3 秒間隔，避免觸發 TG rate limit
        }

        $this->info("完成。推播 {$pushed} 人，略過 {$skip} 人。");
        Log::channel('av_scraper')->info('[AV推播] 完成', compact('pushed', 'skip'));
        return 0;
    }
}
