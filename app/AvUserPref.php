<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AvUserPref extends Model
{
    protected $table    = 'av_user_prefs';
    protected $fillable = ['bot_id', 'tg_chat_id', 'tg_username', 'fav_tags', 'push_enabled'];
    protected $casts    = ['fav_tags' => 'array', 'push_enabled' => 'boolean'];
}
