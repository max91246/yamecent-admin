<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SysMenu extends Model
{
    protected $table = 'sys_menus';

    protected $fillable = [
        'parent_id', 'menu_type', 'title', 'name', 'path', 'component',
        'redirect', 'icon', 'extra_icon', 'auths', 'frame_src',
        'enter_transition', 'leave_transition', 'active_path', 'rank',
        'frame_loading', 'keep_alive', 'hidden_tag', 'fixed_tag',
        'show_link', 'show_parent',
    ];

    protected $casts = [
        'frame_loading' => 'boolean',
        'keep_alive'    => 'boolean',
        'hidden_tag'    => 'boolean',
        'fixed_tag'     => 'boolean',
        'show_link'     => 'boolean',
        'show_parent'   => 'boolean',
    ];
}
