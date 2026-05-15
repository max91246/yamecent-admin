<?php

namespace App\Http\Controllers\Api\Bot;

use App\MezastarPokemon;
use App\TgBot;
use App\TgMezastarHand;
use App\Http\Controllers\Api\Bot\Concerns\TelegramHelpers;
use Illuminate\Support\Facades\Cache;

class MezastarBotHandler
{
    use TelegramHelpers;

    const HAND_TTL    = 86400; // 24h
    const POKEMON_TTL = 86400; // 24h

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

        // 主選單指令
        if (in_array($text, ['/start', '/menu'])) {
            $this->clearState($bot->id, $chatId);
            $this->sendMessage($bot->token, $chatId, "🎮 寶可夢 Mezastar 機台助手", $this->getMainKeyboard());
            return response()->json(['ok' => true]);
        }

        // 狀態機
        $stateObj = $this->getState($bot->id, $chatId);
        $state    = $stateObj->state ?? null;

        if ($state === 'mezastar_recording') {
            $this->handleRecord($bot, $chatId, $text);
        } elseif ($state === 'mezastar_battling') {
            $this->handleBattle($bot, $chatId, $text);
        } else {
            // 主選單文字按鈕
            match (true) {
                str_contains($text, '記錄寶可夢') => $this->startRecording($bot, $chatId),
                str_contains($text, '對戰寶可夢') => $this->startBattle($bot, $chatId),
                str_contains($text, '清空手卡')   => $this->clearHand($bot, $chatId),
                str_contains($text, '我的手牌')   => $this->showHand($bot, $chatId),
                default                           => $this->sendMessage($bot->token, $chatId, "請使用下方選單操作", $this->getMainKeyboard()),
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

        $pokemon = $this->findPokemon($name);
        if (!$pokemon) {
            $this->sendMessage($bot->token, $chatId, "❌ 找不到「{$name}」\n請確認名稱正確（如：皮卡丘、超夢、雷公），或輸入其他名稱繼續記錄：");
            return;
        }

        // 避免重複新增同一隻
        $exists = TgMezastarHand::where('bot_id', $bot->id)
            ->where('tg_chat_id', $chatId)
            ->where('pokemon_id', $pokemon->id)
            ->exists();

        if ($exists) {
            $this->sendMessage($bot->token, $chatId, "⚠️ 手牌中已有「{$pokemon->name}」（{$pokemon->series}），繼續輸入其他名稱：");
            return;
        }

        TgMezastarHand::create([
            'bot_id'     => $bot->id,
            'tg_chat_id' => $chatId,
            'pokemon_id' => $pokemon->id,
        ]);
        $this->invalidateHandCache($bot->id, $chatId);

        $typeStr = $pokemon->type2 ? "{$pokemon->type1}/{$pokemon->type2}" : $pokemon->type1;
        $stars   = str_repeat('⭐', $pokemon->grade);
        $this->sendMessage($bot->token, $chatId,
            "✅ 已記錄！\n🎴 {$pokemon->name}（{$pokemon->series}）\n屬性：{$typeStr}　招式：{$pokemon->move_type}\n星級：{$stars}\n\n繼續輸入下一隻，或點選其他選單："
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

        $opponent = $this->findPokemon($name);
        if (!$opponent) {
            $this->sendMessage($bot->token, $chatId, "❌ 找不到對手「{$name}」，請重新輸入：");
            return;
        }

        $weaknesses = $opponent->weakness; // array
        $hand       = $this->getHand($bot->id, $chatId);

        // 找出手牌中能克制對手的寶可夢
        $counters = $hand->filter(fn($h) => in_array($h->pokemon->move_type, $weaknesses));

        $opponentType = $opponent->type2
            ? "{$opponent->type1}/{$opponent->type2}"
            : $opponent->type1;

        $weakStr = implode('、', $weaknesses);

        if ($counters->isEmpty()) {
            $reply = "⚔️ 對手：<b>{$opponent->name}</b>（{$opponentType}）\n";
            $reply .= "弱點：{$weakStr}\n\n";
            $reply .= "😢 手牌中沒有能克制對方的寶可夢！";
        } else {
            $reply = "⚔️ 對手：<b>{$opponent->name}</b>（{$opponentType}）\n";
            $reply .= "弱點：{$weakStr}\n\n";
            $reply .= "✅ 你的手牌剋制：\n";
            foreach ($counters as $h) {
                $p     = $h->pokemon;
                $type  = $p->type2 ? "{$p->type1}/{$p->type2}" : $p->type1;
                $stars = str_repeat('⭐', $p->grade);
                $reply .= "  🎴 <b>{$p->name}</b>（{$p->series}）{$type} 招式:{$p->move_type} {$stars}\n";
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
            $p     = $h->pokemon;
            $type  = $p->type2 ? "{$p->type1}/{$p->type2}" : $p->type1;
            $stars = str_repeat('⭐', $p->grade);
            $text .= "🎴 <b>{$p->name}</b>（{$p->series}）\n";
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

    /** 查寶可夢（名稱模糊比對，快取24h） */
    private function findPokemon(string $name): ?MezastarPokemon
    {
        $all = Cache::remember('mezastar_pokemon:all', self::POKEMON_TTL, function () {
            return MezastarPokemon::all()->keyBy('name');
        });

        // 完全匹配優先
        if ($all->has($name)) return $all->get($name);

        // 模糊匹配
        $found = $all->first(fn($p) => str_contains($p->name, $name) || str_contains($name, $p->name));
        return $found ?: null;
    }

    /** 取得用戶手牌（快取24h） */
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
    }
}
