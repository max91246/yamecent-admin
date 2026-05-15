<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MezastarPokemon extends Model
{
    protected $table = 'mezastar_pokemons';

    protected $fillable = [
        'name', 'series', 'type1', 'type2', 'move_type', 'weakness', 'grade',
    ];

    protected $casts = [
        'weakness' => 'array',
        'grade'    => 'integer',
    ];
}
