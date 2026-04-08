<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TgSettlement extends Model
{
    protected $table    = 'tg_settlements';
    protected $fillable = [
        'bot_id', 'tg_chat_id', 'tg_user_id',
        'stock_code', 'stock_name', 'shares',
        'buy_price', 'settlement_amount', 'settle_date', 'is_settled',
    ];

    protected $casts = [
        'settle_date' => 'date',
    ];
}
