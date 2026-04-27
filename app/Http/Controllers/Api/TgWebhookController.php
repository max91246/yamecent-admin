<?php

namespace App\Http\Controllers\Api;

use App\DisposalStock;
use App\Member;
use App\OilPrice;
use App\TgBot;
use App\TgHolding;
use App\TgHoldingTrade;
use App\TgMessage;
use App\TgState;
use App\TgSettlement;
use App\TgWallet;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

        // ── AV Bot 分流（type == 2）──────────────────────────────
        if ($bot->type == 2) {
            return $this->handleAvBot($bot, $update);
        }

        // ── callback_query（inline keyboard 按鈕點擊）────────────
        if (isset($update['callback_query'])) {
            $cq       = $update['callback_query'];
            $chatId   = (int) $cq['message']['chat']['id'];
            $userId   = (string) $cq['from']['id'];
            $username = $cq['from']['username'] ?? null;
            $data     = $cq['data'];

            $this->answerCallbackQuery($bot->token, $cq['id']);
            $this->logMessage($bot->id, $userId, $username, $chatId, '[callback] ' . $data, 1, 'callback');

            $replyText   = null;
            $replyMarkup = null;

            $stateObj = $this->getState($bot->id, $chatId);
            $lang     = $this->getUserLang($userId);

            // 融資確認按鈕（狀態機 step3）
            if ($stateObj && $stateObj->state === 'holding_step3') {
                [$replyText, $replyMarkup] = $this->handleHoldingStep3Callback($bot, $chatId, $data, $stateObj, $lang);

            // 添加持股
            } elseif ($data === 'holding_add') {
                $this->setState($bot->id, $chatId, 'holding_step1');
                $replyText = $this->t('holding_add_prompt', $lang);

            // 賣出持股 — 進入賣出流程（新格式：holding_sell_c_{code}_{margin}）
            } elseif (str_starts_with($data, 'holding_sell_c_')) {
                $parts     = explode('_', substr($data, strlen('holding_sell_c_')));
                $isMargin  = (int) array_pop($parts);
                $stockCode = implode('_', $parts);
                $holdings  = TgHolding::where('bot_id', $bot->id)
                    ->where('tg_chat_id', $chatId)
                    ->where('stock_code', $stockCode)
                    ->where('is_margin', $isMargin)
                    ->orderBy('created_at')
                    ->get();
                if ($holdings->isNotEmpty()) {
                    $totalShares = $holdings->sum('shares');
                    $stockName   = $holdings->first()->stock_name;
                    $typeTag     = $isMargin ? $this->t('margin_tag', $lang) : '';
                    $this->setState($bot->id, $chatId, 'sell_step1', [
                        'stock_code' => $stockCode,
                        'is_margin'  => $isMargin,
                    ]);
                    $replyText = $this->t('sell_prompt', $lang, [
                        'name'   => $stockName,
                        'code'   => $stockCode,
                        'type'   => $typeTag,
                        'shares' => $this->sharesDisplay($totalShares),
                        'max'    => $totalShares,
                    ]);
                } else {
                    [$replyText, $replyMarkup] = $this->buildPortfolioReply($bot->id, $chatId, $lang);
                }

            // 交割款查詢
            } elseif ($data === 'view_settlements') {
                [$replyText, $replyMarkup] = $this->buildSettlementReply($bot->id, $chatId);

            // 設定資金 — 先選模式
            } elseif ($data === 'set_capital') {
                $replyText   = $this->t('capital_mode_prompt', $lang);
                $replyMarkup = ['inline_keyboard' => [[
                    ['text' => $this->t('capital_btn_total',  $lang), 'callback_data' => 'set_capital_total'],
                    ['text' => $this->t('capital_btn_remain', $lang), 'callback_data' => 'set_capital_remain'],
                ]]];

            // 設定資金 — 總資金模式
            } elseif ($data === 'set_capital_total') {
                $this->setState($bot->id, $chatId, 'set_capital_total');
                $replyText = $this->t('capital_total_prompt', $lang);

            // 設定資金 — 剩餘資金模式
            } elseif ($data === 'set_capital_remain') {
                $this->setState($bot->id, $chatId, 'set_capital_remain');
                $replyText = $this->t('capital_remain_prompt', $lang);

            // 設置選單
            } elseif ($data === 'portfolio_settings') {
                $replyText   = $this->t('settings_title', $lang) . "\n\n" . $this->t('settings_prompt', $lang);
                $replyMarkup = ['inline_keyboard' => [
                    [['text' => $this->t('settings_banner', $lang), 'callback_data' => 'set_banner']],
                    [['text' => $this->t('settings_lang',   $lang), 'callback_data' => 'set_lang']],
                ]];

            // 設置 Banner — 上傳說明
            } elseif ($data === 'set_banner') {
                $lang = $this->getUserLang($userId);
                $this->setState($bot->id, $chatId, 'upload_banner');
                $replyText = $this->t('banner_prompt', $lang);

            // 語系選擇選單
            } elseif ($data === 'set_lang') {
                $lang        = $this->getUserLang($userId);
                $currentLang = $lang;
                $mark = fn($l) => $currentLang === $l ? ' ✓' : '';
                $replyText   = $this->t('lang_title', $lang);
                $replyMarkup = ['inline_keyboard' => [[
                    ['text' => $this->t('lang_zh_hant', $lang) . $mark('zh-Hant'), 'callback_data' => 'lang_zh-Hant'],
                    ['text' => $this->t('lang_zh_hans', $lang) . $mark('zh-Hans'), 'callback_data' => 'lang_zh-Hans'],
                    ['text' => $this->t('lang_en',      $lang) . $mark('en'),      'callback_data' => 'lang_en'],
                ]]];

            // 語系切換
            } elseif (str_starts_with($data, 'lang_')) {
                $newLang = substr($data, 5); // lang_zh-Hant → zh-Hant
                if (in_array($newLang, ['zh-Hant', 'zh-Hans', 'en'])) {
                    Member::updateOrCreate(
                        ['account' => 'tg_' . $userId],
                        ['language' => $newLang]
                    );
                    $replyText   = $this->t('lang_updated', $newLang);
                    $replyMarkup = $this->getMainKeyboard($newLang);
                }

            }

            if ($replyText !== null) {
                $this->sendMessage($bot->token, $chatId, $replyText, $replyMarkup);
                $this->logMessage($bot->id, $userId, $username, $chatId, $replyText, 2, 'reply');
            }

            return response()->json(['ok' => true]);
        }

        // ── 一般文字訊息 / 圖片訊息 ─────────────────────────────────
        if (isset($update['message'])) {
            $msg      = $update['message'];
            $chatId   = (int) $msg['chat']['id'];
            $userId   = (string) $msg['from']['id'];
            $username = $msg['from']['username'] ?? null;
            $text     = trim($msg['text'] ?? '');

            // ── 圖片訊息：處理 banner 上傳 ──────────────────────────
            if (isset($msg['photo'])) {
                $this->logMessage($bot->id, $userId, $username, $chatId, '[photo]', 1, 'photo');

                $stateObj = $this->getState($bot->id, $chatId);
                if ($stateObj && $stateObj->state === 'upload_banner') {
                    $lang   = $this->getUserLang($userId);
                    $result = $this->handleBannerUpload($bot, $chatId, $userId, $msg['photo'], $lang);
                    $this->logMessage($bot->id, $userId, $username, $chatId, $result, 2, 'reply');
                }

                return response()->json(['ok' => true]);
            }

            $this->logMessage($bot->id, $userId, $username, $chatId, $text, 1, 'text');

            $replyText   = null;
            $replyMarkup = null;

            // 取消 → 清除狀態，顯示主選單
            if (in_array($text, ['取消', '❌ 取消', '/cancel', 'cancel'])) {
                $lang = $this->getUserLang($userId);
                $this->clearState($bot->id, $chatId);
                $replyText   = $this->t('cancelled', $lang);
                $replyMarkup = $this->getMainKeyboard($lang);

            } else {
                $stateObj = $this->getState($bot->id, $chatId);
                $state    = $stateObj ? $stateObj->state : null;

                // 主選單按鈕優先：不論目前狀態，按到主選單直接跳過去
                if ($state && $this->isMainMenuText($text)) {
                    $this->clearState($bot->id, $chatId);
                    $stateObj = null;
                    $state    = null;
                }

                if ($state) {
                    [$replyText, $replyMarkup] = $this->handleState($bot, $chatId, $userId, $text, $stateObj);
                } else {
                    [$replyText, $replyMarkup] = $this->handleMainMenu($bot, $chatId, $userId, $text);
                }
            }

            if ($replyText !== null) {
                $this->sendMessage($bot->token, $chatId, $replyText, $replyMarkup);
                $this->logMessage($bot->id, $userId, $username, $chatId, $replyText, 2, 'reply');
            }
        }

        return response()->json(['ok' => true]);
    }

    // ─── 主選單處理 ──────────────────────────────────────────────
    private function handleMainMenu(TgBot $bot, int $chatId, string $userId, string $text): array
    {
        $lang = $this->getUserLang($userId);

        if ($this->matchesMenuKey($text, 'menu_wtx')) {
            return [$this->buildWtxReply($lang), null];
        }
        if ($this->matchesMenuKey($text, 'menu_hedge')) {
            return [$this->buildHedgeReply($lang), null];
        }
        if ($this->matchesMenuKey($text, 'menu_stock')) {
            $this->setState($bot->id, $chatId, 'stock_query');
            return [$this->t('stock_query_prompt', $lang), null];
        }
        if ($this->isSettingsText($text)) {
            $replyText   = $this->t('settings_title', $lang) . "\n\n" . $this->t('settings_prompt', $lang);
            $replyMarkup = ['inline_keyboard' => [
                [['text' => $this->t('settings_banner', $lang), 'callback_data' => 'set_banner']],
                [['text' => $this->t('settings_lang', $lang),   'callback_data' => 'set_lang']],
            ]];
            return [$replyText, $replyMarkup];
        }
        if ($this->matchesMenuKey($text, 'menu_portfolio')) {
            $member  = Member::where('account', 'tg_' . $userId)->first();
            $banner  = $member->banner ?? null;
            [$portfolioText, $portfolioMarkup] = $this->buildPortfolioReply($bot->id, $chatId, $lang);
            // caption 上限 1024 字元；超過時截斷並補 ...
            $caption = mb_strlen($portfolioText) <= 1024
                ? $portfolioText
                : mb_substr($portfolioText, 0, 1021) . '...';
            $sent = $this->sendBannerByValue($bot->token, $chatId, $banner, $caption, $portfolioMarkup);
            if (!$sent) {
                // banner 無法顯示（換 bot 或無設定），改用預設圖
                $defaultPath = public_path('assets/images/login-bg.jpg');
                if (file_exists($defaultPath)) {
                    $this->sendPhoto($bot->token, $chatId, $defaultPath, $caption, $portfolioMarkup);
                    return [null, null];
                }
            } else {
                return [null, null];
            }
            return [$portfolioText, $portfolioMarkup];
        }

        // 其他（/start, 任意文字）→ 顯示主選單
        return [$this->t('main_menu', $lang), $this->getMainKeyboard($lang)];
    }

    // ─── 狀態機分派 ──────────────────────────────────────────────
    private function handleState(TgBot $bot, int $chatId, string $userId, string $text, TgState $stateObj): array
    {
        $lang = $this->getUserLang($userId);
        switch ($stateObj->state) {
            case 'stock_query':
                return $this->handleStockQuery($bot, $chatId, $text, $lang);
            case 'holding_step1':
                return $this->handleHoldingStep1($bot, $chatId, $text, $stateObj, $lang);
            case 'holding_step2':
                return $this->handleHoldingStep2($bot, $chatId, $text, $stateObj, $lang);
            case 'holding_step3':
                return [$this->t('holding_margin_wait', $lang), null];
            case 'holding_step4':
                return $this->handleHoldingStep4($bot, $chatId, $userId, $text, $stateObj, $lang);
            case 'sell_step1':
                return $this->handleSellStep1($bot, $chatId, $userId, $text, $stateObj, $lang);
            case 'sell_step2':
                return $this->handleSellStep2($bot, $chatId, $userId, $text, $stateObj, $lang);
            case 'set_capital_total':
                return $this->handleSetCapital($bot, $chatId, $userId, $text, 'total', $lang);
            case 'set_capital_remain':
                return $this->handleSetCapital($bot, $chatId, $userId, $text, 'remain', $lang);
            case 'upload_banner':
                return [$this->t('banner_wait', $lang), null];
            default:
                $this->clearState($bot->id, $chatId);
                return [$this->t('main_menu', $lang), $this->getMainKeyboard($lang)];
        }
    }

    // ─── 台股查詢 ─────────────────────────────────────────────────
    private function handleStockQuery(TgBot $bot, int $chatId, string $text, string $lang = 'zh-Hant'): array
    {
        $code  = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $text));
        $quote = $this->fetchStockQuote($code);

        if (!$quote) {
            return [$this->t('stock_not_found', $lang, ['code' => $text]), null];
        }

        $this->clearState($bot->id, $chatId);
        return [$this->buildStockReply($code, $quote, $lang), null];
    }

    // ─── 持股添加：step1 輸入代號 ─────────────────────────────────
    private function handleHoldingStep1(TgBot $bot, int $chatId, string $text, TgState $stateObj, string $lang = 'zh-Hant'): array
    {
        $code  = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $text));
        $quote = $this->fetchStockQuote($code);

        if (!$quote) {
            return [$this->t('stock_not_found', $lang, ['code' => $text]), null];
        }

        $this->setState($bot->id, $chatId, 'holding_step2', [
            'code' => $code,
            'name' => $quote['name'],
        ]);

        return [
            $this->t('holding_found', $lang, ['name' => $quote['name'], 'code' => $code, 'price' => $quote['price']]),
            null,
        ];
    }

    // ─── 持股添加：step2 輸入張數 ─────────────────────────────────
    private function handleHoldingStep2(TgBot $bot, int $chatId, string $text, TgState $stateObj, string $lang = 'zh-Hant'): array
    {
        if (!ctype_digit($text) || (int) $text <= 0) {
            return [$this->t('holding_invalid_shares', $lang), null];
        }

        $data           = $stateObj->state_data ?? [];
        $data['shares'] = (int) $text;
        $this->setState($bot->id, $chatId, 'holding_step3', $data);

        $inlineKeyboard = [
            'inline_keyboard' => [[
                ['text' => $this->t('holding_margin_yes', $lang), 'callback_data' => 'margin_yes'],
                ['text' => $this->t('holding_margin_no',  $lang), 'callback_data' => 'margin_no'],
            ]],
        ];

        return [$this->t('holding_margin_prompt', $lang), $inlineKeyboard];
    }

    // ─── 持股添加：step3 融資確認（callback）────────────────────────
    private function handleHoldingStep3Callback(TgBot $bot, int $chatId, string $data, TgState $stateObj, string $lang = 'zh-Hant'): array
    {
        if (!in_array($data, ['margin_yes', 'margin_no'])) {
            return [null, null];
        }

        $stateData              = $stateObj->state_data ?? [];
        $stateData['is_margin'] = ($data === 'margin_yes') ? 1 : 0;
        $this->setState($bot->id, $chatId, 'holding_step4', $stateData);

        $name   = $stateData['name']   ?? '';
        $shares = $stateData['shares'] ?? 0;

        return [$this->t('holding_price_prompt', $lang, ['name' => $name, 'shares' => $this->sharesDisplay((int)$shares)]), null];
    }

    // ─── 持股添加：step4 輸入買進價格，自動計算成本 ─────────────────
    private function handleHoldingStep4(TgBot $bot, int $chatId, string $userId, string $text, TgState $stateObj, string $lang = 'zh-Hant'): array
    {
        $buyPrice = (float) str_replace([',', '，'], '', $text);

        if ($buyPrice <= 0) {
            return [$this->t('holding_invalid_price', $lang), null];
        }

        $data      = $stateObj->state_data ?? [];
        $shares    = (int) ($data['shares'] ?? 0);
        $isMargin  = (int) ($data['is_margin'] ?? 0);
        $marketVal = $buyPrice * $shares;

        // 現股：全額；融資：自備約 4 成
        $cost = $isMargin ? $marketVal * 0.4 : $marketVal;

        TgHolding::create([
            'bot_id'     => $bot->id,
            'tg_chat_id' => $chatId,
            'tg_user_id' => $userId,
            'stock_code' => $data['code'],
            'stock_name' => $data['name'],
            'shares'     => $shares,
            'is_margin'  => $isMargin,
            'total_cost' => $cost,
            'buy_price'  => $buyPrice,
        ]);

        // 判斷是否為處置股：處置股買進立即扣款，一般股票 T+2
        $isDisposal = $this->isDisposedCached($data['code']);
        $buyFee     = (int) ceil($marketVal * 0.001425);
        $settleAmt  = $cost + $buyFee;  // $cost 已是現股=全額 / 融資=40%

        if ($isDisposal) {
            // 處置股：立即扣款並標記已完成
            $today = Carbon::now('Asia/Taipei')->toDateString();
            TgSettlement::create([
                'bot_id'            => $bot->id,
                'tg_chat_id'        => $chatId,
                'tg_user_id'        => $userId,
                'stock_code'        => $data['code'],
                'stock_name'        => $data['name'],
                'shares'            => $shares,
                'buy_price'         => $buyPrice,
                'settlement_amount' => $settleAmt,
                'settle_date'       => $today,
                'is_settled'        => 1,
            ]);
            // 直接從 wallet 扣除
            $wallet = TgWallet::where('bot_id', $bot->id)->where('tg_chat_id', $chatId)->first();
            if ($wallet) {
                $wallet->decrement('capital', $settleAmt);
            }
        } else {
            // 一般股票：T+2 交割，由 settle:payments cron 每日處理
            $settleDate = $this->calcSettleDate(Carbon::now('Asia/Taipei'));
            TgSettlement::create([
                'bot_id'            => $bot->id,
                'tg_chat_id'        => $chatId,
                'tg_user_id'        => $userId,
                'stock_code'        => $data['code'],
                'stock_name'        => $data['name'],
                'shares'            => $shares,
                'buy_price'         => $buyPrice,
                'settlement_amount' => $settleAmt,
                'settle_date'       => $settleDate->toDateString(),
                'is_settled'        => 0,
            ]);
        }

        $this->clearState($bot->id, $chatId);

        $marginTag = $isMargin ? $this->t('holding_margin_tag', $lang) : $this->t('holding_cash_tag', $lang);
        $confirm   = $this->t('holding_added', $lang, [
            'name'       => $data['name'],
            'code'       => $data['code'],
            'shares'     => $this->sharesDisplay($shares),
            'type'       => $marginTag,
            'buy_price'  => $buyPrice,
            'market_val' => number_format($marketVal, 0),
            'cost'       => number_format($cost, 0),
        ]) . ($isMargin ? $this->t('holding_margin_note', $lang) : '')
           . ($isDisposal ? $this->t('holding_disposal_note', $lang) : '');

        // 先送確認訊息，再回傳持股列表
        $this->sendMessage($bot->token, $chatId, $confirm);

        return $this->buildPortfolioReply($bot->id, $chatId, $lang);
    }

    // ─── 賣出：step1 輸入賣出張數 ────────────────────────────────
    private function handleSellStep1(TgBot $bot, int $chatId, string $userId, string $text, TgState $stateObj, string $lang = 'zh-Hant'): array
    {
        if (!ctype_digit($text) || (int) $text <= 0) {
            return [$this->t('sell_invalid_shares', $lang), null];
        }

        $data      = $stateObj->state_data ?? [];
        $holdings  = TgHolding::where('bot_id', $bot->id)
            ->where('tg_chat_id', $chatId)
            ->where('stock_code', $data['stock_code'])
            ->where('is_margin', $data['is_margin'])
            ->orderBy('created_at')
            ->get();

        if ($holdings->isEmpty()) {
            $this->clearState($bot->id, $chatId);
            return $this->buildPortfolioReply($bot->id, $chatId, $lang);
        }

        $totalShares = $holdings->sum('shares');
        $sellShares  = (int) $text;
        if ($sellShares > $totalShares) {
            return [$this->t('sell_exceed', $lang, ['shares' => $this->sharesDisplay($totalShares)]), null];
        }

        $data['sell_shares'] = $sellShares;
        $this->setState($bot->id, $chatId, 'sell_step2', $data);

        return [$this->t('sell_price_prompt', $lang), null];
    }

    // ─── 賣出：step2 輸入賣出價格，計算盈虧 ──────────────────────
    private function handleSellStep2(TgBot $bot, int $chatId, string $userId, string $text, TgState $stateObj, string $lang = 'zh-Hant'): array
    {
        $sellPrice = (float) str_replace([',', '，'], '', $text);
        if ($sellPrice <= 0) {
            return [$this->t('sell_invalid_price', $lang), null];
        }

        $data     = $stateObj->state_data ?? [];
        // 取出所有同股票+同類型的持股（FIFO 順序：最早買進的先賣）
        $holdings = TgHolding::where('bot_id', $bot->id)
            ->where('tg_chat_id', $chatId)
            ->where('stock_code', $data['stock_code'])
            ->where('is_margin', $data['is_margin'])
            ->orderBy('created_at')
            ->get();

        if ($holdings->isEmpty()) {
            $this->clearState($bot->id, $chatId);
            return $this->buildPortfolioReply($bot->id, $chatId, $lang);
        }

        $sellShares = (int) $data['sell_shares'];
        $isMargin   = (int) $data['is_margin'];
        $stockCode  = $data['stock_code'];
        $stockName  = $holdings->first()->stock_name;

        // FIFO：依序扣減持股，並計算加權平均買價與比例成本
        $remaining        = $sellShares;
        $totalBuyValue    = 0;
        $totalCostDeducted = 0;
        foreach ($holdings as $h) {
            if ($remaining <= 0) break;
            $deduct = min($remaining, $h->shares);
            $totalBuyValue    += (float) $h->buy_price * $deduct;
            $totalCostDeducted += (float) $h->total_cost * ($deduct / $h->shares);

            if ($deduct >= $h->shares) {
                $h->delete();
            } else {
                $remainShares = $h->shares - $deduct;
                $newCost      = (float) $h->total_cost * ($remainShares / $h->shares);
                $h->update(['shares' => $remainShares, 'total_cost' => $newCost]);
            }
            $remaining -= $deduct;
        }

        $avgBuyPrice = $sellShares > 0 ? round($totalBuyValue / $sellShares, 4) : 0;
        $buyValue    = $totalBuyValue;
        $sellValue   = $sellPrice * $sellShares;

        // 交易成本
        $feeRate   = 0.001425;
        $taxRate   = 0.003;
        $buyFee    = ceil($buyValue  * $feeRate);
        $sellFee   = ceil($sellValue * $feeRate);
        $sellTax   = ceil($sellValue * $taxRate);

        // 損益 = 賣出價值 - 買進價值 - 交易成本
        $profit = $sellValue - $buyValue - $buyFee - $sellFee - $sellTax;

        // 記錄交易（加權平均買價）
        TgHoldingTrade::create([
            'bot_id'      => $bot->id,
            'tg_chat_id'  => $chatId,
            'tg_user_id'  => $userId,
            'stock_code'  => $stockCode,
            'stock_name'  => $stockName,
            'sell_shares' => $sellShares,
            'buy_price'   => $avgBuyPrice,
            'sell_price'  => $sellPrice,
            'is_margin'   => $isMargin,
            'profit'      => $profit,
        ]);

        // 建立賣出 T+2 待收款記錄
        $loanRepay  = $isMargin ? $buyValue * 0.6 : 0;
        $sellSettle = $sellValue - $loanRepay - $sellFee - $sellTax;
        $sellSettleDate = $this->calcSettleDate(Carbon::now('Asia/Taipei'));
        TgSettlement::create([
            'bot_id'            => $bot->id,
            'tg_chat_id'        => $chatId,
            'tg_user_id'        => $userId,
            'stock_code'        => $stockCode,
            'stock_name'        => $stockName,
            'shares'            => $sellShares,
            'buy_price'         => $sellPrice,
            'settlement_amount' => $sellSettle,
            'settle_date'       => $sellSettleDate->toDateString(),
            'is_settled'        => 0,
            'direction'         => 'sell',
        ]);

        $this->clearState($bot->id, $chatId);

        $sign      = $profit >= 0 ? '+' : '';
        $profitTag = $profit >= 0 ? $this->t('sell_profit', $lang) : $this->t('sell_loss', $lang);
        $confirm   = $this->t('sell_done', $lang, [
            'name'       => $stockName,
            'code'       => $stockCode,
            'shares'     => $this->sharesDisplay($sellShares),
            'buy_price'  => $avgBuyPrice,
            'sell_price' => $sellPrice,
            'fee'        => number_format($buyFee + $sellFee, 0),
            'tax'        => number_format($sellTax, 0),
            'profit_tag' => $profitTag,
            'sign'       => $sign,
            'profit'     => number_format($profit, 0),
        ]);

        $this->sendMessage($bot->token, $chatId, $confirm);

        return $this->buildPortfolioReply($bot->id, $chatId, $lang);
    }

    // ─── 設定資金總額（total=直接設定 / remain=剩餘+持股成本反推）──
    private function handleSetCapital(TgBot $bot, int $chatId, string $userId, string $text, string $mode = 'total', string $lang = 'zh-Hant'): array
    {
        $input = (float) str_replace([',', '，', '$', 'NT$', ' '], '', $text);
        if ($input <= 0) {
            return [$this->t('capital_invalid', $lang), null];
        }

        // wallet.capital 是 running balance（剩餘現金），不是總資金
        // remain 模式：直接存入剩餘現金
        // total 模式：total - 持股成本 = 剩餘現金
        $selfCostSum = (float) TgHolding::where('bot_id', $bot->id)
            ->where('tg_chat_id', $chatId)
            ->sum('total_cost');

        if ($mode === 'remain') {
            $capital = $input;
            $note    = $this->t('capital_set_remain', $lang, [
                'capital' => number_format($capital, 0),
                'total'   => number_format($capital + $selfCostSum, 0),
            ]);
        } else {
            // 總資金模式：換算成剩餘現金存入（= total - 已佔用持股成本）
            $capital = $input - $selfCostSum;
            $note    = $this->t('capital_set_total', $lang, [
                'total'  => number_format($input, 0),
                'cost'   => number_format($selfCostSum, 0),
                'remain' => number_format($capital, 0),
            ]);
            if ($capital < 0) {
                $note .= $this->t('capital_warning', $lang);
            }
        }

        TgWallet::updateOrCreate(
            ['bot_id' => $bot->id, 'tg_chat_id' => $chatId, 'tg_user_id' => $userId],
            ['capital' => $capital]
        );

        $this->clearState($bot->id, $chatId);
        $this->sendMessage($bot->token, $chatId, $note);

        return $this->buildPortfolioReply($bot->id, $chatId, $lang);
    }

    // ─── 計算 T+2 交割日（跳過周末，不處理國定假日）────────────
    // ─── 處置股 Redis Cache 查詢 ────────────────────────────────
    /**
     * 從 Redis 判斷該股票是否為處置股。
     * cache_ready 旗標存在時直接信任 Redis，不查 DB。
     * 旗標不存在（首次或過期）則 fallback 至 DB，並順帶寫入 cache。
     */
    private function isDisposedCached(string $code): bool
    {
        return $this->getDisposalCached($code) !== null;
    }

    /**
     * 取得處置股 Cache 資料（陣列）或 null（非處置股）。
     */
    private function getDisposalCached(string $code): ?array
    {
        $key = 'disposal:' . $code;

        if (Cache::has('disposal:cache_ready')) {
            // Cache 已就緒：key 存在代表處置中，不存在代表非處置股
            $raw = Cache::get($key);
            return $raw ? json_decode($raw, true) : null;
        }

        // Cache 尚未建立：fallback 至 DB，並寫入 cache（TTL 到今天 07:59）
        $now    = Carbon::now('Asia/Taipei');
        $expire = $now->copy()->startOfDay()->setTime(7, 59, 0);
        if ($expire->lte($now)) {
            $expire->addDay();
        }
        $ttl = max(60, (int) $now->diffInSeconds($expire));

        $disposal = DisposalStock::where('stock_code', $code)
            ->where('end_date', '>=', $now->toDateString())
            ->first();

        if ($disposal) {
            $data = [
                'start_date' => $disposal->start_date->toDateString(),
                'end_date'   => $disposal->end_date->toDateString(),
                'reason'     => $disposal->reason,
                'market'     => $disposal->market,
            ];
            Cache::put($key, json_encode($data, JSON_UNESCAPED_UNICODE), $ttl);
            return $data;
        }

        return null;
    }

    private function calcSettleDate(\Carbon\Carbon $tradeDate): \Carbon\Carbon
    {
        $d    = $tradeDate->copy()->startOfDay();
        $days = 0;
        while ($days < 2) {
            $d->addDay();
            if ($d->isWeekday()) {
                $days++;
            }
        }
        return $d;
    }

    // ─── 回覆組建：油價 ──────────────────────────────────────────
    private function buildOilReply(string $lang = 'zh-Hant'): string
    {
        $latest = OilPrice::where('ticker', 'QA')
            ->whereNotNull('close')
            ->orderBy('candle_at', 'desc')
            ->first();

        if (!$latest) {
            return $this->t('oil_no_data', $lang);
        }

        $prev = OilPrice::where('ticker', 'QA')
            ->whereNotNull('close')
            ->where('candle_at', '<', $latest->candle_at)
            ->orderBy('candle_at', 'desc')
            ->first();

        $changeStr = '';
        if ($prev) {
            $diff  = (float) $latest->close - (float) $prev->close;
            $pct   = (float) $prev->close > 0 ? ($diff / (float) $prev->close * 100) : 0;
            $sign  = $diff >= 0 ? '+' : '';
            $arrow = $diff >= 0 ? '📈' : '📉';
            $changeStr = "\n{$arrow} " . $this->t('reply_change_5m', $lang) . "：{$sign}" . number_format($diff, 4) . "（{$sign}" . number_format($pct, 2) . "%）";
        }

        $vix    = OilPrice::where('ticker', 'VIX')->whereNotNull('close')->orderBy('candle_at', 'desc')->first();
        $vixStr = $vix ? "\n" . $this->t('reply_vix_label', $lang) . "：" . number_format((float) $vix->close, 2) : '';

        return $this->t('oil_title', $lang) . "\n" . $this->t('oil_latest', $lang) . "：{$latest->close}{$changeStr}\n🕐 " . $this->t('reply_time', $lang) . "：{$latest->candle_at}{$vixStr}";
    }

    // ─── 回覆組建：台指 ──────────────────────────────────────────
    private function buildWtxReply(string $lang = 'zh-Hant'): string
    {
        // 撈最近 6 筆（5 根 K + 1 根用來算最舊那根的變化）
        $rows = OilPrice::where('ticker', 'WTX')
            ->whereNotNull('close')
            ->orderBy('candle_at', 'desc')
            ->limit(6)
            ->get();

        if ($rows->isEmpty()) {
            return $this->t('wtx_no_data', $lang);
        }

        // 由舊到新排列
        $rows = $rows->reverse()->values();
        $display = $rows->slice(1);   // 顯示最新 5 根
        $oldest  = $rows->first();    // 第 6 筆，用來算最舊顯示根的變化

        $lines = [];
        foreach ($display as $i => $row) {
            $prev      = $i === 0 ? $oldest : $display->get($i - 1);
            $price     = (float) $row->close;
            $prevPrice = $prev ? (float) $prev->close : null;
            $time      = \Carbon\Carbon::parse($row->candle_at)->setTimezone('Asia/Taipei')->format('H:i');

            if ($prevPrice !== null && $prevPrice > 0) {
                $diff  = $price - $prevPrice;
                $pct   = $diff / $prevPrice * 100;
                $sign  = $diff >= 0 ? '+' : '';
                $arrow = $diff >= 0 ? '▲' : '▼';
                $changeStr = "  {$arrow}{$sign}" . number_format($diff, 0) . "（{$sign}" . number_format($pct, 2) . "%）";
            } else {
                $changeStr = '';
            }

            $lines[] = "🕐 {$time}　" . number_format($price, 0) . $changeStr;
        }

        $vix    = OilPrice::where('ticker', 'VIX')->whereNotNull('close')->orderBy('candle_at', 'desc')->first();
        $vixStr = $vix ? "\n\n" . $this->t('reply_vix_label', $lang) . "：" . number_format((float) $vix->close, 2) : '';

        return $this->t('wtx_title', $lang) . "\n"
             . "─────────────────\n"
             . implode("\n", array_reverse($lines))
             . $vixStr;
    }

    // ─── 回覆組建：VIX ───────────────────────────────────────────
    private function buildVixReply(string $lang = 'zh-Hant'): string
    {
        $latest = OilPrice::where('ticker', 'VIX')
            ->whereNotNull('close')
            ->orderBy('candle_at', 'desc')
            ->first();

        if (!$latest) {
            return $this->t('vix_no_data', $lang);
        }

        $prev = OilPrice::where('ticker', 'VIX')
            ->whereNotNull('close')
            ->where('candle_at', '<', $latest->candle_at)
            ->orderBy('candle_at', 'desc')
            ->first();

        $changeStr = '';
        if ($prev) {
            $diff  = (float) $latest->close - (float) $prev->close;
            $pct   = (float) $prev->close > 0 ? ($diff / (float) $prev->close * 100) : 0;
            $sign  = $diff >= 0 ? '+' : '';
            $arrow = $diff >= 0 ? '📈' : '📉';
            $changeStr = "\n{$arrow} " . $this->t('reply_change_5m', $lang) . "：{$sign}" . number_format($diff, 2) . "（{$sign}" . number_format($pct, 2) . "%）";
        }

        return $this->t('vix_title', $lang) . "\n" . $this->t('vix_current', $lang) . "：" . number_format((float) $latest->close, 2) . "{$changeStr}\n🕐 " . $this->t('reply_time', $lang) . "：{$latest->candle_at}";
    }

    // ─── 回覆組建：黃金 ──────────────────────────────────────────
    private function buildGoldReply(string $lang = 'zh-Hant'): string
    {
        $latest = OilPrice::where('ticker', 'GOLD')
            ->whereNotNull('close')
            ->orderBy('candle_at', 'desc')
            ->first();

        if (!$latest) {
            return $this->t('gold_no_data', $lang);
        }

        $prev = OilPrice::where('ticker', 'GOLD')
            ->whereNotNull('close')
            ->where('candle_at', '<', $latest->candle_at)
            ->orderBy('candle_at', 'desc')
            ->first();

        $changeStr = '';
        if ($prev) {
            $diff  = (float) $latest->close - (float) $prev->close;
            $pct   = (float) $prev->close > 0 ? ($diff / (float) $prev->close * 100) : 0;
            $sign  = $diff >= 0 ? '+' : '';
            $arrow = $diff >= 0 ? '📈' : '📉';
            $changeStr = "\n{$arrow} " . $this->t('reply_change_5m', $lang) . "：{$sign}" . number_format($diff, 2) . "（{$sign}" . number_format($pct, 2) . "%）";
        }

        return $this->t('gold_title', $lang) . "\n" . $this->t('gold_current', $lang) . "：" . number_format((float) $latest->close, 2) . "{$changeStr}\n🕐 " . $this->t('reply_time', $lang) . "：{$latest->candle_at}";
    }

    // ─── 回覆組建：避險商品（油價 + VIX + 黃金）────────────────────
    private function buildHedgeReply(string $lang = 'zh-Hant'): string
    {
        $blocks = [];

        foreach (['QA' => 'oil', 'VIX' => 'vix', 'GOLD' => 'gold'] as $ticker => $type) {
            $latest = OilPrice::where('ticker', $ticker)
                ->whereNotNull('close')
                ->orderBy('candle_at', 'desc')
                ->first();

            if (!$latest) {
                continue;
            }

            $prev = OilPrice::where('ticker', $ticker)
                ->whereNotNull('close')
                ->where('candle_at', '<', $latest->candle_at)
                ->orderBy('candle_at', 'desc')
                ->first();

            $changeStr = '';
            if ($prev) {
                $diff  = (float) $latest->close - (float) $prev->close;
                $pct   = (float) $prev->close > 0 ? ($diff / (float) $prev->close * 100) : 0;
                $sign  = $diff >= 0 ? '+' : '';
                $arrow = $diff >= 0 ? '📈' : '📉';
                $changeStr = "　{$arrow} {$sign}" . number_format($diff, 2) . "（{$sign}" . number_format($pct, 2) . "%）";
            }

            $title   = $this->t("{$type}_title", $lang);
            $current = $this->t("{$type}_current", $lang);
            $blocks[] = "━━ {$title} ━━\n{$current}：<b>" . number_format((float) $latest->close, 2) . "</b>{$changeStr}\n🕐 {$latest->candle_at}";
        }

        if (empty($blocks)) {
            return '暫無避險商品資料';
        }

        return implode("\n\n", $blocks);
    }

    // ─── 回覆組建：台股查詢結果 ───────────────────────────────────
    private function buildStockReply(string $code, array $quote, string $lang = 'zh-Hant'): string
    {
        $name  = $quote['name'];
        $price = $quote['price'];

        $changeStr = '';
        if ($quote['priceChange'] !== null && $quote['priceChangePct'] !== null) {
            $diff  = (float) $quote['priceChange'];
            $pct   = (float) $quote['priceChangePct'];
            $sign  = $diff >= 0 ? '+' : '';
            $arrow = $diff >= 0 ? '📈' : '📉';
            $changeStr = "\n{$arrow} " . $this->t('stock_change', $lang) . "：{$sign}" . number_format($diff, 2) . "（{$sign}" . number_format($pct, 2) . "%）";
        }

        $volumeStr = $quote['volume'] !== null
            ? "\n📦 " . $this->t('stock_volume', $lang) . "：" . number_format((int) round((float) $quote['volume'] / 1000))
            : '';

        // 處置股標記（優先從 Redis 取，避免每次查 DB）
        $disposalData = $this->getDisposalCached($code);
        $isDisposal   = $disposalData !== null;
        $disposalTag  = $isDisposal ? ' ' . $this->t('stock_disposal_tag', $lang) : '';

        $reply = "📊 {$name}（{$code}.TW）{$disposalTag}\n💰 {$price}{$changeStr}{$volumeStr}";

        // 三大法人
        $inst = $this->fetchInstitutional($code);
        if ($inst) {
            $rows       = array_slice($inst, 0, 10);
            $sumForeign = array_sum(array_column($rows, 'foreign'));
            $sumTrust   = array_sum(array_column($rows, 'trust'));
            $sumDealer  = array_sum(array_column($rows, 'dealer'));

            // 橫條圖：以三者絕對值最大作為基準
            $maxAbs = max(abs($sumForeign), abs($sumTrust), abs($sumDealer), 1);

            $reply .= "\n\n━━ " . $this->t('stock_inst_title', $lang) . " ━━";
            $reply .= "\n" . $this->buildInstBar($this->t('stock_inst_foreign', $lang), $sumForeign, $maxAbs);
            $reply .= "\n" . $this->buildInstBar($this->t('stock_inst_trust',   $lang), $sumTrust,   $maxAbs);
            $reply .= "\n" . $this->buildInstBar($this->t('stock_inst_dealer',  $lang), $sumDealer,  $maxAbs);

            // 每日明細（緊湊格式）
            $fLabel = mb_substr($this->t('stock_inst_foreign', $lang), 0, 1);
            $tLabel = mb_substr($this->t('stock_inst_trust',   $lang), 0, 1);
            $dLabel = mb_substr($this->t('stock_inst_dealer',  $lang), 0, 1);
            $reply .= "\n\n📅 " . $this->t('stock_inst_daily', $lang);
            foreach ($rows as $row) {
                $date = substr($row['date'], 5); // 取 MM/DD
                $f    = $this->fmtInstCompact($row['foreign'] ?? null);
                $t    = $this->fmtInstCompact($row['trust']   ?? null);
                $d    = $this->fmtInstCompact($row['dealer']  ?? null);
                $reply .= "\n{$date}  {$fLabel}{$f}  {$tLabel}{$t}  {$dLabel}{$d}";
            }
        }

        // 月營收
        $revenues = $this->fetchRevenue($code);
        if ($revenues) {
            $reply .= "\n\n━━ " . $this->t('stock_rev_title', $lang) . " ━━";
            foreach ($revenues as $r) {
                // 日期格式 "2026-02-01T..."  → "26/02"
                $d       = explode('-', substr($r['date'], 0, 7));
                $label   = substr($d[0], 2) . '/' . $d[1];
                $revK    = (int) round((float) ($r['revenue'] ?? 0) / 1000);
                $mom     = (float) ($r['revenueMoM'] ?? 0);
                $yoy     = (float) ($r['revenueYoY'] ?? 0);
                $momArrow = $mom >= 0 ? '▲' : '▼';
                $yoyArrow = $yoy >= 0 ? '▲' : '▼';
                $lastRevK = isset($r['lastYear']['revenue'])
                    ? (int) round((float) $r['lastYear']['revenue'] / 1000)
                    : null;
                $lastStr = $lastRevK !== null ? "　" . $this->t('stock_rev_last_yr', $lang) . " " . number_format($lastRevK) : '';
                $reply .= "\n{$label}  " . number_format($revK)
                        . "　MoM{$momArrow}" . number_format(abs($mom), 1) . "%"
                        . "　YoY{$yoyArrow}" . number_format(abs($yoy), 1) . "%"
                        . $lastStr;
            }

            // 最新月份的累計資訊
            $latest   = $revenues[0];
            $accK     = (int) round((float) ($latest['revenueAcc'] ?? 0) / 1000);
            $lastAccK = isset($latest['lastYear']['revenueAcc'])
                ? (int) round((float) $latest['lastYear']['revenueAcc'] / 1000)
                : null;
            $accYoY   = (float) ($latest['revenueYoYAcc'] ?? 0);
            $dParts   = explode('-', substr($latest['date'], 0, 7));
            $accLabel = substr($dParts[0], 2) . '/' . $dParts[1];
            $accArrow = $accYoY >= 0 ? '▲' : '▼';

            $reply .= "\n\n📊 " . $this->t('stock_rev_acc', $lang, ['label' => $accLabel]) . "：" . number_format($accK) . "K";
            if ($lastAccK !== null) {
                $reply .= "\n   " . $this->t('stock_rev_last_yr_same', $lang) . "：" . number_format($lastAccK) . "K"
                        . "　" . $this->t('stock_rev_yoy_acc', $lang) . "{$accArrow}" . number_format(abs($accYoY), 1) . "%";
            }
        }

        // 最新新聞
        $news = $this->fetchStockNews($code);
        if (!empty($news)) {
            $reply .= "\n\n━━ " . $this->t('stock_news_title', $lang) . " ━━";
            foreach ($news as $i => $n) {
                $title = htmlspecialchars($n['title'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $link  = $n['link'] ?? '';
                if ($link) {
                    $linkEsc = htmlspecialchars($link, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $reply .= "\n" . ($i + 1) . ". <a href=\"{$linkEsc}\">{$title}</a>\n   🕐 {$n['date']}";
                } else {
                    $reply .= "\n" . ($i + 1) . ". {$title}\n   🕐 {$n['date']}";
                }
            }
        }

        // 處置股資訊區塊
        if ($isDisposal && $disposalData) {
            $reply .= "\n\n━━ " . $this->t('stock_disposal_tag', $lang) . " ━━";
            $reply .= "\n📅 " . $this->t('stock_disposal_period', $lang) . "：{$disposalData['start_date']} ~ {$disposalData['end_date']}";
            if (!empty($disposalData['reason'])) {
                $reply .= "\n📋 " . $this->t('stock_disposal_reason', $lang) . "：{$disposalData['reason']}";
            }
        }

        return $reply;
    }

    // ─── 抓取股票新聞（Yahoo 奇摩股市 RSS）───────────────────────
    private function fetchStockNews(string $code, int $limit = 5): array
    {
        $cacheKey = "tw-news-{$code}";
        return Cache::remember($cacheKey, 600, function () use ($code, $limit) {
            try {
                $url      = "https://tw.stock.yahoo.com/rss?s={$code}";
                $response = \Illuminate\Support\Facades\Http::timeout(5)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                    ->get($url);

                if (!$response->ok()) return [];

                $xml = simplexml_load_string($response->body());
                if (!$xml) return [];

                $items  = [];
                $count  = 0;
                foreach ($xml->channel->item as $item) {
                    if ($count >= $limit) break;
                    $pubDate = isset($item->pubDate) ? date('m/d H:i', strtotime((string) $item->pubDate)) : '';
                    // SimpleXML 對 RSS <link> 解析不穩定，嘗試多種方式取得
                $link = '';
                if (!empty((string) $item->link)) {
                    $link = trim((string) $item->link);
                } elseif (!empty((string) $item->children('http://www.w3.org/1999/xhtml')->a)) {
                    $link = trim((string) $item->children('http://www.w3.org/1999/xhtml')->a->attributes()->href);
                } else {
                    // fallback：從 guid 取連結
                    $link = trim((string) $item->guid);
                }
                $items[] = [
                        'title' => html_entity_decode(strip_tags((string) $item->title), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                        'link'  => $link,
                        'date'  => $pubDate,
                    ];
                    $count++;
                }
                return $items;
            } catch (\Exception $e) {
                return [];
            }
        });
    }

    // 橫條圖：外資 [████████░░░░] ▼9,206張
    private function buildInstBar(string $label, int $val, int $maxAbs): string
    {
        $barLen  = 12;
        $filled  = (int) round(abs($val) / $maxAbs * $barLen);
        $filled  = max(0, min($barLen, $filled));
        $empty   = $barLen - $filled;

        $bar     = str_repeat('█', $filled) . str_repeat('░', $empty);
        $up      = $val >= 0;
        $arrow   = $up ? '▲' : '▼';
        $color   = $up ? '🟢' : '🔴';
        $sign    = $up ? '+' : '';

        return "{$color} {$label} [{$bar}] {$arrow}" . number_format(abs($val)) . "張（{$sign}" . number_format($val) . "）";
    }

    // 每日明細緊湊格式：▲1,234 或 ▼567
    private function fmtInstCompact($val): string
    {
        if ($val === null) return '-';
        $v     = (int) $val;
        $arrow = $v >= 0 ? '▲' : '▼';
        return $arrow . number_format(abs($v));
    }

    // ─── Banner 圖片發送 ───────────────────────────────────────────
    private function sendBannerPhoto(TgBot $bot, int $chatId, string $userId, ?array $replyMarkup = null): void
    {
        $member = Member::where('account', 'tg_' . $userId)->first();
        $banner = $member->banner ?? null;

        $sent = $this->sendBannerByValue($bot->token, $chatId, $banner, '', $replyMarkup);

        if (!$sent) {
            $defaultPath = public_path('assets/images/login-bg.jpg');
            if (file_exists($defaultPath)) {
                $this->sendPhoto($bot->token, $chatId, $defaultPath, '', $replyMarkup);
            }
        }
    }

    // ─── 統一 Banner 發送：file_id / 舊路徑，失敗回傳 false ──────────
    // $bannerValue 可能是 TG file_id（新）或 /uploads/... 路徑（舊）
    private function sendBannerByValue(string $token, $chatId, ?string $bannerValue, string $caption = '', ?array $replyMarkup = null): bool
    {
        if (!$bannerValue) {
            return false;
        }

        // 舊格式：本地路徑
        if (str_starts_with($bannerValue, '/')) {
            $path = public_path($bannerValue);
            if (file_exists($path)) {
                $this->sendPhoto($token, $chatId, $path, $caption, $replyMarkup);
                return true;
            }
            return false;
        }

        // 新格式：TG file_id
        return $this->sendPhotoById($token, $chatId, $bannerValue, $caption, $replyMarkup);
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

    // ─── Banner 上傳處理 ────────────────────────────────────────────
    private function handleBannerUpload(TgBot $bot, int $chatId, string $userId, array $photos, string $lang = 'zh-Hant'): string
    {
        // Telegram 回傳多個尺寸，取 file_size 最大的；若無 file_size 則取最後一個
        $photo  = collect($photos)->sortByDesc('file_size')->first() ?? end($photos);
        $fileId = $photo['file_id'];

        try {
            // 直接存 file_id，不下載到本地
            Member::updateOrCreate(
                ['account' => 'tg_' . $userId],
                ['banner'  => $fileId]
            );

            $this->clearState($bot->id, $chatId);

            // 用 file_id 回傳預覽
            $this->sendPhotoById($bot->token, $chatId, $fileId, $this->t('banner_success', $lang));

            return '✅ Banner 更新成功';

        } catch (\Exception $e) {
            Log::channel('tg_webhook')->error('[TG Webhook] handleBannerUpload 失敗', [
                'chat_id' => $chatId,
                'error'   => $e->getMessage(),
            ]);
            $this->sendMessage($bot->token, $chatId, $this->t('banner_update_fail', $lang));
            return '❌ Banner 更新失敗：' . $e->getMessage();
        }
    }

    // ─── 回覆組建：我的持股 ───────────────────────────────────────
    private function buildPortfolioReply(int $botId, int $chatId, string $lang = 'zh-Hant'): array
    {
        $holdings = TgHolding::where('bot_id', $botId)
            ->where('tg_chat_id', $chatId)
            ->get();

        $addButton  = [['text' => $this->t('portfolio_btn_add',     $lang), 'callback_data' => 'holding_add']];
        $capitalBtn = [['text' => $this->t('portfolio_btn_capital',  $lang), 'callback_data' => 'set_capital']];

        if ($holdings->isEmpty()) {
            return [
                $this->t('portfolio_empty', $lang),
                ['inline_keyboard' => [$addButton, $capitalBtn]],
            ];
        }

        $feeRate = 0.001425;
        $taxRate = 0.003;

        $wallet          = TgWallet::where('bot_id', $botId)->where('tg_chat_id', $chatId)->first();
        $capital         = $wallet ? (float) $wallet->capital : null;

        // 融資年利率（從 admin_configs 取得，預設 6.5%）
        $marginAnnualRate = (float) (getConfig('margin_interest_rate') ?: 6.5) / 100;

        $totalSelfCost    = 0;  // 自備款合計
        $totalOriginVal   = 0;  // 買進原始市值合計
        $totalCurrentVal  = 0;  // 當前市值合計
        $totalTxCost      = 0;  // 預估交易成本合計（買進手續費 + 賣出手續費+稅）
        $totalInterest    = 0;  // 融資利息合計
        $lines            = [];
        $delButtons       = [];

        // 按股票代號分組顯示
        $grouped = $holdings->groupBy('stock_code');

        foreach ($grouped as $stockCode => $group) {
            $quote    = $this->fetchStockQuote($stockCode);
            $curPrice = $quote ? (float) $quote['price'] : null;

            // 當前價格與漲跌幅（整組共用）
            $curPriceStr = '';
            if ($quote && $curPrice !== null) {
                $diff = isset($quote['priceChange'])    ? (float) $quote['priceChange']    : null;
                $pct  = isset($quote['priceChangePct']) ? (float) $quote['priceChangePct'] : null;
                $curPriceStr = $this->t('portfolio_cur_price', $lang) . "：NT$" . $curPrice;
                if ($diff !== null && $pct !== null) {
                    $s = $diff >= 0 ? '+' : '';
                    $a = $diff >= 0 ? '📈' : '📉';
                    $curPriceStr .= "　{$a}{$s}" . number_format($diff, 2) . "（{$s}" . number_format($pct, 2) . "%）";
                }
            }

            $firstName  = $group->first()->stock_name;
            $totalShares = $group->sum('shares');
            $lotCount   = $group->count();

            // 處置股標記（從 Redis cache 取）
            $disposalTag = $this->isDisposedCached($stockCode) ? ' ' . $this->t('stock_disposal_tag', $lang) : '';

            // 分組標頭
            $groupHeader = "📌 {$firstName}（{$stockCode}）{$disposalTag}" . $this->t('portfolio_total_label', $lang) . " " . $this->sharesDisplay($totalShares);
            if ($curPriceStr) {
                $groupHeader .= "\n   {$curPriceStr}";
            }

            $lotLines       = [];
            $groupCurrentVal = 0;
            $groupNetProfit  = 0;

            foreach ($group as $idx => $h) {
                $buyPrice    = (float) $h->buy_price;
                $originValue = $buyPrice > 0 ? $buyPrice * $h->shares : (float) $h->total_cost;
                $selfCost    = (float) $h->total_cost;
                $curValue    = $curPrice !== null ? $curPrice * $h->shares : null;

                $buyFee  = $buyPrice > 0 ? (int) ceil($originValue * $feeRate) : 0;
                $sellFee = $curValue !== null ? (int) ceil($curValue * $feeRate) : 0;
                $sellTax = $curValue !== null ? (int) ceil($curValue * $taxRate)  : 0;
                $txCost  = $buyFee + $sellFee + $sellTax;

                $interest = 0;
                $daysHeld = 1;
                if ($h->is_margin && $buyPrice > 0) {
                    $loanAmount = $originValue * 0.6;
                    $daysHeld   = max(1, (int) $h->created_at->diffInDays(now()));
                    $interest   = (int) round($loanAmount * $marginAnnualRate * $daysHeld / 365);
                }

                $totalSelfCost  += $selfCost;
                $totalOriginVal += $originValue;
                $totalTxCost    += $txCost;
                $totalInterest  += $interest;
                if ($curValue !== null) {
                    $totalCurrentVal += $curValue;
                    $groupCurrentVal += $curValue;
                }

                $marginTag = $h->is_margin ? $this->t('holding_margin_tag', $lang) : $this->t('holding_cash_tag', $lang);
                $buyStr    = $buyPrice > 0 ? $this->t('portfolio_bought', $lang) . "：NT$" . $buyPrice : '';

                $stockProfit = $curValue !== null ? $curValue - $originValue - $txCost - $interest : null;
                if ($stockProfit !== null) {
                    $groupNetProfit += $stockProfit;
                }

                $profitStr = '';
                if ($stockProfit !== null) {
                    $sign        = $stockProfit >= 0 ? '+' : '';
                    $txCostStr   = 'NT$' . number_format($txCost, 0);
                    $interestStr = $interest > 0
                        ? "　" . $this->t('portfolio_interest_lbl', $lang) . "：NT$" . number_format($interest, 0) . "（{$daysHeld}" . $this->t('portfolio_days_unit', $lang) . "）"
                        : '';
                    $profitStr   = "\n      " . $this->t('portfolio_fees', $lang) . "：{$txCostStr}" . $this->t('portfolio_fees_detail', $lang) . "{$interestStr}　" . $this->t('portfolio_net_pnl', $lang) . "：{$sign}NT$" . number_format($stockProfit, 0);
                }

                $isLast    = $idx === $group->keys()->last();
                $prefix    = $isLast ? '   └' : '   ├';
                $curValStr = $curValue !== null ? $this->t('portfolio_cur_val', $lang) . '：NT$' . number_format($curValue, 0) : $this->t('portfolio_quote_fail', $lang);

                $lotLines[] = "{$prefix} " . $this->sharesDisplay($h->shares) . "·{$marginTag}　{$buyStr}\n      {$curValStr}{$profitStr}";
            }

            // 按 is_margin 分組各加一顆賣出按鈕（合併同股同類型全部持股）
            $marginGroups = $group->groupBy('is_margin');
            $hasMultipleTypes = $marginGroups->count() > 1;
            foreach ($marginGroups as $marginVal => $mGroup) {
                $typeTag = $hasMultipleTypes ? ('(' . ($marginVal ? $this->t('holding_margin_tag', $lang) : $this->t('holding_cash_tag', $lang)) . ')') : '';
                $delButtons[] = ['text' => "💰 賣出 {$stockCode}{$typeTag}", 'callback_data' => "holding_sell_c_{$stockCode}_{$marginVal}"];
            }

            // 多筆時顯示分組合計
            $groupSummary = '';
            if ($lotCount > 1 && $groupCurrentVal > 0) {
                $sign         = $groupNetProfit >= 0 ? '+' : '';
                $groupSummary = "\n   " . $this->t('portfolio_total_val', $lang) . "：NT$" . number_format($groupCurrentVal, 0)
                              . "　" . $this->t('portfolio_pnl', $lang) . "：{$sign}NT$" . number_format($groupNetProfit, 0);
            }

            $lines[] = $groupHeader . "\n" . implode("\n", $lotLines) . $groupSummary;
        }

        // 損益摘要
        $profitStr = "\n\n" . $this->t('portfolio_self_cost', $lang) . "：NT$" . number_format($totalSelfCost, 0)
                   . "　" . $this->t('portfolio_orig_val', $lang) . "：NT$" . number_format($totalOriginVal, 0);
        if ($totalCurrentVal > 0) {
            $netProfit = $totalCurrentVal - $totalOriginVal - $totalTxCost - $totalInterest;
            $roi       = $totalSelfCost > 0 ? ($netProfit / $totalSelfCost * 100) : 0;
            $sign      = $netProfit >= 0 ? '+' : '';
            $profitStr .= "\n" . $this->t('portfolio_cur_total', $lang) . "：NT$" . number_format($totalCurrentVal, 0)
                        . "\n" . $this->t('portfolio_est_fees', $lang) . "：NT$" . number_format($totalTxCost, 0);
            if ($totalInterest > 0) {
                $profitStr .= "\n" . $this->t('portfolio_margin_int', $lang) . "：NT$" . number_format($totalInterest, 0)
                            . "（" . $this->t('portfolio_ann_rate', $lang) . " " . number_format($marginAnnualRate * 100, 1) . "%）";
            }
            $profitStr .= "\n" . $this->t('portfolio_net_pnl2', $lang) . "：{$sign}NT$" . number_format($netProfit, 0)
                        . "　" . $this->t('portfolio_self_roi', $lang) . "：{$sign}" . number_format($roi, 2) . "%";
        }

        $today = Carbon::now('Asia/Taipei')->toDateString();

        // T+2 待付交割款（只算買入方向，供帳戶資金計算用）
        $pendingBuySettlements = TgSettlement::where('bot_id', $botId)
            ->where('tg_chat_id', $chatId)
            ->where('is_settled', 0)
            ->where('settle_date', '>=', $today)
            ->where('stock_code', '!=', 'MANUAL')
            ->where(fn($q) => $q->where('direction', 'buy')->orWhereNull('direction'))
            ->get();

        $totalPendingBuy = (float) $pendingBuySettlements->sum('settlement_amount');

        // 所有待交割（買+賣）按日期算每日淨額
        $allPendingSettlements = TgSettlement::where('bot_id', $botId)
            ->where('tg_chat_id', $chatId)
            ->where('is_settled', 0)
            ->where('settle_date', '>=', $today)
            ->where('stock_code', '!=', 'MANUAL')
            ->orderBy('settle_date')
            ->get();

        // 每日淨額：賣出(+) - 買入(-)
        $byDate = $allPendingSettlements->groupBy(fn($s) => $s->settle_date->format('m/d'));
        $settleLines = [];
        $grandNet = 0;
        foreach ($byDate as $dateStr => $group) {
            $dayBuy  = $group->filter(fn($s) => ($s->direction ?? 'buy') !== 'sell')->sum('settlement_amount');
            $daySell = $group->filter(fn($s) => ($s->direction ?? 'buy') === 'sell')->sum('settlement_amount');
            $dayNet  = $daySell - $dayBuy;
            $grandNet += $dayNet;
            $sign  = $dayNet >= 0 ? '+' : '';
            $emoji = $dayNet >= 0 ? '🟢' : '🔴';
            $settleLines[] = "   ├ {$dateStr} " . $this->t('portfolio_settle_lbl', $lang) . "：{$emoji}{$sign}NT$" . number_format($dayNet, 0);
        }

        // 帳戶資金區塊
        if ($capital !== null) {
            $totalCapital = $capital + $totalSelfCost;
            $afterSettle  = $capital + $grandNet;
            $warning      = $afterSettle < 0 ? ' ⚠️' : '';
            $profitStr   .= "\n\n" . $this->t('portfolio_total_cap', $lang) . "：NT$" . number_format($totalCapital, 0)
                          . "\n   ├ " . $this->t('portfolio_holding_cost', $lang) . "：NT$" . number_format($totalSelfCost, 0)
                          . "\n   ├ " . $this->t('portfolio_cash_lbl', $lang) . "：NT$" . number_format($capital, 0);
            if (!empty($settleLines)) {
                $last = count($settleLines) - 1;
                $settleLines[$last] = str_replace('├', '├', $settleLines[$last]);
                $profitStr .= "\n" . implode("\n", $settleLines);
                $profitStr .= "\n   └ " . $this->t('portfolio_post_settle', $lang) . "：NT$" . number_format($afterSettle, 0) . $warning;
            } else {
                $profitStr .= "\n   └ " . $this->t('portfolio_available', $lang) . "：NT$" . number_format($capital, 0);
            }
        } else {
            $profitStr .= "\n\n" . $this->t('portfolio_no_capital', $lang);
        }

        $text = $this->t('portfolio_title', $lang) . "\n\n" . implode("\n\n", $lines) . $profitStr;

        // Inline keyboard：添加 + 設定資金 + 交割款查詢 + 賣出（每排最多2個）
        $settleQueryBtn = [['text' => $this->t('portfolio_btn_settle', $lang), 'callback_data' => 'view_settlements']];
        $inlineRows = [$addButton, $capitalBtn, $settleQueryBtn];
        foreach (array_chunk($delButtons, 2) as $row) {
            $inlineRows[] = $row;
        }

        return [$text, ['inline_keyboard' => $inlineRows]];
    }

    // ─── 回覆組建：交割款查詢 ────────────────────────────────────────
    private function buildSettlementReply(int $botId, int $chatId): array
    {
        $today = Carbon::now('Asia/Taipei')->toDateString();

        $settlements = TgSettlement::where('bot_id', $botId)
            ->where('tg_chat_id', $chatId)
            ->where('is_settled', 0)
            ->where('settle_date', '>=', $today)
            ->where('stock_code', '!=', 'MANUAL')
            ->orderBy('settle_date')
            ->orderBy('direction')
            ->get();

        if ($settlements->isEmpty()) {
            return ["📅 交割款查詢\n\n目前無待交割款項。", null];
        }

        // 按交割日分組
        $byDate = $settlements->groupBy(fn($s) => Carbon::parse($s->settle_date)->format('m/d'));

        $text = "📅 交割款查詢\n";

        $grandNet = 0;

        foreach ($byDate as $dateStr => $group) {
            $buys  = $group->filter(fn($s) => ($s->direction ?? 'buy') !== 'sell');
            $sells = $group->filter(fn($s) => ($s->direction ?? 'buy') === 'sell');

            $totalOut = (float) $buys->sum('settlement_amount');
            $totalIn  = (float) $sells->sum('settlement_amount');
            $net      = $totalIn - $totalOut;
            $grandNet += $net;

            $sign  = $net >= 0 ? '+' : '';
            $emoji = $net >= 0 ? '🟢' : '🔴';

            $text .= "\n━━ {$dateStr} 交割　{$emoji} 淨額：{$sign}NT$" . number_format($net, 0) . " ━━";

            foreach ($buys as $s) {
                $text .= "\n📌 {$s->stock_name}（{$s->stock_code}）" . $this->sharesDisplay($s->shares) . "　買進：NT$" . $s->buy_price
                       . "\n   💸 應付：-NT$" . number_format($s->settlement_amount, 0);
            }
            foreach ($sells as $s) {
                $text .= "\n💚 {$s->stock_name}（{$s->stock_code}）" . $this->sharesDisplay($s->shares) . "　賣出：NT$" . $s->buy_price
                       . "\n   💰 待收：+NT$" . number_format($s->settlement_amount, 0);
            }
        }

        $grandSign  = $grandNet >= 0 ? '+' : '';
        $grandEmoji = $grandNet >= 0 ? '🟢' : '🔴';
        $text .= "\n\n─────────────────"
               . "\n{$grandEmoji} 合計淨額：{$grandSign}NT$" . number_format($grandNet, 0)
               . "\n（正數 = 淨收款　負數 = 淨付款）";

        return [$text, null];
    }

    // ─── 股數顯示 helper ─────────────────────────────────────────
    private function sharesDisplay(int $shares): string
    {
        $lots = intdiv($shares, 1000);
        $odd  = $shares % 1000;

        if ($odd === 0) {
            return number_format($shares) . '股（' . $lots . '張）';
        }
        if ($lots > 0) {
            return number_format($shares) . '股（' . $lots . '張' . $odd . '零股）';
        }
        return number_format($shares) . '股（零股）';
    }

    // ─── Yahoo Finance：股票現價 ──────────────────────────────────
    // ─── 股價快取 TTL 計算 ────────────────────────────────────────
    // 台股交易時間：週一～五 09:00～13:30
    // 盤中：快取 5 分鐘
    // 盤後/假日：快取到下一個交易日 09:00
    private function stockCacheTtl(): int
    {
        $now = Carbon::now('Asia/Taipei');

        $isWeekday    = $now->isWeekday();                          // 週一～五
        $afterOpen    = $now->format('H:i') >= '09:00';
        $beforeClose  = $now->format('H:i') <  '13:30';
        $inTradingHrs = $isWeekday && $afterOpen && $beforeClose;

        if ($inTradingHrs) {
            return 10; // 盤中 10 秒
        }

        // 找下一個交易日 09:00
        $next = $now->copy()->setTimeFromTimeString('09:00:00');

        // 若今天是交易日但還沒開盤（早於 09:00），就是今天
        if ($isWeekday && !$afterOpen) {
            // $next 已是今天 09:00，無需調整
        } else {
            // 已過盤後或今天是假日 → 往後找
            $next->addDay();
            while (!$next->isWeekday()) {
                $next->addDay();
            }
        }

        $seconds = max(60, (int) $now->diffInSeconds($next));
        return $seconds;
    }

    private function fetchStockQuote(string $code): ?array
    {
        $cacheKey    = 'tw-' . strtoupper($code);
        $cachedData  = Cache::get($cacheKey);
        if ($cachedData !== null) {
            return $cachedData;
        }

        $symbolsJson = json_encode([$code . '.TW']);
        $symbolsEnc  = rawurlencode($symbolsJson);
        $base        = rtrim(getConfig('yahoo_stock_chart_base'), '/');
        $url         = "{$base};period=d;symbols={$symbolsEnc}";

        try {
            $client = new Client(['timeout' => 10]);
            $res    = $client->get($url, [
                'query' => [
                    'intl'       => 'tw',
                    'lang'       => 'zh-Hant-TW',
                    'region'     => 'TW',
                    'site'       => 'finance',
                    'returnMeta' => 'true',
                ],
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Referer'    => 'https://tw.stock.yahoo.com/',
                    'Accept'     => 'application/json, text/plain, */*',
                ],
            ]);

            $rawBody = (string) $res->getBody();
            $data    = json_decode($rawBody, true);

            $chart = $data['data'][0]['chart'] ?? null;

            if (!$chart) {
                return null;
            }

            $quote = $chart['quote'] ?? [];
            $meta  = $chart['meta']  ?? [];
            $price = $quote['price'] ?? null;

            if ($price === null) {
                return null;
            }

            // 股票名稱：優先使用 meta，其次 quote
            $name = $meta['name']
                ?? $meta['longName']
                ?? $meta['shortName']
                ?? $quote['shortName']
                ?? $quote['name']
                ?? $code;

            $result = [
                'name'           => $name,
                'price'          => $price,
                'priceChange'    => $quote['priceChange']      ?? $quote['change']          ?? null,
                'priceChangePct' => $quote['priceChangePercent'] ?? $quote['changePercent'] ?? null,
                'volume'         => $quote['volume']           ?? null,
            ];

            Cache::put($cacheKey, $result, $this->stockCacheTtl());

            return $result;
        } catch (\Exception $e) {
            Log::channel('tg_webhook')->warning('[TG Webhook] fetchStockQuote 失敗', [
                'code'  => $code,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ─── Yahoo Finance：三大法人 ──────────────────────────────────
    private function fetchInstitutional(string $code): ?array
    {
        $symbol = $code . '.TW';
        $base   = rtrim(getConfig('yahoo_institutional_base'), '/');
        $url    = "{$base};limit=10;period=day;symbol={$symbol}";

        try {
            $client = new Client(['timeout' => 10]);
            $res    = $client->get($url, [
                'query' => [
                    'intl'   => 'tw',
                    'lang'   => 'zh-Hant-TW',
                    'region' => 'TW',
                    'site'   => 'finance',
                ],
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Referer'    => "https://tw.stock.yahoo.com/quote/{$symbol}",
                    'Accept'     => 'application/json, text/plain, */*',
                ],
            ]);

            $data = json_decode((string) $res->getBody(), true);
            $list = $data['list'] ?? [];

            if (empty($list)) {
                Log::channel('tg_webhook')->info('[TG Webhook] fetchInstitutional 無資料', [
                    'code' => $code,
                    'raw'  => array_keys($data ?? []),
                ]);
                return null;
            }

            $result = [];
            foreach ($list as $row) {
                // 欄位單位已是張（VolK = 千股 = 張）
                $result[] = [
                    'date'    => $row['formattedDate'] ?? substr($row['date'] ?? '', 0, 10),
                    'foreign' => isset($row['foreignDiffVolK'])          ? (int) $row['foreignDiffVolK']          : null,
                    'trust'   => isset($row['investmentTrustDiffVolK'])  ? (int) $row['investmentTrustDiffVolK']  : null,
                    'dealer'  => isset($row['dealerDiffVolK'])           ? (int) $row['dealerDiffVolK']           : null,
                ];
            }

            return $result ?: null;
        } catch (\Exception $e) {
            Log::channel('tg_webhook')->warning('[TG Webhook] fetchInstitutional 失敗', [
                'code'  => $code,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ─── Yahoo Finance：月營收 ───────────────────────────────────
    private function fetchRevenue(string $code): ?array
    {
        $symbol  = $code . '.TW';
        $baseUrl = getConfig('yahoo_revenue_base')
            ?: 'https://tw.stock.yahoo.com/_td-stock/api/resource/StockServices.revenues';
        $url = rtrim($baseUrl, '/') . ";period=month;symbol={$symbol}";

        try {
            $client = new Client(['timeout' => 10]);
            $res    = $client->get($url, [
                'query' => [
                    'intl'   => 'tw',
                    'lang'   => 'zh-Hant-TW',
                    'region' => 'TW',
                    'site'   => 'finance',
                ],
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Referer'    => "https://tw.stock.yahoo.com/quote/{$symbol}",
                    'Accept'     => 'application/json, text/plain, */*',
                ],
            ]);

            $data     = json_decode((string) $res->getBody(), true);
            $revenues = $data['data']['result']['revenues'] ?? [];

            return !empty($revenues) ? array_slice($revenues, 0, 6) : null;
        } catch (\Exception $e) {
            Log::channel('tg_webhook')->warning('[TG Webhook] fetchRevenue 失敗', [
                'code'  => $code,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

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

    // ════════════════════════════════════════════════════════════
    //  AV Bot（type == 2）
    // ════════════════════════════════════════════════════════════

    private const AV_TAGS = [
        '巨乳','美乳','中出','潮吹','人妻','美少女','OL','制服',
        '素人','無碼','高清','4K','企劃','單體','系列','SM',
        '女同','3P','口交','肛交','泳裝','護士','教師',
    ];

    private function handleAvBot(TgBot $bot, array $update): \Illuminate\Http\JsonResponse
    {
        // callback_query
        if (isset($update['callback_query'])) {
            $cq     = $update['callback_query'];
            $chatId = (int) $cq['message']['chat']['id'];
            $data   = $cq['data'];
            $this->answerCallbackQuery($bot->token, $cq['id']);

            if ($data === 'av_tag_save') {
                $this->clearState($bot->id, $chatId);
                $this->sendMessage($bot->token, $chatId, "✅ 喜好設定已儲存！", $this->getAvKeyboard());
            } elseif ($data === 'av_push_toggle') {
                $pref = \App\AvUserPref::firstOrCreate(['bot_id' => $bot->id, 'tg_chat_id' => $chatId]);
                $pref->update(['push_enabled' => !$pref->push_enabled]);
                [$text, $markup] = $this->buildAvTagMenu($bot, $chatId);
                $this->sendMessage($bot->token, $chatId, $text, $markup);
            } elseif (str_starts_with($data, 'av_tag_')) {
                $tag = substr($data, 7);
                $this->avToggleTag($bot, $chatId, $tag);
                [$text, $markup] = $this->buildAvTagMenu($bot, $chatId);
                $this->sendMessage($bot->token, $chatId, $text, $markup);
            }
            return response()->json(['ok' => true]);
        }

        $msg      = $update['message'] ?? $update['edited_message'] ?? null;
        if (!$msg) return response()->json(['ok' => true]);

        $chatId   = (int) $msg['chat']['id'];
        $text     = trim($msg['text'] ?? '');

        if (str_starts_with($text, '/start') || in_array($text, ['開始', 'start'])) {
            $this->sendMessage($bot->token, $chatId, "🔞 AV 速報機器人\n\n請選擇功能：", $this->getAvKeyboard());
            return response()->json(['ok' => true]);
        }

        if ($text === '🎬 今日新片') {
            $reply = $this->buildAvTodayReply($bot, $chatId);
            $this->sendMessage($bot->token, $chatId, $reply, $this->getAvKeyboard(), 'HTML');
            return response()->json(['ok' => true]);
        }

        if ($text === '⭐ 喜好設定') {
            [$menuText, $markup] = $this->buildAvTagMenu($bot, $chatId);
            $this->sendMessage($bot->token, $chatId, $menuText, $markup);
            return response()->json(['ok' => true]);
        }

        $this->sendMessage($bot->token, $chatId, "請選擇功能：", $this->getAvKeyboard());
        return response()->json(['ok' => true]);
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

    private function buildAvTagMenu(TgBot $bot, int $chatId): array
    {
        $pref     = \App\AvUserPref::firstOrCreate(['bot_id' => $bot->id, 'tg_chat_id' => $chatId]);
        $selected = $pref->fav_tags ?? [];
        $pushOn   = $pref->push_enabled;

        $text = "⭐ <b>喜好標籤設定</b>\n已選 " . count($selected) . " 個標籤\n點選標籤切換選取，完成後按「儲存」";

        $rows = [];
        $chunks = array_chunk(self::AV_TAGS, 3);
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
        $pref = \App\AvUserPref::firstOrCreate(['bot_id' => $bot->id, 'tg_chat_id' => $chatId]);
        $tags = $pref->fav_tags ?? [];
        $pos  = array_search($tag, $tags);
        if ($pos !== false) {
            array_splice($tags, $pos, 1);
        } else {
            $tags[] = $tag;
        }
        $pref->update(['fav_tags' => array_values($tags)]);
    }

    private function buildAvTodayReply(TgBot $bot, int $chatId): string
    {
        $today = now('Asia/Taipei')->toDateString();

        // 今日熱門（先查今日點擊次數最多的 code）
        $topCodes = \Illuminate\Support\Facades\DB::table('ya_av_video_clicks')
            ->whereDate('clicked_at', $today)
            ->select('video_code', \Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
            ->groupBy('video_code')
            ->orderBy('cnt', 'desc')
            ->limit(5)
            ->pluck('video_code')
            ->toArray();

        if (!empty($topCodes)) {
            $hot = \App\AvVideo::whereIn('code', $topCodes)->get()
                ->sortBy(fn($v) => array_search($v->code, $topCodes))->values();
        } else {
            // 無點擊資料 → 今日新片隨機
            $hot = \App\AvVideo::whereDate('release_date', $today)->inRandomOrder()->limit(5)->get();
        }

        // 今日無片 → 近 3 天隨機
        if ($hot->isEmpty()) {
            $hot = \App\AvVideo::where('release_date', '>=', now()->subDays(3)->toDateString())
                ->inRandomOrder()->limit(5)->get();
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
        \Illuminate\Support\Facades\DB::table('ya_av_video_clicks')->insert($inserts);

        $lines = ["🎬 <b>今日新片</b>（" . $today . "）\n"];
        foreach ($hot as $v) {
            $actress = $v->actresses ? implode(' / ', $v->actresses) : '-';
            $tags    = $v->tags ? implode(' ｜ ', array_slice($v->tags, 0, 5)) : '';
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
