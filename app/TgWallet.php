<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TgWallet extends Model
{
    protected $table    = 'tg_wallets';
    protected $fillable = ['bot_id', 'tg_chat_id', 'tg_user_id', 'capital'];
}
