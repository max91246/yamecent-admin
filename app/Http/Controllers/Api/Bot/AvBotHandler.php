<?php

namespace App\Http\Controllers\Api\Bot;

use App\AvUserPref;
use App\AvVideo;
use App\TgBot;
use App\Http\Controllers\Api\Bot\Concerns\TelegramHelpers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AvBotHandler
{
    use TelegramHelpers;

    public function handle(TgBot $bot, array $update): \Illuminate\Http\JsonResponse
    {
        // callback_query
        if (isset($update['callback_query'])) {
            $cq       = $update['callback_query'];
            $chatId   = (int) $cq['message']['chat']['id'];
            $data     = $cq['data'];
            $username = $cq['from']['username'] ?? null;
            $this->answerCallbackQuery($bot->token, $cq['id']);

            if ($data === 'av_tag_save') {
                $this->clearState($bot->id, $chatId);
                $this->sendMessage($bot->token, $chatId, "✅ 喜好設定已儲存！", $this->getAvKeyboard());
            } elseif ($data === 'av_push_toggle') {
                $pref = AvUserPref::firstOrCreate(['bot_id' => $bot->id, 'tg_chat_id' => $chatId]);
                $pref->update(['push_enabled' => !$pref->push_enabled]);
                [$text, $markup] = $this->buildAvTagMenu($bot, $chatId, $username);
                $this->sendMessage($bot->token, $chatId, $text, $markup);
            } elseif (str_starts_with($data, 'av_tag_')) {
                $tag = substr($data, 7);
                $this->avToggleTag($bot, $chatId, $tag);
                [$text, $markup] = $this->buildAvTagMenu($bot, $chatId, $username);
                $this->sendMessage($bot->token, $chatId, $text, $markup);
            }
            return response()->json(['ok' => true]);
        }

        $msg      = $update['message'] ?? $update['edited_message'] ?? null;
        if (!$msg) return response()->json(['ok' => true]);

        $chatId   = (int) $msg['chat']['id'];
        $userId   = (string) ($msg['from']['id'] ?? $chatId);
        $username = $msg['from']['username'] ?? null;
        $text     = trim($msg['text'] ?? '');

        // 記錄用戶訊息
        if ($text) {
            $this->logMessage($bot->id, $userId, $username, $chatId, $text, 1, 'text');
        }

        if (str_starts_with($text, '/start') || in_array($text, ['開始', 'start'])) {
            $reply = "🔞 AV 速報機器人\n\n請選擇功能：";
            $this->sendMessage($bot->token, $chatId, $reply, $this->getAvKeyboard());
            $this->logMessage($bot->id, $userId, $username, $chatId, $reply, 2, 'reply');
            return response()->json(['ok' => true]);
        }

        if ($text === '🎬 今日新片') {
            $reply = $this->buildAvTodayReply($bot, $chatId);
            $this->sendMessage($bot->token, $chatId, $reply, $this->getAvKeyboard(), 'HTML');
            $this->logMessage($bot->id, $userId, $username, $chatId, $reply, 2, 'reply');
            return response()->json(['ok' => true]);
        }

        if ($text === '⭐ 喜好設定') {
            [$menuText, $markup] = $this->buildAvTagMenu($bot, $chatId, $username);
            $this->sendMessage($bot->token, $chatId, $menuText, $markup);
            $this->logMessage($bot->id, $userId, $username, $chatId, $menuText, 2, 'reply');
            return response()->json(['ok' => true]);
        }

        $reply = "請選擇功能：";
        $this->sendMessage($bot->token, $chatId, $reply, $this->getAvKeyboard());
        $this->logMessage($bot->id, $userId, $username, $chatId, $reply, 2, 'reply');
        return response()->json(['ok' => true]);
    }

    // 動態從 DB 統計最常見標籤，快取 1 小時
    private function getAvTags(): array
    {
        return Cache::remember('av_popular_tags', 3600, function () {
            $rows = AvVideo::whereNotNull('tags')->pluck('tags');
            $count = [];
            foreach ($rows as $tagArr) {
                if (!is_array($tagArr)) continue;
                foreach ($tagArr as $tag) {
                    $t = trim($tag);
                    if ($t && mb_strlen($t) <= 10) {
                        $count[$t] = ($count[$t] ?? 0) + 1;
                    }
                }
            }
            arsort($count);
            return array_keys(array_slice($count, 0, 40, true));
        });
    }

    private function getAvKeyboard(): array
    {
        return [
            'keyboard' => [
                [['text' => '🎬 今日新片'], ['text' => '⭐ 喜好設定']],
            ],
            'resize_keyboard' => true,
            'persistent'      => true,
        ];
    }

    private function buildAvTagMenu(TgBot $bot, int $chatId, ?string $username = null): array
    {
        $pref = AvUserPref::firstOrCreate(['bot_id' => $bot->id, 'tg_chat_id' => $chatId]);
        if ($username && $pref->tg_username !== $username) {
            $pref->update(['tg_username' => $username]);
        }
        $selected = $pref->fav_tags ?? [];
        $pushOn   = $pref->push_enabled;

        $text = "⭐ <b>喜好標籤設定</b>\n已選 " . count($selected) . " 個標籤\n點選標籤切換選取，完成後按「儲存」";

        $rows = [];
        $chunks = array_chunk($this->getAvTags(), 3);
        foreach ($chunks as $row) {
            $btns = [];
            foreach ($row as $tag) {
                $active  = in_array($tag, $selected);
                $btns[]  = ['text' => ($active ? '✅ ' : '') . $tag, 'callback_data' => 'av_tag_' . $tag];
            }
            $rows[] = $btns;
        }

        $rows[] = [
            ['text' => ($pushOn ? '🔔 每日推播：開' : '🔕 每日推播：關'), 'callback_data' => 'av_push_toggle'],
        ];
        $rows[] = [
            ['text' => '💾 儲存設定', 'callback_data' => 'av_tag_save'],
        ];

        return [$text, ['inline_keyboard' => $rows]];
    }

    private function avToggleTag(TgBot $bot, int $chatId, string $tag): void
    {
        $pref = AvUserPref::firstOrCreate(['bot_id' => $bot->id, 'tg_chat_id' => $chatId]);
        $tags = $pref->fav_tags ?? [];
        $pos  = array_search($tag, $tags);
        if ($pos !== false) {
            array_splice($tags, $pos, 1);
        } else {
            $tags[] = $tag;
        }
        $pref->update(['fav_tags' => array_values($tags)]);
        // 清除用戶偏好快取
        Cache::forget("av_pref_{$bot->id}_{$chatId}");
    }

    private function buildAvTodayReply(TgBot $bot, int $chatId): string
    {
        // D-1：爬蟲當天抓到的通常是昨日發行，以昨日為「今日新片」基準
        $targetDate = now('Asia/Taipei')->subDay()->toDateString();
        $limit      = 5;

        // 讀用戶喜好 tag（從 Redis 快取，miss 才查 DB）
        $prefKey  = "av_pref_{$bot->id}_{$chatId}";
        $favTags  = Cache::remember($prefKey, 600, function () use ($bot, $chatId) {
            $pref = AvUserPref::where('bot_id', $bot->id)
                ->where('tg_chat_id', $chatId)->first();
            return $pref ? ($pref->fav_tags ?? []) : [];
        });

        $hot = collect();

        // ── 1. D-1 × 喜好 tag（OR，符合任一 tag 即可）────────────
        if (!empty($favTags)) {
            $hot = AvVideo::whereDate('release_date', $targetDate)
                ->where(function ($q) use ($favTags) {
                    foreach ($favTags as $tag) {
                        $q->orWhereJsonContains('tags', $tag);
                    }
                })
                ->inRandomOrder()->limit($limit)->get();
        }

        // ── 2. D-1 任意新片（補不足或無喜好）──────────────────────
        if ($hot->count() < $limit) {
            $need    = $limit - $hot->count();
            $exclude = $hot->pluck('code')->toArray();
            $fill    = AvVideo::whereDate('release_date', $targetDate)
                ->when(!empty($exclude), fn($q) => $q->whereNotIn('code', $exclude))
                ->inRandomOrder()->limit($need)->get();
            $hot = $hot->concat($fill);
        }

        // ── 3. 近 3 天最新片（D-1 無資料才用）─────────────────────
        if ($hot->isEmpty()) {
            $hot = AvVideo::where('release_date', '>=', now()->subDays(3)->toDateString())
                ->inRandomOrder()->limit($limit)->get();
        }

        if ($hot->isEmpty()) {
            return '目前暫無新片資料，請稍後再試。';
        }

        // 記錄點擊
        $inserts = [];
        foreach ($hot as $v) {
            $inserts[] = [
                'video_code' => $v->code,
                'tg_chat_id' => (string) $chatId,
                'bot_id'     => $bot->id,
                'clicked_at' => now(),
            ];
        }
        DB::table('av_video_clicks')->insert($inserts);

        $lines = ["🎬 <b>今日新片</b>（" . $targetDate . "）\n"];
        foreach ($hot as $v) {
            $actress = $v->actresses ? implode(' / ', $v->actresses) : '-';
            $tags    = $v->tags ? implode(' ｜ ', $v->tags) : '';
            $lines[] = "📀 <b>{$v->code}</b>";
            if ($v->title) $lines[] = "📝 " . mb_substr($v->title, 0, 50) . (mb_strlen($v->title) > 50 ? '…' : '');
            $lines[] = "👤 {$actress}";
            if ($tags) $lines[] = "🏷 {$tags}";
            if ($v->studio) $lines[] = "🏢 {$v->studio}";
            if ($v->source_url) $lines[] = "🔗 {$v->source_url}";
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
