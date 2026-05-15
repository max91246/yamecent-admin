<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TgMezastarHand extends Model
{
    protected $table = 'tg_mezastar_hands';

    protected $fillable = ['bot_id', 'tg_chat_id', 'pokemon_id'];

    public function pokemon()
    {
        return $this->belongsTo(MezastarPokemon::class, 'pokemon_id');
    }
}
