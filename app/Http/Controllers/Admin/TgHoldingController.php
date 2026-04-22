<?php

namespace App\Http\Controllers\Admin;

use App\DisposalStock;
use App\TgBot;
use App\TgHolding;
use App\TgHoldingTrade;
use App\TgSettlement;
use App\TgWallet;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TgHoldingController extends Controller
{
    // 用戶持股總覽（以用戶為單位）
    public function holdingList(Request $request)
    {
        $botId  = $request->input('bot_id');
        $chatId = $request->input('tg_chat_id');

        // 查出所有有持股或有交易記錄的用戶（以 chat_id 為單位）
        $query = TgWallet::orderBy('id', 'desc');
        if ($botId)  $query->where('bot_id', $botId);
        if ($chatId) $query->where('tg_chat_id', $chatId);

        $wallets = $query->paginate(20)->appends($request->query());

        // 為每個用戶附加持股筆數、歷史損益
        $chatIds = $wallets->pluck('tg_chat_id')->toArray();

        $holdingCounts = TgHolding::whereIn('tg_chat_id', $chatIds)
            ->selectRaw('tg_chat_id, COUNT(*) as cnt, SUM(total_cost) as total_cost')
            ->groupBy('tg_chat_id')
            ->get()
            ->keyBy('tg_chat_id');

        $tradeProfits = TgHoldingTrade::whereIn('tg_chat_id', $chatIds)
            ->selectRaw('tg_chat_id, COUNT(*) as total, SUM(profit) as profit, SUM(profit > 0) as win')
            ->groupBy('tg_chat_id')
            ->get()
            ->keyBy('tg_chat_id');

        return view('admin.tg_holding_list', [
            'wallets'       => $wallets,
            'holdingCounts' => $holdingCounts,
            'tradeProfits'  => $tradeProfits,
            'bots'          => TgBot::orderBy('id')->get(['id', 'name']),
        ]);
    }

    // 單一用戶詳情
    public function userDetail(Request $request, $chatId)
    {
        $botId  = $request->input('bot_id');

        $wallet   = TgWallet::where('tg_chat_id', $chatId)
            ->when($botId, fn($q) => $q->where('bot_id', $botId))
            ->first();

        // 同股票＋同類型（現股/融資）合併為一筆，張數與成本加總，買進均價重新加權平均
        $holdings = TgHolding::where('tg_chat_id', $chatId)
            ->when($botId, fn($q) => $q->where('bot_id', $botId))
            ->get()
            ->groupBy(fn($h) => $h->stock_code . '_' . $h->is_margin)
            ->map(function ($group) {
                $first      = $group->first();
                $totalShares = $group->sum('shares');
                $totalCost   = $group->sum('total_cost');
                $avgPrice    = $totalShares > 0
                    ? round($group->sum(fn($h) => $h->buy_price * $h->shares) / $totalShares, 2)
                    : $first->buy_price;

                $first->shares     = $totalShares;
                $first->total_cost = $totalCost;
                $first->buy_price  = $avgPrice;
                return $first;
            })
            ->sortByDesc('created_at')
            ->values();

        $trades = TgHoldingTrade::where('tg_chat_id', $chatId)
            ->when($botId, fn($q) => $q->where('bot_id', $botId))
            ->orderBy('created_at', 'desc')
            ->get();

        $settlements = TgSettlement::where('tg_chat_id', $chatId)
            ->when($botId, fn($q) => $q->where('bot_id', $botId))
            ->where('is_settled', 0)
            ->orderBy('settle_date')
            ->get();

        $tradeTotal  = $trades->count();
        $tradeWin    = $trades->where('profit', '>', 0)->count();
        $tradeProfit = $trades->sum('profit');
        $holdingCost = $holdings->sum('total_cost');
        $settleBuy   = $settlements->where('direction', 'buy')->sum('settlement_amount');
        $settleSell  = $settlements->where('direction', 'sell')->sum('settlement_amount');

        // 取得目前持股中有哪些是處置股
        $holdingCodes = $holdings->pluck('stock_code')->unique()->toArray();
        $disposalCodes = DisposalStock::whereIn('stock_code', $holdingCodes)
            ->where('end_date', '>=', now()->toDateString())
            ->pluck('stock_code')
            ->flip()
            ->toArray();

        return view('admin.tg_user_detail', [
            'chatId'      => $chatId,
            'wallet'      => $wallet,
            'holdings'    => $holdings,
            'trades'      => $trades,
            'settlements' => $settlements,
            'holdingCost' => $holdingCost,
            'tradeTotal'  => $tradeTotal,
            'tradeWin'    => $tradeWin,
            'tradeWinPct' => $tradeTotal > 0 ? round($tradeWin / $tradeTotal * 100) : 0,
            'tradeProfit' => $tradeProfit,
            'settleBuy'    => $settleBuy,
            'settleSell'   => $settleSell,
            'settleNet'    => $settleSell - $settleBuy,
            'bots'         => TgBot::orderBy('id')->get(['id', 'name']),
            'disposalCodes' => $disposalCodes,
        ]);
    }

    // 歷史交易記錄（保留，可從用戶詳情連結過來）
    public function tradeList(Request $request)
    {
        $query = TgHoldingTrade::orderBy('id', 'desc');

        if ($botId = $request->input('bot_id'))       $query->where('bot_id', $botId);
        if ($chatId = $request->input('tg_chat_id'))  $query->where('tg_chat_id', $chatId);
        if ($code = $request->input('stock_code'))    $query->where('stock_code', 'like', "%{$code}%");

        $list = $query->paginate(20)->appends($request->query());

        $totalProfit = TgHoldingTrade::when($request->input('bot_id'), fn($q, $v) => $q->where('bot_id', $v))
            ->when($request->input('tg_chat_id'), fn($q, $v) => $q->where('tg_chat_id', $v))
            ->when($request->input('stock_code'), fn($q, $v) => $q->where('stock_code', 'like', "%{$v}%"))
            ->sum('profit');

        return view('admin.tg_holding_trade_list', [
            'list'        => $list,
            'totalProfit' => $totalProfit,
            'bots'        => TgBot::orderBy('id')->get(['id', 'name']),
        ]);
    }
}
