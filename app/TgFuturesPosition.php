<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TgFuturesPosition extends Model
{
    protected $table    = 'tg_futures_positions';
    protected $fillable = ['bot_id', 'tg_chat_id', 'tg_user_id', 'entry_point', 'contracts', 'is_open'];
    protected $casts    = [
        'entry_point' => 'integer',
        'contracts'   => 'integer',
        'is_open'     => 'integer',
    ];

    public function bot()
    {
        return $this->belongsTo(TgBot::class);
    }
}
