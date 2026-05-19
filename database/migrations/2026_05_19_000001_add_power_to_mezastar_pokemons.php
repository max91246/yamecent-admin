<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPowerToMezastarPokemons extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('ya_mezastar_pokemons', 'power')) {
            Schema::table('ya_mezastar_pokemons', function (Blueprint $table) {
                $table->unsignedSmallInteger('power')->nullable()->after('image_url')->comment('寶可能量');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('ya_mezastar_pokemons', 'power')) {
            Schema::table('ya_mezastar_pokemons', function (Blueprint $table) {
                $table->dropColumn('power');
            });
        }
    }
}
