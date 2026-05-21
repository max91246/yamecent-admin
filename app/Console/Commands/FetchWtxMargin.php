<?php

namespace App\Console\Commands;

use App\AdminConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchWtxMargin extends Command
{
    protected $signature   = 'fetch:wtx-margin';
    protected $description = '爬取台灣期交所小台保證金（結算/維持/原始），存入 admin_configs';

    const URL = 'https://www.taifex.com.tw/cht/5/indexMarging';

    public function handle()
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; YaBot/1.0)'])
                ->get(self::URL);

            if (!$response->successful()) {
                $this->error('HTTP 失敗：' . $response->status());
                return 1;
            }

            $html = $response->body();

            // 抓 <td>小型臺指</td> 後三個 <td align="center"> 數字
            if (!preg_match(
                '/<td[^>]*>\s*小型臺指\s*<\/td>\s*<td[^>]*>([\d,]+)<\/td>\s*<td[^>]*>([\d,]+)<\/td>\s*<td[^>]*>([\d,]+)<\/td>/s',
                $html,
                $m
            )) {
                $this->error('解析失敗：找不到小型臺指資料');
                Log::error('[fetch:wtx-margin] 解析失敗', ['html_len' => strlen($html)]);
                return 1;
            }

            $settle   = (int) str_replace(',', '', $m[1]);
            $maintain = (int) str_replace(',', '', $m[2]);
            $initial  = (int) str_replace(',', '', $m[3]);

            $this->saveConfig('wtx_margin_settle',   $settle,   '小台結算保證金');
            $this->saveConfig('wtx_margin_maintain', $maintain, '小台維持保證金');
            $this->saveConfig('wtx_margin_initial',  $initial,  '小台原始保證金');

            $this->info("✅ 小台保證金更新完成");
            $this->line("   結算：{$settle}　維持：{$maintain}　原始：{$initial}");

            Log::info("[fetch:wtx-margin] 更新成功 settle={$settle} maintain={$maintain} initial={$initial}");
            return 0;

        } catch (\Exception $e) {
            $this->error('爬取失敗：' . $e->getMessage());
            Log::error('[fetch:wtx-margin] 例外', ['msg' => $e->getMessage()]);
            return 1;
        }
    }

    private function saveConfig(string $key, int $value, string $name): void
    {
        AdminConfig::updateOrCreate(
            ['config_key' => $key],
            ['name' => $name, 'config_value' => (string) $value, 'type' => 'text']
        );
        Cache::forget('admin_config:' . $key);
    }
}
