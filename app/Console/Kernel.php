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
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/scrape-wantgoo.log'));

        // 每 5 分鐘獲取布蘭特原油最新價格並推送 Telegram
        $schedule->command('fetch:oil-price')
                 ->cron('*/5 * * * *')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/oil-price.log'));
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
