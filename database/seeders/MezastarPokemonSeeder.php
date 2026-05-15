<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MezastarPokemonSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('mezastar_pokemons')->truncate();

        $data = [
            // ── 星塵第1彈 ─────────────────────────────────────────
            ['name' => '皮卡丘',   'series' => '星塵1彈', 'type1' => '電',   'type2' => null,   'move_type' => '電',   'weakness' => ['地面'],                   'grade' => 3],
            ['name' => '可達鴨',   'series' => '星塵1彈', 'type1' => '水',   'type2' => null,   'move_type' => '水',   'weakness' => ['電', '草'],               'grade' => 2],
            ['name' => '傑尼龜',   'series' => '星塵1彈', 'type1' => '水',   'type2' => null,   'move_type' => '水',   'weakness' => ['電', '草'],               'grade' => 2],
            ['name' => '火球鼠',   'series' => '星塵1彈', 'type1' => '火',   'type2' => null,   'move_type' => '火',   'weakness' => ['水', '地面', '岩石'],     'grade' => 2],
            ['name' => '木木梟',   'series' => '星塵1彈', 'type1' => '草',   'type2' => '飛行', 'move_type' => '草',   'weakness' => ['火', '冰', '毒', '飛行', '岩石'], 'grade' => 2],
            ['name' => '胡地',     'series' => '星塵1彈', 'type1' => '超能', 'type2' => null,   'move_type' => '超能', 'weakness' => ['幽靈', '惡'],             'grade' => 3],
            ['name' => '迪路喵',   'series' => '星塵1彈', 'type1' => '一般', 'type2' => null,   'move_type' => '一般', 'weakness' => ['格鬥'],                   'grade' => 1],
            ['name' => '妙蛙種子', 'series' => '星塵1彈', 'type1' => '草',   'type2' => '毒',   'move_type' => '草',   'weakness' => ['火', '冰', '超能'],       'grade' => 2],
            ['name' => '小鋸鱷',   'series' => '星塵1彈', 'type1' => '水',   'type2' => null,   'move_type' => '水',   'weakness' => ['電', '草'],               'grade' => 1],
            ['name' => '溫吞吐',   'series' => '星塵1彈', 'type1' => '火',   'type2' => null,   'move_type' => '火',   'weakness' => ['水', '地面', '岩石'],     'grade' => 2],
            ['name' => '伊布',     'series' => '星塵1彈', 'type1' => '一般', 'type2' => null,   'move_type' => '一般', 'weakness' => ['格鬥'],                   'grade' => 2],

            // ── 星塵第2彈 ─────────────────────────────────────────
            ['name' => '皮卡丘',   'series' => '星塵2彈', 'type1' => '電',   'type2' => null,   'move_type' => '電',   'weakness' => ['地面'],                          'grade' => 3],
            ['name' => '喇叭芽',   'series' => '星塵2彈', 'type1' => '草',   'type2' => null,   'move_type' => '草',   'weakness' => ['火', '冰', '毒', '飛行', '蟲'],  'grade' => 2],
            ['name' => '波克基古', 'series' => '星塵2彈', 'type1' => '超能', 'type2' => null,   'move_type' => '超能', 'weakness' => ['幽靈', '惡'],                    'grade' => 2],
            ['name' => '菊草葉',   'series' => '星塵2彈', 'type1' => '草',   'type2' => null,   'move_type' => '草',   'weakness' => ['火', '冰', '毒', '飛行', '蟲'],  'grade' => 1],
            ['name' => '火球鼠',   'series' => '星塵2彈', 'type1' => '火',   'type2' => null,   'move_type' => '火',   'weakness' => ['水', '地面', '岩石'],            'grade' => 2],
            ['name' => '荷葉童子', 'series' => '星塵2彈', 'type1' => '水',   'type2' => null,   'move_type' => '水',   'weakness' => ['電', '草'],                      'grade' => 1],
            ['name' => '西多藍',   'series' => '星塵2彈', 'type1' => '一般', 'type2' => null,   'move_type' => '一般', 'weakness' => ['格鬥'],                          'grade' => 2],
            ['name' => '快龍',     'series' => '星塵2彈', 'type1' => '龍',   'type2' => '飛行', 'move_type' => '龍',   'weakness' => ['冰', '龍', '妖精'],              'grade' => 3],
            ['name' => '蚊香蛙',   'series' => '星塵2彈', 'type1' => '水',   'type2' => '毒',   'move_type' => '水',   'weakness' => ['電', '地面'],                    'grade' => 2],
            ['name' => '耿鬼',     'series' => '星塵2彈', 'type1' => '幽靈', 'type2' => '毒',   'move_type' => '幽靈', 'weakness' => ['幽靈', '惡'],                    'grade' => 3],
            ['name' => '冰雪果',   'series' => '星塵2彈', 'type1' => '冰',   'type2' => null,   'move_type' => '冰',   'weakness' => ['火', '格鬥', '岩石', '鋼'],      'grade' => 2],

            // ── 星塵第3彈 ─────────────────────────────────────────
            ['name' => '皮卡丘',   'series' => '星塵3彈', 'type1' => '電',   'type2' => null,   'move_type' => '電',   'weakness' => ['地面'],                               'grade' => 3],
            ['name' => '雷公',     'series' => '星塵3彈', 'type1' => '電',   'type2' => null,   'move_type' => '電',   'weakness' => ['地面'],                               'grade' => 5],
            ['name' => '烈空座',   'series' => '星塵3彈', 'type1' => '飛行', 'type2' => '龍',   'move_type' => '龍',   'weakness' => ['冰', '龍', '妖精', '岩石'],          'grade' => 5],
            ['name' => '帕路奇犽', 'series' => '星塵3彈', 'type1' => '電',   'type2' => '草',   'move_type' => '電',   'weakness' => ['冰', '毒', '地面'],                  'grade' => 3],
            ['name' => '帕奇利茲', 'series' => '星塵3彈', 'type1' => '電',   'type2' => '草',   'move_type' => '電',   'weakness' => ['冰', '毒', '地面'],                  'grade' => 2],
            ['name' => '固拉多',   'series' => '星塵3彈', 'type1' => '地面', 'type2' => null,   'move_type' => '地面', 'weakness' => ['水', '草', '冰'],                    'grade' => 5],
            ['name' => '卡比獸',   'series' => '星塵3彈', 'type1' => '一般', 'type2' => null,   'move_type' => '一般', 'weakness' => ['格鬥'],                              'grade' => 3],
            ['name' => '噴火龍',   'series' => '星塵3彈', 'type1' => '火',   'type2' => '飛行', 'move_type' => '火',   'weakness' => ['水', '電', '岩石'],                  'grade' => 4],
            ['name' => '呆呆獸',   'series' => '星塵3彈', 'type1' => '水',   'type2' => '超能', 'move_type' => '水',   'weakness' => ['電', '草', '幽靈', '惡', '蟲'],     'grade' => 2],
            ['name' => '九尾',     'series' => '星塵3彈', 'type1' => '火',   'type2' => null,   'move_type' => '火',   'weakness' => ['水', '地面', '岩石'],                'grade' => 3],
            ['name' => '沙奈朵',   'series' => '星塵3彈', 'type1' => '超能', 'type2' => '妖精', 'move_type' => '超能', 'weakness' => ['幽靈', '鋼', '毒'],                 'grade' => 4],

            // ── 星塵第4彈 ─────────────────────────────────────────
            ['name' => '皮卡丘',   'series' => '星塵4彈', 'type1' => '電',   'type2' => null,   'move_type' => '電',   'weakness' => ['地面'],                          'grade' => 3],
            ['name' => '蓋歐卡',   'series' => '星塵4彈', 'type1' => '水',   'type2' => null,   'move_type' => '水',   'weakness' => ['電', '草'],                      'grade' => 5],
            ['name' => '超夢',     'series' => '星塵4彈', 'type1' => '超能', 'type2' => null,   'move_type' => '超能', 'weakness' => ['幽靈', '惡', '蟲'],             'grade' => 5],
            ['name' => '洛奇亞',   'series' => '星塵4彈', 'type1' => '超能', 'type2' => '飛行', 'move_type' => '超能', 'weakness' => ['電', '冰', '岩石', '幽靈', '惡'], 'grade' => 5],
            ['name' => '勒克牛',   'series' => '星塵4彈', 'type1' => '草',   'type2' => '鋼',   'move_type' => '草',   'weakness' => ['火', '格鬥', '地面'],            'grade' => 3],
            ['name' => '夢幻',     'series' => '星塵4彈', 'type1' => '超能', 'type2' => null,   'move_type' => '超能', 'weakness' => ['幽靈', '惡', '蟲'],             'grade' => 5],
            ['name' => '精靈球',   'series' => '星塵4彈', 'type1' => '一般', 'type2' => null,   'move_type' => '一般', 'weakness' => ['格鬥'],                          'grade' => 1],
            ['name' => '雷電獸',   'series' => '星塵4彈', 'type1' => '電',   'type2' => null,   'move_type' => '電',   'weakness' => ['地面'],                          'grade' => 3],
            ['name' => '巴斯蝶',   'series' => '星塵4彈', 'type1' => '蟲',   'type2' => '飛行', 'move_type' => '蟲',   'weakness' => ['電', '冰', '岩石', '火'],       'grade' => 2],
            ['name' => '磁怪',     'series' => '星塵4彈', 'type1' => '電',   'type2' => '鋼',   'move_type' => '電',   'weakness' => ['火', '格鬥', '地面'],            'grade' => 3],
            ['name' => '千面避役', 'series' => '星塵4彈', 'type1' => '一般', 'type2' => '飛行', 'move_type' => '一般', 'weakness' => ['電', '冰', '岩石'],             'grade' => 2],

            // ── 銀河第1彈 ─────────────────────────────────────────
            ['name' => '皮卡丘',   'series' => '銀河1彈', 'type1' => '電',   'type2' => null,   'move_type' => '電',   'weakness' => ['地面'],                               'grade' => 3],
            ['name' => '超夢',     'series' => '銀河1彈', 'type1' => '超能', 'type2' => null,   'move_type' => '超能', 'weakness' => ['幽靈', '惡', '蟲'],                  'grade' => 5],
            ['name' => '雷公',     'series' => '銀河1彈', 'type1' => '電',   'type2' => null,   'move_type' => '電',   'weakness' => ['地面'],                               'grade' => 5],
            ['name' => '帕路奇犽', 'series' => '銀河1彈', 'type1' => '電',   'type2' => '草',   'move_type' => '電',   'weakness' => ['冰', '毒', '地面'],                  'grade' => 3],
            ['name' => '幻夢蛙',   'series' => '銀河1彈', 'type1' => '水',   'type2' => '超能', 'move_type' => '水',   'weakness' => ['電', '草', '幽靈', '蟲'],            'grade' => 4],
            ['name' => '托亞克基拉', 'series' => '銀河1彈', 'type1' => '岩石', 'type2' => '超能', 'move_type' => '岩石', 'weakness' => ['水', '草', '格鬥', '地面', '幽靈', '惡', '鋼'], 'grade' => 4],
            ['name' => '鐵柱',     'series' => '銀河1彈', 'type1' => '電',   'type2' => '鋼',   'move_type' => '電',   'weakness' => ['火', '格鬥', '地面'],                'grade' => 5],
            ['name' => '燒焰鼬',   'series' => '銀河1彈', 'type1' => '火',   'type2' => '毒',   'move_type' => '火',   'weakness' => ['水', '地面', '岩石', '超能'],        'grade' => 4],
            ['name' => '大刃鬼',   'series' => '銀河1彈', 'type1' => '格鬥', 'type2' => '幽靈', 'move_type' => '格鬥', 'weakness' => ['飛行', '超能', '幽靈', '妖精'],     'grade' => 5],
            ['name' => '夢妖蛾',   'series' => '銀河1彈', 'type1' => '超能', 'type2' => '飛行', 'move_type' => '超能', 'weakness' => ['電', '冰', '岩石', '幽靈', '惡'],   'grade' => 3],
            ['name' => '龍電狐',   'series' => '銀河1彈', 'type1' => '龍',   'type2' => '電',   'move_type' => '龍',   'weakness' => ['冰', '龍', '妖精', '地面'],          'grade' => 5],
        ];

        $now = now();
        foreach ($data as &$row) {
            $row['weakness']   = json_encode($row['weakness'], JSON_UNESCAPED_UNICODE);
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }
        unset($row);

        DB::table('mezastar_pokemons')->insert($data);
    }
}
