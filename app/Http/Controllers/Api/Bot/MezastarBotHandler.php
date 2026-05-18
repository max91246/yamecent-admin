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

    const HAND_TTL    = 86400;
    const POKEMON_TTL = 86400;

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
        } else {
            match (true) {
                str_contains($text, '記錄寶可夢') => $this->startRecording($bot, $chatId),
                str_contains($text, '對戰寶可夢') => $this->startBattle($bot, $chatId),
                str_contains($text, '清空手卡')   => $this->clearHand($bot, $chatId),
                str_contains($text, '我的手牌')   => $this->showHand($bot, $chatId),
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
        }
    }

    // ── 主選單鍵盤 ───────────────────────────────────────────────
    private function getMainKeyboard(): array
    {
        return [
            'keyboard'          => [
                [['text' => '🃏 記錄寶可夢'], ['text' => '⚔️ 對戰寶可夢']],
                [['text' => '📋 我的手牌'],   ['text' => '🗑️ 清空手卡']],
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
        if (in_array($name, ['🃏 記錄寶可夢', '⚔️ 對戰寶可夢', '📋 我的手牌', '🗑️ 清空手卡'])) {
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
        if (in_array($name, ['🃏 記錄寶可夢', '⚔️ 對戰寶可夢', '📋 我的手牌', '🗑️ 清空手卡'])) {
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
            }
        }

        $this->clearState($bot->id, $chatId);
        $this->sendMessage($bot->token, $chatId, $reply, $this->getMainKeyboard(), 'HTML');
    }

    // ── 顯示手牌 ─────────────────────────────────────────────────
    private function showHand(TgBot $bot, int $chatId): void
    {
        $hand = $this->getHand($bot->id, $chatId);

        if ($hand->isEmpty()) {
            $this->sendMessage($bot->token, $chatId, "📋 手牌是空的，請先記錄寶可夢。", $this->getMainKeyboard());
            return;
        }

        $text = "📋 <b>我的手牌</b>（{$hand->count()} 隻）\n\n";
        foreach ($hand as $h) {
            $p      = $h->pokemon;
            $type   = $p->type2 ? "{$p->type1}/{$p->type2}" : ($p->type1 ?? '?');
            $stars  = $p->grade ? str_repeat('⭐', $p->grade) : '';
            $badges = $this->formatBadges($p);
            $text .= "🎴 <b>{$p->name}</b>（{$p->series}）{$badges}\n";
            $text .= "   屬性:{$type}　招式:{$p->move_type}　{$stars}\n";
        }

        $this->sendMessage($bot->token, $chatId, $text, $this->getMainKeyboard(), 'HTML');
    }

    // ── 清空手牌 ─────────────────────────────────────────────────
    private function clearHand(TgBot $bot, int $chatId): void
    {
        TgMezastarHand::where('bot_id', $bot->id)->where('tg_chat_id', $chatId)->delete();
        $this->invalidateHandCache($bot->id, $chatId);
        $this->sendMessage($bot->token, $chatId, "🗑️ 手牌已清空！", $this->getMainKeyboard());
    }

    // ── helpers ──────────────────────────────────────────────────

    private function handleMainText(TgBot $bot, int $chatId, string $text): void
    {
        match (true) {
            str_contains($text, '記錄寶可夢') => $this->startRecording($bot, $chatId),
            str_contains($text, '對戰寶可夢') => $this->startBattle($bot, $chatId),
            str_contains($text, '清空手卡')   => $this->clearHand($bot, $chatId),
            str_contains($text, '我的手牌')   => $this->showHand($bot, $chatId),
            default                           => null,
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

    /** 產生形態徽章字串 */
    private function formatBadges(MezastarPokemon $p): string
    {
        $badges = [];
        if ($p->is_mega)              $badges[] = '[超級進化]';
        if ($p->is_gigantamax)        $badges[] = '[極巨化]';
        if ($p->is_ultra_gigantamax)  $badges[] = '[超極巨化]';
        if ($p->is_dual_move)         $badges[] = '[雙重招式]';
        return $badges ? ' ' . implode('', $badges) : '';
    }
}
