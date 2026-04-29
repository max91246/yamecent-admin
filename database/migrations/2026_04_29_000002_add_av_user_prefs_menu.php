<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddAvUserPrefsMenu extends Migration
{
    public function up()
    {
        DB::table('admin_menus')->insert([
            'pid'        => 20,
            'name'       => '用戶偏好',
            'url'        => '/admin/av/user-prefs',
            'icon'       => null,
            'sort'       => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        DB::table('admin_menus')->where('url', '/admin/av/user-prefs')->delete();
    }
}
