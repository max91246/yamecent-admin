<?php

namespace App\Console\Commands;

use App\OilPrice;
use App\Services\OilNewsService;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class FetchOilPrice extends Command
{
    protected $signature = 'fetch:oil-price
                            {--debug     : 顯示原始 API 回應}
                            {--no-tg    : 只存 DB，不推送任何 Telegram}
                            {--no-news  : 告警時不搜尋新聞（加速或離線測試用）}
                            {--force-alert : 強制推送一次當前價格（測試用）}';

    protected $description = '獲取布蘭特原油 5 分K 並存 DB；暴漲暴跌時發送 Telegram 告警';

    const FINVIZ_URL     = 'https://finviz.com/api/quote.ashx';
    const TG_API         = 'https://api.telegram.org/bot%s/sendMessage';
    const TICKER         = 'QA';
    const TW_TICKER      = 'WTX';
    const TW_INDEX_URL   = 'https://tw.stock.yahoo.com/_td-stock/api/resource/FinanceChartService.ApacLibraCharts;symbols=%5B%22WTX%26%22%5D;type=tick';

    // 告警閾值
    const ALERT_5M_PCT  = 0.5;   // 當前+前一根 K 棒合併振幅超過 0.5% 告警
    const ALERT_1H_PCT  = 2.0;   // 小時區間振幅超過 2% 告警

    public function handle()
    {
        $this->info('正在獲取布蘭特原油（' . self::TICKER . '）最新價格...');

        [$price, $lastCandle, $allCandles] = $this->fetchPrice();

        if ($price === null) {
            $this->line('[ERROR] 無法解析價格，請加 --debug 查看原始回應。');
            return 1;
        }

        $candleAt = isset($lastCandle['t'])
            ? date('Y-m-d H:i:s', (int) $lastCandle['t'])
            : date('Y-m-d H:i:s');

        $this->line(str_repeat('─', 50));
        $this->info('布蘭特原油 5分K 最新收盤：' . $price);
        $this->line('  K棒時間：' . $candleAt . '（台北時間）');
        $this->line('  開高低收：' . implode(' / ', [
            $lastCandle['o'] ?? '-', $lastCandle['h'] ?? '-',
            $lastCandle['l'] ?? '-', $lastCandle['c'],
        ]));
        $this->line(str_repeat('─', 50));

        // ── 1. 存入 DB（補寫今天所有 K 棒，避免因時序漏掉）────────
        $newCount = $this->saveAllCandles($allCandles);
        $this->line("  [DB] 本次新寫入 {$newCount} 根 K 棒");

        // ── 2. 抓取台指現價並存 DB ──────────────────────────────
        [$twPrice, $twRefreshedAt] = $this->fetchTwIndex();
        if ($twPrice !== null) {
            $this->saveTwIndexPrice($twPrice, $twRefreshedAt);
            $this->line("  [台指] 當前：{$twPrice}（更新：{$twRefreshedAt}）");
        } else {
            $this->line('  [台指] 取得失敗，略過');
        }

        // ── 3. 測試強制推送 ───────────────────────────────────────
        if ($this->option('force-alert')) {
            $msg = "🔔 <b>測試推送</b>\n"
                 . "💰 當前布蘭特原油：<b>{$price}</b>\n"
                 . "⏰ {$candleAt}"
                 . $this->buildNewsBlock(3, 1);
            $this->pushTelegram($msg);
            return 0;
        }

        if ($this->option('no-tg')) {
            return 0;
        }

        // ── 4. 計算兩項告警，合併成一則推送 ──────────────────────
        $alert5m = $this->calc5mAlert($candleAt);
        $alert1h = $this->calc1hAlert($candleAt);

        if ($alert5m !== null || $alert1h !== null) {
            $msg = "🚨 <b>布蘭特原油告警</b>　💰 當前：<b>{$price}</b>（{$candleAt}）\n";

            if ($alert5m !== null) {
                $this->warn('  [5分告警] 觸發！' . $alert5m['arrow'] . $alert5m['direction'] . ' 振幅 ' . $alert5m['pctFmt']);
                $msg .= "\n━━ 📊 5分K震盪 ━━\n"
                      . "{$alert5m['arrow']} <b>方向：{$alert5m['direction']}</b>　收盤 {$alert5m['prevClose']} → <b>{$alert5m['currClose']}</b>（<b>{$alert5m['closePctFmt']}</b> / {$alert5m['closeDiffFmt']}）\n"
                      . "🕐 區間：<b>{$alert5m['fromTime']} – {$alert5m['currTime']}</b>\n"
                      . "🔺 區間最高：<b>{$alert5m['maxHigh']}</b>　🔻 區間最低：<b>{$alert5m['minLow']}</b>\n"
                      . "📊 振幅：<b>{$alert5m['pctFmt']}</b>（{$alert5m['diffFmt']}）　⚠️ 閾值 " . self::ALERT_5M_PCT . "%";
            }

            if ($alert1h !== null) {
                $this->warn('  [小時告警] 觸發！' . $alert1h['arrow'] . $alert1h['direction'] . ' 振幅 ' . $alert1h['pctFmt']);
                $msg .= "\n\n━━ 🕐 小時震盪 ━━\n"
                      . "{$alert1h['arrow']} <b>方向：{$alert1h['direction']}</b>　收盤 {$alert1h['firstClose']} → <b>{$alert1h['lastClose']}</b>（<b>{$alert1h['closePctFmt']}</b> / {$alert1h['closeDiffFmt']}）\n"
                      . "🕐 區間：<b>{$alert1h['fromTime']} – {$alert1h['currTime']}</b>\n"
                      . "🔺 區間最高：<b>{$alert1h['maxHigh']}</b>　🔻 區間最低：<b>{$alert1h['minLow']}</b>\n"
                      . "📊 振幅：<b>{$alert1h['pctFmt']}</b>（{$alert1h['diffFmt']}）　⚠️ 閾值 " . self::ALERT_1H_PCT . "%";
            }

            // ── 台指現況（附帶在油價告警內）──────────────────────
            if ($twPrice !== null) {
                $twBlock = $this->buildTwIndexBlock($twPrice);
                if ($twBlock !== '') {
                    $msg .= "\n\n" . $twBlock;
                }
            }

            $msg .= $this->buildNewsBlock(3, 1);
            $this->pushTelegram($msg);
        }

        return 0;
    }

    // ────────────────────────────────────────────────────────────
    //  抓取台指現價（Yahoo Finance TW）
    //  回傳 [price, refreshedAt(台北時間字串)] 或 [null, null]
    // ────────────────────────────────────────────────────────────
    private function fetchTwIndex(): array
    {
        $client = new Client(['timeout' => 10]);
        try {
            $res = $client->get(self::TW_INDEX_URL, [
                'query' => [
                    'intl'       => 'tw',
                    'lang'       => 'zh-Hant-TW',
                    'region'     => 'TW',
                    'site'       => 'finance',
                    'returnMeta' => 'true',
                ],
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Referer'    => 'https://tw.stock.yahoo.com/tw-futures/WTX',
                    'Accept'     => 'application/json, text/plain, */*',
                ],
            ]);
        } catch (\Exception $e) {
            $this->line('[ERROR] 台指 HTTP 失敗：' . $e->getMessage());
            return [null, null];
        }

        $data  = json_decode((string) $res->getBody(), true);
        $quote = $data['data'][0]['chart']['quote'] ?? [];
        $price = $quote['price'] ?? null;

        if (!is_numeric($price)) {
            return [null, null];
        }

        // refreshedTs 是 UTC（ISO8601），轉換成台北時間（UTC+8）
        $refreshedAt = null;
        if (!empty($quote['refreshedTs'])) {
            $ts          = strtotime($quote['refreshedTs']);
            $refreshedAt = date('Y-m-d H:i:s', $ts + 8 * 3600);
        }

        return [(float) $price, $refreshedAt];
    }

    // ────────────────────────────────────────────────────────────
    //  儲存台指現價
    //  以 refreshedTs（台北時間）對齊到 5 分鐘窗口作為 candle_at
    //  若無 refreshedTs 則用程式執行時間對齊
    // ────────────────────────────────────────────────────────────
    private function saveTwIndexPrice(float $price, ?string $refreshedAt): void
    {
        if ($refreshedAt !== null) {
            // 對齊到 5 分鐘窗口（例如 13:47 → 13:45）
            $ts       = strtotime($refreshedAt);
            $candleAt = date('Y-m-d H:i:s', (int) (floor($ts / 300) * 300));
        } else {
            $candleAt = date('Y-m-d H:i:s', (int) (floor(time() / 300) * 300));
        }

        OilPrice::updateOrCreate(
            ['ticker' => self::TW_TICKER, 'candle_at' => $candleAt],
            ['timeframe' => 'i5', 'close' => $price]
        );
    }

    // ────────────────────────────────────────────────────────────
    //  台指現況區塊：5分變化 + 近1小時振幅（附帶在油價告警訊息內）
    // ────────────────────────────────────────────────────────────
    private function buildTwIndexBlock(float $currentPrice): string
    {
        // 5分變化：最近兩筆記錄
        $last2 = OilPrice::where('ticker', self::TW_TICKER)
            ->whereNotNull('close')
            ->orderBy('candle_at', 'desc')
            ->limit(2)
            ->get();

        $change5m    = null;
        $changePct5m = null;
        if ($last2->count() >= 2) {
            $prevClose   = (float) $last2->last()->close;
            $change5m    = $currentPrice - $prevClose;
            $changePct5m = $prevClose > 0 ? ($change5m / $prevClose * 100) : null;
        }

        // 近1小時振幅
        $prevHourStart = date('Y-m-d H:00:00', time() - 3600);
        $hourRows = OilPrice::where('ticker', self::TW_TICKER)
            ->where('candle_at', '>=', $prevHourStart)
            ->whereNotNull('close')
            ->get(['close']);

        $amp1h   = null;
        $maxClose = null;
        $minClose = null;
        if ($hourRows->isNotEmpty()) {
            $maxClose = (float) $hourRows->max('close');
            $minClose = (float) $hourRows->min('close');
            $amp1h    = $minClose > 0 ? (($maxClose - $minClose) / $minClose * 100) : null;
        }

        $arrow = ($change5m !== null && $change5m >= 0) ? '📈' : '📉';
        $sign  = ($change5m !== null && $change5m >= 0) ? '+' : '';

        $block = "━━ 🇹🇼 台指現況 ━━\n"
               . "💹 當前：<b>" . number_format($currentPrice, 0) . "</b>";

        if ($change5m !== null) {
            $block .= "　{$arrow} 5分：<b>{$sign}" . number_format($change5m, 0) . "點</b>"
                    . "（" . sprintf('%s%.2f%%', $sign, $changePct5m) . "）";
        }

        if ($amp1h !== null) {
            $block .= "\n📊 近1小時振幅：<b>" . sprintf('%.2f%%', $amp1h) . "</b>"
                    . "　高 " . number_format($maxClose, 0)
                    . " / 低 " . number_format($minClose, 0);
        }

        return $block;
    }

    // ────────────────────────────────────────────────────────────
    //  存入 DB — 一次補寫當天所有 K 棒（避免因時序漏掉）
    // ────────────────────────────────────────────────────────────
    private function saveAllCandles(array $allCandles): int
    {
        $newCount = 0;
        foreach ($allCandles as $candle) {
            if (!isset($candle['t'])) {
                continue;
            }
            $candleAt = date('Y-m-d H:i:s', (int) $candle['t']);
            $record   = OilPrice::firstOrCreate(
                ['ticker' => self::TICKER, 'candle_at' => $candleAt],
                [
                    'timeframe' => 'i5',
                    'open'      => $candle['o'] ?? null,
                    'high'      => $candle['h'] ?? null,
                    'low'       => $candle['l'] ?? null,
                    'close'     => $candle['c'],
                    'volume'    => $candle['v'] ?? null,
                ]
            );
            if ($record->wasRecentlyCreated) {
                $newCount++;
            }
        }
        return $newCount;
    }

    // ────────────────────────────────────────────────────────────
    //  5 分鐘：當前 + 前一根 K 棒合併高低振幅
    //  未觸發回傳 null，觸發回傳資料陣列
    // ────────────────────────────────────────────────────────────
    private function calc5mAlert(string $candleAt): ?array
    {
        $current = OilPrice::where('ticker', self::TICKER)
            ->where('candle_at', $candleAt)
            ->first();

        $prev = OilPrice::where('ticker', self::TICKER)
            ->where('candle_at', '<', $candleAt)
            ->orderBy('candle_at', 'desc')
            ->first();

        if (!$current || $current->high === null || $current->low === null) {
            $this->line('  [5分告警] 缺少當前 K 棒高低價，略過');
            return null;
        }

        if (!$prev || $prev->high === null || $prev->low === null) {
            $this->line('  [5分告警] 缺少前一根 K 棒高低價，略過');
            return null;
        }

        $maxHigh     = max((float) $current->high, (float) $prev->high);
        $minLow      = min((float) $current->low,  (float) $prev->low);
        $pct         = $minLow > 0 ? (($maxHigh - $minLow) / $minLow * 100) : 0;
        $currClose   = (float) $current->close;
        $prevClose   = (float) $prev->close;
        $closeDiff   = $currClose - $prevClose;
        $closePct    = $prevClose > 0 ? ($closeDiff / $prevClose * 100) : 0;

        $this->line(sprintf('  [5分告警] 合併振幅 高%.4f 低%.4f = %.4f%%', $maxHigh, $minLow, $pct));

        if ($pct < self::ALERT_5M_PCT) {
            return null;
        }

        $up        = $closeDiff >= 0;
        $arrow     = $up ? '📈' : '📉';
        $direction = $up ? '上漲' : '下跌';
        $sign      = $up ? '+' : '';

        return [
            'maxHigh'     => $maxHigh,
            'minLow'      => $minLow,
            'pctFmt'      => sprintf('%.2f%%', $pct),
            'diffFmt'     => sprintf('%.4f', $maxHigh - $minLow),
            'fromTime'    => $prev->candle_at->format('H:i'),
            'currTime'    => substr($candleAt, 11, 5),
            'arrow'       => $arrow,
            'direction'   => $direction,
            'prevClose'   => $prevClose,
            'currClose'   => $currClose,
            'closeDiffFmt'=> sprintf('%s%.4f', $sign, $closeDiff),
            'closePctFmt' => sprintf('%s%.2f%%', $sign, $closePct),
        ];
    }

    // ────────────────────────────────────────────────────────────
    //  小時：上個整點 00 分 → 當前，區間高低振幅
    //  ★ 同時納入錨點（窗口前最後一根收盤），捕捉跨交易時段跳空缺口
    //  未觸發回傳 null，觸發回傳資料陣列
    // ────────────────────────────────────────────────────────────
    private function calc1hAlert(string $candleAt): ?array
    {
        $prevHourStart = date('Y-m-d H:00:00', strtotime($candleAt) - 3600);

        $rows = OilPrice::where('ticker', self::TICKER)
            ->whereBetween('candle_at', [$prevHourStart, $candleAt])
            ->whereNotNull('high')
            ->whereNotNull('low')
            ->orderBy('candle_at', 'asc')
            ->get(['candle_at', 'high', 'low', 'close']);

        if ($rows->isEmpty()) {
            $this->line('  [小時告警] 無資料，略過');
            return null;
        }

        $maxHigh   = (float) $rows->max('high');
        $minLow    = (float) $rows->min('low');
        $lastClose = (float) $rows->last()->close;

        // ── 錨點：窗口開始前的最後一根 K 棒收盤 ──────────────
        // 用途：讓跨交易時段跳空（例如週五收盤→週一開盤）也能被計算進振幅
        $anchor = OilPrice::where('ticker', self::TICKER)
            ->where('candle_at', '<', $prevHourStart)
            ->whereNotNull('close')
            ->orderBy('candle_at', 'desc')
            ->first();

        $anchorClose = $anchor ? (float) $anchor->close : null;
        $fromTime    = $anchor
            ? $anchor->candle_at->format('H:i')
            : substr($prevHourStart, 11, 5);

        if ($anchorClose !== null) {
            $maxHigh = max($maxHigh, $anchorClose);
            $minLow  = min($minLow,  $anchorClose);
        }

        $firstClose = $anchorClose ?? (float) $rows->first()->close;
        $pct        = $minLow > 0 ? (($maxHigh - $minLow) / $minLow * 100) : 0;
        $closeDiff  = $lastClose - $firstClose;
        $closePct   = $firstClose > 0 ? ($closeDiff / $firstClose * 100) : 0;

        $this->line(sprintf(
            '  [小時告警] 振幅 高%.4f 低%.4f = %.4f%%（錨點收盤 %s）',
            $maxHigh, $minLow, $pct,
            $anchorClose !== null ? number_format($anchorClose, 4) : 'N/A'
        ));

        if ($pct < self::ALERT_1H_PCT) {
            return null;
        }

        $up        = $closeDiff >= 0;
        $arrow     = $up ? '📈' : '📉';
        $direction = $up ? '上漲' : '下跌';
        $sign      = $up ? '+' : '';

        return [
            'maxHigh'     => $maxHigh,
            'minLow'      => $minLow,
            'pctFmt'      => sprintf('%.2f%%', $pct),
            'diffFmt'     => sprintf('%.4f', $maxHigh - $minLow),
            'fromTime'    => $fromTime,
            'currTime'    => substr($candleAt, 11, 5),
            'arrow'       => $arrow,
            'direction'   => $direction,
            'firstClose'  => $firstClose,
            'lastClose'   => $lastClose,
            'closeDiffFmt'=> sprintf('%s%.4f', $sign, $closeDiff),
            'closePctFmt' => sprintf('%s%.2f%%', $sign, $closePct),
        ];
    }

    // ────────────────────────────────────────────────────────────
    //  抓取 finviz API
    // ────────────────────────────────────────────────────────────
    private function fetchPrice(): array
    {
        $client   = new Client(['timeout' => 30]);
        $dateFrom = strtotime('today UTC');
        $rev      = (int) round(microtime(true) * 1000);

        try {
            $res = $client->get(self::FINVIZ_URL, [
                'query' => [
                    'aftermarket'          => '0',
                    'chartEventsVersion'   => '2',
                    'dateFrom'             => $dateFrom,
                    'events'               => 'true',
                    'financialAttachments' => '',
                    'instrument'           => 'futures',
                    'patterns'             => 'false',
                    'premarket'            => '0',
                    'rev'                  => $rev,
                    'ticker'               => self::TICKER,
                    'timeframe'            => 'i5',
                ],
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Referer'    => 'https://finviz.com/futures_charts.ashx?t=QA&ty=l&ta=0&p=i5',
                    'Accept'     => 'application/json, text/plain, */*',
                    'Origin'     => 'https://finviz.com',
                ],
            ]);
        } catch (\Exception $e) {
            $this->line('[ERROR] HTTP 請求失敗：' . $e->getMessage());
            return [null, []];
        }

        $body = (string) $res->getBody();

        if ($this->option('debug')) {
            $this->line('原始回應（前 600 字）：' . mb_substr($body, 0, 600));
        }

        $data = json_decode($body, true);

        if (!is_array($data)) {
            $this->line('[ERROR] 回應非 JSON：' . mb_substr($body, 0, 200));
            return [null, []];
        }

        return $this->parseAllCandles($data);
    }

    // ────────────────────────────────────────────────────────────
    //  解析所有 K 棒（finviz 並行陣列格式）
    //  回傳：[$lastPrice, $lastCandle, $allCandles[]]
    // ────────────────────────────────────────────────────────────
    private function parseAllCandles(array $data): array
    {
        $allCandles = [];

        // 從並行陣列建立所有 K 棒
        if (!empty($data['close']) && is_array($data['close'])) {
            $dates   = $data['date']   ?? [];
            $opens   = $data['open']   ?? [];
            $highs   = $data['high']   ?? [];
            $lows    = $data['low']    ?? [];
            $closes  = $data['close'];
            $volumes = $data['volume'] ?? [];

            foreach ($closes as $i => $c) {
                if (!is_numeric($c)) {
                    continue;
                }
                $allCandles[] = [
                    't' => $dates[$i]   ?? null,
                    'o' => $opens[$i]   ?? null,
                    'h' => $highs[$i]   ?? null,
                    'l' => $lows[$i]    ?? null,
                    'c' => (float) $c,
                    'v' => $volumes[$i] ?? null,
                ];
            }
        }

        if (empty($allCandles)) {
            return [null, [], []];
        }

        $lastIdx = count($allCandles) - 1;

        // 最後一根用 lastClose/lastOpen/lastHigh/lastLow 覆蓋（更即時）
        if (isset($data['lastClose']) && is_numeric($data['lastClose'])) {
            $allCandles[$lastIdx]['o'] = $data['lastOpen']   ?? $allCandles[$lastIdx]['o'];
            $allCandles[$lastIdx]['h'] = $data['lastHigh']   ?? $allCandles[$lastIdx]['h'];
            $allCandles[$lastIdx]['l'] = $data['lastLow']    ?? $allCandles[$lastIdx]['l'];
            $allCandles[$lastIdx]['c'] = (float) $data['lastClose'];
            $allCandles[$lastIdx]['v'] = $data['lastVolume'] ?? $allCandles[$lastIdx]['v'];
        }

        $lastCandle = $allCandles[$lastIdx];
        return [(float) $lastCandle['c'], $lastCandle, $allCandles];
    }

    // ────────────────────────────────────────────────────────────
    //  抓取相關新聞並組成訊息區塊
    // ────────────────────────────────────────────────────────────
    private function buildNewsBlock(int $limit, int $hours = 4): string
    {
        $header = "\n\n━━━━━━━━ 📰 相關新聞 ━━━━━━━━";

        if ($this->option('no-news')) {
            return $header . "\n⚙️ 已停用新聞搜尋（--no-news）";
        }

        $this->line('  [新聞] 搜尋相關新聞中...');

        try {
            $news = (new OilNewsService())->fetch($hours, $limit);
        } catch (\Throwable $e) {
            $this->warn('  [新聞] 取得失敗：' . $e->getMessage());
            return $header . "\n❌ 新聞獲取失敗：" . mb_substr($e->getMessage(), 0, 100);
        }

        if (empty($news)) {
            $this->line('  [新聞] 近 1 小時內無相關新聞');
            return $header . "\n📭 近 {$hours} 小時內無相關原油新聞";
        }

        $this->line('  [新聞] 取得 ' . count($news) . ' 則');

        $block = $header;

        foreach ($news as $i => $item) {
            $num    = $i + 1;
            $title  = mb_substr($item['title'], 0, 80) . (mb_strlen($item['title']) > 80 ? '…' : '');
            $block .= "\n\n{$num}. <b>{$title}</b>"
                    . "\n🔗 {$item['url']}"
                    . "\n📌 {$item['source']} · {$item['ago']}";
        }

        return $block;
    }

    // ────────────────────────────────────────────────────────────
    //  推送 Telegram
    // ────────────────────────────────────────────────────────────
    private function pushTelegram(string $text): void
    {
        $token  = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (!$token || !$chatId) {
            $this->warn('TG_BOT_TOKEN 或 TG_CHAT_ID 未設定，跳過推送。');
            return;
        }

        $client = new Client(['timeout' => 10]);

        try {
            $client->post(sprintf(self::TG_API, $token), [
                'json' => [
                    'chat_id'    => $chatId,
                    'text'       => $text,
                    'parse_mode' => 'HTML',
                ],
            ]);
            $this->info('  Telegram 推送成功。');
        } catch (\Exception $e) {
            $this->line('[ERROR] Telegram 推送失敗：' . $e->getMessage());
        }
    }
}
