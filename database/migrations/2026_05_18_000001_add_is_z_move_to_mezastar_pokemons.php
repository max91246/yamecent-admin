<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsZMoveToMezastarPokemons extends Migration
{
    public function up()
    {
        Schema::table('mezastar_pokemons', function (Blueprint $table) {
            $table->boolean('is_z_move')->default(false)->after('is_dual_move');
        });
    }

    public function down()
    {
        Schema::table('mezastar_pokemons', function (Blueprint $table) {
            $table->dropColumn('is_z_move');
        });
    }
}
