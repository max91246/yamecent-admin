<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = [
        'title', 'image', 'content', 'type', 'is_active',
        'source_post_id', 'source_member_id', 'source_published_at',
    ];

    const TYPE_LABELS = [
        1 => '普通文章',
        4 => '玩股網',
    ];

    public function getTypeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? '未知';
    }

    public function comments()
    {
        return $this->hasMany(ArticleComment::class);
    }
}
