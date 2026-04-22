<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // 每小時爬取玩股網精選文章第 1 頁
        $schedule->command('scrape:wantgoo --page=1')
                 ->everySixHours()
                 ->withoutOverlapping();

        // 每 5 分鐘獲取布蘭特原油最新價格並推送 Telegram（台指告警已移至 fetch:tw-index）
        $schedule->command('fetch:oil-price')
                 ->everyFiveMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();

        // 每分鐘抓取台指期 / VIX，5分鐘震盪 ≥ 50 點時告警
        $schedule->command('fetch:tw-index')
                 ->everyMinute()
                 ->withoutOverlapping()
                 ->runInBackground();

        // 每日 00:00 結算當日到期的 T+2 交割款
        $schedule->command('settle:payments')
                 ->dailyAt('00:00');

        // 每日 14:00（台股收盤後）審視持股，漲跌超過閾值時推送通知
        $schedule->command('notify:holdings')
                 ->timezone('Asia/Taipei')
                 ->dailyAt('14:00')
                 ->withoutOverlapping();

        // 每日 08:00 抓取 TPEX + TWSE 最新處置股名單
        $schedule->command('fetch:disposal-stocks')
                 ->timezone('Asia/Taipei')
                 ->dailyAt('08:00')
                 ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

//        require base_path('routes/console.php');
    }
}
