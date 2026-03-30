<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MemberBalanceLog extends Model
{
    protected $fillable = [
        'member_id', 'amount', 'before_balance', 'after_balance', 'type', 'remark',
    ];

    const TYPE_LABELS = [
        1 => '增加',
        2 => '減少',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
