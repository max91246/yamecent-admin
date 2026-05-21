<?php

namespace App\Http\Controllers\System;

use App\DisposalStock;
use App\OilPrice;
use App\TgBot;
use App\TgFuturesPosition;
use App\TgHolding;
use App\TgHoldingTrade;
use App\TgMessage;
use App\TgSettlement;
use App\TgWallet;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TgController extends Controller
{
    // ── Bot 管理 ──────────────────────────────────────────────

    public function bots(Request $request)
    {
        $query = TgBot::query();
        if ($name = $request->input('name')) $query->where('name', 'like', "%{$name}%");
        if ($request->filled('is_active'))   $query->where('is_active', $request->input('is_active'));

        $pageSize    = (int)$request->input('pageSize', 10);
        $currentPage = (int)$request->input('currentPage', 1);
        $paginator   = $query->orderBy('id', 'desc')->paginate($pageSize, ['*'], 'page', $currentPage);

        return response()->json(['success' => true, 'data' => [
            'list'        => collect($paginator->items())->map(fn($b) => $this->formatBot($b)),
            'total'       => $paginator->total(),
            'pageSize'    => $pageSize,
            'currentPage' => $currentPage,
        ]]);
    }

    public function createBot(Request $request)
    {
        if (TgBot::where('token', $request->input('token'))->exists()) {
            return response()->json(['success' => false, 'msg' => 'Token 已存在'], 422);
        }
        $bot = TgBot::create($request->only(['name', 'token', 'type', 'is_active', 'remark']));
        $this->setWebhook($bot);
        return response()->json(['success' => true, 'data' => $this->formatBot($bot)]);
    }

    public function updateBot(Request $request, $id)
    {
        $bot = TgBot::findOrFail($id);
        $tokenChanged = $bot->token !== $request->input('token');
        $bot->update($request->only(['name', 'token', 'type', 'is_active', 'remark']));
        if ($tokenChanged) $this->setWebhook($bot->fresh());
        return response()->json(['success' => true, 'data' => $this->formatBot($bot->fresh())]);
    }

    public function destroyBot($id)
    {
        TgBot::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function setWebhookManual($id)
    {
        $bot = TgBot::findOrFail($id);
        $ok  = $this->setWebhook($bot);
        return response()->json(['success' => $ok, 'msg' => $ok ? 'Webhook 設定成功' : 'Webhook 設定失敗']);
    }

    // ── 訊息記錄 ──────────────────────────────────────────────

    public function messages(Request $request)
    {
        $query = TgMessage::query();
        if ($botId    = $request->input('bot_id'))     $query->where('bot_id', $botId);
        if ($username = $request->input('tg_username')) $query->where('tg_username', 'like', "%{$username}%");
        if ($request->filled('direction'))              $query->where('direction', $request->input('direction'));

        $pageSize    = (int)$request->input('pageSize', 20);
        $currentPage = (int)$request->input('currentPage', 1);
        $paginator   = $query->orderBy('id', 'desc')->paginate($pageSize, ['*'], 'page', $currentPage);

        return response()->json(['success' => true, 'data' => [
            'list'        => collect($paginator->items())->map(fn($m) => [
                'id'          => $m->id,
                'botId'       => $m->bot_id,
                'tgUserId'    => $m->tg_user_id,
                'tgUsername'  => $m->tg_username,
                'tgChatId'    => $m->tg_chat_id,
                'content'     => $m->content,
                'direction'   => $m->direction,
                'dirLabel'    => $m->direction === 1 ? '收到' : '回覆',
                'messageType' => $m->message_type,
                'createdAt'   => $m->created_at?->format('Y-m-d H:i:s'),
            ]),
            'total'       => $paginator->total(),
            'pageSize'    => $pageSize,
            'currentPage' => $currentPage,
        ]]);
    }

    // ── 持股概覽 ──────────────────────────────────────────────

    public function holdings(Request $request)
    {
        $query = TgWallet::query();
        if ($botId  = $request->input('bot_id'))    $query->where('bot_id', $botId);
        if ($chatId = $request->input('tg_chat_id')) $query->where('tg_chat_id', $chatId);

        $pageSize    = (int)$request->input('pageSize', 20);
        $currentPage = (int)$request->input('currentPage', 1);
        $paginator   = $query->orderBy('id', 'desc')->paginate($pageSize, ['*'], 'page', $currentPage);

        $chatIds = collect($paginator->items())->pluck('tg_chat_id');
        $holdingStats = TgHolding::whereIn('tg_chat_id', $chatIds)
            ->selectRaw('tg_chat_id, COUNT(*) as cnt, SUM(total_cost) as total_cost')
            ->groupBy('tg_chat_id')->get()->keyBy('tg_chat_id');
        $tradeStats = TgHoldingTrade::whereIn('tg_chat_id', $chatIds)
            ->selectRaw('tg_chat_id, COUNT(*) as total, SUM(profit) as profit')
            ->groupBy('tg_chat_id')->get()->keyBy('tg_chat_id');

        $list = collect($paginator->items())->map(function ($w) use ($holdingStats, $tradeStats) {
            $hs = $holdingStats[$w->tg_chat_id] ?? null;
            $ts = $tradeStats[$w->tg_chat_id]   ?? null;
            return [
                'id'           => $w->id,
                'botId'        => $w->bot_id,
                'tgChatId'     => $w->tg_chat_id,
                'tgUserId'     => $w->tg_user_id,
                'capital'      => $w->capital,
                'holdingCount' => $hs?->cnt ?? 0,
                'holdingCost'  => $hs?->total_cost ?? 0,
                'tradeTotal'   => $ts?->total ?? 0,
                'tradeProfit'  => $ts?->profit ?? 0,
                'updatedAt'    => $w->updated_at?->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json(['success' => true, 'data' => [
            'list' => $list, 'total' => $paginator->total(),
            'pageSize' => $pageSize, 'currentPage' => $currentPage,
        ]]);
    }

    // ── 交易記錄 ──────────────────────────────────────────────

    public function trades(Request $request)
    {
        $query = TgHoldingTrade::query();
        if ($botId  = $request->input('bot_id'))    $query->where('bot_id', $botId);
        if ($chatId = $request->input('tg_chat_id')) $query->where('tg_chat_id', $chatId);
        if ($code   = $request->input('stock_code')) $query->where('stock_code', 'like', "%{$code}%");

        $pageSize    = (int)$request->input('pageSize', 20);
        $currentPage = (int)$request->input('currentPage', 1);
        $paginator   = $query->orderBy('id', 'desc')->paginate($pageSize, ['*'], 'page', $currentPage);

        return response()->json(['success' => true, 'data' => [
            'list'        => collect($paginator->items())->map(fn($t) => [
                'id'         => $t->id,
                'botId'      => $t->bot_id,
                'tgChatId'   => $t->tg_chat_id,
                'stockCode'  => $t->stock_code,
                'stockName'  => $t->stock_name,
                'sellShares' => $t->sell_shares,
                'buyPrice'   => $t->buy_price,
                'sellPrice'  => $t->sell_price,
                'isMargin'   => $t->is_margin,
                'profit'     => $t->profit,
                'createdAt'  => $t->created_at?->format('Y-m-d H:i:s'),
            ]),
            'total'       => $paginator->total(),
            'pageSize'    => $pageSize,
            'currentPage' => $currentPage,
        ]]);
    }

    // ── 用戶持股明細 ──────────────────────────────────────────

    public function userDetail(Request $request, $chatId)
    {
        $botId = $request->input('bot_id');

        $wallet = TgWallet::where('tg_chat_id', $chatId)
            ->when($botId, fn($q) => $q->where('bot_id', $botId))
            ->first();

        // 同股票＋同類型合併，加權平均買入價
        $holdings = TgHolding::where('tg_chat_id', $chatId)
            ->when($botId, fn($q) => $q->where('bot_id', $botId))
            ->get()
            ->groupBy(fn($h) => $h->stock_code . '_' . $h->is_margin)
            ->map(function ($group) {
                $first       = $group->first();
                $totalShares = $group->sum('shares');
                $totalCost   = $group->sum('total_cost');
                $avgPrice    = $totalShares > 0
                    ? round($group->sum(fn($h) => $h->buy_price * $h->shares) / $totalShares, 2)
                    : $first->buy_price;
                return [
                    'stockCode'  => $first->stock_code,
                    'stockName'  => $first->stock_name,
                    'shares'     => $totalShares,
                    'isMargin'   => $first->is_margin,
                    'buyPrice'   => $avgPrice,
                    'totalCost'  => $totalCost,
                    'createdAt'  => $first->created_at?->format('m/d H:i'),
                    'isDisposal' => false, // 下面批次填入
                ];
            })->values();

        // 批次查詢哪些持股是處置股（避免 N+1）
        $holdingCodes  = $holdings->pluck('stockCode')->unique()->toArray();
        $disposalCodes = DisposalStock::whereIn('stock_code', $holdingCodes)
            ->where('end_date', '>=', now()->toDateString())
            ->pluck('stock_code')->flip()->toArray();
        $holdings = $holdings->map(function ($h) use ($disposalCodes) {
            $h['isDisposal'] = isset($disposalCodes[$h['stockCode']]);
            return $h;
        })->values();

        $trades = TgHoldingTrade::where('tg_chat_id', $chatId)
            ->when($botId, fn($q) => $q->where('bot_id', $botId))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($t) {
                $buyVal     = (float)$t->buy_price * (int)$t->sell_shares;
                $profitPct  = ($buyVal > 0) ? round((float)$t->profit / $buyVal * 100, 2) : null;
                return [
                    'stockCode'  => $t->stock_code,
                    'stockName'  => $t->stock_name,
                    'sellShares' => $t->sell_shares,
                    'buyPrice'   => $t->buy_price,
                    'sellPrice'  => $t->sell_price,
                    'isMargin'   => $t->is_margin,
                    'profit'     => $t->profit,
                    'profitPct'  => $profitPct,
                    'createdAt'  => $t->created_at?->format('m/d H:i'),
                ];
            });

        $settlements = TgSettlement::where('tg_chat_id', $chatId)
            ->when($botId, fn($q) => $q->where('bot_id', $botId))
            ->where('is_settled', 0)
            ->orderBy('settle_date')
            ->get()
            ->map(fn($s) => [
                'stockCode'        => $s->stock_code,
                'stockName'        => $s->stock_name,
                'shares'           => $s->shares,
                'buyPrice'         => $s->buy_price,
                'settlementAmount' => $s->settlement_amount,
                'settleDate'       => $s->settle_date,
                'direction'        => $s->direction,
            ]);

        $tradeTotal  = $trades->count();
        $tradeWin    = $trades->where('profit', '>', 0)->count();
        $tradeProfit = $trades->sum('profit');

        return response()->json(['success' => true, 'data' => [
            'chatId'      => $chatId,
            'wallet'      => $wallet ? [
                'capital'   => $wallet->capital,
                'tgUserId'  => $wallet->tg_user_id,
                'updatedAt' => $wallet->updated_at?->format('Y-m-d H:i:s'),
            ] : null,
            'holdings'    => $holdings,
            'holdingCost' => $holdings->sum('totalCost'),
            'trades'      => $trades,
            'tradeTotal'  => $tradeTotal,
            'tradeWin'    => $tradeWin,
            'tradeWinPct' => $tradeTotal > 0 ? round($tradeWin / $tradeTotal * 100) : 0,
            'tradeProfit' => $tradeProfit,
            'settlements' => $settlements,
        ]]);
    }

    // ── 取得所有 Bot（下拉用）──────────────────────────────────

    public function allBots()
    {
        return response()->json(['success' => true, 'data' => TgBot::orderBy('id')->get(['id', 'name'])]);
    }

    // ── helper ────────────────────────────────────────────────

    private function setWebhook(TgBot $bot): bool
    {
        try {
            $url      = rtrim(config('app.url'), '/') . '/api/tg/webhook/' . $bot->id;
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$bot->token}/setWebhook", ['url' => $url]);
            if ($response->successful() && $response->json('ok') === true) {
                $bot->update(['webhook_set_at' => now()]);
                return true;
            }
        } catch (\Exception $e) {}
        return false;
    }

    // ── 台指期貨持倉查詢 ──────────────────────────────────────

    public function futuresPositions(Request $request)
    {
        $query = TgFuturesPosition::with('bot');
        if ($botId  = $request->input('bot_id'))     $query->where('bot_id', $botId);
        if ($chatId = $request->input('tg_chat_id')) $query->where('tg_chat_id', $chatId);
        if ($request->filled('is_open'))             $query->where('is_open', $request->input('is_open'));

        $pageSize    = (int)$request->input('pageSize', 20);
        $currentPage = (int)$request->input('currentPage', 1);
        $paginator   = $query->orderBy('created_at', 'desc')->paginate($pageSize, ['*'], 'page', $currentPage);

        $wtxLatest    = OilPrice::where('ticker', 'WTX')->whereNotNull('close')->orderBy('candle_at', 'desc')->first();
        $currentPrice = $wtxLatest ? (int) $wtxLatest->close : null;

        $initialMargin  = (int) (getConfig('wtx_margin_initial')  ?: 0);
        $maintainMargin = (int) (getConfig('wtx_margin_maintain') ?: 0);

        $list = collect($paginator->items())->map(function ($p) use ($currentPrice, $initialMargin, $maintainMargin) {
            $diffPoints = $currentPrice !== null ? $currentPrice - $p->entry_point : null;
            $diffAmount = $diffPoints !== null ? $diffPoints * $p->contracts * 50 : null;

            // 維持率計算
            $totalInitial    = $initialMargin > 0 ? $p->contracts * $initialMargin : null;
            $equity          = ($totalInitial !== null && $diffAmount !== null) ? $totalInitial + $diffAmount : null;
            $maintainRate    = ($totalInitial > 0 && $equity !== null) ? round($equity / $totalInitial * 100, 1) : null;
            $maintainWarning = ($maintainMargin > 0 && $initialMargin > 0)
                ? round($maintainMargin / $initialMargin * 100, 1)
                : null;

            return [
                'id'              => $p->id,
                'botId'           => $p->bot_id,
                'botName'         => $p->bot?->name,
                'tgChatId'        => $p->tg_chat_id,
                'tgUserId'        => $p->tg_user_id,
                'entryPoint'      => $p->entry_point,
                'contracts'       => $p->contracts,
                'isOpen'          => $p->is_open,
                'currentPrice'    => $currentPrice,
                'diffPoints'      => $diffPoints,
                'diffAmount'      => $diffAmount,
                'totalInitial'    => $totalInitial,
                'equity'          => $equity,
                'maintainRate'    => $maintainRate,
                'maintainWarning' => $maintainWarning,
                'createdAt'       => $p->created_at?->setTimezone('Asia/Taipei')->format('Y-m-d H:i'),
            ];
        });

        return response()->json(['success' => true, 'data' => [
            'list'            => $list,
            'total'           => $paginator->total(),
            'pageSize'        => $pageSize,
            'currentPage'     => $currentPage,
            'currentPrice'    => $currentPrice,
            'initialMargin'   => $initialMargin ?: null,
            'maintainMargin'  => $maintainMargin ?: null,
            'maintainWarning' => ($maintainMargin > 0 && $initialMargin > 0)
                ? round($maintainMargin / $initialMargin * 100, 1)
                : null,
        ]]);
    }

    private function formatBot(TgBot $b): array
    {
        return [
            'id'           => $b->id,
            'name'         => $b->name,
            'token'        => $b->token,
            'type'         => $b->type,
            'isActive'     => $b->is_active,
            'remark'       => $b->remark,
            'webhookSetAt' => $b->webhook_set_at?->format('Y-m-d H:i:s'),
            'createdAt'    => $b->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
