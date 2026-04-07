<?php

namespace App\Http\Controllers\Admin;

use App\TgBot;
use App\TgHolding;
use App\TgHoldingTrade;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TgHoldingController extends Controller
{
    // 當前持股列表
    public function holdingList(Request $request)
    {
        $query = TgHolding::orderBy('id', 'desc');

        if ($botId = $request->input('bot_id')) {
            $query->where('bot_id', $botId);
        }
        if ($chatId = $request->input('tg_chat_id')) {
            $query->where('tg_chat_id', $chatId);
        }
        if ($code = $request->input('stock_code')) {
            $query->where('stock_code', 'like', "%{$code}%");
        }

        return view('admin.tg_holding_list', [
            'list' => $query->paginate(20)->appends($request->query()),
            'bots' => TgBot::orderBy('id')->get(['id', 'name']),
        ]);
    }

    // 歷史交易記錄
    public function tradeList(Request $request)
    {
        $query = TgHoldingTrade::orderBy('id', 'desc');

        if ($botId = $request->input('bot_id')) {
            $query->where('bot_id', $botId);
        }
        if ($chatId = $request->input('tg_chat_id')) {
            $query->where('tg_chat_id', $chatId);
        }
        if ($code = $request->input('stock_code')) {
            $query->where('stock_code', 'like', "%{$code}%");
        }

        $list = $query->paginate(20)->appends($request->query());

        // 計算篩選後的總損益
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
