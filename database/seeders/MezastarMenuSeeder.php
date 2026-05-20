<?php

namespace Database\Seeders;

use App\SysMenu;
use Illuminate\Database\Seeder;

class MezastarMenuSeeder extends Seeder
{
    public function run()
    {
        // 把「Mezastar 卡牌」改名為「卡牌」
        SysMenu::where('id', 73)->update(['title' => '卡牌']);

        // 新增「用戶手牌」子選單（防重複）
        if (!SysMenu::where('path', '/pokemon/mezastar/hands')->exists()) {
            SysMenu::create([
                'parent_id'   => 72,
                'menu_type'   => 1,
                'title'       => '用戶手牌',
                'name'        => 'MezastarHands',
                'path'        => '/pokemon/mezastar/hands',
                'component'   => 'mezastar/hands',
                'redirect'    => '',
                'icon'        => '',
                'extra_icon'  => '',
                'auths'       => '',
                'frame_src'   => '',
                'rank'        => 2,
                'show_link'   => true,
                'show_parent' => false,
                'keep_alive'  => false,
                'hidden_tag'  => false,
                'fixed_tag'   => false,
                'frame_loading' => false,
            ]);
        }

        echo "MezastarMenuSeeder 完成" . PHP_EOL;
    }
}
