<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mezastar_pokemons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('寶可夢名稱（中文）');
            $table->string('series', 20)->comment('所屬彈數，如 星塵1彈');
            $table->string('type1', 20)->comment('主屬性');
            $table->string('type2', 20)->nullable()->comment('副屬性');
            $table->string('move_type', 20)->comment('招式屬性（用來克制對方）');
            $table->json('weakness')->comment('弱點屬性 JSON 陣列');
            $table->tinyInteger('grade')->default(1)->comment('星級 1-5');
            $table->timestamps();

            $table->index('series');
            $table->index('type1');
            $table->index('move_type');
        });

        Schema::create('tg_mezastar_hands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->bigInteger('tg_chat_id');
            $table->unsignedBigInteger('pokemon_id');
            $table->timestamps();

            $table->index(['bot_id', 'tg_chat_id']);
            $table->foreign('pokemon_id')->references('id')->on('mezastar_pokemons')->onDelete('cascade');
        });

        // 後台菜單
        $maxRank = DB::table('sys_menus')->max('rank') ?? 99;

        // 父節點：寶可夢工具
        $parentId = DB::table('sys_menus')->insertGetId([
            'parent_id'  => 0,
            'menu_type'  => 0,
            'title'      => '寶可夢工具',
            'name'       => 'Pokemon',
            'path'       => '/pokemon',
            'component'  => '',
            'icon'       => 'ri:sword-line',
            'rank'       => $maxRank + 1,
            'show_link'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sys_menus')->insert([
            'parent_id'  => $parentId,
            'menu_type'  => 0,
            'title'      => 'Mezastar 卡牌',
            'name'       => 'MezastarCard',
            'path'       => '/pokemon/mezastar',
            'component'  => 'mezastar/index',
            'icon'       => 'ri:database-2-line',
            'rank'       => 1,
            'show_link'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tg_mezastar_hands');
        Schema::dropIfExists('mezastar_pokemons');

        DB::table('sys_menus')->where('name', 'MezastarCard')->delete();
        DB::table('sys_menus')->where('name', 'Pokemon')->delete();
    }
};
