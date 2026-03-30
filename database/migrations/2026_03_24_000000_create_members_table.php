<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMembersTable extends Migration
{
    public function up()
    {
        Schema::create('members', function (Blueprint $table) {
            $table->increments('id');
            $table->string('account', 50)->unique()->default('');
            $table->string('password', 500)->default('');
            $table->string('nickname', 50)->default('');
            $table->string('email', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->decimal('balance', 10, 2)->default(0.00)->comment('存款餘額，管理員專用');
            $table->tinyInteger('is_active')->default(1)->comment('0:停用 1:啟用');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('members');
    }
}
