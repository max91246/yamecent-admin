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
        $isZMove      = $request->input('is_z_move', '');
        $pageSize     = (int) $request->input('pageSize', 50);
        $page         = (int) $request->input('page', 1);

        $q = MezastarPokemon::query();

        if ($series)           $q->where('series', $series);
        if ($type)             $q->where(fn($w) => $w->where('type1', $type)->orWhere('type2', $type));
        if ($weakness)         $q->whereJsonContains('weakness', $weakness);
        if ($name)             $q->where('name', 'like', "%{$name}%");
        $isUltraGigantamax = $request->input('is_ultra_gigantamax', '');
        $isDualMove        = $request->input('is_dual_move', '');

        if (is_numeric($grade))              $q->where('grade', (int) $grade);
        if (is_numeric($isMega))             $q->where('is_mega', (int) $isMega);
        if (is_numeric($isGigantamax))       $q->where('is_gigantamax', (int) $isGigantamax);
        if (is_numeric($isUltraGigantamax))  $q->where('is_ultra_gigantamax', (int) $isUltraGigantamax);
        if (is_numeric($isDualMove))         $q->where('is_dual_move', (int) $isDualMove);
        if (is_numeric($isZMove))            $q->where('is_z_move', (int) $isZMove);

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

    /** 切換超極巨化標記 */
    public function toggleUltraGigantamax(Request $request, $id)
    {
        $card = MezastarPokemon::findOrFail($id);
        $card->is_ultra_gigantamax = $request->input('is_ultra_gigantamax', 0) ? 1 : 0;
        $card->save();
        return response()->json(['success' => true, 'data' => $card->is_ultra_gigantamax]);
    }

    /** 切換雙重招式標記 */
    public function toggleDualMove(Request $request, $id)
    {
        $card = MezastarPokemon::findOrFail($id);
        $card->is_dual_move = $request->input('is_dual_move', 0) ? 1 : 0;
        $card->save();
        return response()->json(['success' => true, 'data' => $card->is_dual_move]);
    }

    /** 切換 Z招式標記 */
    public function toggleZMove(Request $request, $id)
    {
        $card = MezastarPokemon::findOrFail($id);
        $card->is_z_move = $request->input('is_z_move', 0) ? 1 : 0;
        $card->save();
        return response()->json(['success' => true, 'data' => $card->is_z_move]);
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
