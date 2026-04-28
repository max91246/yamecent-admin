<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class RemoveAvNewsMenu extends Migration
{
    public function up()
    {
        DB::table('admin_menus')->where('name', '女優速報')->delete();
    }

    public function down()
    {
        //
    }
}
