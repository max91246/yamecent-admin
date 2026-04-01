<?php

namespace App\Http\Controllers\Api;

use App\OilPrice;
use App\TgBot;
use App\TgMessage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TgWebhookController extends Controller
{
    public function handle(Request $request, $botId)
    {
        $bot = TgBot::find($botId);
        $update = $request->all();

        Log::channel('tg_webhook')->info('[TG Webhook] 收到請求', [
            'bot_id' => $botId,
            'bot_found' => (bool) $bot,
            'bot_active' => $bot ? (bool) $bot->is_active : false,
            'update' => $update,
        ]);

        if (!$bot || !$bot->is_active) {
            Log::channel('tg_webhook')->warning('[TG Webhook] Bot 不存在或已停用', ['bot_id' => $botId]);
            return response()->json(['ok' => true]);
        }

        // 處理 callback_query（用戶點擊 inline keyboard 按鈕）
        if (isset($update['callback_query'])) {
            $cq       = $update['callback_query'];
            $chatId   = $cq['message']['chat']['id'];
            $userId   = (string) $cq['from']['id'];
            $username = $cq['from']['username'] ?? null;
            $data     = $cq['data']; // 'oil' or 'wtx'

            // 記錄收到的 callback
            TgMessage::create([
                'bot_id'       => $bot->id,
                'tg_user_id'   => $userId,
                'tg_username'  => $username,
                'tg_chat_id'   => $chatId,
                'content'      => '[callback] ' . $data,
                'direction'    => 1,
                'message_type' => 'callback',
            ]);

            // 查詢最新價格並組成回覆
            $replyText = ($data === 'oil') ? $this->buildOilReply() : $this->buildWtxReply();
            Log::channel('tg_webhook')->info('[TG Webhook] callback 回覆', [
                'bot_id' => $bot->id, 'chat_id' => $chatId, 'data' => $data, 'reply' => $replyText,
            ]);

            // 先 answer callback 消除 loading 狀態
            $this->answerCallbackQuery($bot->token, $cq['id']);

            // 回覆訊息
            $this->sendMessage($bot->token, $chatId, $replyText);

            // 記錄 bot 回覆
            TgMessage::create([
                'bot_id'       => $bot->id,
                'tg_user_id'   => $userId,
                'tg_username'  => $username,
                'tg_chat_id'   => $chatId,
                'content'      => $replyText,
                'direction'    => 2,
                'message_type' => 'reply',
            ]);

            return response()->json(['ok' => true]);
        }

        // 處理一般訊息
        if (isset($update['message'])) {
            $msg      = $update['message'];
            $chatId   = $msg['chat']['id'];
            $userId   = (string) $msg['from']['id'];
            $username = $msg['from']['username'] ?? null;
            $text     = trim($msg['text'] ?? '');

            // 記錄收到的訊息
            TgMessage::create([
                'bot_id'       => $bot->id,
                'tg_user_id'   => $userId,
                'tg_username'  => $username,
                'tg_chat_id'   => $chatId,
                'content'      => $text,
                'direction'    => 1,
                'message_type' => 'text',
            ]);

            // 按下「布蘭特原油」按鈕
            if (str_contains($text, '布蘭特原油')) {
                $replyText = $this->buildOilReply();
                $this->sendMessage($bot->token, $chatId, $replyText);

            // 按下「台指期貨」按鈕
            } elseif (str_contains($text, '台指期貨')) {
                $replyText = $this->buildWtxReply();
                $this->sendMessage($bot->token, $chatId, $replyText);

            // 其他訊息（/start 或任意文字）→ 顯示固定鍵盤
            } else {
                $keyboard = [
                    'keyboard' => [
                        [
                            ['text' => '🛢 布蘭特原油'],
                            ['text' => '📈 台指期貨'],
                        ],
                    ],
                    'resize_keyboard' => true,
                    'persistent'      => true,
                ];
                $replyText = '請選擇要查詢的指數：';
                $this->sendMessage($bot->token, $chatId, $replyText, $keyboard);
            }

            // 記錄 bot 回覆
            TgMessage::create([
                'bot_id'       => $bot->id,
                'tg_user_id'   => $userId,
                'tg_username'  => $username,
                'tg_chat_id'   => $chatId,
                'content'      => $replyText,
                'direction'    => 2,
                'message_type' => 'reply',
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function buildOilReply(): string
    {
        $latest = OilPrice::where('ticker', 'QA')
            ->whereNotNull('close')
            ->orderBy('candle_at', 'desc')
            ->first();

        if (!$latest) {
            return '暫無油價資料';
        }

        $prev = OilPrice::where('ticker', 'QA')
            ->whereNotNull('close')
            ->where('candle_at', '<', $latest->candle_at)
            ->orderBy('candle_at', 'desc')
            ->first();

        $changeStr = '';
        if ($prev) {
            $diff = (float) $latest->close - (float) $prev->close;
            $pct  = (float) $prev->close > 0 ? ($diff / (float) $prev->close * 100) : 0;
            $sign = $diff >= 0 ? '+' : '';
            $arrow = $diff >= 0 ? '📈' : '📉';
            $changeStr = "\n{$arrow} 5分變化：{$sign}" . number_format($diff, 4) . "（{$sign}" . number_format($pct, 2) . "%）";
        }

        $vix = OilPrice::where('ticker', 'VIX')->whereNotNull('close')->orderBy('candle_at', 'desc')->first();
        $vixStr = $vix ? "\n😨 VIX 恐慌指數：" . number_format((float) $vix->close, 2) : '';

        return "🛢 布蘭特原油\n最新價：{$latest->close}{$changeStr}\n🕐 時間：{$latest->candle_at}{$vixStr}";
    }

    private function buildWtxReply(): string
    {
        $latest = OilPrice::where('ticker', 'WTX')
            ->whereNotNull('close')
            ->orderBy('candle_at', 'desc')
            ->first();

        if (!$latest) {
            return '暫無台指資料';
        }

        $prev = OilPrice::where('ticker', 'WTX')
            ->whereNotNull('close')
            ->where('candle_at', '<', $latest->candle_at)
            ->orderBy('candle_at', 'desc')
            ->first();

        $changeStr = '';
        if ($prev) {
            $diff = (float) $latest->close - (float) $prev->close;
            $pct  = (float) $prev->close > 0 ? ($diff / (float) $prev->close * 100) : 0;
            $sign = $diff >= 0 ? '+' : '';
            $arrow = $diff >= 0 ? '📈' : '📉';
            $changeStr = "\n{$arrow} 5分變化：{$sign}" . number_format($diff, 0) . "點（{$sign}" . number_format($pct, 2) . "%）";
        }

        $vix = OilPrice::where('ticker', 'VIX')->whereNotNull('close')->orderBy('candle_at', 'desc')->first();
        $vixStr = $vix ? "\n😨 VIX 恐慌指數：" . number_format((float) $vix->close, 2) : '';

        return "📈 台指期貨\n最新價：" . number_format((float) $latest->close, 0) . "{$changeStr}\n🕐 時間：{$latest->candle_at}{$vixStr}";
    }

    private function sendMessage(string $token, $chatId, string $text, array $replyMarkup = null): void
    {
        $params = ['chat_id' => $chatId, 'text' => $text];
        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        try {
            $res = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", $params);
            Log::channel('tg_webhook')->info('[TG Webhook] sendMessage 回應', [
                'chat_id' => $chatId,
                'status'  => $res->status(),
                'body'    => $res->json(),
            ]);
        } catch (\Exception $e) {
            Log::channel('tg_webhook')->error('[TG Webhook] sendMessage 失敗', [
                'chat_id' => $chatId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function answerCallbackQuery(string $token, string $callbackQueryId): void
    {
        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
                'callback_query_id' => $callbackQueryId,
            ]);
        } catch (\Exception $e) {
            Log::channel('tg_webhook')->error('[TG Webhook] answerCallbackQuery 失敗', [
                'callback_query_id' => $callbackQueryId,
                'error'             => $e->getMessage(),
            ]);
        }
    }
}
