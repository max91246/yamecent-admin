<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMemberBalanceLogsTable extends Migration
{
    public function up()
    {
        Schema::create('member_balance_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('member_id');
            $table->decimal('amount', 10, 2)->comment('本次變動金額，始終為正數');
            $table->decimal('before_balance', 10, 2)->comment('變動前餘額');
            $table->decimal('after_balance', 10, 2)->comment('變動後餘額');
            $table->tinyInteger('type')->comment('1=增加 2=減少');
            $table->string('remark', 255)->nullable()->comment('備注');
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('member_balance_logs');
    }
}
