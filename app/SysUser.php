<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class SysUser extends Model
{
    protected $table = 'sys_users';

    protected $fillable = [
        'username', 'password', 'nickname', 'avatar',
        'sex', 'phone', 'email', 'status', 'remark',
    ];

    protected $hidden = ['password'];

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Hash::make($value);
    }

    public function roles()
    {
        return $this->belongsToMany(SysRole::class, 'sys_user_roles', 'user_id', 'role_id');
    }
}
