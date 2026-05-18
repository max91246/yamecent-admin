<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * 全量替換 Mezastar 寶可夢資料 + 歸零用戶手卡
 * 資料來源：Pokemon_Mezastar_Fixed_Final_Checklist.xlsx
 * 欄位：超級進化 / 極巨化 / 超極巨化 / 雙重招式 四個獨立標記
 */
class MezastarReplaceSeeder extends Seeder
{
    const SERIES_MAP = [
        '星塵第1彈' => '星塵1彈', '星塵第2彈' => '星塵2彈',
        '星塵第3彈' => '星塵3彈', '星塵第4彈' => '星塵4彈',
        '銀河第1彈' => '銀河1彈', '活動卡匣'  => '活動卡匣',
    ];
    const GRADE_MAP = ['★6'=>6,'★5'=>5,'★4'=>4,'★3'=>3,'★2'=>2,'★1'=>1,'P'=>0];

    public function run(): void
    {
        DB::table('tg_mezastar_hands')->delete();
        DB::table('mezastar_pokemons')->delete();
        $this->command->info('✅ 舊資料清除完成');

        // [系列, 星等, 卡號, 名稱, 超級進化, 極巨化, 超極巨化, 雙重招式, 屬性, 弱點]
        // T=true, F=false
        $excel = [
            // ── 銀河第1彈 ─────────────────────────────────────────
            ['銀河第1彈','★6','2-1-001','轟擂金剛猩', false,false,true,false, '草',         '火、冰、毒、飛行、蟲'],
            ['銀河第1彈','★6','2-1-002','閃焰王牌',   false,false,true,false, '火',         '水、地面、岩石'],
            ['銀河第1彈','★6','2-1-003','千面避役',   false,false,true,false, '水',         '電、草'],
            ['銀河第1彈','★6','2-1-004','皮卡丘',     false,false,true,false, '電',         '地面'],
            ['銀河第1彈','★6','2-1-005','雷公',       false,false,false,true, '電',         '地面'],
            ['銀河第1彈','★6','2-1-006','炎帝',       false,false,false,true, '火',         '水、地面、岩石'],
            ['銀河第1彈','★6','2-1-007','水君',       false,false,false,true, '水',         '電、草'],
            ['銀河第1彈','★6','2-1-008','超夢',       false,true,false,false, '超能力',     '蟲、幽靈、惡'],
            ['銀河第1彈','★6','2-1-009','阿爾宙斯',   false,false,false,false, '一般',       '格鬥'],
            ['銀河第1彈','★6','2-1-010','洗翠索羅亞克',false,false,false,false,'一般/幽靈',  '惡'],
            ['銀河第1彈','★5','2-1-011','骨紋巨聲鱷', false,false,false,false, '火/幽靈',    '水、地面、岩石、幽靈、惡'],
            ['銀河第1彈','★5','2-1-012','魔幻假面喵', false,false,false,false, '草/惡',      '蟲、火、冰、毒、飛行、格鬥、妖精'],
            ['銀河第1彈','★5','2-1-013','狂歡浪舞鴨', false,false,false,false, '水/格鬥',    '電、草、飛行、超能力、妖精'],
            ['銀河第1彈','★5','2-1-014','太樂巴戈斯', false,false,false,false, '一般',       '格鬥'],
            ['銀河第1彈','★4','2-1-015','新葉喵',     false,false,false,false, '草',         '火、冰、毒、飛行、蟲'],
            ['銀河第1彈','★5','2-1-016','巴布土撥',   false,false,false,false, '電/格鬥',    '地面、超能力、妖精'],
            ['銀河第1彈','★5','2-1-017','紅蓮鎧騎',   false,false,false,false, '火/超能力',  '水、地面、岩石、幽靈、惡'],
            ['銀河第1彈','★4','2-1-018','呆火鱷',     false,false,false,false, '火',         '水、地面、岩石'],
            ['銀河第1彈','★5','2-1-019','蒼炎刃鬼',   false,false,false,false, '火/幽靈',    '水、地面、岩石、幽靈、惡'],
            ['銀河第1彈','★5','2-1-020','一家鼠',     false,false,false,false, '一般',       '格鬥'],
            ['銀河第1彈','★4','2-1-021','潤水鴨',     false,false,false,false, '水',         '電、草'],
            ['銀河第1彈','★5','2-1-022','烏波 (洗翠)',false,false,false,false, '毒/地面',    '地面、水、超能力、冰'],
            ['銀河第1彈','★5','2-1-023','土王',       false,false,false,false, '毒/地面',    '地面、水、超能力、冰'],
            ['銀河第1彈','★5','2-1-024','賽富豪',     false,false,false,false, '鋼/幽靈',    '地面、幽靈、火、惡'],
            ['銀河第1彈','★4','2-1-025','路卡利歐',   false,false,false,false, '格鬥/鋼',    '火、地面、格鬥'],
            ['銀河第1彈','★5','2-1-026','戟脊龍',     false,false,false,false, '龍/冰',      '格鬥、岩石、龍、鋼、妖精'],
            ['銀河第1彈','★4','2-1-028','快龍',       false,false,false,false, '龍/飛行',    '冰、岩石、龍、妖精'],
            ['銀河第1彈','★4','2-1-030','巨金怪',     false,false,false,false, '鋼/超能力',  '火、地面、幽靈、惡'],
            ['銀河第1彈','★3','2-1-035','蒂蕾喵',     false,false,false,false, '草',         '火、冰、毒、飛行、蟲'],
            ['銀河第1彈','★3','2-1-038','炙燙鱷',     false,false,false,false, '火',         '水、地面、岩石'],
            ['銀河第1彈','★3','2-1-041','湧躍鴨',     false,false,false,false, '水',         '電、草'],
            ['銀河第1彈','★3','2-1-045','利歐路',     false,false,false,false, '格鬥',       '飛行、超能力、妖精'],
            ['銀河第1彈','★2','2-1-055','布撥',       false,false,false,false, '電',         '地面'],
            ['銀河第1彈','★2','2-1-058','炭小侍',     false,false,false,false, '火',         '水、地面、岩石'],
            ['銀河第1彈','★2','2-1-062','涼脊龍',     false,false,false,false, '龍/冰',      '格鬥、岩石、龍、鋼、妖精'],
            ['銀河第1彈','★2','2-1-065','米立龍',     false,false,false,false, '水/龍',      '龍、妖精'],

            // ── 星塵第4彈 ─────────────────────────────────────────
            ['星塵第4彈','★6','1-4-001','帝牙盧卡',   false,false,false,false, '鋼/龍',      '格鬥、地面'],
            ['星塵第4彈','★6','1-4-002','帕路奇亞',   false,false,false,false, '水/龍',      '龍、妖精'],
            ['星塵第4彈','★6','1-4-003','哲爾尼亞斯', false,true,false,false, '妖精',       '毒、鋼'],
            ['星塵第4彈','★6','1-4-004','伊裴爾塔爾', false,true,false,false, '惡/飛行',    '電、冰、岩石、妖精'],
            ['星塵第4彈','★6','1-4-005','蕾冠王 (白馬)',false,false,false,false,'超能力/冰',  '火、蟲、岩石、鋼、惡、幽靈'],
            ['星塵第4彈','★6','1-4-006','蕾冠王 (黑馬)',false,false,false,false,'超能力/幽靈','幽靈、惡'],
            ['星塵第4彈','★6','1-4-007','謎擬Q',      false,false,false,false, '幽靈/妖精',  '幽靈、鋼'],
            ['星塵第4彈','★6','1-4-008','焰白酋雷姆', false,false,false,false, '龍/冰',      '格鬥、岩石、龍、鋼'],
            ['星塵第4彈','★6','1-4-009','闇黑酋雷姆', false,false,false,false, '龍/冰',      '格鬥、岩石、龍、鋼'],
            ['星塵第4彈','★6','1-4-010','烈空坐',     true,false,false,false, '龍/飛行',    '冰、岩石、龍、妖精'],
            ['星塵第4彈','★5','1-4-011','波士可多拉', true,false,false,false, '鋼',         '火、格鬥、地面'],
            ['星塵第4彈','★5','1-4-012','大竺葵',     false,false,false,false, '草',         '火、冰、毒、飛行、蟲'],
            ['星塵第4彈','★5','1-4-013','火爆獸',     false,false,false,false, '火',         '水、地面、岩石'],
            ['星塵第4彈','★5','1-4-014','大力鱷',     false,false,false,false, '水',         '電、草'],
            ['星塵第4彈','★5','1-4-015','黏美龍',     false,true,false,false, '龍',         '冰、龍、妖精'],
            ['星塵第4彈','★5','1-4-016','勾魂眼',     true,false,false,false, '惡/幽靈',    '妖精'],
            ['星塵第4彈','★5','1-4-017','大嘴娃',     true,false,false,false, '鋼/妖精',    '火、地面'],
            ['星塵第4彈','★5','1-4-018','雷電雲',     false,false,false,false, '電/飛行',    '岩石、冰'],
            ['星塵第4彈','★5','1-4-019','土地雲',     false,false,false,false, '地面/飛行',  '冰、水'],
            ['星塵第4彈','★5','1-4-021','龍捲雲',     false,false,false,false, '飛行',       '電、冰、岩石'],
            ['星塵第4彈','★5','1-4-022','眷戀雲',     false,false,false,false, '妖精/飛行',  '電、冰、岩石、毒、鋼'],
            ['星塵第4彈','★5','1-4-024','美納斯',     false,false,false,false, '水',         '電、草'],
            ['星塵第4彈','★5','1-4-025','波克基斯',   false,false,false,false, '妖精/飛行',  '電、冰、岩石、毒、鋼'],
            ['星塵第4彈','★5','1-4-027','索羅亞克',   false,false,false,false, '一般/幽靈',  '惡'],
            ['星塵第4彈','★5','1-4-029','水晶燈火靈', false,false,false,false, '幽靈/火',    '水、地面、岩石、幽靈、惡'],
            ['星塵第4彈','★4','1-4-020','木守宮',     false,false,false,false, '草',         '火、冰、毒、飛行、蟲'],
            ['星塵第4彈','★4','1-4-023','火稚雞',     false,false,false,false, '火',         '水、地面、岩石'],
            ['星塵第4彈','★4','1-4-026','水躍魚',     false,false,false,false, '水',         '電、草'],
            ['星塵第4彈','★4','1-4-028','堅盾劍怪',   false,false,false,false, '鋼/幽靈',    '地面、幽靈、火、惡'],
            ['星塵第4彈','★4','1-4-030','音波龍',     false,false,false,false, '飛行/龍',    '冰、岩石、龍、妖精'],
            ['星塵第4彈','★3','1-4-035','森林蜥蜴',   false,false,false,false, '草',         '火、冰、毒、飛行、蟲'],
            ['星塵第4彈','★3','1-4-038','力壯雞',     false,false,false,false, '火/格鬥',    '水、地面、飛行、超能力'],
            ['星塵第4彈','★3','1-4-041','沼躍魚',     false,false,false,false, '水/地面',    '草'],
            ['星塵第4彈','★2','1-4-052','獨角犀牛',   false,false,false,false, '地面/岩石',  '水、草、冰、格鬥'],
            ['星塵第4彈','★2','1-4-055','可可多拉',   false,false,false,false, '鋼/岩石',    '格鬥、地面、水'],
            ['星塵第4彈','★2','1-4-058','黏黏寶',     false,false,false,false, '龍',         '冰、龍、妖精'],

            // ── 星塵第3彈 ─────────────────────────────────────────
            ['星塵第3彈','★6','1-3-001','洛奇亞',     false,false,false,false, '超能力/飛行','電、冰、岩石、幽靈、惡'],
            ['星塵第3彈','★6','1-3-002','鳳王',       false,false,false,false, '火/飛行',    '岩石、水、電'],
            ['星塵第3彈','★6','1-3-003','索爾迦雷歐', false,false,false,false, '超能力/鋼',  '火、地面、幽靈、惡'],
            ['星塵第3彈','★6','1-3-004','露奈雅拉',   false,false,false,false, '超能力/幽靈','幽靈、惡'],
            ['星塵第3彈','★6','1-3-005','甲賀忍蛙',   false,false,false,false, '水/惡',      '電、草、格鬥、蟲、妖精'],
            ['星塵第3彈','★6','1-3-006','捷拉奧拉',   false,false,false,false, '電',         '地面'],
            ['星塵第3彈','★6','1-3-007','無極汰那',   false,false,false,false, '毒/龍',      '地面、超能力、冰、龍'],
            ['星塵第3彈','★6','1-3-008','凱路迪歐',   false,true,false,false, '水/格鬥',    '電、草、飛行、妖精'],
            ['星塵第3彈','★6','1-3-009','長毛巨魔',   false,false,true,false, '惡/妖精',    '毒、鋼、妖精'],
            ['星塵第3彈','★6','1-3-010','基格爾德',   false,false,false,false, '龍/地面',    '冰、龍、妖精'],
            ['星塵第3彈','★5','1-3-011','蜥蜴王',     true,false,false,false, '草',         '火、冰、毒、飛行、蟲'],
            ['星塵第3彈','★5','1-3-012','火焰雞',     true,false,false,false, '火/格鬥',    '水、地面、飛行、超能力'],
            ['星塵第3彈','★5','1-3-013','巨沼怪',     true,false,false,false, '水/地面',    '草'],
            ['星塵第3彈','★5','1-3-014','水箭龜',     false,false,true,false, '水',         '電、草'],
            ['星塵第3彈','★5','1-3-015','妙蛙花',     false,false,true,false, '草/毒',      '火、冰、飛行、超能力'],
            ['星塵第3彈','★5','1-3-016','噴火龍',     false,false,true,false, '火/飛行',    '岩石、水、電'],
            ['星塵第3彈','★5','1-3-017','薩戮德',     false,false,false,false, '草/惡',      '蟲、火、冰、毒、飛行、格鬥'],
            ['星塵第3彈','★5','1-3-019','雷吉洛克',   false,false,false,false, '岩石',       '水、草、格鬥、地面'],
            ['星塵第3彈','★5','1-3-020','雷吉艾斯',   false,false,false,false, '冰',         '火、格鬥、岩石、鋼'],
            ['星塵第3彈','★5','1-3-021-A','雷吉斯奇魯',false,false,false,false,'鋼',         '火、格鬥、地面'],
            ['星塵第3彈','★5','1-3-022','雷吉艾勒奇', false,false,false,false, '電',         '地面'],
            ['星塵第3彈','★5','1-3-023','雷吉鐸拉戈', false,false,false,false, '龍',         '冰、龍、妖精'],
            ['星塵第3彈','★5','1-3-024-A','洗翠狙射樹梟',false,false,false,false,'草/格鬥',  '飛行、火、冰、毒'],
            ['星塵第3彈','★5','1-3-025','列陣兵',     false,false,false,false, '格鬥',       '飛行、超能力、妖精'],
            ['星塵第3彈','★5','1-3-028','堅果啞鈴',   false,false,false,false, '草/鋼',      '火、格鬥'],
            ['星塵第3彈','★4','1-3-026','紅蓮鎧騎',   false,false,false,false, '火/超能力',  '水、地面、岩石、幽靈、惡'],
            ['星塵第3彈','★4','1-3-027','蒼炎刃鬼',   false,false,false,false, '火/幽靈',    '水、地面、岩石、幽靈、惡'],
            ['星塵第3彈','★4','1-3-018','敲音猴',     false,false,false,false, '草',         '火、冰、毒、飛行、蟲'],
            ['星塵第3彈','★4','1-3-021-B','炎兔兒',   false,false,false,false, '火',         '水、地面、岩石'],
            ['星塵第3彈','★4','1-3-024-B','淚眼蜥',   false,false,false,false, '水',         '電、草'],
            ['星塵第3彈','★3','1-3-032','啪咚猴',     false,false,false,false, '草',         '火、冰、毒、飛行、蟲'],
            ['星塵第3彈','★3','1-3-035','騰蹴小將',   false,false,false,false, '火',         '水、地面、岩石'],
            ['星塵第3彈','★3','1-3-038','變澀蜥',     false,false,false,false, '水',         '電、草'],
            ['星塵第3彈','★2','1-3-050','幼基拉斯',   false,false,false,false, '岩石/地面',  '水、草、冰、格鬥'],
            ['星塵第3彈','★2','1-3-053','鐵甲蛹',     false,false,false,false, '蟲',         '火、岩石、飛行'],

            // ── 星塵第2彈 ─────────────────────────────────────────
            ['星塵第2彈','★6','1-2-001','蓋歐卡',     false,false,false,false, '水',         '電、草'],
            ['星塵第2彈','★6','1-2-002','固拉多',     false,false,false,false, '地面',       '水、草、冰'],
            ['星塵第2彈','★6','1-2-003','故勒頓',     false,false,false,false, '格鬥/龍',    '妖精、飛行、超能力'],
            ['星塵第2彈','★6','1-2-004','密勒頓',     false,false,false,false, '電/龍',      '地面、冰、龍、妖精'],
            ['星塵第2彈','★6','1-2-006','卡比獸',     false,false,true,false, '一般',       '格鬥'],
            ['星塵第2彈','★6','1-2-007','沙奈朵',     true,false,false,false, '超能力/妖精','毒、幽靈、鋼'],
            ['星塵第2彈','★6','1-2-008','萊希拉姆',   false,false,false,false, '龍/火',      '地面、岩石、龍'],
            ['星塵第2彈','★6','1-2-009','捷克羅姆',   false,false,false,false, '龍/電',      '地面、冰、龍、妖精'],
            ['星塵第2彈','★6','1-2-010','酋雷姆',     false,false,false,false, '龍/冰',      '格鬥、岩石、龍、鋼'],
            ['星塵第2彈','★5','1-2-011','路卡利歐',   true,false,false,false, '格鬥/鋼',    '火、格鬥、地面'],
            ['星塵第2彈','★5','1-2-012','阿勃梭魯',   true,false,false,false, '惡',         '格鬥、蟲、妖精'],
            ['星塵第2彈','★5','1-2-013','急凍鳥',     false,false,false,false, '冰/飛行',    '岩石、火、電'],
            ['星塵第2彈','★5','1-2-014','閃電鳥',     false,false,false,false, '電/飛行',    '岩石、冰'],
            ['星塵第2彈','★5','1-2-015','火焰鳥',     false,false,false,false, '火/飛行',    '岩石、水、電'],
            ['星塵第2彈','★5','1-2-017','布里卡隆',   false,false,false,false, '草/格鬥',    '飛行、火、冰'],
            ['星塵第2彈','★5','1-2-018','妖火紅狐',   false,false,false,false, '火/超能力',  '水、地面、岩石、幽靈、惡'],
            ['星塵第2彈','★5','1-2-020','洗翠火暴獸', false,false,false,false, '火/幽靈',    '水、地面、岩石、幽靈、惡'],
            ['星塵第2彈','★5','1-2-021','洗翠大劍鬼', false,false,false,false, '水/惡',      '電、草、格鬥、蟲、妖精'],
            ['星塵第2彈','★5','1-2-023','巴流武道熊師',false,false,false,false,'格鬥/水',    '飛行、超能力、電、草'],
            ['星塵第2彈','★5','1-2-024','一擊武道熊師',false,false,false,false,'格鬥/惡',    '妖精、格鬥、飛行'],
            ['星塵第2彈','★5','1-2-026','風速狗',     false,false,false,false, '火',         '水、地面、岩石'],
            ['星塵第2彈','★5','1-2-027','怪力',       false,false,false,false, '格鬥',       '飛行、超能力、妖精'],
            ['星塵第2彈','★5','1-2-029','多龍巴魯托', false,false,false,false, '幽靈/龍',    '幽靈、冰、龍、惡'],
            ['星塵第2彈','★5','1-2-030','顫弦蠑螈',   false,false,false,false, '電/毒',      '地面、超能力'],
            ['星塵第2彈','★4','1-2-016','妙蛙種子',   false,false,false,false, '草/毒',      '火、冰、飛行、超能力'],
            ['星塵第2彈','★4','1-2-019','小火龍',     false,false,false,false, '火',         '水、地面、岩石'],
            ['星塵第2彈','★4','1-2-022','傑尼龜',     false,false,false,false, '水',         '電、草'],
            ['星塵第2彈','★3','1-2-031','妙蛙草',     false,false,false,false, '草/毒',      '火、冰、飛行、超能力'],
            ['星塵第2彈','★3','1-2-034','火恐龍',     false,false,false,false, '火',         '水、地面、岩石'],
            ['星塵第2彈','★3','1-2-037','卡咪龜',     false,false,false,false, '水',         '電、草'],
            ['星塵第2彈','★2','1-2-048','利歐路',     false,false,false,false, '格鬥',       '飛行、超能力、妖精'],
            ['星塵第2彈','★2','1-2-054','伊布',       false,false,false,false, '一般',       '格鬥'],

            // ── 星塵第1彈 ─────────────────────────────────────────
            ['星塵第1彈','★6','1-1-001','超夢',       false,false,false,false, '超能力',     '蟲、幽靈、惡'],
            ['星塵第1彈','★6','1-1-002','夢幻',       false,false,false,false, '超能力',     '蟲、幽靈、惡'],
            ['星塵第1彈','★6','1-1-003','蒼響',       false,false,false,false, '鋼/妖精',    '火、地面'],
            ['星塵第1彈','★6','1-1-004','藏瑪然特',   false,false,false,false, '鋼/格鬥',    '火、地面、格鬥'],
            ['星塵第1彈','★6','1-1-005','班基拉斯',   true,false,false,false, '岩石/惡',    '格鬥、地面、鋼'],
            ['星塵第1彈','★6','1-1-006','巨金怪',     true,false,false,false, '鋼/超能力',  '火、地面、幽靈、惡'],
            ['星塵第1彈','★6','1-1-008','妙蛙花',     true,false,false,false, '草/毒',      '火、冰、飛行、超能力'],
            ['星塵第1彈','★6','1-1-009','噴火龍',     true,false,false,false, '火/龍',      '地面、岩石、龍'],
            ['星塵第1彈','★6','1-1-010','水箭龜',     true,false,false,false, '水',         '電、草'],
            ['星塵第1彈','★5','1-1-011','耿鬼',       true,false,false,false, '幽靈/毒',    '幽靈、惡、地面、超能力'],
            ['星塵第1彈','★5','1-1-012','轟擂金剛猩', false,true,false,false, '草',         '火、冰、毒、飛行、蟲'],
            ['星塵第1彈','★5','1-1-013','閃焰王牌',   false,true,false,false, '火',         '水、地面、岩石'],
            ['星塵第1彈','★5','1-1-014','千面避役',   false,true,false,false, '水',         '電、草'],
            ['星塵第1彈','★5','1-1-015','阿羅拉九尾', false,false,false,false, '冰/妖精',    '鋼、毒、火'],
            ['星塵第1彈','★5','1-1-016','死神鋼',     false,false,false,false, '地面/鋼',    '火、水、格鬥、地面'],
            ['星塵第1彈','★5','1-1-017','謎擬Q',      false,false,false,false, '幽靈/妖精',  '幽靈、鋼'],
            ['星塵第1彈','★5','1-1-019','鋼鎧鴉',     false,false,false,false, '飛行/鋼',    '火、電'],
            ['星塵第1彈','★5','1-1-020','白蓬蓬',     false,false,false,false, '草',         '火、冰、毒、飛行'],
            ['星塵第1彈','★5','1-1-022','暴噬龜',     false,false,false,false, '水/岩石',    '草、電、格鬥'],
            ['星塵第1彈','★5','1-1-023','巨炭山',     false,false,false,false, '岩石/火',    '水、地面'],
            ['星塵第1彈','★5','1-1-025','霜奶仙',     false,false,false,false, '妖精',       '毒、鋼'],
            ['星塵第1彈','★5','1-1-027','莫魯貝可',   false,false,false,false, '電/惡',      '格鬥、地面、蟲、妖精'],
            ['星塵第1彈','★5','1-1-029','大王銅象',   false,false,false,false, '鋼',         '火、格鬥、地面'],
            ['星塵第1彈','★5','1-1-030','鋁鋼龍',     false,false,false,false, '鋼/龍',      '格鬥、地面'],
            ['星塵第1彈','★4','1-1-018','皮卡丘',     false,false,false,false, '電',         '地面'],
            ['星塵第1彈','★4','1-1-021','鐵啞鈴',     false,false,false,false, '鋼/超能力',  '火、地面、幽靈'],
            ['星塵第1彈','★4','1-1-024','波波',       false,false,false,false, '一般/飛行',  '電、冰、岩石'],
            ['星塵第1彈','★3','1-1-032','金屬怪',     false,false,false,false, '鋼/超能力',  '火、地面、幽靈'],
            ['星塵第1彈','★3','1-1-035','比比鳥',     false,false,false,false, '一般/飛行',  '電、冰、岩石'],
            ['星塵第1彈','★2','1-1-048','鬼斯',       false,false,false,false, '幽靈/毒',    '幽靈、惡、地面'],
            ['星塵第1彈','★2','1-1-051','小拉達',     false,false,false,false, '一般',       '格鬥'],

            // ── 活動卡匣 ──────────────────────────────────────────
            ['活動卡匣','P','P-台北紀念',    '快龍',    false,false,false,false, '龍/飛行',   '冰、岩石、龍、妖精'],
            ['活動卡匣','P','P-活動-暴鯉龍', '暴鯉龍',  false,false,false,false, '水/飛行',   '電、岩石'],
            ['活動卡匣','P','P-活動-武道熊師','武道熊師',false,false,false,false, '格鬥/惡',   '妖精、飛行、超能力'],
            ['活動卡匣','P','P-活動-基格爾德','基格爾德',false,false,false,false, '龍/地面',   '冰、龍、妖精'],
            ['活動卡匣','P','P-活動-時拉比', '時拉比',  false,false,false,false, '草/超能力', '蟲、火、冰、毒、飛行'],
            ['活動卡匣','P','P-特別-夢幻',   '夢幻 (銀色限定)',false,false,false,false,'超能力','蟲、幽靈、惡'],
            ['活動卡匣','P','P-特別-達克萊伊','達克萊伊',false,false,false,false, '惡',        '格鬥、蟲、妖精'],
        ];

        $now  = now();
        $rows = [];

        foreach ($excel as [$rawSeries, $rawGrade, $cardNo, $name, $isMega, $isGmax, $isUltra, $isDual, $typeStr, $weakStr]) {
            $series = self::SERIES_MAP[$rawSeries] ?? $rawSeries;
            $grade  = self::GRADE_MAP[$rawGrade]   ?? 1;

            $types = explode('/', preg_replace('/\(.*?\)/', '', $typeStr));
            $type1 = trim($types[0]);
            $type2 = isset($types[1]) ? trim($types[1]) : null;
            if ($type2 === '') $type2 = null;

            $weakness = array_values(array_filter(array_map(
                fn($w) => trim(preg_replace('/\(.*?\)/', '', $w)),
                explode('、', $weakStr)
            ), fn($w) => $w !== ''));

            $rows[] = [
                'card_no'            => $cardNo,
                'series'             => $series,
                'name'               => $name,
                'type1'              => $type1,
                'type2'              => $type2,
                'move_type'          => $type1,
                'weakness'           => json_encode($weakness, JSON_UNESCAPED_UNICODE),
                'grade'              => $grade,
                'is_mega'            => $isMega ? 1 : 0,
                'is_gigantamax'      => $isGmax ? 1 : 0,
                'is_ultra_gigantamax'=> $isUltra ? 1 : 0,
                'is_dual_move'       => $isDual ? 1 : 0,
                'image_url'          => null,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        DB::table('mezastar_pokemons')->insert($rows);

        foreach (['mezastar_pokemon:all','mezastar_pokemon:all_list'] as $k) Cache::forget($k);

        $total = count($rows);
        $this->command->info("✅ 新資料寫入完成：共 {$total} 張");
        $this->command->info("  超級進化: " . collect($rows)->sum('is_mega'));
        $this->command->info("  極巨化:   " . collect($rows)->sum('is_gigantamax'));
        $this->command->info("  超極巨化: " . collect($rows)->sum('is_ultra_gigantamax'));
        $this->command->info("  雙重招式: " . collect($rows)->sum('is_dual_move'));
        $this->command->info("📌 請接著執行 php artisan scrape:mezastar 更新圖片");
    }
}
