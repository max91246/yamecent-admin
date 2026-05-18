<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatsToMezastarPokemons extends Migration
{
    public function up()
    {
        Schema::table('mezastar_pokemons', function (Blueprint $table) {
            $table->unsignedSmallInteger('hp')->nullable()->after('is_z_move');
            $table->unsignedSmallInteger('attack')->nullable()->after('hp');
            $table->unsignedSmallInteger('defense')->nullable()->after('attack');
            $table->unsignedSmallInteger('sp_attack')->nullable()->after('defense');
            $table->unsignedSmallInteger('sp_defense')->nullable()->after('sp_attack');
            $table->unsignedSmallInteger('speed')->nullable()->after('sp_defense');
        });
    }

    public function down()
    {
        Schema::table('mezastar_pokemons', function (Blueprint $table) {
            $table->dropColumn(['hp', 'attack', 'defense', 'sp_attack', 'sp_defense', 'speed']);
        });
    }
}
