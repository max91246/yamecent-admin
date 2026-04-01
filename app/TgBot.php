<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TgBot extends Model
{
    protected $table = 'tg_bots';

    protected $fillable = ['name', 'token', 'type', 'is_active', 'remark', 'webhook_set_at'];

    protected $casts = [
        'webhook_set_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(TgMessage::class, 'bot_id');
    }
}
