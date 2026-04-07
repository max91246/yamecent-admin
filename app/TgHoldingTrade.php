<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TgHoldingTrade extends Model
{
    protected $table = 'tg_holding_trades';

    protected $fillable = [
        'bot_id',
        'tg_chat_id',
        'tg_user_id',
        'stock_code',
        'stock_name',
        'sell_shares',
        'buy_price',
        'sell_price',
        'is_margin',
        'profit',
    ];
}
