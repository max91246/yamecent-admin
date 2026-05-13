<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 表名不含 ya_ 前綴，由 DB_PREFIX=ya_ 自動加上
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->default(0)->comment('上級菜單ID，0=頂級');
            $table->tinyInteger('menu_type')->default(0)->comment('0=菜單 1=iframe 2=外鏈 3=按鈕');
            $table->string('title', 100)->comment('菜單名稱/i18n key');
            $table->string('name', 100)->default('')->comment('路由 name');
            $table->string('path', 200)->default('')->comment('路由 path');
            $table->string('component', 200)->default('')->comment('組件路徑');
            $table->string('redirect', 200)->default('')->comment('重定向');
            $table->string('icon', 100)->default('')->comment('菜單圖標');
            $table->string('extra_icon', 100)->default('')->comment('副圖標');
            $table->string('auths', 200)->default('')->comment('按鈕權限標識');
            $table->string('frame_src', 500)->default('')->comment('iframe URL');
            $table->string('enter_transition', 50)->default('')->comment('進入動畫');
            $table->string('leave_transition', 50)->default('')->comment('離開動畫');
            $table->string('active_path', 200)->default('')->comment('激活路徑');
            $table->integer('rank')->default(99)->comment('排序');
            $table->boolean('frame_loading')->default(true);
            $table->boolean('keep_alive')->default(false);
            $table->boolean('hidden_tag')->default(false);
            $table->boolean('fixed_tag')->default(false);
            $table->boolean('show_link')->default(true)->comment('是否顯示在菜單');
            $table->boolean('show_parent')->default(false);
            $table->timestamps();
        });

        Schema::create('sys_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('角色名稱');
            $table->string('code', 50)->unique()->comment('角色標識，如 admin/editor');
            $table->tinyInteger('status')->default(1)->comment('1=啟用 0=停用');
            $table->string('remark', 255)->default('');
            $table->timestamps();
        });

        Schema::create('sys_users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('password', 255);
            $table->string('nickname', 50)->default('');
            $table->string('avatar', 500)->default('');
            $table->tinyInteger('sex')->default(0)->comment('0=男 1=女');
            $table->string('phone', 20)->default('');
            $table->string('email', 100)->default('');
            $table->tinyInteger('status')->default(1)->comment('1=啟用 0=停用');
            $table->string('remark', 255)->default('');
            $table->timestamps();
        });

        Schema::create('sys_role_menus', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('menu_id');
            $table->primary(['role_id', 'menu_id']);
        });

        Schema::create('sys_user_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_user_roles');
        Schema::dropIfExists('sys_role_menus');
        Schema::dropIfExists('sys_users');
        Schema::dropIfExists('sys_roles');
        Schema::dropIfExists('sys_menus');
    }
};
