<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TgHolding extends Model
{
    protected $table = 'tg_holdings';

    protected $fillable = [
        'bot_id',
        'tg_chat_id',
        'tg_user_id',
        'stock_code',
        'stock_name',
        'shares',
        'is_margin',
        'total_cost',
        'buy_price',
    ];
}
