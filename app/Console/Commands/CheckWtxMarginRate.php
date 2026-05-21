<?php

namespace App\Console\Commands;

use App\OilPrice;
use App\TgBot;
use App\TgFuturesPosition;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckWtxMarginRate extends Command
{
    protected $signature   = 'check:wtx-margin-rate';
    protected $description = '交易時段每小時檢查台指持倉維持率，低於警戒線時發 TG 通知';

    // 告警冷卻 4 小時，避免同一用戶重複收到
    const COOLDOWN_SEC = 14400;

    public function handle()
    {
        // ── 交易時段判斷（日盤 08:45–13:45 / 夜盤 15:00–05:00）─────
        if (!$this->isTradingHours()) {
            $this->line('  [維持率] 非交易時段，略過');
            return 0;
        }

        $initialMargin = (int) (getConfig('wtx_margin_initial')  ?: 0);
        $maintMargin   = (int) (getConfig('wtx_margin_maintain') ?: 0);
        $threshold     = (float) (getConfig('wtx_margin_alert_rate') ?: 80);

        if (!$initialMargin) {
            $this->line('  [維持率] 保證金資料未設定，請先執行 fetch:wtx-margin');
            return 0;
        }

        $latest = OilPrice::where('ticker', 'WTX')
            ->whereNotNull('close')
            ->orderBy('candle_at', 'desc')
            ->first();

        if (!$latest) {
            $this->line('  [維持率] 無台指現價，略過');
            return 0;
        }

        $currentPrice = (int) $latest->close;
        $warnRate     = $maintMargin > 0 ? round($maintMargin / $initialMargin * 100, 1) : null;

        // ── 按 bot_id + tg_chat_id 聚合持倉 ─────────────────────────
        $positions = TgFuturesPosition::where('is_open', 1)->get();

        if ($positions->isEmpty()) {
            $this->line('  [維持率] 無持倉記錄');
            return 0;
        }

        $grouped = $positions->groupBy(fn($p) => "{$p->bot_id}_{$p->tg_chat_id}");
        $notified = 0;

        foreach ($grouped as $userPositions) {
            $botId  = $userPositions->first()->bot_id;
            $chatId = (int) $userPositions->first()->tg_chat_id;

            // 計算用戶總維持率
            $totalInit   = 0;
            $totalAmount = 0;
            $lines       = [];

            foreach ($userPositions as $pos) {
                $posInit    = $pos->contracts * $initialMargin;
                $posAmount  = ($currentPrice - $pos->entry_point) * $pos->contracts * 50;
                $totalInit   += $posInit;
                $totalAmount += $posAmount;

                $sign  = $posAmount >= 0 ? '+' : '';
                $arrow = $posAmount >= 0 ? '▲' : '▼';
                $rate  = $posInit > 0 ? round(($posInit + $posAmount) / $posInit * 100, 1) : null;
                $lines[] = "  {$arrow} " . number_format($pos->entry_point) . " × {$pos->contracts}口"
                         . "　{$sign}NT$" . number_format($posAmount)
                         . ($rate !== null ? "　{$rate}%" : '');
            }

            if ($totalInit <= 0) continue;

            $equity      = $totalInit + $totalAmount;
            $overallRate = round($equity / $totalInit * 100, 1);

            if ($overallRate >= $threshold) {
                $this->line("  [維持率] chatId={$chatId} rate={$overallRate}% 正常");
                continue;
            }

            // ── 冷卻判斷（4小時內不重複通知）────────────────────────
            $cacheKey = "wtx_margin_alert:{$botId}:{$chatId}";
            if (Cache::has($cacheKey)) {
                $this->line("  [維持率] chatId={$chatId} rate={$overallRate}% 冷卻中，略過");
                continue;
            }
            Cache::put($cacheKey, true, self::COOLDOWN_SEC);

            $bot = TgBot::find($botId);
            if (!$bot || !$bot->is_active) continue;

            $totalSign = $totalAmount >= 0 ? '+' : '';
            $posBlock  = implode("\n", $lines);

            $msg = "⚠️ <b>台指保證金警告</b>\n\n"
                 . "📊 台指現價：<b>" . number_format($currentPrice) . "</b>\n"
                 . "💹 整體維持率：<b>{$overallRate}%</b>"
                 . ($warnRate ? "（警戒線：{$warnRate}%）" : '') . "\n"
                 . "💸 合計損益：<b>{$totalSign}NT$" . number_format($totalAmount) . "</b>\n\n"
                 . "<b>各部位明細：</b>\n{$posBlock}\n\n"
                 . "⚠️ 維持率已低於 <b>{$threshold}%</b>，請注意追繳保證金風險！";

            $this->pushTg($bot->token, $chatId, $msg);
            $notified++;
            $this->line("  [維持率] ⚠️ 已通知 chatId={$chatId} rate={$overallRate}%");
            Log::warning('[check:wtx-margin-rate] 維持率警告', [
                'chatId'       => $chatId,
                'overallRate'  => $overallRate,
                'threshold'    => $threshold,
                'currentPrice' => $currentPrice,
            ]);
        }

        $this->line("  [維持率] 完成，共通知 {$notified} 位用戶");
        return 0;
    }

    // ─── 交易時段：日盤 08:45–13:45 / 夜盤 15:00–05:00 ──────────
    private function isTradingHours(): bool
    {
        $now = Carbon::now('Asia/Taipei');
        $t   = $now->hour * 60 + $now->minute;

        $isDay   = ($t >= 8 * 60 + 45 && $t <= 13 * 60 + 45);
        $isNight = ($t >= 15 * 60 || $t < 5 * 60);

        return $isDay || $isNight;
    }

    private function pushTg(string $token, int $chatId, string $msg): void
    {
        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id'    => $chatId,
                'text'       => $msg,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Exception $e) {
            Log::error('[check:wtx-margin-rate] TG 發送失敗', ['error' => $e->getMessage()]);
        }
    }
}
