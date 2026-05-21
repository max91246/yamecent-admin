<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddTgFuturesMenu extends Migration
{
    public function up()
    {
        DB::table('sys_menus')->insertOrIgnore([
            'parent_id'        => 50,
            'menu_type'        => 0,
            'title'            => '台指持倉',
            'name'             => 'TgFutures',
            'path'             => '/tg/futures',
            'component'        => 'stock/futures',
            'redirect'         => '',
            'icon'             => 'ri:funds-line',
            'extra_icon'       => '',
            'auths'            => '',
            'frame_src'        => '',
            'enter_transition' => '',
            'leave_transition' => '',
            'active_path'      => '',
            'rank'             => 5,
            'frame_loading'    => 1,
            'keep_alive'       => 0,
            'hidden_tag'       => 0,
            'fixed_tag'        => 0,
            'show_link'        => 1,
            'show_parent'      => 0,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function down()
    {
        DB::table('sys_menus')->where('name', 'TgFutures')->delete();
    }
}
