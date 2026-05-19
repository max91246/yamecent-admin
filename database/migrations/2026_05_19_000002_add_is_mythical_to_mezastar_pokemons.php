<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsMythicalToMezastarPokemons extends Migration
{
    public function up()
    {
        Schema::table('mezastar_pokemons', function (Blueprint $table) {
            $table->boolean('is_mythical')->default(0)->after('is_z_move')->comment('幻之寶可夢');
        });
    }

    public function down()
    {
        Schema::table('mezastar_pokemons', function (Blueprint $table) {
            $table->dropColumn('is_mythical');
        });
    }
}
