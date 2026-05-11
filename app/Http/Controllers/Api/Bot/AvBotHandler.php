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
            } elseif (str_starts_with($data, 'avbt_')) {
                // tag搜片：選中某 tag，顯示第 1 頁
                $tag = substr($data, 5);
                [$text, $markup] = $this->buildTagVideoList($tag, 1);
                $this->sendMessage($bot->token, $chatId, $text, $markup, 'HTML');
            } elseif (str_starts_with($data, 'avbp_')) {
                // tag搜片：翻頁，格式 avbp_{page}_{tag}
                $parts = explode('_', substr($data, 5), 2);
                $page  = (int) ($parts[0] ?? 1);
                $tag   = $parts[1] ?? '';
                [$text, $markup] = $this->buildTagVideoList($tag, $page);
                $this->sendMessage($bot->token, $chatId, $text, $markup, 'HTML');
            } elseif ($data === 'avba_menu') {
                [$text, $markup] = $this->buildActressBrowseMenu();
                $this->sendMessage($bot->token, $chatId, $text, $markup);
            } elseif ($data === 'avba_search') {
                $this->setState($bot->id, $chatId, 'av_actress_search');
                $this->sendMessage($bot->token, $chatId, "🔍 請輸入女優姓名關鍵字：");
            } elseif (str_starts_with($data, 'avbap_')) {
                // 女優搜片：翻頁，格式 avbap_{page}_{name}（要在 avba_ 之前判斷）
                $parts = explode('_', substr($data, 6), 2);
                $page  = (int) ($parts[0] ?? 1);
                $name  = $parts[1] ?? '';
                [$text, $markup] = $this->buildActressVideoList($name, $page);
                $this->sendMessage($bot->token, $chatId, $text, $markup, 'HTML');
            } elseif (str_starts_with($data, 'avba_')) {
                // 女優搜片：選中某女優，顯示第 1 頁
                $name = substr($data, 5);
                [$text, $markup] = $this->buildActressVideoList($name, 1);
                $this->sendMessage($bot->token, $chatId, $text, $markup, 'HTML');
            } elseif ($data === 'avb_menu') {
                [$text, $markup] = $this->buildTagBrowseMenu($chatId);
                $this->sendMessage($bot->token, $chatId, $text, $markup);
            } elseif ($data === 'avb_search') {
                $this->setState($bot->id, $chatId, 'av_browse_search');
                $this->sendMessage($bot->token, $chatId, "🔍 請輸入標籤關鍵字：");
            } elseif ($data === 'av_tag_search') {
                $this->setState($bot->id, $chatId, 'av_search_tag');
                $this->sendMessage($bot->token, $chatId, "🔍 請輸入標籤關鍵字（例：顏射、素人）：");
            } elseif ($data === 'av_search_back') {
                $this->clearState($bot->id, $chatId);
                [$text, $markup] = $this->buildAvTagMenu($bot, $chatId, $username);
                $this->sendMessage($bot->token, $chatId, $text, $markup);
            } elseif (str_starts_with($data, 'av_tag_')) {
                $tag = substr($data, 7);
                $this->avToggleTag($bot, $chatId, $tag);
                $stateObj = $this->getState($bot->id, $chatId);
                // 搜尋結果頁點選後回到主選單
                if ($stateObj && $stateObj->state === 'av_search_tag') {
                    $this->clearState($bot->id, $chatId);
                }
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
            $this->clearState($bot->id, $chatId);
            [$menuText, $markup] = $this->buildAvTagMenu($bot, $chatId, $username);
            $this->sendMessage($bot->token, $chatId, $menuText, $markup);
            $this->logMessage($bot->id, $userId, $username, $chatId, $menuText, 2, 'reply');
            return response()->json(['ok' => true]);
        }

        if ($text === '👤 女優搜片') {
            $this->clearState($bot->id, $chatId);
            [$menuText, $markup] = $this->buildActressBrowseMenu();
            $this->sendMessage($bot->token, $chatId, $menuText, $markup);
            $this->logMessage($bot->id, $userId, $username, $chatId, $menuText, 2, 'reply');
            return response()->json(['ok' => true]);
        }

        if ($text === '🏷 tag搜片') {
            $this->clearState($bot->id, $chatId);
            [$menuText, $markup] = $this->buildTagBrowseMenu($chatId);
            $this->sendMessage($bot->token, $chatId, $menuText, $markup);
            $this->logMessage($bot->id, $userId, $username, $chatId, $menuText, 2, 'reply');
            return response()->json(['ok' => true]);
        }

        // 搜尋標籤 state：用戶輸入關鍵字，從 DB 找出匹配 tag
        $stateObj = $this->getState($bot->id, $chatId);
        if ($stateObj && $stateObj->state === 'av_actress_search' && $text) {
            [$reply, $markup] = $this->buildActressSearchResult($text);
            $this->sendMessage($bot->token, $chatId, $reply, $markup);
            $this->logMessage($bot->id, $userId, $username, $chatId, $reply, 2, 'reply');
            return response()->json(['ok' => true]);
        }
        if ($stateObj && $stateObj->state === 'av_browse_search' && $text) {
            [$reply, $markup] = $this->buildTagSearchResult($bot, $chatId, $text, 'browse');
            $this->sendMessage($bot->token, $chatId, $reply, $markup);
            $this->logMessage($bot->id, $userId, $username, $chatId, $reply, 2, 'reply');
            return response()->json(['ok' => true]);
        }
        if ($stateObj && $stateObj->state === 'av_search_tag' && $text) {
            [$reply, $markup] = $this->buildTagSearchResult($bot, $chatId, $text);
            $this->sendMessage($bot->token, $chatId, $reply, $markup);
            $this->logMessage($bot->id, $userId, $username, $chatId, $reply, 2, 'reply');
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
                [['text' => '🏷 tag搜片'],  ['text' => '👤 女優搜片']],
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
            ['text' => '🔍 搜尋更多標籤', 'callback_data' => 'av_tag_search'],
            ['text' => '💾 儲存設定',      'callback_data' => 'av_tag_save'],
        ];

        return [$text, ['inline_keyboard' => $rows]];
    }

    private function buildActressBrowseMenu(): array
    {
        // 依片子數量排名前 40 女優
        $topActresses = \App\AvActress::withCount('videos')
            ->orderByDesc('videos_count')
            ->limit(40)
            ->pluck('name')
            ->toArray();

        $text = "👤 <b>女優搜片</b>\n選擇女優查看對應影片：";
        $rows = [];
        foreach (array_chunk($topActresses, 3) as $row) {
            $btns = [];
            foreach ($row as $name) {
                $btns[] = ['text' => $name, 'callback_data' => 'avba_' . $name];
            }
            $rows[] = $btns;
        }
        $rows[] = [['text' => '🔍 搜尋更多女優', 'callback_data' => 'avba_search']];
        return [$text, ['inline_keyboard' => $rows]];
    }

    private function buildActressVideoList(string $name, int $page): array
    {
        $perPage  = 5;
        $maxPages = 5;
        $offset   = ($page - 1) * $perPage;

        $total  = AvVideo::where('actresses', 'like', "%{$name}%")->count();
        $videos = AvVideo::where('actresses', 'like', "%{$name}%")
            ->orderBy('release_date', 'desc')
            ->orderBy('id', 'desc')
            ->skip($offset)
            ->take($perPage)
            ->get();

        if ($videos->isEmpty()) {
            return ["👤 <b>{$name}</b>\n\n暫無相關影片。", ['inline_keyboard' => [[['text' => '⬅️ 返回', 'callback_data' => 'avba_menu']]]]];
        }

        $totalPages = min($maxPages, (int) ceil($total / $perPage));
        $lines = ["👤 <b>{$name}</b>（第 {$page}/{$totalPages} 頁，共 {$total} 部）\n"];

        foreach ($videos as $v) {
            $lines[] = "📀 <b>{$v->code}</b>";
            if ($v->title) $lines[] = "📝 " . mb_substr($v->title, 0, 40) . (mb_strlen($v->title) > 40 ? '…' : '');
            if ($v->release_date) $lines[] = "📅 " . $v->release_date->format('Y-m-d');
            if ($v->source_url)   $lines[] = "🔗 {$v->source_url}";
            $lines[] = '';
        }

        $prevPage = $page - 1;
        $nextPage = $page + 1;
        $navBtns  = [];
        if ($page > 1)           $navBtns[] = ['text' => '⬅️ 上一頁', 'callback_data' => "avbap_{$prevPage}_{$name}"];
        if ($page < $totalPages) $navBtns[] = ['text' => '下一頁 ➡️', 'callback_data' => "avbap_{$nextPage}_{$name}"];

        $rows = [];
        if (!empty($navBtns)) $rows[] = $navBtns;
        $rows[] = [['text' => '⬅️ 返回女優選單', 'callback_data' => 'avba_menu']];

        return [implode("\n", $lines), ['inline_keyboard' => $rows]];
    }

    private function buildActressSearchResult(string $keyword): array
    {
        $matched = \App\AvActress::where('name', 'like', "%{$keyword}%")
            ->withCount('videos')
            ->orderByDesc('videos_count')
            ->limit(15)
            ->pluck('name')
            ->toArray();

        if (empty($matched)) {
            return [
                "🔍 找不到含「{$keyword}」的女優，請換個關鍵字：",
                ['inline_keyboard' => [[['text' => '⬅️ 返回', 'callback_data' => 'avba_menu']]]],
            ];
        }

        $rows = [];
        foreach (array_chunk($matched, 3) as $row) {
            $btns = [];
            foreach ($row as $name) {
                $btns[] = ['text' => $name, 'callback_data' => 'avba_' . $name];
            }
            $rows[] = $btns;
        }
        $rows[] = [['text' => '⬅️ 返回', 'callback_data' => 'avba_menu']];

        return ["🔍 含「{$keyword}」的女優（共 " . count($matched) . " 位）：", ['inline_keyboard' => $rows]];
    }

    private function buildTagBrowseMenu(int $chatId): array
    {
        $text = "🏷 <b>tag搜片</b>\n選擇標籤查看對應影片：";
        $rows = [];
        foreach (array_chunk($this->getAvTags(), 3) as $row) {
            $btns = [];
            foreach ($row as $tag) {
                $btns[] = ['text' => $tag, 'callback_data' => 'avbt_' . $tag];
            }
            $rows[] = $btns;
        }
        $rows[] = [['text' => '🔍 搜尋更多標籤', 'callback_data' => 'avb_search']];
        return [$text, ['inline_keyboard' => $rows]];
    }

    private function buildTagVideoList(string $tag, int $page): array
    {
        $perPage = 5;
        $offset  = ($page - 1) * $perPage;

        $maxPages = 5;
        $total    = AvVideo::whereJsonContains('tags', $tag)->count();
        $videos   = AvVideo::whereJsonContains('tags', $tag)
            ->orderBy('release_date', 'desc')
            ->orderBy('id', 'desc')
            ->skip($offset)
            ->take($perPage)
            ->get();

        if ($videos->isEmpty()) {
            return ["🏷 <b>{$tag}</b>\n\n暫無相關影片。", ['inline_keyboard' => [[['text' => '⬅️ 返回', 'callback_data' => 'avb_menu']]]]];
        }

        $totalPages = min($maxPages, (int) ceil($total / $perPage));
        $lines = ["🏷 <b>{$tag}</b>（第 {$page}/{$totalPages} 頁，共 {$total} 部）\n"];

        foreach ($videos as $v) {
            $actress = $v->actresses ? implode(' / ', $v->actresses) : '-';
            $lines[] = "📀 <b>{$v->code}</b>";
            if ($v->title) $lines[] = "📝 " . mb_substr($v->title, 0, 40) . (mb_strlen($v->title) > 40 ? '…' : '');
            $lines[] = "👤 {$actress}";
            if ($v->release_date) $lines[] = "📅 " . $v->release_date->format('Y-m-d');
            if ($v->source_url)   $lines[] = "🔗 {$v->source_url}";
            $lines[] = '';
        }

        // 分頁按鈕
        $prevPage = $page - 1;
        $nextPage = $page + 1;
        $navBtns  = [];
        if ($page > 1)           $navBtns[] = ['text' => '⬅️ 上一頁', 'callback_data' => "avbp_{$prevPage}_{$tag}"];
        if ($page < $totalPages) $navBtns[] = ['text' => '下一頁 ➡️', 'callback_data' => "avbp_{$nextPage}_{$tag}"];

        $rows = [];
        if (!empty($navBtns)) $rows[] = $navBtns;
        $rows[] = [['text' => '⬅️ 返回標籤選單', 'callback_data' => 'avb_menu']];

        return [implode("\n", $lines), ['inline_keyboard' => $rows]];
    }

    private function buildTagSearchResult(TgBot $bot, int $chatId, string $keyword, string $mode = 'pref'): array
    {
        // 從所有影片 tags 中找含關鍵字的，取前 15 個
        $allTags = Cache::remember('av_all_tags', 3600, function () {
            $rows  = AvVideo::whereNotNull('tags')->pluck('tags');
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
            return array_keys($count);
        });

        $matched = array_values(array_filter(
            $allTags,
            fn($tag) => mb_strpos($tag, $keyword) !== false
        ));

        if (empty($matched)) {
            return [
                "🔍 找不到含「{$keyword}」的標籤，請換個關鍵字：",
                ['inline_keyboard' => [[['text' => '⬅️ 返回設定', 'callback_data' => 'av_search_back']]]],
            ];
        }

        $pref     = AvUserPref::firstOrCreate(['bot_id' => $bot->id, 'tg_chat_id' => $chatId]);
        $selected = $pref->fav_tags ?? [];

        $rows = [];
        foreach (array_chunk(array_slice($matched, 0, 15), 3) as $row) {
            $btns = [];
            foreach ($row as $tag) {
                if ($mode === 'browse') {
                    $btns[] = ['text' => $tag, 'callback_data' => 'avbt_' . $tag];
                } else {
                    $active = in_array($tag, $selected);
                    $btns[] = ['text' => ($active ? '✅ ' : '') . $tag, 'callback_data' => 'av_tag_' . $tag];
                }
            }
            $rows[] = $btns;
        }
        $backCallback = $mode === 'browse' ? 'avb_menu' : 'av_search_back';
        $rows[] = [['text' => '⬅️ 返回', 'callback_data' => $backCallback]];

        $count = count($matched);
        $hint  = $count > 15 ? "（顯示前 15 筆，共 {$count} 筆）" : "（共 {$count} 筆）";

        return [
            "🔍 含「{$keyword}」的標籤 {$hint}\n點選加入喜好：",
            ['inline_keyboard' => $rows],
        ];
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

        // ── 2. D-1 任意新片（只在無喜好設定時補齊）────────────────
        // 有喜好 tag 的用戶不補非相關影片，避免推送不符合偏好的內容
        if (empty($favTags) && $hot->count() < $limit) {
            $need    = $limit - $hot->count();
            $exclude = $hot->pluck('code')->toArray();
            $fill    = AvVideo::whereDate('release_date', $targetDate)
                ->when(!empty($exclude), fn($q) => $q->whereNotIn('code', $exclude))
                ->inRandomOrder()->limit($need)->get();
            $hot = $hot->concat($fill);
        }

        // ── 3. 近 3 天最新片（D-1 無資料才用，同樣依喜好篩選）──────
        if ($hot->isEmpty()) {
            $query = AvVideo::where('release_date', '>=', now()->subDays(3)->toDateString());
            if (!empty($favTags)) {
                $query->where(function ($q) use ($favTags) {
                    foreach ($favTags as $tag) {
                        $q->orWhereJsonContains('tags', $tag);
                    }
                });
            }
            $hot = $query->inRandomOrder()->limit($limit)->get();
        }

        // ── 4. 真的完全沒資料才顯示任意近期影片 ──────────────────
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
