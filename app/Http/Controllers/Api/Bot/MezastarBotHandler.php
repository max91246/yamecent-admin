<?php

namespace App\Http\Controllers\Api\Bot;

use App\MezastarPokemon;
use App\TgBot;
use App\TgMezastarHand;
use App\Http\Controllers\Api\Bot\Concerns\TelegramHelpers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MezastarBotHandler
{
    use TelegramHelpers;

    const HAND_TTL         = 86400;
    const POKEMON_TTL      = 86400;
    const POKEDEX_PER_PAGE = 10;

    const SERIES_LIST = ['星塵1彈', '星塵2彈', '星塵3彈', '星塵4彈', '銀河1彈'];

    public function handle(TgBot $bot, array $update): \Illuminate\Http\JsonResponse
    {
        if (isset($update['callback_query'])) {
            $cq     = $update['callback_query'];
            $chatId = (int) $cq['message']['chat']['id'];
            $data   = $cq['data'];
            $this->answerCallbackQuery($bot->token, $cq['id']);
            $this->handleCallback($bot, $chatId, $data);
            return response()->json(['ok' => true]);
        }

        $message = $update['message'] ?? null;
        if (!$message) return response()->json(['ok' => true]);

        $chatId = (int) $message['chat']['id'];
        $text   = trim($message['text'] ?? '');

        if (in_array($text, ['/start', '/menu'])) {
            $this->clearState($bot->id, $chatId);
            $this->sendMessage($bot->token, $chatId, "🎮 寶可夢 Mezastar 機台助手", $this->getMainKeyboard());
            return response()->json(['ok' => true]);
        }

        $stateObj = $this->getState($bot->id, $chatId);
        $state    = $stateObj->state ?? null;

        if ($state === 'mezastar_recording') {
            $this->handleRecord($bot, $chatId, $text);
        } elseif ($state === 'mezastar_battling') {
            $this->handleBattle($bot, $chatId, $text);
        } elseif ($state === 'mezastar_pokedex') {
            $this->handlePokedex($bot, $chatId, $text);
        } else {
            match (true) {
                str_contains($text, '記錄寶可夢') => $this->startRecording($bot, $chatId),
                str_contains($text, '對戰寶可夢') => $this->startBattle($bot, $chatId),
                str_contains($text, '我的手牌')   => $this->showHand($bot, $chatId),
                str_contains($text, '寶可夢小幫手') => $this->startPokedex($bot, $chatId),
                default => $this->sendMessage($bot->token, $chatId, "請使用下方選單操作", $this->getMainKeyboard()),
            };
        }

        return response()->json(['ok' => true]);
    }

    // ── callback_query ───────────────────────────────────────────
    private function handleCallback(TgBot $bot, int $chatId, string $data): void
    {
        if ($data === 'mz_menu') {
            $this->clearState($bot->id, $chatId);
            $this->sendMessage($bot->token, $chatId, "🎮 主選單", $this->getMainKeyboard());

        } elseif ($data === 'mz_hand') {
            $this->showHand($bot, $chatId);

        } elseif (str_starts_with($data, 'mz_hand_page_')) {
            $page = (int) substr($data, 13);
            $this->showHand($bot, $chatId, $page);

        } elseif (str_starts_with($data, 'mz_remove_')) {
            $handId = (int) substr($data, 10);
            TgMezastarHand::where('id', $handId)->where('tg_chat_id', $chatId)->delete();
            $this->invalidateHandCache($bot->id, $chatId);
            $this->sendMessage($bot->token, $chatId, "✅ 已移除手卡", $this->getMainKeyboard());

        } elseif (str_starts_with($data, 'mz_record_')) {
            // 用戶從搜尋結果中選擇要記錄的寶可夢
            $pokemonId = (int) substr($data, 10);
            $this->doRecord($bot, $chatId, $pokemonId);

        } elseif (str_starts_with($data, 'mz_battle_')) {
            // 用戶從搜尋結果中選擇要對戰的對手
            $pokemonId = (int) substr($data, 10);
            $this->doBattleResult($bot, $chatId, $pokemonId);

        } elseif (str_starts_with($data, 'mz_pdx_battle_')) {
            $pokemonId = (int) substr($data, 14);
            $this->showPokedexBattleCheck($bot, $chatId, $pokemonId);

        } elseif (str_starts_with($data, 'mz_pokedex_')) {
            $pokemonId = (int) substr($data, 11);
            $this->showPokedexCard($bot, $chatId, $pokemonId);

        } elseif ($data === 'mz_pdx_menu') {
            $this->showPokedexMenu($bot, $chatId);

        } elseif ($data === 'mz_pdx_kw') {
            $this->setState($bot->id, $chatId, 'mezastar_pokedex');
            $this->sendMessage($bot->token, $chatId, "🔍 請輸入寶可夢名稱關鍵字：");

        } elseif (str_starts_with($data, 'mz_pdx_s_')) {
            // mz_pdx_s_{seriesIdx}_{page}
            $parts     = explode('_', substr($data, 9));
            $seriesIdx = (int) ($parts[0] ?? 0);
            $page      = (int) ($parts[1] ?? 0);
            $this->showSeriesList($bot, $chatId, $seriesIdx, $page);

        } elseif ($data === 'mz_noop') {
            // 頁碼顯示按鈕，不做任何事

        } elseif ($data === 'mz_clear_hand') {
            $this->clearHand($bot, $chatId);
        }
    }

    // ── 主選單鍵盤 ───────────────────────────────────────────────
    private function getMainKeyboard(): array
    {
        return [
            'keyboard'          => [
                [['text' => '🃏 記錄寶可夢'], ['text' => '⚔️ 對戰寶可夢']],
                [['text' => '📋 我的手牌'],   ['text' => '🔍 寶可夢小幫手']],
            ],
            'resize_keyboard'   => true,
            'one_time_keyboard' => false,
        ];
    }

    // ── 記錄寶可夢 ───────────────────────────────────────────────
    private function startRecording(TgBot $bot, int $chatId): void
    {
        $this->setState($bot->id, $chatId, 'mezastar_recording');
        $this->sendMessage($bot->token, $chatId, "🃏 請輸入你手上的寶可夢名稱（中文），每次輸入一隻：");
    }

    private function handleRecord(TgBot $bot, int $chatId, string $name): void
    {
        if (in_array($name, ['🃏 記錄寶可夢', '⚔️ 對戰寶可夢', '📋 我的手牌', '🔍 寶可夢小幫手'])) {
            $this->clearState($bot->id, $chatId);
            $this->handleMainText($bot, $chatId, $name);
            return;
        }

        $matches = $this->searchPokemon($name);

        if ($matches->isEmpty()) {
            $this->sendMessage($bot->token, $chatId,
                "❌ 查無「{$name}」相關的寶可夢\n請確認名稱（如：皮卡丘、超夢、噴火龍），或繼續輸入其他名稱："
            );
            return;
        }

        if ($matches->count() === 1) {
            // 唯一結果，直接記錄
            $this->doRecord($bot, $chatId, $matches->first()->id);
            return;
        }

        // 多個結果，讓用戶選擇
        $markup = $this->buildPokemonButtons($matches, 'mz_record_');
        $this->sendMessage($bot->token, $chatId,
            "🔍 找到 {$matches->count()} 隻相關寶可夢，請選擇要記錄的：",
            $markup
        );
    }

    private function doRecord(TgBot $bot, int $chatId, int $pokemonId): void
    {
        $pokemon = MezastarPokemon::find($pokemonId);
        if (!$pokemon) {
            $this->sendMessage($bot->token, $chatId, "❌ 找不到此寶可夢，請重試。");
            return;
        }

        $exists = TgMezastarHand::where('bot_id', $bot->id)
            ->where('tg_chat_id', $chatId)
            ->where('pokemon_id', $pokemonId)
            ->exists();

        if ($exists) {
            $this->sendMessage($bot->token, $chatId,
                "⚠️ 手牌中已有「{$pokemon->name}」（{$pokemon->series}），繼續輸入其他名稱："
            );
            return;
        }

        TgMezastarHand::create([
            'bot_id'     => $bot->id,
            'tg_chat_id' => $chatId,
            'pokemon_id' => $pokemonId,
        ]);
        $this->invalidateHandCache($bot->id, $chatId);

        $typeStr = $pokemon->type2 ? "{$pokemon->type1}/{$pokemon->type2}" : ($pokemon->type1 ?? '?');
        $stars   = $pokemon->grade ? str_repeat('⭐', $pokemon->grade) : '';
        $badges  = $this->formatBadges($pokemon);
        $this->sendMessage($bot->token, $chatId,
            "✅ 已記錄！\n🎴 {$pokemon->name}（{$pokemon->series}）{$badges}\n屬性：{$typeStr}　招式：{$pokemon->move_type}\n星級：{$stars}\n\n繼續輸入下一隻，或點選其他選單："
        );
    }

    // ── 對戰寶可夢 ───────────────────────────────────────────────
    private function startBattle(TgBot $bot, int $chatId): void
    {
        $hand = $this->getHand($bot->id, $chatId);
        if ($hand->isEmpty()) {
            $this->sendMessage($bot->token, $chatId, "⚠️ 手牌是空的！請先用「🃏 記錄寶可夢」記錄你的手卡。", $this->getMainKeyboard());
            return;
        }
        $this->setState($bot->id, $chatId, 'mezastar_battling');
        $this->sendMessage($bot->token, $chatId, "⚔️ 請輸入對手的寶可夢名稱：");
    }

    private function handleBattle(TgBot $bot, int $chatId, string $name): void
    {
        if (in_array($name, ['🃏 記錄寶可夢', '⚔️ 對戰寶可夢', '📋 我的手牌', '🔍 寶可夢小幫手'])) {
            $this->clearState($bot->id, $chatId);
            $this->handleMainText($bot, $chatId, $name);
            return;
        }

        $matches = $this->searchPokemon($name);

        if ($matches->isEmpty()) {
            $this->sendMessage($bot->token, $chatId,
                "❌ 查無「{$name}」相關的寶可夢\n請確認名稱正確後重新輸入："
            );
            return;
        }

        if ($matches->count() === 1) {
            // 唯一結果，直接顯示克制
            $this->clearState($bot->id, $chatId);
            $this->doBattleResult($bot, $chatId, $matches->first()->id);
            return;
        }

        // 多個結果，讓用戶選擇對手
        $markup = $this->buildPokemonButtons($matches, 'mz_battle_');
        $this->sendMessage($bot->token, $chatId,
            "🔍 找到 {$matches->count()} 隻相關寶可夢，請選擇對手：",
            $markup
        );
    }

    private function doBattleResult(TgBot $bot, int $chatId, int $pokemonId): void
    {
        $opponent = MezastarPokemon::find($pokemonId);
        if (!$opponent) {
            $this->sendMessage($bot->token, $chatId, "❌ 找不到此寶可夢，請重試。", $this->getMainKeyboard());
            return;
        }

        $weaknesses   = $opponent->weakness ?? [];
        $hand         = $this->getHand($bot->id, $chatId);
        $counters     = $hand->filter(fn($h) => !empty($weaknesses) && in_array($h->pokemon->move_type, $weaknesses));
        $opponentType = $opponent->type2 ? "{$opponent->type1}/{$opponent->type2}" : ($opponent->type1 ?? '?');
        $weakStr      = empty($weaknesses) ? '（資料待補）' : implode('、', $weaknesses);

        if ($counters->isEmpty()) {
            $reply  = "⚔️ 對手：<b>{$opponent->name}</b>（{$opponentType}）\n";
            $reply .= "弱點：{$weakStr}\n\n";
            $reply .= "😢 手牌中沒有能克制對方的寶可夢！";
        } else {
            $reply  = "⚔️ 對手：<b>{$opponent->name}</b>（{$opponentType}）\n";
            $reply .= "弱點：{$weakStr}\n\n";
            $reply .= "✅ 你的手牌剋制：\n";
            foreach ($counters as $h) {
                $p      = $h->pokemon;
                $type   = $p->type2 ? "{$p->type1}/{$p->type2}" : ($p->type1 ?? '?');
                $stars  = $p->grade ? str_repeat('⭐', $p->grade) : '';
                $badges = $this->formatBadges($p);
                $reply .= "  🎴 <b>{$p->name}</b>{$badges}（{$p->series}）{$type} 招式:{$p->move_type} {$stars}\n";
                // 卡號 + 寶可能量
                $line2 = "   📌 {$p->card_no}";
                if ($p->power !== null) $line2 .= "　⚡<b>{$p->power}</b>";
                $reply .= $line2 . "\n";
                // 六項能力值
                if ($p->hp !== null) {
                    $reply .= "   ❤️{$p->hp} ⚔️{$p->attack} 🛡️{$p->defense} ✨{$p->sp_attack} 🔰{$p->sp_defense} 💨{$p->speed}\n";
                }
            }
        }

        $this->clearState($bot->id, $chatId);
        $this->sendMessage($bot->token, $chatId, $reply, $this->getMainKeyboard(), 'HTML');
    }

    // ── 顯示手牌（分頁）────────────────────────────────────────────
    private function showHand(TgBot $bot, int $chatId, int $page = 1): void
    {
        $hand = $this->getHand($bot->id, $chatId);

        if ($hand->isEmpty()) {
            $this->sendMessage($bot->token, $chatId, "📋 手牌是空的，請先記錄寶可夢。", $this->getMainKeyboard());
            return;
        }

        // 依星級↓ 寶可能量↓ 排序
        $sorted = $hand->sortBy([
            fn($a, $b) => ($b->pokemon->grade ?? 0) <=> ($a->pokemon->grade ?? 0),
            fn($a, $b) => ($b->pokemon->power ?? 0) <=> ($a->pokemon->power ?? 0),
        ])->values();

        $perPage   = 5;
        $total     = $sorted->count();
        $totalPage = (int) ceil($total / $perPage);
        $page      = max(1, min($page, $totalPage));
        $slice     = $sorted->slice(($page - 1) * $perPage, $perPage);

        $text = "📋 <b>我的手牌</b>（{$total} 隻）　第 {$page}/{$totalPage} 頁\n\n";
        foreach ($slice as $h) {
            $p      = $h->pokemon;
            $type   = $p->type2 ? "{$p->type1}/{$p->type2}" : ($p->type1 ?? '?');
            $stars  = $p->grade ? str_repeat('⭐', $p->grade) : '';
            $badges = $this->formatBadges($p);
            $text .= "🎴 <b>{$p->name}</b>（{$p->series}）{$badges}\n";
            $text .= "   屬性:{$type}　招式:{$p->move_type}　{$stars}\n";
            $line3 = "   📌 {$p->card_no}";
            if ($p->power !== null) $line3 .= "　⚡<b>{$p->power}</b>";
            $text .= $line3 . "\n";
            if ($p->hp !== null) {
                $text .= "   ❤️{$p->hp} ⚔️{$p->attack} 🛡️{$p->defense} ✨{$p->sp_attack} 🔰{$p->sp_defense} 💨{$p->speed}\n";
            }
            $text .= "\n";
        }

        // 分頁按鈕
        $navRow = [];
        if ($page > 1)          $navRow[] = ['text' => '⬅️ 上一頁', 'callback_data' => "mz_hand_page_" . ($page - 1)];
        if ($page < $totalPage) $navRow[] = ['text' => '下一頁 ➡️', 'callback_data' => "mz_hand_page_" . ($page + 1)];

        $keyboard = [];
        if (!empty($navRow)) $keyboard[] = $navRow;
        $keyboard[] = [['text' => '🗑️ 清空手卡', 'callback_data' => 'mz_clear_hand']];

        $this->sendMessage($bot->token, $chatId, $text, ['inline_keyboard' => $keyboard], 'HTML');
    }

    // ── 清空手牌 ─────────────────────────────────────────────────
    private function clearHand(TgBot $bot, int $chatId): void
    {
        TgMezastarHand::where('bot_id', $bot->id)->where('tg_chat_id', $chatId)->delete();
        $this->invalidateHandCache($bot->id, $chatId);
        $this->sendMessage($bot->token, $chatId, "🗑️ 手牌已清空！", $this->getMainKeyboard());
    }

    // ── 寶可夢小幫手 ─────────────────────────────────────────────
    private function startPokedex(TgBot $bot, int $chatId): void
    {
        $this->showPokedexMenu($bot, $chatId);
    }

    private function showPokedexMenu(TgBot $bot, int $chatId): void
    {
        $seriesButtons = [];
        foreach (self::SERIES_LIST as $idx => $name) {
            $seriesButtons[] = ['text' => $name, 'callback_data' => "mz_pdx_s_{$idx}_0"];
        }

        // 每列 2 個彈數按鈕
        $rows = [];
        foreach (array_chunk($seriesButtons, 2) as $chunk) {
            $rows[] = $chunk;
        }
        $rows[] = [['text' => '🔍 關鍵名稱查詢', 'callback_data' => 'mz_pdx_kw']];

        $this->sendMessage($bot->token, $chatId,
            "🔍 寶可夢小幫手\n請選擇查詢方式：",
            ['inline_keyboard' => $rows]
        );
    }

    private function showSeriesList(TgBot $bot, int $chatId, int $seriesIdx, int $page): void
    {
        $series = self::SERIES_LIST[$seriesIdx] ?? null;
        if ($series === null) return;

        $all      = Cache::remember('mezastar_pokemon:all_list', self::POKEMON_TTL, fn() => MezastarPokemon::all());
        $filtered = $all->where('series', $series)->values();
        $total    = $filtered->count();
        $perPage  = self::POKEDEX_PER_PAGE;
        $totalPages = (int) ceil($total / $perPage) ?: 1;
        $page       = max(0, min($page, $totalPages - 1));
        $items      = $filtered->slice($page * $perPage, $perPage)->values();

        $buttons = [];
        foreach ($items as $p) {
            $stars  = $p->grade ? str_repeat('★', $p->grade) : '';
            $badges = $this->formatBadgesShort($p);
            $label  = "{$p->card_no} {$p->name}{$badges} {$stars}";
            $buttons[] = [['text' => $label, 'callback_data' => 'mz_pokedex_' . $p->id]];
        }

        // 分頁列
        $navRow = [];
        if ($page > 0) {
            $navRow[] = ['text' => '⬅️ 上頁', 'callback_data' => "mz_pdx_s_{$seriesIdx}_" . ($page - 1)];
        }
        $navRow[] = ['text' => ($page + 1) . '/' . $totalPages, 'callback_data' => 'mz_noop'];
        if ($page < $totalPages - 1) {
            $navRow[] = ['text' => '下頁 ➡️', 'callback_data' => "mz_pdx_s_{$seriesIdx}_" . ($page + 1)];
        }
        $buttons[] = $navRow;
        $buttons[] = [['text' => '🔙 返回', 'callback_data' => 'mz_pdx_menu']];

        $this->sendMessage($bot->token, $chatId,
            "📦 {$series}（第 " . ($page + 1) . " / {$totalPages} 頁，共 {$total} 隻）\n點選寶可夢查看詳細資料：",
            ['inline_keyboard' => $buttons]
        );
    }

    private function handlePokedex(TgBot $bot, int $chatId, string $keyword): void
    {
        if (in_array($keyword, ['🃏 記錄寶可夢', '⚔️ 對戰寶可夢', '📋 我的手牌', '🔍 寶可夢小幫手'])) {
            $this->clearState($bot->id, $chatId);
            $this->handleMainText($bot, $chatId, $keyword);
            return;
        }

        $matches = $this->searchPokemon($keyword);

        if ($matches->isEmpty()) {
            $this->sendMessage($bot->token, $chatId,
                "❌ 查無「{$keyword}」相關的寶可夢\n請重新輸入關鍵字："
            );
            return;
        }

        $this->clearState($bot->id, $chatId);

        if ($matches->count() === 1) {
            $this->showPokedexCard($bot, $chatId, $matches->first()->id);
            return;
        }

        $markup = $this->buildPokemonButtons($matches, 'mz_pokedex_');
        $this->sendMessage($bot->token, $chatId,
            "🔍 找到 {$matches->count()} 隻相關寶可夢，請選擇：",
            $markup
        );
    }

    private function showPokedexCard(TgBot $bot, int $chatId, int $pokemonId): void
    {
        $p = MezastarPokemon::find($pokemonId);
        if (!$p) {
            $this->sendMessage($bot->token, $chatId, "❌ 找不到此寶可夢。", $this->getMainKeyboard());
            return;
        }

        $type     = $p->type2 ? "{$p->type1}/{$p->type2}" : ($p->type1 ?? '未知');
        $stars    = $p->grade ? str_repeat('⭐', $p->grade) : '（未知）';
        $badges   = $this->formatBadges($p);
        $weakness = !empty($p->weakness) ? implode('、', $p->weakness) : '（資料待補）';

        $caption  = "🎴 <b>{$p->name}</b>{$badges}\n";
        $caption .= "📦 {$p->series}　卡號：{$p->card_no}\n";
        if ($p->power !== null) {
            $caption .= "⚡ 寶可能量：<b>{$p->power}</b>\n";
        }
        $caption .= "🔷 屬性：{$type}\n";
        $caption .= "🥊 招式屬性：{$p->move_type}\n";
        $caption .= "💥 弱點：{$weakness}\n";
        $caption .= "⭐ 星級：{$stars}";

        if ($p->hp !== null) {
            $caption .= "\n\n📊 <b>能力值</b>\n";
            $caption .= "❤️ HP <b>{$p->hp}</b>\n";
            $caption .= "⚔️ 攻擊 <b>{$p->attack}</b>　🛡️ 防禦 <b>{$p->defense}</b>\n";
            $caption .= "✨ 特攻 <b>{$p->sp_attack}</b>　🔰 特防 <b>{$p->sp_defense}</b>\n";
            $caption .= "💨 速度 <b>{$p->speed}</b>";
        }

        $inlineMarkup = [
            'inline_keyboard' => [
                [['text' => '⚔️ 對戰分析（比對手牌）', 'callback_data' => "mz_pdx_battle_{$pokemonId}"]],
                [['text' => '🔙 回選單', 'callback_data' => 'mz_pdx_menu']],
            ],
        ];

        if ($p->image_url) {
            $this->sendPhoto($bot->token, $chatId, $p->image_url, $caption, $inlineMarkup);
        } else {
            $this->sendMessage($bot->token, $chatId, $caption . "\n\n（無卡牌圖片）", $inlineMarkup, 'HTML');
        }
    }

    private function showPokedexBattleCheck(TgBot $bot, int $chatId, int $pokemonId): void
    {
        $opponent = MezastarPokemon::find($pokemonId);
        if (!$opponent) {
            return;
        }

        $weaknesses   = $opponent->weakness ?? [];
        $hand         = $this->getHand($bot->id, $chatId);
        $counters     = $hand->filter(fn($h) => !empty($weaknesses) && in_array($h->pokemon->move_type, $weaknesses));
        $opponentType = $opponent->type2 ? "{$opponent->type1}/{$opponent->type2}" : ($opponent->type1 ?? '?');
        $weakStr      = empty($weaknesses) ? '（資料待補）' : implode('、', $weaknesses);

        $reply  = "⚔️ 對手：<b>{$opponent->name}</b>（{$opponentType}）\n";
        $reply .= "弱點：{$weakStr}\n\n";

        if ($hand->isEmpty()) {
            $reply .= "📋 手牌是空的，請先記錄寶可夢！";
        } elseif ($counters->isEmpty()) {
            $reply .= "😢 手牌中沒有能克制對方的寶可夢！";
        } else {
            $reply .= "✅ 你的手牌剋制：\n";
            foreach ($counters as $h) {
                $p      = $h->pokemon;
                $type   = $p->type2 ? "{$p->type1}/{$p->type2}" : ($p->type1 ?? '?');
                $stars  = $p->grade ? str_repeat('⭐', $p->grade) : '';
                $badges = $this->formatBadges($p);
                $reply .= "  🎴 <b>{$p->name}</b>{$badges}（{$p->series}）{$type} 招式:{$p->move_type} {$stars}\n";
                $line2 = "   📌 {$p->card_no}";
                if ($p->power !== null) $line2 .= "　⚡<b>{$p->power}</b>";
                $reply .= $line2 . "\n";
                if ($p->hp !== null) {
                    $reply .= "   ❤️{$p->hp} ⚔️{$p->attack} 🛡️{$p->defense} ✨{$p->sp_attack} 🔰{$p->sp_defense} 💨{$p->speed}\n";
                }
            }
        }

        $backMarkup = [
            'inline_keyboard' => [
                [['text' => '🔙 回寶可夢資料', 'callback_data' => "mz_pokedex_{$pokemonId}"]],
            ],
        ];
        $this->sendMessage($bot->token, $chatId, $reply, $backMarkup, 'HTML');
    }

    private function sendPhoto(string $token, int $chatId, string $photoUrl, string $caption, ?array $replyMarkup = null): void
    {
        $url = "https://api.telegram.org/bot{$token}/sendPhoto";
        $client = new \GuzzleHttp\Client(['timeout' => 10]);
        $payload = [
            'chat_id'    => $chatId,
            'photo'      => $photoUrl,
            'caption'    => $caption,
            'parse_mode' => 'HTML',
        ];
        if ($replyMarkup) {
            $payload['reply_markup'] = $replyMarkup;
        }
        try {
            $client->post($url, ['json' => $payload]);
        } catch (\Exception $e) {
            // 圖片失敗時改送純文字
            $this->sendMessage($token, $chatId, $caption, $replyMarkup ?? $this->getMainKeyboard(), 'HTML');
        }
    }

    // ── helpers ──────────────────────────────────────────────────

    private function handleMainText(TgBot $bot, int $chatId, string $text): void
    {
        match (true) {
            str_contains($text, '記錄寶可夢')  => $this->startRecording($bot, $chatId),
            str_contains($text, '對戰寶可夢')  => $this->startBattle($bot, $chatId),
            str_contains($text, '我的手牌')    => $this->showHand($bot, $chatId),
            str_contains($text, '寶可夢小幫手') => $this->startPokedex($bot, $chatId),
            default                            => null,
        };
    }

    /**
     * 模糊搜尋寶可夢（回傳全部符合的，完全符合排最前）
     * 例：搜「超夢」→「超夢（星塵1彈）」+「超夢 (極巨化)（銀河1彈）」都列出
     */
    private function searchPokemon(string $keyword): Collection
    {
        $all = Cache::remember('mezastar_pokemon:all_list', self::POKEMON_TTL, function () {
            return MezastarPokemon::all();
        });

        // 找所有名稱含關鍵字的記錄（含完全符合）
        $matched = $all->filter(fn($p) =>
            str_contains($p->name, $keyword) || str_contains($keyword, $p->name)
        );

        // 完全符合排前面，其餘依原順序
        $sorted = $matched->sortBy(fn($p) => $p->name === $keyword ? 0 : 1);

        return $sorted->values()->take(8);
    }

    /**
     * 建立 inline keyboard 讓用戶從搜尋結果中選擇
     * $prefix: 'mz_record_' 或 'mz_battle_'
     */
    private function buildPokemonButtons(Collection $matches, string $prefix): array
    {
        $buttons = [];
        $row     = [];

        foreach ($matches as $i => $p) {
            $stars = $p->grade ? str_repeat('★', $p->grade) : '';
            $label = "{$p->name}（{$p->series} {$stars}）";
            $row[] = ['text' => $label, 'callback_data' => $prefix . $p->id];

            // 每行 1 個按鈕（名稱較長，避免截斷）
            $buttons[] = $row;
            $row = [];
        }

        return ['inline_keyboard' => $buttons];
    }

    private function getHand(int $botId, int $chatId)
    {
        $key = "mezastar_hand:{$botId}:{$chatId}";
        return Cache::remember($key, self::HAND_TTL, function () use ($botId, $chatId) {
            return TgMezastarHand::with('pokemon')
                ->where('bot_id', $botId)
                ->where('tg_chat_id', $chatId)
                ->get();
        });
    }

    private function invalidateHandCache(int $botId, int $chatId): void
    {
        Cache::forget("mezastar_hand:{$botId}:{$chatId}");
        Cache::forget('mezastar_pokemon:all_list');
    }

    /** 產生形態徽章字串（完整版） */
    private function formatBadges(MezastarPokemon $p): string
    {
        $badges = [];
        if ($p->is_mega)              $badges[] = '[超級進化]';
        if ($p->is_gigantamax)        $badges[] = '[極巨化]';
        if ($p->is_ultra_gigantamax)  $badges[] = '[超極巨化]';
        if ($p->is_dual_move)         $badges[] = '[雙重招式]';
        if ($p->is_z_move)            $badges[] = '[Z招式]';
        if ($p->is_mythical)          $badges[] = '[幻]';
        return $badges ? ' ' . implode('', $badges) : '';
    }

    /** 產生形態徽章字串（按鈕用簡短版） */
    private function formatBadgesShort(MezastarPokemon $p): string
    {
        $badges = [];
        if ($p->is_mega)             $badges[] = '超進';
        if ($p->is_gigantamax)       $badges[] = '極巨';
        if ($p->is_ultra_gigantamax) $badges[] = '超極';
        if ($p->is_dual_move)        $badges[] = '雙招';
        if ($p->is_z_move)           $badges[] = 'Z招';
        return $badges ? '[' . implode('/', $badges) . ']' : '';
    }
}
