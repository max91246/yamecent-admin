<?php

namespace App\Console\Commands;

use App\TgSettlement;
use App\TgWallet;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SettlePayments extends Command
{
    protected $signature   = 'settle:payments';
    protected $description = '結算今日到期的 T+2 交割款，從 wallet 扣除並標記已完成';

    public function handle()
    {
        $today = Carbon::now('Asia/Taipei')->toDateString();

        $dues = TgSettlement::where('is_settled', 0)
            ->where('settle_date', '<=', $today)
            ->where('stock_code', '!=', 'MANUAL')
            ->get();

        if ($dues->isEmpty()) {
            $this->line('  [結算] 今日無到期款項');
            Log::channel('settle_payments')->info('今日無到期款項', ['date' => $today]);
            return 0;
        }

        Log::channel('settle_payments')->info('開始結算', ['date' => $today, 'count' => $dues->count()]);

        foreach ($dues as $s) {
            $wallet = TgWallet::where('bot_id', $s->bot_id)
                ->where('tg_chat_id', $s->tg_chat_id)
                ->first();

            $direction = $s->direction ?? 'buy';

            if ($wallet) {
                if ($direction === 'sell') {
                    // 賣出交割：將實收款加回 wallet
                    $wallet->increment('capital', $s->settlement_amount);
                    $this->line("  [結算-收款] {$s->stock_name}（{$s->stock_code}）"
                        . " 交割日 {$s->settle_date} +NT$" . number_format($s->settlement_amount, 0));
                    Log::channel('settle_payments')->info("結算-收款 {$s->stock_name}（{$s->stock_code}）", [
                        'settle_date' => $s->settle_date,
                        'amount'      => $s->settlement_amount,
                    ]);
                } else {
                    // 買入交割：扣除交割款
                    $wallet->decrement('capital', $s->settlement_amount);
                    $this->line("  [結算-付款] {$s->stock_name}（{$s->stock_code}）"
                        . " 交割日 {$s->settle_date} -NT$" . number_format($s->settlement_amount, 0));
                    Log::channel('settle_payments')->info("結算-付款 {$s->stock_name}（{$s->stock_code}）", [
                        'settle_date' => $s->settle_date,
                        'amount'      => $s->settlement_amount,
                    ]);
                }
            }

            $s->update(['is_settled' => 1]);
        }

        $this->info("  [結算] 共處理 {$dues->count()} 筆，完成。");
        Log::channel('settle_payments')->info('結算完成', ['processed' => $dues->count()]);
        return 0;
    }
}
