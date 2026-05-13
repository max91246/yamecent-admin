<?php

namespace App\Http\Controllers\System;

use App\Article;
use App\AvActress;
use App\AvVideo;
use App\DisposalStock;
use App\Member;
use App\TgMessage;
use App\TgWallet;
use App\TgHoldingTrade;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats()
    {
        $today     = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        // ── 數字卡片 ──────────────────────────────────────────────
        $cards = [
            'articles'      => [
                'total'   => Article::count(),
                'today'   => Article::whereDate('created_at', $today)->count(),
            ],
            'members'       => [
                'total'   => Member::count(),
                'today'   => Member::whereDate('created_at', $today)->count(),
            ],
            'avVideos'      => [
                'total'   => AvVideo::count(),
                'today'   => AvVideo::whereDate('created_at', $yesterday)->count(), // D-1 入庫
            ],
            'avActresses'   => [
                'total'   => AvActress::count(),
                'today'   => AvActress::whereDate('created_at', $today)->count(),
            ],
            'tgMessages'    => [
                'total'   => TgMessage::count(),
                'today'   => TgMessage::whereDate('created_at', $today)->count(),
            ],
            'disposalActive' => [
                'total'   => DisposalStock::where('end_date', '>=', $today)->count(),
            ],
        ];

        // ── 影片每日入庫趨勢（近 14 天）──────────────────────────
        $videoTrend = collect(range(13, 0))->map(function ($i) {
            $date  = now()->subDays($i)->toDateString();
            $count = AvVideo::whereDate('created_at', $date)->count();
            return ['date' => $date, 'count' => $count];
        })->values();

        // ── TG 訊息趨勢（近 7 天，收/回分開）────────────────────
        $msgTrend = collect(range(6, 0))->map(function ($i) {
            $date = now()->subDays($i)->toDateString();
            return [
                'date'    => $date,
                'receive' => TgMessage::whereDate('created_at', $date)->where('direction', 1)->count(),
                'reply'   => TgMessage::whereDate('created_at', $date)->where('direction', 2)->count(),
            ];
        })->values();

        // ── 當前處置股（最多 10 筆）──────────────────────────────
        $disposals = DisposalStock::where('end_date', '>=', $today)
            ->orderBy('start_date', 'desc')
            ->limit(10)
            ->get(['market', 'stock_code', 'stock_name', 'start_date', 'end_date', 'reason'])
            ->map(fn($d) => [
                'market'    => $d->market,
                'stockCode' => $d->stock_code,
                'stockName' => $d->stock_name,
                'startDate' => $d->start_date?->format('Y-m-d'),
                'endDate'   => $d->end_date?->format('Y-m-d'),
                'reason'    => $d->reason,
            ]);

        // ── TG 損益排行（前 5）───────────────────────────────────
        $profitRank = TgHoldingTrade::select('tg_chat_id', DB::raw('SUM(profit) as total_profit'), DB::raw('COUNT(*) as trade_count'))
            ->groupBy('tg_chat_id')
            ->orderBy('total_profit', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($r) => [
                'chatId'      => $r->tg_chat_id,
                'totalProfit' => $r->total_profit,
                'tradeCount'  => $r->trade_count,
            ]);

        return response()->json([
            'success' => true,
            'data'    => compact('cards', 'videoTrend', 'msgTrend', 'disposals', 'profitRank'),
        ]);
    }
}
