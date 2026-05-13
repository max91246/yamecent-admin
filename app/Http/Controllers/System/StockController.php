<?php

namespace App\Http\Controllers\System;

use App\DisposalStock;
use App\OilPrice;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StockController extends Controller
{
    // ── 處置股 ────────────────────────────────────────────────

    public function disposals(Request $request)
    {
        $query = DisposalStock::query();
        if ($code = $request->input('stock_code')) {
            $query->where(fn($q) => $q->where('stock_code', 'like', "%{$code}%")->orWhere('stock_name', 'like', "%{$code}%"));
        }
        if ($market = $request->input('market')) $query->where('market', $market);
        if ($request->input('status', 'active') === 'active') $query->where('end_date', '>=', now()->toDateString());

        $pageSize    = (int)$request->input('pageSize', 20);
        $currentPage = (int)$request->input('currentPage', 1);
        $paginator   = $query->orderBy('start_date', 'desc')->paginate($pageSize, ['*'], 'page', $currentPage);

        return response()->json(['success' => true, 'data' => [
            'list'        => collect($paginator->items())->map(fn($d) => [
                'id'            => $d->id,
                'market'        => $d->market,
                'stockCode'     => $d->stock_code,
                'stockName'     => $d->stock_name,
                'announcedDate' => $d->announced_date?->format('Y-m-d'),
                'startDate'     => $d->start_date?->format('Y-m-d'),
                'endDate'       => $d->end_date?->format('Y-m-d'),
                'reason'        => $d->reason,
                'isActive'      => $d->end_date >= now()->toDateString(),
            ]),
            'total' => $paginator->total(), 'pageSize' => $pageSize, 'currentPage' => $currentPage,
        ]]);
    }

    // ── 油價 ──────────────────────────────────────────────────

    public function oilPrices(Request $request)
    {
        $query = OilPrice::query();
        if ($ticker    = $request->input('ticker'))    $query->where('ticker', $ticker);
        if ($timeframe = $request->input('timeframe')) $query->where('timeframe', $timeframe);
        if ($from = $request->input('from')) $query->where('candle_at', '>=', $from);
        if ($to   = $request->input('to'))   $query->where('candle_at', '<=', $to);

        $pageSize    = (int)$request->input('pageSize', 50);
        $currentPage = (int)$request->input('currentPage', 1);
        $paginator   = $query->orderBy('candle_at', 'desc')->paginate($pageSize, ['*'], 'page', $currentPage);

        return response()->json(['success' => true, 'data' => [
            'list'        => collect($paginator->items())->map(fn($o) => [
                'id'        => $o->id,
                'ticker'    => $o->ticker,
                'timeframe' => $o->timeframe,
                'candleAt'  => $o->candle_at?->format('Y-m-d H:i'),
                'open'      => $o->open,
                'high'      => $o->high,
                'low'       => $o->low,
                'close'     => $o->close,
                'volume'    => $o->volume,
            ]),
            'total' => $paginator->total(), 'pageSize' => $pageSize, 'currentPage' => $currentPage,
        ]]);
    }
}
