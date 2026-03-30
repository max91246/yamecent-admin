<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Member extends Model
{
    protected $fillable = [
        'account', 'avatar', 'password', 'nickname',
        'email', 'phone', 'balance', 'is_active', 'can_comment',
        'is_member', 'member_expired_at', 'member_applied_at',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'member_expired_at'  => 'datetime',
        'member_applied_at'  => 'datetime',
    ];

    public function isMemberActive(): bool
    {
        return $this->is_member == 1
            && $this->member_expired_at !== null
            && $this->member_expired_at->gt(now());
    }

    public static function isExist(string $account): bool
    {
        $instance = new static;
        return $instance->where('account', $account)->count() > 0;
    }

    public function isExistForUpdate(string $account): bool
    {
        return $this->where('id', '!=', $this->id)
            ->where('account', $account)
            ->count() > 0;
    }

    protected function setPasswordAttribute($value)
    {
        if (is_null($value) || $value === '') {
            return;
        }
        $this->attributes['password'] = Hash::make($value);
    }
}
