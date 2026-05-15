<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MezastarPokemon extends Model
{
    protected $table = 'mezastar_pokemons';

    protected $fillable = [
        'card_no', 'name', 'series', 'type1', 'type2', 'move_type', 'weakness', 'grade', 'image_url',
    ];

    protected $casts = [
        'weakness' => 'array',
        'grade'    => 'integer',
    ];
}
