<?php

namespace App\Console\Commands;

use App\MezastarPokemon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ScrapeMezastar extends Command
{
    protected $signature = 'scrape:mezastar
                            {--cassette= : 只抓指定卡匣ID（如 2），不填則抓全部}
                            {--dry-run   : 只印出結果，不寫入DB}';

    protected $description = '從 pokemonmezastar.com.tw 爬取各彈卡牌（名稱+圖片），屬性資料需另外補充';

    // cassette_id => 系列名稱
    const CASSETTES = [
        2  => '星塵1彈',
        7  => '星塵2彈',
        8  => '星塵3彈',
        9  => '星塵4彈',
        10 => '銀河1彈',
    ];

    public function handle()
    {
        $targetId = $this->option('cassette') ? (int) $this->option('cassette') : null;
        $dryRun   = $this->option('dry-run');
        $client   = new Client(['timeout' => 15, 'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]]);

        $cassettes = $targetId
            ? array_filter(self::CASSETTES, fn($id) => $id === $targetId, ARRAY_FILTER_USE_KEY)
            : self::CASSETTES;

        foreach ($cassettes as $cassetteId => $series) {
            $this->info("── 抓取 {$series}（cassette/{$cassetteId}）...");

            try {
                $html  = (string) $client->get("https://www.pokemonmezastar.com.tw/cassette/{$cassetteId}")->getBody();
                $cards = $this->parseCards($html);
            } catch (\Exception $e) {
                $this->error("  抓取失敗：{$e->getMessage()}");
                continue;
            }

            $this->line("  共 " . count($cards) . " 張卡");

            foreach ($cards as $card) {
                $this->line("  {$card['card_no']}  {$card['name']}");

                if (!$dryRun) {
                    // 爬蟲只負責更新圖片，不新增資料（資料來源為 Excel seeder）
                    MezastarPokemon::where('card_no', $card['card_no'])
                        ->whereNotNull('card_no')
                        ->update(['image_url' => $card['image_url']]);
                }
            }

            $this->info("  ✅ {$series} 完成" . ($dryRun ? '（dry-run，未寫入）' : ''));
        }

        // 清快取讓前台重新載入
        if (!$dryRun) {
            Cache::forget('mezastar_pokemon:all');
        }

        $this->info('全部完成！');
        return 0;
    }

    private function parseCards(string $html): array
    {
        $items  = [];
        $blocks = [];

        // 抓所有 li.cassette-list__item
        preg_match_all('/<li[^>]*class="cassette-list__item"[^>]*>(.*?)<\/li>/s', $html, $m);
        $blocks = $m[1] ?? [];

        foreach ($blocks as $block) {
            // 名稱
            preg_match('/alt="([^"]+)"/', $block, $nm);
            $name = $nm[1] ?? null;
            if (!$name) continue;

            // 圖片（優先 src=，其次 srcset= 第一個）
            preg_match('/src="(https:\/\/www\.pokemonmezastar\.com\.tw\/uploads\/images\/[^"]+)"/', $block, $im);
            if (!$im) {
                preg_match('/srcset="(https:\/\/[^\s"]+)\s/', $block, $im);
            }
            $imageUrl = $im[1] ?? null;

            // 卡號
            preg_match('/<p[^>]*>(.*?)<\/p>/s', $block, $pm);
            $cardNo = '';
            if ($pm) {
                $text   = preg_replace('/<[^>]+>/', '', $pm[1]);
                $parts  = preg_split('/\s+/', trim($text));
                $cardNo = $parts[0] ?? '';
            }

            $items[] = [
                'card_no'   => $cardNo,
                'name'      => $name,
                'image_url' => $imageUrl,
            ];
        }

        return $items;
    }
}
