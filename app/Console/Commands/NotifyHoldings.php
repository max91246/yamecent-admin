<?php

namespace App\Console\Commands;

use App\TgBot;
use App\TgHolding;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class NotifyHoldings extends Command
{
    protected $signature   = 'notify:holdings
                              {--dry-run : 只計算輸出，不推送 Telegram}';
    protected $description = '每日收盤後審視所有持股，漲跌幅超過閾值時推送 Telegram 通知';

    const TG_API = 'https://api.telegram.org/bot%s/sendMessage';

    public function handle()
    {
        $threshold = (float) getConfig('holding_alert_pct', 10);

        // 取得所有不重複的 (bot_id, tg_chat_id)
        $users = TgHolding::select('bot_id', 'tg_chat_id')
            ->distinct()
            ->get();

        if ($users->isEmpty()) {
            $this->line('  [持股通知] 無持股記錄，略過');
            return 0;
        }

        $this->line("  [持股通知] 共 {$users->count()} 組用戶，閾值 ±{$threshold}%");

        foreach ($users as $user) {
            $bot = TgBot::find($user->bot_id);
            if (!$bot || !$bot->is_active) {
                $this->line("  [持股通知] bot_id={$user->bot_id} 不存在或未啟用，略過");
                continue;
            }

            $holdings = TgHolding::where('bot_id', $user->bot_id)
                ->where('tg_chat_id', $user->tg_chat_id)
                ->get();

            if ($holdings->isEmpty()) {
                continue;
            }

            $alerts = [];

            foreach ($holdings as $holding) {
                $quote = $this->fetchStockQuote($holding->stock_code);

                if ($quote === null) {
                    $this->line("  [持股通知] {$holding->stock_code} 無法取得報價，略過");
                    continue;
                }

                $currentPrice = (float) $quote['price'];
                $buyPrice     = (float) $holding->buy_price;

                if ($buyPrice <= 0) {
                    continue;
                }

                $diff   = $currentPrice - $buyPrice;
                $pct    = $diff / $buyPrice * 100;
                $absPct = abs($pct);

                $this->line(sprintf(
                    '  [%s %s] 買%.2f 現%.2f → %+.2f%%',
                    $holding->stock_code, $holding->stock_name,
                    $buyPrice, $currentPrice, $pct
                ));

                if ($absPct < $threshold) {
                    continue;
                }

                $isProfit = $diff >= 0;
                $sign     = $isProfit ? '+' : '';
                $emoji    = $isProfit ? '📈' : '📉';
                $label    = $isProfit ? '獲利' : '虧損';

                $alerts[] = "{$emoji} <b>{$holding->stock_name}（{$holding->stock_code}）</b>\n"
                          . "   買入：<b>{$buyPrice}</b>　現價：<b>{$currentPrice}</b>\n"
                          . "   {$label}：<b>{$sign}" . number_format($pct, 2) . "%</b>";
            }

            if (empty($alerts)) {
                $this->line("  [持股通知] chat_id={$user->tg_chat_id} 無超過閾值持股，略過");
                continue;
            }

            $msg = "⚠️ <b>每日持股提醒</b>\n"
                 . "以下持股漲跌幅超過 {$threshold}%，請特別注意：\n"
                 . "─────────────────\n"
                 . implode("\n\n", $alerts);

            $this->warn("  [持股通知] chat_id={$user->tg_chat_id} 觸發 " . count($alerts) . " 筆");

            if ($this->option('dry-run')) {
                $this->line($msg);
            } else {
                $this->pushTelegram($bot->token, $user->tg_chat_id, $msg);
            }
        }

        $this->info('  [持股通知] 完成');
        return 0;
    }

    // ─── 抓取股票現價（含快取，同一次執行相同股票只查一次）───────
    private function fetchStockQuote(string $code): ?array
    {
        $cacheKey = 'notify-tw-' . strtoupper($code);
        $cached   = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
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
            $price = $quote['price'] ?? null;

            if ($price === null) {
                return null;
            }

            $result = ['price' => (float) $price];
            Cache::put($cacheKey, $result, 1800); // 快取 30 分鐘，收盤後不會變

            return $result;
        } catch (\Exception $e) {
            $this->line('[ERROR] fetchStockQuote ' . $code . '：' . $e->getMessage());
            return null;
        }
    }

    // ─── 推送 Telegram ───────────────────────────────────────────
    private function pushTelegram(string $token, $chatId, string $text): void
    {
        $client = new Client(['timeout' => 10]);
        try {
            $client->post(sprintf(self::TG_API, $token), [
                'json' => [
                    'chat_id'    => $chatId,
                    'text'       => $text,
                    'parse_mode' => 'HTML',
                ],
            ]);
            $this->info("  Telegram 推送成功（chat_id={$chatId}）");
        } catch (\Exception $e) {
            $this->line('[ERROR] Telegram 推送失敗：' . $e->getMessage());
        }
    }
}
