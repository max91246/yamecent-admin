<?php

namespace App\Http\Controllers\Admin;

use App\TgBot;
use App\TgMessage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TgMessageController extends Controller
{
    public function tgMessageList(Request $request)
    {
        $query = TgMessage::with('bot')->orderBy('id', 'desc');

        if ($botId = $request->input('bot_id')) {
            $query->where('bot_id', $botId);
        }

        if ($username = $request->input('tg_username')) {
            $query->where('tg_username', 'like', "%{$username}%");
        }

        if ($request->input('direction') !== null && $request->input('direction') !== '') {
            $query->where('direction', $request->input('direction'));
        }

        return view('admin.tg_message_list', [
            'list' => $query->paginate(20)->appends($request->query()),
            'bots' => TgBot::orderBy('id', 'asc')->get(['id', 'name']),
        ]);
    }
}
