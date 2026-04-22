<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDisposalStocksTable extends Migration
{
    public function up()
    {
        Schema::create('ya_disposal_stocks', function (Blueprint $table) {
            $table->id();
            $table->enum('market', ['twse', 'tpex'])->comment('上市/上櫃');
            $table->string('stock_code', 10)->comment('股票代號');
            $table->string('stock_name', 50)->comment('股票名稱');
            $table->date('announced_date')->comment('公告日期');
            $table->date('start_date')->comment('處置起始日');
            $table->date('end_date')->comment('處置截止日');
            $table->string('reason', 255)->nullable()->comment('處置原因');
            $table->text('condition')->nullable()->comment('處置措施內容');
            $table->timestamps();

            $table->index(['stock_code', 'end_date']);
            $table->index('end_date');
            $table->unique(['market', 'stock_code', 'start_date'], 'uq_market_code_start');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ya_disposal_stocks');
    }
}
