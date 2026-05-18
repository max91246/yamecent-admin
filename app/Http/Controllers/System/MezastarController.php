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
        $series       = $request->input('series', '');
        $type         = $request->input('type', '');
        $weakness     = $request->input('weakness', '');
        $name         = $request->input('name', '');
        $grade        = $request->input('grade', '');
        $isGigantamax = $request->input('is_gigantamax', '');
        $isMega       = $request->input('is_mega', '');
        $pageSize     = (int) $request->input('pageSize', 50);
        $page         = (int) $request->input('page', 1);

        $q = MezastarPokemon::query();

        if ($series)           $q->where('series', $series);
        if ($type)             $q->where(fn($w) => $w->where('type1', $type)->orWhere('type2', $type));
        if ($weakness)         $q->whereJsonContains('weakness', $weakness);
        if ($name)             $q->where('name', 'like', "%{$name}%");
        if (is_numeric($grade))        $q->where('grade', (int) $grade);
        if (is_numeric($isGigantamax)) $q->where('is_gigantamax', (int) $isGigantamax);
        if (is_numeric($isMega))       $q->where('is_mega', (int) $isMega);

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

    /** 切換極巨化標記 */
    public function toggleGigantamax(Request $request, $id)
    {
        $card = MezastarPokemon::findOrFail($id);
        $card->is_gigantamax = $request->input('is_gigantamax', 0) ? 1 : 0;
        $card->save();
        return response()->json(['success' => true, 'data' => $card->is_gigantamax]);
    }

    /** 切換超級進化標記 */
    public function toggleMega(Request $request, $id)
    {
        $card = MezastarPokemon::findOrFail($id);
        $card->is_mega = $request->input('is_mega', 0) ? 1 : 0;
        $card->save();
        return response()->json(['success' => true, 'data' => $card->is_mega]);
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
