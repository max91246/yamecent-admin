<?php

namespace App\Console\Commands;

use App\OilPrice;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FetchTwIndex extends Command
{
    protected $signature   = 'fetch:tw-index
                              {--no-tg : 只存 DB，不推送 Telegram}
                              {--debug : 顯示原始 API 回應}';
    protected $description = '每分鐘抓取台指期 / VIX 現價，存 DB 並在 5 分鐘震盪 ≥ 50 點時發送告警';

    const TW_TICKER          = 'WTX';
    const VIX_TICKER         = 'VIX';
    const TG_API             = 'https://api.telegram.org/bot%s/sendMessage';
    const ALERT_POINTS       = 50;     // 5 分鐘震盪閾值（點）
    const ALERT_COOLDOWN_SEC = 300;    // 告警冷卻 5 分鐘，避免重複推送

    public function handle()
    {
        // ── 1. 抓取台指 ──────────────────────────────────────────
        [$twPrice, $twRefreshedAt] = $this->fetchTwIndex();

        if ($twPrice === null) {
            $this->line('  [台指] 取得失敗或休市，略過');
            return 0;
        }

        $this->saveTwIndexPrice($twPrice, $twRefreshedAt);
        $this->line(sprintf('  [台指] 現價 %.0f（%s）', $twPrice, $twRefreshedAt ?? 'N/A'));

        // ── 2. 抓取 VIX ─────────────────────────────────────────
        [$vixPrice, $vixTime] = $this->fetchVix();
        if ($vixPrice !== null) {
            $this->saveVixPrice($vixPrice, $vixTime);
            $this->line(sprintf('  [VIX] %.2f（%s）', $vixPrice, $vixTime));
        }

        if ($this->option('no-tg')) {
            return 0;
        }

        // ── 3. 5 分鐘震盪告警 ────────────────────────────────────
        $alert = $this->calcAlert($twPrice);
        if ($alert === null) {
            return 0;
        }

        // 冷卻鎖：5 分鐘內不重複告警
        if (!Cache::add('wtx_alert_lock', 1, self::ALERT_COOLDOWN_SEC)) {
            $this->line('  [台指告警] 冷卻中，略過');
            return 0;
        }

        $this->warn(sprintf('  [台指告警] %s %s %s點', $alert['arrow'], $alert['direction'], $alert['pointsFmt']));

        $msg = "🚨 <b>台指期貨 5分震盪</b>\n"
             . "💹 當前：<b>" . number_format($twPrice, 0) . "</b>（{$alert['currTime']}）\n"
             . "{$alert['arrow']} <b>方向：{$alert['direction']}</b>　"
             . "{$alert['prevClose']} → <b>{$alert['currClose']}</b>（<b>{$alert['pointsFmt']}點</b> / <b>{$alert['pctFmt']}</b>）\n"
             . "📅 比較基準：{$alert['prevTime']}\n"
             . "⚠️ 閾值 " . self::ALERT_POINTS . " 點";

        if ($vixPrice !== null) {
            $msg .= "\n\n" . $this->buildVixBlock($vixPrice);
        }

        $this->pushTelegram($msg);

        return 0;
    }

    // ─── 告警計算：當前價 vs 5 分鐘前最近一筆 ──────────────────
    private function calcAlert(float $currentPrice): ?array
    {
        $fiveMinAgo = date('Y-m-d H:i:s', time() - self::ALERT_COOLDOWN_SEC);

        $prevRecord = OilPrice::where('ticker', self::TW_TICKER)
            ->where('candle_at', '<=', $fiveMinAgo)
            ->whereNotNull('close')
            ->orderBy('candle_at', 'desc')
            ->first();

        if (!$prevRecord) {
            $this->line('  [台指告警] 5分鐘前無記錄，略過');
            return null;
        }

        $prevClose = (float) $prevRecord->close;
        $diff      = $currentPrice - $prevClose;
        $absDiff   = abs($diff);
        $pct       = $prevClose > 0 ? ($diff / $prevClose * 100) : 0;

        $this->line(sprintf('  [台指告警] 5分前 %.0f → 現 %.0f = %.0f 點', $prevClose, $currentPrice, $diff));

        if ($absDiff < self::ALERT_POINTS) {
            return null;
        }

        $up        = $diff >= 0;
        $sign      = $up ? '+' : '';

        return [
            'arrow'      => $up ? '📈' : '📉',
            'direction'  => $up ? '上漲' : '下跌',
            'prevClose'  => number_format($prevClose, 0),
            'currClose'  => number_format($currentPrice, 0),
            'pointsFmt'  => sprintf('%s%.0f', $sign, $diff),
            'pctFmt'     => sprintf('%s%.2f%%', $sign, $pct),
            'prevTime'   => $prevRecord->candle_at->format('H:i'),
            'currTime'   => date('H:i'),
        ];
    }

    // ─── 抓取台指（Yahoo Finance TW）───────────────────────────
    private function fetchTwIndex(): array
    {
        $client = new Client(['timeout' => 10]);
        try {
            $res = $client->get(getConfig('tw_index_api_url'), [
                'query' => [
                    'intl'       => 'tw',
                    'lang'       => 'zh-Hant-TW',
                    'region'     => 'TW',
                    'site'       => 'finance',
                    'returnMeta' => 'true',
                ],
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Referer'    => 'https://tw.stock.yahoo.com/tw-futures/WTX',
                    'Accept'     => 'application/json, text/plain, */*',
                ],
            ]);
        } catch (\Exception $e) {
            $this->line('[ERROR] 台指 HTTP 失敗：' . $e->getMessage());
            return [null, null];
        }

        if ($this->option('debug')) {
            $this->line(mb_substr((string) $res->getBody(), 0, 400));
        }

        $data  = json_decode((string) $res->getBody(), true);
        $quote = $data['data'][0]['chart']['quote'] ?? [];
        $price = $quote['price'] ?? null;

        if (!is_numeric($price)) {
            return [null, null];
        }

        $refreshedAt = null;
        if (!empty($quote['refreshedTs'])) {
            $ts = strtotime($quote['refreshedTs']);
            if ($ts < time() - 900) {
                $this->line('  [台指] 資料超過 15 分鐘未更新，視為休市，略過');
                return [null, null];
            }
            $refreshedAt = date('Y-m-d H:i:s', $ts);
        }

        return [(float) $price, $refreshedAt];
    }

    // ─── 儲存台指（對齊 5 分鐘窗口）───────────────────────────
    private function saveTwIndexPrice(float $price, ?string $refreshedAt): void
    {
        $ts       = $refreshedAt ? strtotime($refreshedAt) : time();
        $candleAt = date('Y-m-d H:i:s', (int) (floor($ts / 300) * 300));

        OilPrice::updateOrCreate(
            ['ticker' => self::TW_TICKER, 'candle_at' => $candleAt],
            ['timeframe' => 'i5', 'close' => $price]
        );
    }

    // ─── 抓取 VIX（Yahoo Finance）──────────────────────────────
    private function fetchVix(): array
    {
        $client = new Client(['timeout' => 10]);
        try {
            $res = $client->get(getConfig('vix_api_url'), [
                'query' => [
                    'interval'       => '5m',
                    'range'          => '1d',
                    'includePrePost' => 'true',
                    'lang'           => 'zh-Hant-HK',
                    'region'         => 'HK',
                ],
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept'     => 'application/json, text/plain, */*',
                ],
            ]);
        } catch (\Exception $e) {
            return [null, null];
        }

        $data = json_decode((string) $res->getBody(), true);
        $meta = $data['chart']['result'][0]['meta'] ?? null;

        if (!$meta || !isset($meta['regularMarketPrice'])) {
            return [null, null];
        }

        $price       = (float) $meta['regularMarketPrice'];
        $ts          = $meta['regularMarketTime'] ?? time();
        $refreshedAt = date('Y-m-d H:i:s', $ts);

        return [$price, $refreshedAt];
    }

    // ─── 儲存 VIX（對齊 5 分鐘窗口）────────────────────────────
    private function saveVixPrice(float $price, ?string $refreshedAt): void
    {
        $ts       = $refreshedAt ? strtotime($refreshedAt) : time();
        $candleAt = date('Y-m-d H:i:s', (int) (floor($ts / 300) * 300));

        OilPrice::updateOrCreate(
            ['ticker' => self::VIX_TICKER, 'candle_at' => $candleAt],
            ['timeframe' => 'i5', 'close' => $price]
        );
    }

    // ─── VIX 附帶區塊 ────────────────────────────────────────
    private function buildVixBlock(float $currentPrice): string
    {
        $last2 = OilPrice::where('ticker', self::VIX_TICKER)
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

        $arrow = ($change5m !== null && $change5m >= 0) ? '📈' : '📉';
        $sign  = ($change5m !== null && $change5m >= 0) ? '+' : '';

        $block = "━━ 😨 VIX 恐慌指數 ━━\n"
               . "📊 當前：<b>" . number_format($currentPrice, 2) . "</b>";

        if ($change5m !== null) {
            $block .= "　{$arrow} 5分：<b>{$sign}" . number_format($change5m, 2) . "</b>"
                    . "（" . sprintf('%s%.2f%%', $sign, $changePct5m) . "）";
        }

        return $block;
    }

    // ─── 推送 Telegram ───────────────────────────────────────
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
