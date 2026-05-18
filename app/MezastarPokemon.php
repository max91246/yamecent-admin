<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MezastarPokemon extends Model
{
    protected $table = 'mezastar_pokemons';

    protected $fillable = [
        'card_no', 'name', 'series', 'type1', 'type2', 'move_type', 'weakness', 'grade',
        'is_mega', 'is_gigantamax', 'is_ultra_gigantamax', 'is_dual_move', 'is_z_move', 'image_url',
        'hp', 'attack', 'defense', 'sp_attack', 'sp_defense', 'speed',
    ];

    protected $casts = [
        'weakness' => 'array',
        'grade'    => 'integer',
    ];
}
