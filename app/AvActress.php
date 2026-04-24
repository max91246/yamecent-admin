<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AvActress extends Model
{
    protected $table = 'ya_av_actresses';

    protected $fillable = [
        'name', 'missav_slug', 'image_url',
        'height', 'bust', 'waist', 'hip',
        'birthday', 'debut_year', 'notes', 'is_active',
    ];

    protected $casts = [
        'birthday'  => 'date',
        'is_active' => 'boolean',
    ];

    public function getAgeAttribute(): ?int
    {
        return $this->birthday ? $this->birthday->age : null;
    }
}
