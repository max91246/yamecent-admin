<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mezastar_pokemons', function (Blueprint $table) {
            $table->boolean('is_gigantamax')->default(false)->after('grade')->comment('是否為極巨化寶可夢（圖像偵測）');
        });
    }

    public function down(): void
    {
        Schema::table('mezastar_pokemons', function (Blueprint $table) {
            $table->dropColumn('is_gigantamax');
        });
    }
};
