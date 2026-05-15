<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mezastar_pokemons', function (Blueprint $table) {
            $table->string('card_no', 20)->nullable()->after('id')->comment('卡牌編號，如 1-1-001');
            $table->string('image_url', 500)->nullable()->after('grade')->comment('官網卡牌圖片URL');
        });

        // 讓屬性欄位允許 null（爬蟲先抓名稱+圖片，屬性待補）
        \DB::statement('ALTER TABLE `ya_mezastar_pokemons`
            MODIFY `type1`     VARCHAR(20)  NULL,
            MODIFY `move_type` VARCHAR(20)  NULL,
            MODIFY `weakness`  JSON         NULL
        ');
    }

    public function down(): void
    {
        Schema::table('mezastar_pokemons', function (Blueprint $table) {
            $table->dropColumn(['card_no', 'image_url']);
        });
    }
};
