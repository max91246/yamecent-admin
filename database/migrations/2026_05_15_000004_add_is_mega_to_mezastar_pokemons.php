<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mezastar_pokemons', function (Blueprint $table) {
            $table->boolean('is_mega')->default(false)->after('is_gigantamax')->comment('是否為超級進化寶可夢');
        });
    }

    public function down(): void
    {
        Schema::table('mezastar_pokemons', function (Blueprint $table) {
            $table->dropColumn('is_mega');
        });
    }
};
