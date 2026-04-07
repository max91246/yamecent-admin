<?php

namespace App\Http\Controllers\Api;

use App\OilPrice;
use App\TgBot;
use App\TgHolding;
use App\TgHoldingTrade;
use App\TgMessage;
use App\TgState;
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

            // 賣出持股 — 進入賣出流程
            } elseif (str_starts_with($data, 'holding_sell_')) {
                $holdingId = (int) substr($data, strlen('holding_sell_'));
                $holding   = TgHolding::where('id', $holdingId)
                    ->where('bot_id', $bot->id)
                    ->where('tg_chat_id', $chatId)
                    ->first();
                if ($holding) {
                    $this->setState($bot->id, $chatId, 'sell_step1', ['holding_id' => $holdingId]);
                    $replyText = "💰 賣出 {$holding->stock_name}（{$holding->stock_code}）\n"
                               . "持有：{$holding->shares} 張\n\n"
                               . "請輸入賣出張數（最多 {$holding->shares} 張）：\n\n輸入「取消」可返回";
                } else {
                    [$replyText, $replyMarkup] = $this->buildPortfolioReply($bot->id, $chatId);
                }

            // 設定資金
            } elseif ($data === 'set_capital') {
                $this->setState($bot->id, $chatId, 'set_capital');
                $replyText = "💰 設定資金總額\n請輸入您的資金總額（台幣整數，例如：1500000）：\n\n輸入「取消」可返回";
            }

            if ($replyText !== null) {
                $this->sendMessage($bot->token, $chatId, $replyText, $replyMarkup);
                $this->logMessage($bot->id, $userId, $username, $chatId, $replyText, 2, 'reply');
            }

            return response()->json(['ok' => true]);
        }

        // ── 一般文字訊息 ──────────────────────────────────────────
        if (isset($update['message'])) {
            $msg      = $update['message'];
            $chatId   = (int) $msg['chat']['id'];
            $userId   = (string) $msg['from']['id'];
            $username = $msg['from']['username'] ?? null;
            $text     = trim($msg['text'] ?? '');

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
            return $this->buildPortfolioReply($bot->id, $chatId);
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
            case 'set_capital':
                return $this->handleSetCapital($bot, $chatId, $userId, $text);
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
            "✅ 找到：{$quote['name']}（{$code}）\n💰 當前價：{$quote['price']}\n\n請輸入持有張數（整數，1張=1000股）：",
            null,
        ];
    }

    // ─── 持股添加：step2 輸入張數 ─────────────────────────────────
    private function handleHoldingStep2(TgBot $bot, int $chatId, string $text, TgState $stateObj): array
    {
        if (!ctype_digit($text) || (int) $text <= 0) {
            return ['❌ 張數請輸入正整數（例如：5）：', null];
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

        return ["請輸入當時買進的每股價格（元）：\n例如：{$name} 買 {$shares} 張，每股 53.5 就輸入 53.5\n\n輸入「取消」可返回", null];
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
        $marketVal = $buyPrice * $shares * 1000;

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

        // 扣除帳戶資金（若已設定）
        $walletRow = TgWallet::where('bot_id', $bot->id)
            ->where('tg_chat_id', $chatId)
            ->where('tg_user_id', $userId)
            ->first();
        if ($walletRow) {
            $walletRow->decrement('capital', $cost);
        }

        $this->clearState($bot->id, $chatId);

        $marginTag = $isMargin ? '融資' : '現股';
        $confirm   = "✅ 已添加持股：\n"
                   . "📌 {$data['name']}（{$data['code']}）\n"
                   . "📦 {$shares} 張 · {$marginTag}\n"
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
            return ['❌ 請輸入有效的賣出張數（正整數）：', null];
        }

        $data      = $stateObj->state_data ?? [];
        $holding   = TgHolding::where('id', $data['holding_id'])
            ->where('bot_id', $bot->id)
            ->where('tg_chat_id', $chatId)
            ->first();

        if (!$holding) {
            $this->clearState($bot->id, $chatId);
            return $this->buildPortfolioReply($bot->id, $chatId);
        }

        $sellShares = (int) $text;
        if ($sellShares > $holding->shares) {
            return ["❌ 持有只有 {$holding->shares} 張，請重新輸入：", null];
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

        $data    = $stateObj->state_data ?? [];
        $holding = TgHolding::where('id', $data['holding_id'])
            ->where('bot_id', $bot->id)
            ->where('tg_chat_id', $chatId)
            ->first();

        if (!$holding) {
            $this->clearState($bot->id, $chatId);
            return $this->buildPortfolioReply($bot->id, $chatId);
        }

        $sellShares  = (int) $data['sell_shares'];
        $buyPrice    = (float) $holding->buy_price;
        $isMargin    = (int) $holding->is_margin;

        $buyValue  = $buyPrice  * $sellShares * 1000;
        $sellValue = $sellPrice * $sellShares * 1000;

        // 交易成本
        $feeRate      = 0.001425; // 手續費 0.1425%（買賣皆收）
        $taxRate      = 0.003;    // 交易稅 0.3%（僅賣出）
        $buyFee       = ceil($buyValue  * $feeRate); // 買進手續費（無條件進位）
        $sellFee      = ceil($sellValue * $feeRate); // 賣出手續費
        $sellTax      = ceil($sellValue * $taxRate); // 證券交易稅
        $totalCost    = $buyFee + $sellFee + $sellTax;

        // 損益 = 賣出價值 - 買進價值 - 交易成本
        $profit = $sellValue - $buyValue - $totalCost;

        // 記錄交易
        TgHoldingTrade::create([
            'bot_id'      => $bot->id,
            'tg_chat_id'  => $chatId,
            'tg_user_id'  => $userId,
            'stock_code'  => $holding->stock_code,
            'stock_name'  => $holding->stock_name,
            'sell_shares' => $sellShares,
            'buy_price'   => $buyPrice,
            'sell_price'  => $sellPrice,
            'is_margin'   => $isMargin,
            'profit'      => $profit,
        ]);

        // 計算回款（在更新持股前讀取原始張數與成本）
        $proportionalCost = (float) $holding->total_cost * ($sellShares / $holding->shares);
        $walletAdd        = $proportionalCost + $profit;

        // 更新或刪除持股
        if ($sellShares >= $holding->shares) {
            $holding->delete();
        } else {
            // 剩餘持股：成本按比例扣除
            $remainShares = $holding->shares - $sellShares;
            $newCost      = (float) $holding->total_cost * ($remainShares / $holding->shares);
            $holding->update(['shares' => $remainShares, 'total_cost' => $newCost]);
        }

        // 回款至帳戶資金（若已設定）
        $walletRow = TgWallet::where('bot_id', $bot->id)
            ->where('tg_chat_id', $chatId)
            ->where('tg_user_id', $userId)
            ->first();
        if ($walletRow) {
            $walletRow->increment('capital', $walletAdd);
        }

        $this->clearState($bot->id, $chatId);

        $sign      = $profit >= 0 ? '+' : '';
        $profitTag = $profit >= 0 ? '✅ 獲利' : '❌ 虧損';
        $confirm   = "📤 賣出完成：\n"
                   . "📌 {$holding->stock_name}（{$holding->stock_code}）{$sellShares} 張\n"
                   . "💵 買進：NT$" . $buyPrice . "　賣出：NT$" . $sellPrice . "\n"
                   . "💸 手續費：NT$" . number_format($buyFee + $sellFee, 0)
                   . "　交易稅：NT$" . number_format($sellTax, 0) . "\n"
                   . "{$profitTag}：{$sign}NT$" . number_format($profit, 0);

        $this->sendMessage($bot->token, $chatId, $confirm);

        return $this->buildPortfolioReply($bot->id, $chatId);
    }

    // ─── 設定資金總額 ────────────────────────────────────────────
    private function handleSetCapital(TgBot $bot, int $chatId, string $userId, string $text): array
    {
        $amount = (float) str_replace([',', '，', '$', 'NT$', ' '], '', $text);
        if ($amount <= 0) {
            return ['❌ 請輸入有效金額（正整數，例如：1500000）：', null];
        }

        TgWallet::updateOrCreate(
            ['bot_id' => $bot->id, 'tg_chat_id' => $chatId, 'tg_user_id' => $userId],
            ['capital' => $amount]
        );

        $this->clearState($bot->id, $chatId);
        $this->sendMessage($bot->token, $chatId, '✅ 資金總額已設定為 NT$' . number_format($amount, 0));

        return $this->buildPortfolioReply($bot->id, $chatId);
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
            $diff  = (float) $latest->close - (float) $prev->close;
            $pct   = (float) $prev->close > 0 ? ($diff / (float) $prev->close * 100) : 0;
            $sign  = $diff >= 0 ? '+' : '';
            $arrow = $diff >= 0 ? '📈' : '📉';
            $changeStr = "\n{$arrow} 5分變化：{$sign}" . number_format($diff, 0) . "點（{$sign}" . number_format($pct, 2) . "%）";
        }

        $vix    = OilPrice::where('ticker', 'VIX')->whereNotNull('close')->orderBy('candle_at', 'desc')->first();
        $vixStr = $vix ? "\n😨 VIX 恐慌指數：" . number_format((float) $vix->close, 2) : '';

        return "📈 台指期貨\n最新價：" . number_format((float) $latest->close, 0) . "{$changeStr}\n🕐 時間：{$latest->candle_at}{$vixStr}";
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

        return $reply;
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

    // ─── 回覆組建：我的持股 ───────────────────────────────────────
    private function buildPortfolioReply(int $botId, int $chatId): array
    {
        $holdings = TgHolding::where('bot_id', $botId)
            ->where('tg_chat_id', $chatId)
            ->get();

        $addButton  = [['text' => '➕ 添加持股', 'callback_data' => 'holding_add']];
        $capitalBtn = [['text' => '⚙️ 設定資金', 'callback_data' => 'set_capital']];

        if ($holdings->isEmpty()) {
            return [
                "💼 我的持股\n\n目前沒有持股記錄。",
                ['inline_keyboard' => [$addButton, $capitalBtn]],
            ];
        }

        $feeRate = 0.001425;
        $taxRate = 0.003;

        $wallet          = TgWallet::where('bot_id', $botId)->where('tg_chat_id', $chatId)->first();
        $capital         = $wallet ? (float) $wallet->capital : null;

        $totalSelfCost   = 0;  // 自備款合計
        $totalOriginVal  = 0;  // 買進原始市值合計
        $totalCurrentVal = 0;  // 當前市值合計
        $totalTxCost     = 0;  // 預估交易成本合計（買進手續費 + 賣出手續費+稅）
        $lines           = [];
        $delButtons      = [];

        foreach ($holdings as $h) {
            $quote       = $this->fetchStockQuote($h->stock_code);
            $curPrice    = $quote ? (float) $quote['price'] : null;
            $curValue    = $curPrice !== null ? $curPrice * $h->shares * 1000 : null;
            $buyPrice    = (float) $h->buy_price;
            $originValue = $buyPrice > 0 ? $buyPrice * $h->shares * 1000 : (float) $h->total_cost;
            $selfCost    = (float) $h->total_cost;

            // 預估交易成本：買進手續費 + 賣出手續費 + 賣出交易稅
            $buyFee   = $buyPrice > 0 ? (int) ceil($originValue * $feeRate) : 0;
            $sellFee  = $curValue  !== null ? (int) ceil($curValue * $feeRate) : 0;
            $sellTax  = $curValue  !== null ? (int) ceil($curValue * $taxRate)  : 0;
            $txCost   = $buyFee + $sellFee + $sellTax;

            $totalSelfCost   += $selfCost;
            $totalOriginVal  += $originValue;
            $totalTxCost     += $txCost;
            if ($curValue !== null) {
                $totalCurrentVal += $curValue;
            }

            $marginTag   = $h->is_margin ? '融資' : '現股';
            $curValueStr = $curValue !== null ? 'NT$' . number_format($curValue, 0) : '查詢失敗';

            // 個股淨損益 = 現值 - 原始市值 - 交易成本
            $stockProfit    = $curValue !== null ? $curValue - $originValue - $txCost : null;
            $stockProfitStr = '';
            if ($stockProfit !== null) {
                $sign           = $stockProfit >= 0 ? '+' : '';
                $txCostStr      = 'NT$' . number_format($txCost, 0);
                $stockProfitStr = "\n   稅費：{$txCostStr}（買費+賣費+稅）　淨損益：{$sign}NT$" . number_format($stockProfit, 0);
            }

            $buyStr  = $buyPrice > 0 ? "　買進：NT$" . $buyPrice : '';
            $lines[] = "📌 {$h->stock_name}（{$h->stock_code}）{$h->shares}張·{$marginTag}{$buyStr}"
                     . "\n   現值：{$curValueStr}{$stockProfitStr}";

            $delButtons[] = ['text' => "💰 賣出 {$h->stock_code}", 'callback_data' => 'holding_sell_' . $h->id];
        }

        // 損益摘要
        $profitStr = "\n\n📊 自備成本：NT$" . number_format($totalSelfCost, 0)
                   . "　原始市值：NT$" . number_format($totalOriginVal, 0);
        if ($totalCurrentVal > 0) {
            $netProfit = $totalCurrentVal - $totalOriginVal - $totalTxCost;
            $roi       = $totalSelfCost > 0 ? ($netProfit / $totalSelfCost * 100) : 0;
            $sign      = $netProfit >= 0 ? '+' : '';
            $profitStr .= "\n📈 現值合計：NT$" . number_format($totalCurrentVal, 0)
                        . "\n💸 預估稅費：NT$" . number_format($totalTxCost, 0)
                        . "\n💹 淨損益：{$sign}NT$" . number_format($netProfit, 0)
                        . "　自備報酬：{$sign}" . number_format($roi, 2) . "%";
        }

        // 帳戶資金區塊
        if ($capital !== null) {
            $available    = $capital - $totalSelfCost;
            $warning      = $available < 0 ? ' ⚠️' : '';
            $profitStr   .= "\n\n💰 帳戶資金：NT$" . number_format($capital, 0)
                          . "\n   ├ 持股占用：NT$" . number_format($totalSelfCost, 0)
                          . "\n   └ 剩餘可用：NT$" . number_format($available, 0) . $warning;
        } else {
            $profitStr .= "\n\n💰 帳戶資金：未設定（點擊⚙️設定資金）";
        }

        $text = "💼 我的持股\n\n" . implode("\n\n", $lines) . $profitStr;

        // Inline keyboard：添加 + 設定資金 + 賣出（每排最多2個）
        $inlineRows = [$addButton, $capitalBtn];
        foreach (array_chunk($delButtons, 2) as $row) {
            $inlineRows[] = $row;
        }

        return [$text, ['inline_keyboard' => $inlineRows]];
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
            return 300; // 盤中 5 分鐘
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

    private function sendMessage(string $token, $chatId, string $text, ?array $replyMarkup = null): void
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
