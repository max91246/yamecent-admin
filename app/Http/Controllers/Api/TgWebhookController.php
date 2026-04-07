<?php

namespace App\Http\Controllers\Api;

use App\OilPrice;
use App\TgBot;
use App\TgHolding;
use App\TgMessage;
use App\TgState;
use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
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

            // 刪除持股
            } elseif (str_starts_with($data, 'holding_del_')) {
                $holdingId = (int) substr($data, strlen('holding_del_'));
                TgHolding::where('id', $holdingId)
                    ->where('bot_id', $bot->id)
                    ->where('tg_chat_id', $chatId)
                    ->delete();
                [$replyText, $replyMarkup] = $this->buildPortfolioReply($bot->id, $chatId);
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

        $marginHint = $stateData['is_margin']
            ? "（融資自備約 4 成，例如買進市值 250,000 則自備約 100,000）"
            : "（例如：150000）";

        return ["請輸入持有總成本（台幣金額）：\n{$marginHint}\n\n輸入「取消」可返回", null];
    }

    // ─── 持股添加：step4 輸入成本 ─────────────────────────────────
    private function handleHoldingStep4(TgBot $bot, int $chatId, string $userId, string $text, TgState $stateObj): array
    {
        $cost = (float) str_replace([',', '，'], '', $text);

        if ($cost <= 0) {
            return ['❌ 請輸入有效的台幣金額（例如：150000）：', null];
        }

        $data = $stateObj->state_data ?? [];

        TgHolding::create([
            'bot_id'     => $bot->id,
            'tg_chat_id' => $chatId,
            'tg_user_id' => $userId,
            'stock_code' => $data['code'],
            'stock_name' => $data['name'],
            'shares'     => $data['shares'],
            'is_margin'  => $data['is_margin'] ?? 0,
            'total_cost' => $cost,
        ]);

        $this->clearState($bot->id, $chatId);

        $marginTag = ($data['is_margin'] ?? 0) ? '融資' : '現股';
        $reply     = "✅ 已添加持股：\n"
                   . "📌 {$data['name']}（{$data['code']}）\n"
                   . "📦 {$data['shares']} 張 · {$marginTag}\n"
                   . "💰 成本：NT$" . number_format($cost, 0);

        return [$reply, null];
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
            ? "\n📦 成交量：" . number_format((int) $quote['volume']) . " 股"
            : '';

        $reply = "📊 {$name}（{$code}.TW）\n💰 成交價：{$price}{$changeStr}{$volumeStr}";

        // 三大法人
        $inst = $this->fetchInstitutional($code);
        if ($inst) {
            $reply .= "\n\n━━ 近3日三大法人買賣超 ━━";
            foreach (array_slice($inst, 0, 3) as $row) {
                $reply .= "\n📅 {$row['date']}";
                if (array_key_exists('foreign', $row)) {
                    $reply .= "  外資：" . $this->fmtInstShares($row['foreign']);
                }
                if (array_key_exists('trust', $row)) {
                    $reply .= "  投信：" . $this->fmtInstShares($row['trust']);
                }
                if (array_key_exists('dealer', $row)) {
                    $reply .= "  自營：" . $this->fmtInstShares($row['dealer']);
                }
            }
        }

        return $reply;
    }

    private function fmtInstShares($val): string
    {
        if ($val === null) {
            return '-';
        }
        $v    = (int) $val;
        $sign = $v >= 0 ? '+' : '';
        return "{$sign}" . number_format($v) . "張";
    }

    // ─── 回覆組建：我的持股 ───────────────────────────────────────
    private function buildPortfolioReply(int $botId, int $chatId): array
    {
        $holdings = TgHolding::where('bot_id', $botId)
            ->where('tg_chat_id', $chatId)
            ->get();

        $addButton = [['text' => '➕ 添加持股', 'callback_data' => 'holding_add']];

        if ($holdings->isEmpty()) {
            return [
                "💼 我的持股\n\n目前沒有持股記錄。",
                ['inline_keyboard' => [$addButton]],
            ];
        }

        $totalCost  = 0;
        $totalValue = 0;
        $lines      = [];
        $delButtons = [];

        foreach ($holdings as $h) {
            $quote = $this->fetchStockQuote($h->stock_code);
            $price = $quote ? (float) $quote['price'] : null;
            $value = $price !== null ? $price * $h->shares * 1000 : null;

            $totalCost += (float) $h->total_cost;
            if ($value !== null) {
                $totalValue += $value;
            }

            $marginTag = $h->is_margin ? '融資' : '現股';
            $valueStr  = $value !== null
                ? 'NT$' . number_format($value, 0)
                : '查詢失敗';

            $costStr = 'NT$' . number_format((float) $h->total_cost, 0);
            $lines[] = "📌 {$h->stock_name}（{$h->stock_code}）{$h->shares}張·{$marginTag}"
                     . "\n   成本：{$costStr}　現值：{$valueStr}";

            $delButtons[] = ['text' => "🗑 {$h->stock_code}", 'callback_data' => 'holding_del_' . $h->id];
        }

        // 損益摘要
        $profitStr = "\n\n📊 總成本：NT$" . number_format($totalCost, 0);
        if ($totalValue > 0) {
            $profit    = $totalValue - $totalCost;
            $profitPct = $totalCost > 0 ? ($profit / $totalCost * 100) : 0;
            $sign      = $profit >= 0 ? '+' : '';
            $profitStr .= "\n📈 現值合計：NT$" . number_format($totalValue, 0)
                        . "\n💹 損益：{$sign}NT$" . number_format($profit, 0)
                        . "（{$sign}" . number_format($profitPct, 2) . "%）";
        }

        $text = "💼 我的持股\n\n" . implode("\n\n", $lines) . $profitStr;

        // Inline keyboard：添加 + 刪除（每排最多2個）
        $inlineRows   = [$addButton];
        foreach (array_chunk($delButtons, 2) as $row) {
            $inlineRows[] = $row;
        }

        return [$text, ['inline_keyboard' => $inlineRows]];
    }

    // ─── Yahoo Finance：股票現價 ──────────────────────────────────
    private function fetchStockQuote(string $code): ?array
    {
        $symbolsJson = json_encode([$code . '.TW']);
        $symbolsEnc  = rawurlencode($symbolsJson);
        $url         = "https://tw.stock.yahoo.com/_td-stock/api/resource/FinanceChartService.ApacLibraCharts;period=d;symbols={$symbolsEnc}";

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
            $name = $meta['longName']
                ?? $meta['shortName']
                ?? $quote['shortName']
                ?? $quote['name']
                ?? $code;

            return [
                'name'           => $name,
                'price'          => $price,
                'priceChange'    => $quote['priceChange']      ?? $quote['change']          ?? null,
                'priceChangePct' => $quote['priceChangePercent'] ?? $quote['changePercent'] ?? null,
                'volume'         => $quote['volume']           ?? null,
            ];
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
        $url    = "https://tw.stock.yahoo.com/_td-stock/api/resource/StockServices.tradesWithQuoteStats;limit=5;period=day;symbol={$symbol}";

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

            $data   = json_decode((string) $res->getBody(), true);
            $trades = $data['data']['trades'] ?? $data['trades'] ?? [];

            if (empty($trades)) {
                Log::channel('tg_webhook')->info('[TG Webhook] fetchInstitutional 無資料', [
                    'code' => $code,
                    'raw'  => array_keys($data ?? []),
                ]);
                return null;
            }

            $result = [];
            foreach ($trades as $row) {
                $date  = substr($row['date'] ?? '', 0, 10);
                $items = [];

                foreach ($row['items'] ?? [] as $item) {
                    $type = $item['type'] ?? '';
                    $net  = $item['netBuySell'] ?? $item['net'] ?? null;

                    if ($net !== null) {
                        // 轉換為張（若單位是股則除以1000）
                        $netVal = abs($net) >= 1000 ? (int) round($net / 1000) : (int) $net;
                    } else {
                        $netVal = null;
                    }

                    if (in_array($type, ['SITC', 'Foreign', 'foreign'])) {
                        $items['foreign'] = $netVal;
                    } elseif (in_array($type, ['Trust', 'trust'])) {
                        $items['trust'] = $netVal;
                    } elseif (in_array($type, ['Dealer', 'dealer'])) {
                        $items['dealer'] = $netVal;
                    }
                }

                if ($date) {
                    $result[] = array_merge(['date' => $date], $items);
                }
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
