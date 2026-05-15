<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\MezastarPokemon;
use App\TgMezastarHand;
use Illuminate\Http\Request;

class MezastarController extends Controller
{
    /** 卡牌列表（支援彈數/屬性/弱點篩選） */
    public function cards(Request $request)
    {
        $series    = $request->input('series', '');
        $type      = $request->input('type', '');
        $weakness  = $request->input('weakness', '');
        $name      = $request->input('name', '');
        $pageSize  = (int) $request->input('pageSize', 50);
        $page      = (int) $request->input('page', 1);

        $q = MezastarPokemon::query();

        if ($series)   $q->where('series', $series);
        if ($type)     $q->where(fn($w) => $w->where('type1', $type)->orWhere('type2', $type));
        if ($weakness) $q->whereJsonContains('weakness', $weakness);
        if ($name)     $q->where('name', 'like', "%{$name}%");

        $total = $q->count();
        $list  = $q->orderBy('series')->orderBy('grade', 'desc')->orderBy('id')
                   ->skip(($page - 1) * $pageSize)->take($pageSize)->get();

        return response()->json(['success' => true, 'data' => [
            'list'     => $list,
            'total'    => $total,
            'page'     => $page,
            'pageSize' => $pageSize,
        ]]);
    }

    /** 所有彈數列表（用於下拉） */
    public function series()
    {
        $list = MezastarPokemon::select('series')->distinct()->orderBy('series')->pluck('series');
        return response()->json(['success' => true, 'data' => $list]);
    }

    /** 所有屬性列表（用於下拉） */
    public function types()
    {
        $t1 = MezastarPokemon::select('type1')->distinct()->pluck('type1');
        $t2 = MezastarPokemon::select('type2')->distinct()->whereNotNull('type2')->pluck('type2');
        $all = $t1->merge($t2)->unique()->sort()->values();
        return response()->json(['success' => true, 'data' => $all]);
    }

    /** TG 手牌列表（管理用） */
    public function hands(Request $request)
    {
        $chatId = $request->input('chatId');
        $botId  = $request->input('botId');

        $q = TgMezastarHand::with('pokemon');
        if ($chatId) $q->where('tg_chat_id', $chatId);
        if ($botId)  $q->where('bot_id', $botId);

        $list = $q->get();
        return response()->json(['success' => true, 'data' => $list]);
    }
}
