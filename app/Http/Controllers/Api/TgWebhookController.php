<?php

namespace App\Http\Controllers\Api;

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

            // 融資確認按鈕（狀態機 step3）
            if ($stateObj && $stateObj->state === 'holding_step3') {
                [$replyText, $replyMarkup] = $this->handleHoldingStep3Callback($bot, $chatId, $data, $stateObj);

            // 添加持股
            } elseif ($data === 'holding_add') {
                $this->setState($bot->id, $chatId, 'holding_step1');
                $replyText = "➕ 請輸入要添加的股票代號\n（例如：2317）\n\n輸入「取消」可返回主選單";

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
                    $typeTag     = $isMargin ? '（融資）' : '';
                    $this->setState($bot->id, $chatId, 'sell_step1', [
                        'stock_code' => $stockCode,
                        'is_margin'  => $isMargin,
                    ]);
                    $replyText = "💰 賣出 {$stockName}（{$stockCode}）{$typeTag}\n"
                               . "持有：" . $this->sharesDisplay($totalShares) . "\n\n"
                               . "請輸入賣出股數（最多 {$totalShares} 股）：\n\n輸入「取消」可返回";
                } else {
                    [$replyText, $replyMarkup] = $this->buildPortfolioReply($bot->id, $chatId);
                }

            // 交割款查詢
            } elseif ($data === 'view_settlements') {
                [$replyText, $replyMarkup] = $this->buildSettlementReply($bot->id, $chatId);

            // 設定資金 — 先選模式
            } elseif ($data === 'set_capital') {
                $replyText   = "⚙️ 設定資金模式\n\n"
                             . "📌 <b>總資金設置</b>：直接輸入您的總資金（含持股部位）\n"
                             . "📌 <b>剩餘資金設置</b>：輸入帳戶現金餘額，系統自動加上持股成本計算總資金\n";
                $replyMarkup = ['inline_keyboard' => [[
                    ['text' => '💼 總資金設置',   'callback_data' => 'set_capital_total'],
                    ['text' => '💵 剩餘資金設置', 'callback_data' => 'set_capital_remain'],
                ]]];

            // 設定資金 — 總資金模式
            } elseif ($data === 'set_capital_total') {
                $this->setState($bot->id, $chatId, 'set_capital_total');
                $replyText = "💼 總資金設置\n請輸入您的總資金（台幣整數，例如：2000000）：\n\n輸入「取消」可返回";

            // 設定資金 — 剩餘資金模式
            } elseif ($data === 'set_capital_remain') {
                $this->setState($bot->id, $chatId, 'set_capital_remain');
                $replyText = "💵 剩餘資金設置\n請輸入您目前的帳戶現金餘額（台幣整數，例如：500000）：\n系統將自動加上持股成本計算總資金\n\n輸入「取消」可返回";

            // 設置選單
            } elseif ($data === 'portfolio_settings') {
                $replyText   = "⚙️ 設置\n\n請選擇要設置的項目：";
                $replyMarkup = ['inline_keyboard' => [
                    [['text' => '🖼 我的 Banner', 'callback_data' => 'set_banner']],
                ]];

            // 設置 Banner — 上傳說明
            } elseif ($data === 'set_banner') {
                $this->setState($bot->id, $chatId, 'upload_banner');
                $replyText = "🖼 設置我的 Banner\n\n"
                           . "請直接發送一張圖片作為您的 Banner。\n"
                           . "支援格式：JPG、PNG、GIF\n\n"
                           . "圖片將顯示在「我的持股」資訊上方。\n\n"
                           . "輸入「取消」可返回";

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
                    $result = $this->handleBannerUpload($bot, $chatId, $userId, $msg['photo']);
                    $this->logMessage($bot->id, $userId, $username, $chatId, $result, 2, 'reply');
                }

                return response()->json(['ok' => true]);
            }

            $this->logMessage($bot->id, $userId, $username, $chatId, $text, 1, 'text');

            $replyText   = null;
            $replyMarkup = null;

            // 取消 → 清除狀態，顯示主選單
            if (in_array($text, ['取消', '❌ 取消', '/cancel'])) {
                $this->clearState($bot->id, $chatId);
                $replyText   = '已取消，請選擇查詢項目：';
                $replyMarkup = $this->getMainKeyboard();

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
        if (str_contains($text, '布蘭特原油')) {
            return [$this->buildOilReply(), null];
        }
        if (str_contains($text, '台指期貨')) {
            return [$this->buildWtxReply(), null];
        }
        if (str_contains($text, 'VIX') || str_contains($text, '恐慌指數')) {
            return [$this->buildVixReply(), null];
        }
        if (str_contains($text, '台股查詢')) {
            $this->setState($bot->id, $chatId, 'stock_query');
            return ["📊 台股查詢\n請輸入股票代號（例如：2317）\n\n輸入「取消」可返回", null];
        }
        if (str_contains($text, '我的持股')) {
            $member     = Member::where('account', 'tg_' . $userId)->first();
            $bannerPath = ($member && $member->banner)
                ? public_path($member->banner)
                : public_path('assets/images/login-bg.jpg');
            [$portfolioText, $portfolioMarkup] = $this->buildPortfolioReply($bot->id, $chatId);
            if (file_exists($bannerPath)) {
                // caption 上限 1024 字元；超過時截斷並補 ...
                $caption = mb_strlen($portfolioText) <= 1024
                    ? $portfolioText
                    : mb_substr($portfolioText, 0, 1021) . '...';
                $this->sendPhoto($bot->token, $chatId, $bannerPath, $caption, $portfolioMarkup);
                return [null, null];
            }
            return [$portfolioText, $portfolioMarkup];
        }

        // 其他（/start, 任意文字）→ 顯示主選單
        return ['請選擇查詢項目：', $this->getMainKeyboard()];
    }

    // ─── 狀態機分派 ──────────────────────────────────────────────
    private function handleState(TgBot $bot, int $chatId, string $userId, string $text, TgState $stateObj): array
    {
        switch ($stateObj->state) {
            case 'stock_query':
                return $this->handleStockQuery($bot, $chatId, $text);
            case 'holding_step1':
                return $this->handleHoldingStep1($bot, $chatId, $text, $stateObj);
            case 'holding_step2':
                return $this->handleHoldingStep2($bot, $chatId, $text, $stateObj);
            case 'holding_step3':
                return ["請點選上方按鈕選擇是否融資：\n\n輸入「取消」可返回主選單", null];
            case 'holding_step4':
                return $this->handleHoldingStep4($bot, $chatId, $userId, $text, $stateObj);
            case 'sell_step1':
                return $this->handleSellStep1($bot, $chatId, $userId, $text, $stateObj);
            case 'sell_step2':
                return $this->handleSellStep2($bot, $chatId, $userId, $text, $stateObj);
            case 'set_capital_total':
                return $this->handleSetCapital($bot, $chatId, $userId, $text, 'total');
            case 'set_capital_remain':
                return $this->handleSetCapital($bot, $chatId, $userId, $text, 'remain');
            case 'upload_banner':
                return ["🖼 請直接發送一張圖片作為 Banner。\n支援格式：JPG、PNG、GIF\n\n輸入「取消」可返回", null];
            default:
                $this->clearState($bot->id, $chatId);
                return ['請選擇查詢項目：', $this->getMainKeyboard()];
        }
    }

    // ─── 台股查詢 ─────────────────────────────────────────────────
    private function handleStockQuery(TgBot $bot, int $chatId, string $text): array
    {
        $code  = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $text));
        $quote = $this->fetchStockQuote($code);

        if (!$quote) {
            return ["❌ 找不到股票代號「{$text}」，請重新輸入：\n\n輸入「取消」可返回", null];
        }

        $this->clearState($bot->id, $chatId);
        return [$this->buildStockReply($code, $quote), null];
    }

    // ─── 持股添加：step1 輸入代號 ─────────────────────────────────
    private function handleHoldingStep1(TgBot $bot, int $chatId, string $text, TgState $stateObj): array
    {
        $code  = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $text));
        $quote = $this->fetchStockQuote($code);

        if (!$quote) {
            return ["❌ 找不到股票代號「{$text}」，請重新輸入：\n\n輸入「取消」可返回", null];
        }

        $this->setState($bot->id, $chatId, 'holding_step2', [
            'code' => $code,
            'name' => $quote['name'],
        ]);

        return [
            "✅ 找到：{$quote['name']}（{$code}）\n💰 當前價：{$quote['price']}\n\n請輸入持有【股數】（整股：5張請輸入 5000，零股：500股請輸入 500）：",
            null,
        ];
    }

    // ─── 持股添加：step2 輸入張數 ─────────────────────────────────
    private function handleHoldingStep2(TgBot $bot, int $chatId, string $text, TgState $stateObj): array
    {
        if (!ctype_digit($text) || (int) $text <= 0) {
            return ['❌ 股數請輸入正整數（例如：5000；零股例如：500）：', null];
        }

        $data           = $stateObj->state_data ?? [];
        $data['shares'] = (int) $text;
        $this->setState($bot->id, $chatId, 'holding_step3', $data);

        $inlineKeyboard = [
            'inline_keyboard' => [[
                ['text' => '✅ 是（融資）', 'callback_data' => 'margin_yes'],
                ['text' => '❌ 否（現股）', 'callback_data' => 'margin_no'],
            ]],
        ];

        return ["是否融資購買？", $inlineKeyboard];
    }

    // ─── 持股添加：step3 融資確認（callback）────────────────────────
    private function handleHoldingStep3Callback(TgBot $bot, int $chatId, string $data, TgState $stateObj): array
    {
        if (!in_array($data, ['margin_yes', 'margin_no'])) {
            return [null, null];
        }

        $stateData             = $stateObj->state_data ?? [];
        $stateData['is_margin'] = ($data === 'margin_yes') ? 1 : 0;
        $this->setState($bot->id, $chatId, 'holding_step4', $stateData);

        $name   = $stateData['name']   ?? '';
        $shares = $stateData['shares'] ?? 0;

        return ["請輸入當時買進的每股價格（元）：\n例如：{$name} 買 " . $this->sharesDisplay((int)$shares) . "，每股 53.5 就輸入 53.5\n\n輸入「取消」可返回", null];
    }

    // ─── 持股添加：step4 輸入買進價格，自動計算成本 ─────────────────
    private function handleHoldingStep4(TgBot $bot, int $chatId, string $userId, string $text, TgState $stateObj): array
    {
        $buyPrice = (float) str_replace([',', '，'], '', $text);

        if ($buyPrice <= 0) {
            return ['❌ 請輸入有效的每股買進價格（例如：53.5）：', null];
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

        // 建立 T+2 交割記錄（不立即扣款，由每日 settle:payments cron 處理）
        // 現股：交割全額市值 + 手續費
        // 融資：交割自備款（40%）+ 手續費
        $buyFee     = (int) ceil($marketVal * 0.001425);
        $settleAmt  = $cost + $buyFee;  // $cost 已是現股=全額 / 融資=40%
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

        $this->clearState($bot->id, $chatId);

        $marginTag = $isMargin ? '融資' : '現股';
        $confirm   = "✅ 已添加持股：\n"
                   . "📌 {$data['name']}（{$data['code']}）\n"
                   . "📦 " . $this->sharesDisplay($shares) . " · {$marginTag}\n"
                   . "💵 買進價：NT$" . $buyPrice . "　市值：NT$" . number_format($marketVal, 0) . "\n"
                   . "💰 持有成本：NT$" . number_format($cost, 0)
                   . ($isMargin ? "（自備 40%）" : '');

        // 先送確認訊息，再回傳持股列表
        $this->sendMessage($bot->token, $chatId, $confirm);

        return $this->buildPortfolioReply($bot->id, $chatId);
    }

    // ─── 賣出：step1 輸入賣出張數 ────────────────────────────────
    private function handleSellStep1(TgBot $bot, int $chatId, string $userId, string $text, TgState $stateObj): array
    {
        if (!ctype_digit($text) || (int) $text <= 0) {
            return ['❌ 請輸入有效的賣出股數（正整數）：', null];
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
            return $this->buildPortfolioReply($bot->id, $chatId);
        }

        $totalShares = $holdings->sum('shares');
        $sellShares  = (int) $text;
        if ($sellShares > $totalShares) {
            return ["❌ 持有只有 " . $this->sharesDisplay($totalShares) . "，請重新輸入：", null];
        }

        $data['sell_shares'] = $sellShares;
        $this->setState($bot->id, $chatId, 'sell_step2', $data);

        return [
            "請輸入每股賣出價格（元）：\n"
            . "例如：每股 55 就輸入 55\n\n輸入「取消」可返回",
            null,
        ];
    }

    // ─── 賣出：step2 輸入賣出價格，計算盈虧 ──────────────────────
    private function handleSellStep2(TgBot $bot, int $chatId, string $userId, string $text, TgState $stateObj): array
    {
        $sellPrice = (float) str_replace([',', '，'], '', $text);
        if ($sellPrice <= 0) {
            return ['❌ 請輸入有效的每股賣出價格（例如：55）：', null];
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
            return $this->buildPortfolioReply($bot->id, $chatId);
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
        $profitTag = $profit >= 0 ? '✅ 獲利' : '❌ 虧損';
        $confirm   = "📤 賣出完成：\n"
                   . "📌 {$stockName}（{$stockCode}）" . $this->sharesDisplay($sellShares) . "\n"
                   . "💵 買進均價：NT$" . $avgBuyPrice . "　賣出：NT$" . $sellPrice . "\n"
                   . "💸 手續費：NT$" . number_format($buyFee + $sellFee, 0)
                   . "　交易稅：NT$" . number_format($sellTax, 0) . "\n"
                   . "{$profitTag}：{$sign}NT$" . number_format($profit, 0);

        $this->sendMessage($bot->token, $chatId, $confirm);

        return $this->buildPortfolioReply($bot->id, $chatId);
    }

    // ─── 設定資金總額（total=直接設定 / remain=剩餘+持股成本反推）──
    private function handleSetCapital(TgBot $bot, int $chatId, string $userId, string $text, string $mode = 'total'): array
    {
        $input = (float) str_replace([',', '，', '$', 'NT$', ' '], '', $text);
        if ($input <= 0) {
            return ['❌ 請輸入有效金額（正整數，例如：1500000）：', null];
        }

        // wallet.capital 是 running balance（剩餘現金），不是總資金
        // remain 模式：直接存入剩餘現金
        // total 模式：total - 持股成本 = 剩餘現金
        $selfCostSum = (float) TgHolding::where('bot_id', $bot->id)
            ->where('tg_chat_id', $chatId)
            ->sum('total_cost');

        if ($mode === 'remain') {
            $capital = $input;
            $note    = "✅ 剩餘可用資金設定為 NT$" . number_format($capital, 0)
                     . "\n   帳戶總資金 = NT$" . number_format($capital + $selfCostSum, 0);
        } else {
            // 總資金模式：換算成剩餘現金存入（= total - 已佔用持股成本）
            $capital = $input - $selfCostSum;
            $note    = "✅ 帳戶總資金 NT$" . number_format($input, 0)
                     . "\n   持股占用 NT$" . number_format($selfCostSum, 0)
                     . "\n   → 剩餘可用 NT$" . number_format($capital, 0);
            if ($capital < 0) {
                $note .= " ⚠️（持股成本已超過總資金）";
            }
        }

        TgWallet::updateOrCreate(
            ['bot_id' => $bot->id, 'tg_chat_id' => $chatId, 'tg_user_id' => $userId],
            ['capital' => $capital]
        );

        $this->clearState($bot->id, $chatId);
        $this->sendMessage($bot->token, $chatId, $note);

        return $this->buildPortfolioReply($bot->id, $chatId);
    }

    // ─── 計算 T+2 交割日（跳過周末，不處理國定假日）────────────
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
            $diff  = (float) $latest->close - (float) $prev->close;
            $pct   = (float) $prev->close > 0 ? ($diff / (float) $prev->close * 100) : 0;
            $sign  = $diff >= 0 ? '+' : '';
            $arrow = $diff >= 0 ? '📈' : '📉';
            $changeStr = "\n{$arrow} 5分變化：{$sign}" . number_format($diff, 4) . "（{$sign}" . number_format($pct, 2) . "%）";
        }

        $vix    = OilPrice::where('ticker', 'VIX')->whereNotNull('close')->orderBy('candle_at', 'desc')->first();
        $vixStr = $vix ? "\n😨 VIX 恐慌指數：" . number_format((float) $vix->close, 2) : '';

        return "🛢 布蘭特原油\n最新價：{$latest->close}{$changeStr}\n🕐 時間：{$latest->candle_at}{$vixStr}";
    }

    // ─── 回覆組建：台指 ──────────────────────────────────────────
    private function buildWtxReply(): string
    {
        // 撈最近 6 筆（5 根 K + 1 根用來算最舊那根的變化）
        $rows = OilPrice::where('ticker', 'WTX')
            ->whereNotNull('close')
            ->orderBy('candle_at', 'desc')
            ->limit(6)
            ->get();

        if ($rows->isEmpty()) {
            return '暫無台指資料';
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
        $vixStr = $vix ? "\n\n😨 VIX 恐慌指數：" . number_format((float) $vix->close, 2) : '';

        return "📈 台指期貨（近5根K棒）\n"
             . "─────────────────\n"
             . implode("\n", array_reverse($lines))
             . $vixStr;
    }

    // ─── 回覆組建：VIX ───────────────────────────────────────────
    private function buildVixReply(): string
    {
        $latest = OilPrice::where('ticker', 'VIX')
            ->whereNotNull('close')
            ->orderBy('candle_at', 'desc')
            ->first();

        if (!$latest) {
            return '暫無 VIX 資料';
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
            $changeStr = "\n{$arrow} 5分變化：{$sign}" . number_format($diff, 2) . "（{$sign}" . number_format($pct, 2) . "%）";
        }

        return "😨 VIX 恐慌指數\n當前：" . number_format((float) $latest->close, 2) . "{$changeStr}\n🕐 時間：{$latest->candle_at}";
    }

    // ─── 回覆組建：台股查詢結果 ───────────────────────────────────
    private function buildStockReply(string $code, array $quote): string
    {
        $name  = $quote['name'];
        $price = $quote['price'];

        $changeStr = '';
        if ($quote['priceChange'] !== null && $quote['priceChangePct'] !== null) {
            $diff  = (float) $quote['priceChange'];
            $pct   = (float) $quote['priceChangePct'];
            $sign  = $diff >= 0 ? '+' : '';
            $arrow = $diff >= 0 ? '📈' : '📉';
            $changeStr = "\n{$arrow} 漲跌：{$sign}" . number_format($diff, 2) . "（{$sign}" . number_format($pct, 2) . "%）";
        }

        $volumeStr = $quote['volume'] !== null
            ? "\n📦 成交張數：" . number_format((int) round((float) $quote['volume'] / 1000)) . " 張"
            : '';

        $reply = "📊 {$name}（{$code}.TW）\n💰 成交價：{$price}{$changeStr}{$volumeStr}";

        // 三大法人
        $inst = $this->fetchInstitutional($code);
        if ($inst) {
            $rows       = array_slice($inst, 0, 10);
            $sumForeign = array_sum(array_column($rows, 'foreign'));
            $sumTrust   = array_sum(array_column($rows, 'trust'));
            $sumDealer  = array_sum(array_column($rows, 'dealer'));

            // 橫條圖：以三者絕對值最大作為基準
            $maxAbs = max(abs($sumForeign), abs($sumTrust), abs($sumDealer), 1);

            $reply .= "\n\n━━ 近10日三大法人買賣超 ━━";
            $reply .= "\n" . $this->buildInstBar('外資', $sumForeign, $maxAbs);
            $reply .= "\n" . $this->buildInstBar('投信', $sumTrust,   $maxAbs);
            $reply .= "\n" . $this->buildInstBar('自營', $sumDealer,  $maxAbs);

            // 每日明細（緊湊格式）
            $reply .= "\n\n📅 每日明細";
            foreach ($rows as $row) {
                $date = substr($row['date'], 5); // 取 MM/DD
                $f    = $this->fmtInstCompact($row['foreign'] ?? null);
                $t    = $this->fmtInstCompact($row['trust']   ?? null);
                $d    = $this->fmtInstCompact($row['dealer']  ?? null);
                $reply .= "\n{$date}  外{$f}  信{$t}  營{$d}";
            }
        }

        // 月營收
        $revenues = $this->fetchRevenue($code);
        if ($revenues) {
            $reply .= "\n\n━━ 近月營收（仟元）━━";
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
                $lastStr = $lastRevK !== null ? "　去年 " . number_format($lastRevK) : '';
                $reply .= "\n{$label}  " . number_format($revK)
                        . "　月{$momArrow}" . number_format(abs($mom), 1) . "%"
                        . "　年{$yoyArrow}" . number_format(abs($yoy), 1) . "%"
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

            $reply .= "\n\n📊 累計（至{$accLabel}）：" . number_format($accK) . "仟";
            if ($lastAccK !== null) {
                $reply .= "\n   去年同期：" . number_format($lastAccK) . "仟"
                        . "　年增{$accArrow}" . number_format(abs($accYoY), 1) . "%";
            }
        }

        // 最新新聞
        $news = $this->fetchStockNews($code);
        if (!empty($news)) {
            $reply .= "\n\n━━ 最新消息 ━━";
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
        $bannerPath = $member && $member->banner
            ? public_path($member->banner)
            : public_path('assets/images/login-bg.jpg');

        if (file_exists($bannerPath)) {
            $this->sendPhoto($bot->token, $chatId, $bannerPath, '', $replyMarkup);
        }
    }

    // ─── Banner 上傳處理 ────────────────────────────────────────────
    private function handleBannerUpload(TgBot $bot, int $chatId, string $userId, array $photos): string
    {
        // Telegram 回傳多個尺寸，取最大的（最後一個）
        $photo  = end($photos);
        $fileId = $photo['file_id'];

        try {
            // 1. 取得檔案路徑
            $res = Http::timeout(10)->get("https://api.telegram.org/bot{$bot->token}/getFile", [
                'file_id' => $fileId,
            ]);

            $fileData = $res->json();
            if (!($fileData['ok'] ?? false)) {
                $this->sendMessage($bot->token, $chatId, '❌ 無法取得圖片，請重新發送。');
                return '❌ getFile 失敗';
            }

            $filePath = $fileData['result']['file_path'];
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $ext = 'jpg';
            }

            // 2. 下載圖片
            $fileUrl  = "https://api.telegram.org/file/bot{$bot->token}/{$filePath}";
            $fileRes  = Http::timeout(30)->get($fileUrl);

            if (!$fileRes->ok()) {
                $this->sendMessage($bot->token, $chatId, '❌ 圖片下載失敗，請重試。');
                return '❌ 圖片下載失敗';
            }

            // 3. 存檔到 public/uploads/banner/{YYYYMMDD}/
            $dateDir  = date('Ymd');
            $saveDir  = public_path("uploads/banner/{$dateDir}");
            if (!is_dir($saveDir)) {
                mkdir($saveDir, 0755, true);
            }
            $filename = uniqid() . '.' . $ext;
            $savePath = "{$saveDir}/{$filename}";
            file_put_contents($savePath, $fileRes->body());

            // 4. 更新 Member banner 欄位
            $bannerRelPath = "/uploads/banner/{$dateDir}/{$filename}";
            $member = Member::where('account', 'tg_' . $userId)->first();
            if ($member) {
                $member->update(['banner' => $bannerRelPath]);
            }

            // 5. 清除狀態，回覆成功
            $this->clearState($bot->id, $chatId);

            // 發送新 banner 預覽
            $this->sendPhoto($bot->token, $chatId, $savePath, '✅ Banner 更新成功！');

            return '✅ Banner 更新成功';

        } catch (\Exception $e) {
            Log::channel('tg_webhook')->error('[TG Webhook] handleBannerUpload 失敗', [
                'chat_id' => $chatId,
                'error'   => $e->getMessage(),
            ]);
            $this->sendMessage($bot->token, $chatId, '❌ Banner 更新失敗，請稍後重試。');
            return '❌ Banner 更新失敗：' . $e->getMessage();
        }
    }

    // ─── 回覆組建：我的持股 ───────────────────────────────────────
    private function buildPortfolioReply(int $botId, int $chatId): array
    {
        $holdings = TgHolding::where('bot_id', $botId)
            ->where('tg_chat_id', $chatId)
            ->get();

        $addButton  = [['text' => '➕ 添加持股', 'callback_data' => 'holding_add']];
        $capitalBtn = [['text' => '⚙️ 設定資金', 'callback_data' => 'set_capital']];

        $settingsBtn = [['text' => '⚙️ 設置', 'callback_data' => 'portfolio_settings']];

        if ($holdings->isEmpty()) {
            return [
                "💼 我的持股\n\n目前沒有持股記錄。",
                ['inline_keyboard' => [$addButton, $capitalBtn, $settingsBtn]],
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
                $curPriceStr = "現價：NT$" . $curPrice;
                if ($diff !== null && $pct !== null) {
                    $s = $diff >= 0 ? '+' : '';
                    $a = $diff >= 0 ? '📈' : '📉';
                    $curPriceStr .= "　{$a}{$s}" . number_format($diff, 2) . "（{$s}" . number_format($pct, 2) . "%）";
                }
            }

            $firstName  = $group->first()->stock_name;
            $totalShares = $group->sum('shares');
            $lotCount   = $group->count();

            // 分組標頭
            $groupHeader = "📌 {$firstName}（{$stockCode}）合計 " . $this->sharesDisplay($totalShares);
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

                $marginTag = $h->is_margin ? '融資' : '現股';
                $buyStr    = $buyPrice > 0 ? "買入：NT$" . $buyPrice : '';

                $stockProfit = $curValue !== null ? $curValue - $originValue - $txCost - $interest : null;
                if ($stockProfit !== null) {
                    $groupNetProfit += $stockProfit;
                }

                $profitStr = '';
                if ($stockProfit !== null) {
                    $sign        = $stockProfit >= 0 ? '+' : '';
                    $txCostStr   = 'NT$' . number_format($txCost, 0);
                    $interestStr = $interest > 0 ? "　利息：NT$" . number_format($interest, 0) . "（{$daysHeld}天）" : '';
                    $profitStr   = "\n      稅費：{$txCostStr}（買費+賣費+稅）{$interestStr}　淨損益：{$sign}NT$" . number_format($stockProfit, 0);
                }

                $isLast    = $idx === $group->keys()->last();
                $prefix    = $isLast ? '   └' : '   ├';
                $curValStr = $curValue !== null ? '現值：NT$' . number_format($curValue, 0) : '查詢失敗';

                $lotLines[] = "{$prefix} " . $this->sharesDisplay($h->shares) . "·{$marginTag}　{$buyStr}\n      {$curValStr}{$profitStr}";
            }

            // 按 is_margin 分組各加一顆賣出按鈕（合併同股同類型全部持股）
            $marginGroups = $group->groupBy('is_margin');
            $hasMultipleTypes = $marginGroups->count() > 1;
            foreach ($marginGroups as $marginVal => $mGroup) {
                $typeTag = $hasMultipleTypes ? ($marginVal ? '(融資)' : '(現股)') : '';
                $delButtons[] = ['text' => "💰 賣出 {$stockCode}{$typeTag}", 'callback_data' => "holding_sell_c_{$stockCode}_{$marginVal}"];
            }

            // 多筆時顯示分組合計
            $groupSummary = '';
            if ($lotCount > 1 && $groupCurrentVal > 0) {
                $sign         = $groupNetProfit >= 0 ? '+' : '';
                $groupSummary = "\n   合計現值：NT$" . number_format($groupCurrentVal, 0)
                              . "　損益：{$sign}NT$" . number_format($groupNetProfit, 0);
            }

            $lines[] = $groupHeader . "\n" . implode("\n", $lotLines) . $groupSummary;
        }

        // 損益摘要
        $profitStr = "\n\n📊 自備成本：NT$" . number_format($totalSelfCost, 0)
                   . "　原始市值：NT$" . number_format($totalOriginVal, 0);
        if ($totalCurrentVal > 0) {
            $netProfit = $totalCurrentVal - $totalOriginVal - $totalTxCost - $totalInterest;
            $roi       = $totalSelfCost > 0 ? ($netProfit / $totalSelfCost * 100) : 0;
            $sign      = $netProfit >= 0 ? '+' : '';
            $profitStr .= "\n📈 現值合計：NT$" . number_format($totalCurrentVal, 0)
                        . "\n💸 預估稅費：NT$" . number_format($totalTxCost, 0);
            if ($totalInterest > 0) {
                $profitStr .= "\n📋 融資利息：NT$" . number_format($totalInterest, 0)
                            . "（年利率 " . number_format($marginAnnualRate * 100, 1) . "%）";
            }
            $profitStr .= "\n💹 淨損益：{$sign}NT$" . number_format($netProfit, 0)
                        . "　自備報酬：{$sign}" . number_format($roi, 2) . "%";
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
            $settleLines[] = "   ├ {$dateStr} 交割：{$emoji}{$sign}NT$" . number_format($dayNet, 0);
        }

        // 帳戶資金區塊
        if ($capital !== null) {
            $totalCapital = $capital + $totalSelfCost;
            $afterSettle  = $capital + $grandNet;
            $warning      = $afterSettle < 0 ? ' ⚠️' : '';
            $profitStr   .= "\n\n💰 帳戶總資金：NT$" . number_format($totalCapital, 0)
                          . "\n   ├ 持股占用：NT$" . number_format($totalSelfCost, 0)
                          . "\n   ├ 帳戶現金：NT$" . number_format($capital, 0);
            if (!empty($settleLines)) {
                // 最後一行改成 └
                $last = count($settleLines) - 1;
                $settleLines[$last] = str_replace('├', '├', $settleLines[$last]);
                $profitStr .= "\n" . implode("\n", $settleLines);
                $profitStr .= "\n   └ 交割後剩餘：NT$" . number_format($afterSettle, 0) . $warning;
            } else {
                $profitStr .= "\n   └ 剩餘可用：NT$" . number_format($capital, 0);
            }
        } else {
            $profitStr .= "\n\n💰 帳戶資金：未設定（點擊⚙️設定資金）";
        }

        $text = "💼 我的持股\n\n" . implode("\n\n", $lines) . $profitStr;

        // Inline keyboard：添加 + 設定資金 + 交割款查詢 + 設置 + 賣出（每排最多2個）
        $settleQueryBtn = [['text' => '📅 交割款查詢', 'callback_data' => 'view_settlements']];
        $settingsBtn    = [['text' => '⚙️ 設置', 'callback_data' => 'portfolio_settings']];
        $inlineRows = [$addButton, $capitalBtn, $settleQueryBtn, $settingsBtn];
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

            $data  = json_decode((string) $res->getBody(), true);
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
    private function isMainMenuText(string $text): bool
    {
        return str_contains($text, '布蘭特原油')
            || str_contains($text, '台指期貨')
            || str_contains($text, 'VIX')
            || str_contains($text, '恐慌指數')
            || str_contains($text, '台股查詢')
            || str_contains($text, '我的持股');
    }

    private function getMainKeyboard(): array
    {
        return [
            'keyboard' => [
                [['text' => '🛢 布蘭特原油'], ['text' => '📈 台指期貨']],
                [['text' => '😨 VIX恐慌指數'], ['text' => '📊 台股查詢']],
                [['text' => '💼 我的持股']],
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
}
