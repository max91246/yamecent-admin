<?php

namespace App\Console\Commands;

use App\TgSettlement;
use App\TgWallet;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SettlePayments extends Command
{
    protected $signature   = 'settle:payments';
    protected $description = '結算今日到期的 T+2 交割款，從 wallet 扣除並標記已完成';

    public function handle()
    {
        $today = Carbon::now('Asia/Taipei')->toDateString();

        // 只處理買入交割（sell 方向 wallet 已即時加回，僅標記完成）
        TgSettlement::where('is_settled', 0)
            ->where('direction', 'sell')
            ->where('settle_date', '<=', $today)
            ->update(['is_settled' => 1]);

        $dues = TgSettlement::where('is_settled', 0)
            ->where('direction', '!=', 'sell')
            ->where('settle_date', '<=', $today)
            ->get();

        if ($dues->isEmpty()) {
            $this->line('  [結算] 今日無到期款項');
            return 0;
        }

        foreach ($dues as $s) {
            $wallet = TgWallet::where('bot_id', $s->bot_id)
                ->where('tg_chat_id', $s->tg_chat_id)
                ->first();

            if ($wallet) {
                $wallet->decrement('capital', $s->settlement_amount);
            }

            $s->update(['is_settled' => 1]);

            $this->line("  [結算] {$s->stock_name}（{$s->stock_code}）"
                . " 交割日 {$s->settle_date} NT$" . number_format($s->settlement_amount, 0));
        }

        $this->info("  [結算] 共處理 {$dues->count()} 筆，完成。");
        return 0;
    }
}
