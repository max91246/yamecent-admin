<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mezastar_pokemons', function (Blueprint $table) {
            $table->boolean('is_ultra_gigantamax')->default(false)->after('is_gigantamax')->comment('超極巨化');
            $table->boolean('is_dual_move')->default(false)->after('is_ultra_gigantamax')->comment('雙重招式');
        });
    }

    public function down(): void
    {
        Schema::table('mezastar_pokemons', function (Blueprint $table) {
            $table->dropColumn(['is_ultra_gigantamax', 'is_dual_move']);
        });
    }
};
