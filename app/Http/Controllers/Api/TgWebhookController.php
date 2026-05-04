<?php

namespace App\Http\Controllers\Api;

use App\TgBot;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Bot\AvBotHandler;
use App\Http\Controllers\Api\Bot\StockBotHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TgWebhookController extends Controller
{
    // ─── 主入口 ──────────────────────────────────────────────────
    public function handle(Request $request, $botId)
    {
        $bot    = TgBot::find($botId);
        $update = $request->all();

        Log::channel('tg_webhook')->info('[TG Webhook] 收到請求', [
            'bot_id'     => $botId,
            'bot_found'  => (bool) $bot,
            'bot_active' => $bot ? (bool) $bot->is_active : false,
            'update'     => $update,
        ]);

        if (!$bot || !$bot->is_active) {
            return response()->json(['ok' => true]);
        }

        // ── update_id 去重（防 TG 重試重複處理）────────────────────
        $updateId = $update['update_id'] ?? null;
        if ($updateId) {
            $cacheKey = "tg_upd_{$botId}_{$updateId}";
            if (Cache::has($cacheKey)) {
                return response()->json(['ok' => true]);
            }
            Cache::put($cacheKey, 1, 60); // 60 秒內同一 update_id 忽略
        }

        if ($bot->type == 2) {
            return (new AvBotHandler)->handle($bot, $update);
        }

        return (new StockBotHandler)->handle($bot, $update);
    }
}
