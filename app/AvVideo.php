<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AvVideo extends Model
{
    protected $table = 'ya_av_videos';

    protected $fillable = [
        'code', 'title', 'cover_url', 'thumb_url',
        'release_date', 'studio', 'series', 'duration_min',
        'actresses', 'tags',
        'source', 'source_url',
        'is_uncensored', 'is_leaked',
    ];

    protected $casts = [
        'release_date'  => 'date',
        'actresses'     => 'array',
        'tags'          => 'array',
        'is_uncensored' => 'boolean',
        'is_leaked'     => 'boolean',
    ];

    public function actresses(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            AvActress::class,
            'ya_av_video_actresses',
            'video_id',
            'actress_id'
        );
    }
}
