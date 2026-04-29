<?php

namespace App\Http\Controllers\Admin;

use App\AvUserPref;
use App\TgBot;
use App\Http\Controllers\Controller;

class AvUserPrefController extends Controller
{
    public function index()
    {
        $prefs = AvUserPref::orderBy('bot_id')->orderBy('tg_chat_id')->get();
        $bots  = TgBot::where('type', 2)->pluck('name', 'id');

        return view('admin.av_user_prefs', compact('prefs', 'bots'));
    }
}
