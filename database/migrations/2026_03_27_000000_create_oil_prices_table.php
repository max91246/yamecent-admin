<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOilPricesTable extends Migration
{
    public function up()
    {
        Schema::create('oil_prices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('ticker', 10)->default('QA')->comment('交易代碼');
            $table->string('timeframe', 10)->default('i5')->comment('K線週期');
            $table->timestamp('candle_at')->comment('K棒時間（UTC）');
            $table->decimal('open', 10, 4)->nullable()->comment('開盤價');
            $table->decimal('high', 10, 4)->nullable()->comment('最高價');
            $table->decimal('low', 10, 4)->nullable()->comment('最低價');
            $table->decimal('close', 10, 4)->comment('收盤價');
            $table->unsignedInteger('volume')->nullable()->comment('成交量');
            $table->timestamps();

            // 同一 ticker + 同一根 K 棒不重複寫入
            $table->unique(['ticker', 'candle_at']);
            $table->index(['ticker', 'candle_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('oil_prices');
    }
}
