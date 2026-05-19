<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsDoubleRushToMezastarPokemons extends Migration
{
    public function up()
    {
        Schema::table('mezastar_pokemons', function (Blueprint $table) {
            $table->boolean('is_double_rush')->default(0)->after('is_mythical')->comment('雙重衝擊');
        });
    }

    public function down()
    {
        Schema::table('mezastar_pokemons', function (Blueprint $table) {
            $table->dropColumn('is_double_rush');
        });
    }
}
