<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TgMessage extends Model
{
    protected $table = 'tg_messages';

    protected $fillable = [
        'bot_id', 'tg_user_id', 'tg_username', 'tg_chat_id',
        'content', 'direction', 'message_type',
    ];

    public function bot()
    {
        return $this->belongsTo(TgBot::class, 'bot_id');
    }
}
