<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TgState extends Model
{
    protected $table = 'tg_states';

    protected $fillable = [
        'bot_id',
        'tg_chat_id',
        'state',
        'state_data',
        'expires_at',
    ];

    protected $casts = [
        'state_data' => 'array',
        'expires_at' => 'datetime',
    ];
}
