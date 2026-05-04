<?php

namespace App\Http\Controllers\Api\Bot\Concerns;

use App\Member;
use App\TgMessage;
use App\TgState;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait TelegramHelpers
{
    // ─── 語系 Helper ─────────────────────────────────────────────
    private function getUserLang(string $userId): string
    {
        $member = Member::where('account', 'tg_' . $userId)->first();
        return $member->language ?? 'zh-Hant';
    }

    private function t(string $key, string $lang, array $replace = []): string
    {
        $value = trans('tg.' . $key, [], $lang);
        if (is_array($value)) {
            return $value[0] ?? $key;
        }
        foreach ($replace as $k => $v) {
            $value = str_replace(':' . $k, $v, $value);
        }
        return $value;
    }

    // ─── 狀態管理 ────────────────────────────────────────────────
    private function getState(int $botId, int $chatId): ?TgState
    {
        return TgState::where('bot_id', $botId)
            ->where('tg_chat_id', $chatId)
            ->first();
    }

    private function setState(int $botId, int $chatId, string $state, array $data = []): void
    {
        TgState::updateOrCreate(
            ['bot_id' => $botId, 'tg_chat_id' => $chatId],
            [
                'state'      => $state,
                'state_data' => $data ?: null,
                'expires_at' => now()->addHours(1),
            ]
        );
    }

    private function clearState(int $botId, int $chatId): void
    {
        TgState::where('bot_id', $botId)->where('tg_chat_id', $chatId)->delete();
    }

    // ─── 輔助 ────────────────────────────────────────────────────
    private function matchesMenuKey(string $text, string $key): bool
    {
        foreach (['zh-Hant', 'zh-Hans', 'en'] as $locale) {
            if (str_contains($text, $this->t($key, $locale))) {
                return true;
            }
        }
        return false;
    }

    private function isSettingsText(string $text): bool
    {
        foreach (['zh-Hant', 'zh-Hans', 'en'] as $locale) {
            if (str_contains($text, $this->t('menu_settings', $locale))) {
                return true;
            }
        }
        return false;
    }

    private function isMainMenuText(string $text): bool
    {
        foreach (['menu_hedge', 'menu_wtx', 'menu_stock', 'menu_portfolio', 'menu_settings'] as $key) {
            if ($this->matchesMenuKey($text, $key)) {
                return true;
            }
        }
        return false;
    }

    private function getMainKeyboard(string $lang = 'zh-Hant'): array
    {
        return [
            'keyboard' => [
                [['text' => $this->t('menu_wtx', $lang)], ['text' => $this->t('menu_hedge', $lang)]],
                [['text' => $this->t('menu_stock', $lang)], ['text' => $this->t('menu_portfolio', $lang)]],
                [['text' => $this->t('menu_settings', $lang)]],
            ],
            'resize_keyboard' => true,
            'persistent'      => true,
        ];
    }

    private function logMessage(int $botId, string $userId, ?string $username, int $chatId, string $content, int $direction, string $type): void
    {
        TgMessage::create([
            'bot_id'       => $botId,
            'tg_user_id'   => $userId,
            'tg_username'  => $username,
            'tg_chat_id'   => $chatId,
            'content'      => mb_substr($content, 0, 500),
            'direction'    => $direction,
            'message_type' => $type,
        ]);
    }

    private function sendMessage(string $token, $chatId, string $text, ?array $replyMarkup = null, string $parseMode = 'HTML'): void
    {
        $params = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => $parseMode];
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

    private function sendPhoto(string $token, $chatId, string $photoPath, string $caption = '', ?array $replyMarkup = null): void
    {
        try {
            $params = [
                [
                    'name'     => 'chat_id',
                    'contents' => $chatId,
                ],
                [
                    'name'     => 'parse_mode',
                    'contents' => 'HTML',
                ],
            ];

            if ($caption) {
                $params[] = ['name' => 'caption', 'contents' => $caption];
            }
            if ($replyMarkup) {
                $params[] = ['name' => 'reply_markup', 'contents' => json_encode($replyMarkup)];
            }

            // 本地檔案用 multipart 上傳
            if (file_exists($photoPath)) {
                $params[] = [
                    'name'     => 'photo',
                    'contents' => fopen($photoPath, 'r'),
                    'filename' => basename($photoPath),
                ];
            } else {
                // URL 直接傳送
                $params[] = ['name' => 'photo', 'contents' => $photoPath];
            }

            $client = new Client(['timeout' => 30]);
            $res = $client->post("https://api.telegram.org/bot{$token}/sendPhoto", [
                'multipart' => $params,
            ]);

            Log::channel('tg_webhook')->info('[TG Webhook] sendPhoto 回應', [
                'chat_id' => $chatId,
                'status'  => $res->getStatusCode(),
            ]);
        } catch (\Exception $e) {
            Log::channel('tg_webhook')->error('[TG Webhook] sendPhoto 失敗', [
                'chat_id' => $chatId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    // ─── 用 file_id 發送圖片，失敗回傳 false ─────────────────────────
    private function sendPhotoById(string $token, $chatId, string $fileId, string $caption = '', ?array $replyMarkup = null): bool
    {
        try {
            $params = [
                'chat_id'    => $chatId,
                'photo'      => $fileId,
                'parse_mode' => 'HTML',
            ];
            if ($caption) {
                $params['caption'] = $caption;
            }
            if ($replyMarkup) {
                $params['reply_markup'] = json_encode($replyMarkup);
            }

            $res  = Http::timeout(30)->post("https://api.telegram.org/bot{$token}/sendPhoto", $params);
            $body = $res->json();

            Log::channel('tg_webhook')->info('[TG Webhook] sendPhotoById 回應', [
                'chat_id' => $chatId,
                'ok'      => $body['ok'] ?? false,
            ]);

            return $body['ok'] ?? false;
        } catch (\Exception $e) {
            Log::channel('tg_webhook')->warning('[TG Webhook] sendPhotoById 失敗，將 fallback 預設圖', [
                'chat_id' => $chatId,
                'error'   => $e->getMessage(),
            ]);
            return false;
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
