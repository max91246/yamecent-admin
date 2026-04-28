<?php

namespace App\Console\Commands;

use App\AvActress;
use App\AvVideo;
use Illuminate\Console\Command;

class BackfillAvVideoActresses extends Command
{
    protected $signature   = 'av:backfill-actresses';
    protected $description = '回填既有影片的女優關聯到 ya_av_video_actresses';

    public function handle()
    {
        $total   = AvVideo::whereNotNull('actresses')->count();
        $this->info("共 {$total} 部影片需要回填...");

        $done = 0;
        AvVideo::whereNotNull('actresses')->chunk(100, function ($videos) use (&$done) {
            foreach ($videos as $video) {
                $names = $video->actresses;
                if (empty($names)) continue;

                $actressIds = [];
                foreach ($names as $name) {
                    $name = trim($name);
                    if (!$name) continue;
                    $actress = AvActress::firstOrCreate(
                        ['name' => $name],
                        ['missav_slug' => $name, 'is_active' => true]
                    );
                    $actressIds[] = $actress->id;
                }

                if ($actressIds) {
                    $video->actresses()->sync($actressIds);
                }
                $done++;
            }
            $this->line("  已處理 {$done} 部...");
        });

        $this->info("完成！共關聯 {$done} 部影片的女優資料。");
        return 0;
    }
}
