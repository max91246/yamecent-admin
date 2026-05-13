<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SysRole extends Model
{
    protected $table = 'sys_roles';

    protected $fillable = ['name', 'code', 'status', 'remark'];

    public function menus()
    {
        return $this->belongsToMany(SysMenu::class, 'sys_role_menus', 'role_id', 'menu_id');
    }
}
