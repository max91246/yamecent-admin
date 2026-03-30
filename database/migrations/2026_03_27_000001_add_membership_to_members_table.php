<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMembershipToMembersTable extends Migration
{
    public function up()
    {
        Schema::table('members', function (Blueprint $table) {
            $table->tinyInteger('is_member')->default(0)->comment('0=非會員 1=會員有效')->after('is_active');
            $table->timestamp('member_expired_at')->nullable()->comment('會員到期時間')->after('is_member');
            $table->timestamp('member_applied_at')->nullable()->comment('申請時間')->after('member_expired_at');
        });
    }

    public function down()
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['is_member', 'member_expired_at', 'member_applied_at']);
        });
    }
}
