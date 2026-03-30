<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ArticleComment extends Model
{
    protected $fillable = ['article_id', 'member_id', 'content', 'is_visible', 'admin_reply', 'admin_replied_at'];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
