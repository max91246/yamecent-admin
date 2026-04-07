<?php

namespace App\Services;

use GuzzleHttp\Client;

class OilNewsService
{
    /**
     * RSS 來源清單（從 admin_configs 載入，fallback 至空陣列）
     */
    private array $feeds;

    /**
     * 過濾關鍵字（原油市場 + 地緣政治）
     */
    private array $keywords = [
        // 原油市場
        'crude', 'brent', 'oil', 'barrel', 'opec', 'petroleum',
        'energy', 'wti', 'fuel', '原油', '石油', '油價', 'برنت',
        // 地緣政治（英文）
        'iran', 'trump', 'sanction', 'hormuz', 'war', 'nuclear',
        'military', 'tehran', 'strike', 'attack', 'middle east',
        // 地緣政治（繁體中文）
        '伊朗', '川普', '制裁', '美伊', '戰爭', '核', '軍事',
        '荷姆茲', '中東', '以色列', '胡塞',
    ];

    private Client $client;

    public function __construct()
    {
        $feedsJson   = getConfig('oil_news_feeds');
        $this->feeds = $feedsJson ? (json_decode($feedsJson, true) ?? []) : [];

        $this->client = new Client([
            'timeout'         => 10,
            'connect_timeout' => 5,
            'headers'         => [
                'User-Agent' => 'Mozilla/5.0 (compatible; RSSReader/1.0)',
                'Accept'     => 'application/rss+xml, application/xml, text/xml, */*',
            ],
        ]);
    }

    /**
     * 抓取近期原油相關新聞
     *
     * @param  int $maxHours  只取幾小時內的新聞（預設 4 小時）
     * @param  int $limit     最多回傳幾筆
     * @return array          [ ['title', 'url', 'source', 'ts', 'ago'], ... ]
     */
    public function fetch(int $maxHours = 1, int $limit = 5): array
    {
        $all    = [];
        $cutoff = time() - ($maxHours * 3600);

        foreach ($this->feeds as $source => $feedUrl) {
            try {
                $items = $this->parseFeed($feedUrl, $source);
                $all   = array_merge($all, $items);
            } catch (\Throwable) {
                // 單一來源失敗不影響整體
                continue;
            }
        }

        // 過濾：時間 + 關鍵字
        $filtered = array_filter($all, function (array $item) use ($cutoff): bool {
            return $item['ts'] >= $cutoff && $this->hasKeyword($item['title']);
        });

        // 時間降冪排列，去重（相同 URL）
        usort($filtered, fn ($a, $b) => $b['ts'] - $a['ts']);

        $seen   = [];
        $unique = [];
        foreach ($filtered as $item) {
            $key = md5($item['url']);
            if (!isset($seen[$key])) {
                $seen[$key]  = true;
                $item['ago'] = $this->timeAgo($item['ts']);
                $item['title'] = $this->translateTitle($item['title']);
                $unique[]    = $item;
            }
        }

        return array_slice($unique, 0, $limit);
    }

    // ────────────────────────────────────────────────────────────
    //  解析單一 RSS / Atom feed
    // ────────────────────────────────────────────────────────────
    private function parseFeed(string $url, string $source): array
    {
        $res  = $this->client->get($url);
        $body = (string) $res->getBody();

        // 移除 BOM 與非法字元
        $body = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $body);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_clear_errors();

        if ($xml === false) {
            return [];
        }

        $items = [];

        // ── RSS 2.0 ──
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $items[] = $this->extractRssItem($item, $source);
            }
        }

        // ── Atom ──
        if (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $items[] = $this->extractAtomEntry($entry, $source);
            }
        }

        return array_filter($items, fn ($i) => $i['ts'] > 0);
    }

    private function extractRssItem(\SimpleXMLElement $item, string $source): array
    {
        $title = trim((string) $item->title);
        $url   = trim((string) $item->link);

        // 有些 RSS 把 link 放在 CDATA 之外或用 <guid>
        if (empty($url) && !empty($item->guid)) {
            $url = trim((string) $item->guid);
        }

        $pubDate = trim((string) $item->pubDate);
        $ts      = $pubDate ? (int) strtotime($pubDate) : 0;

        return ['title' => $title, 'url' => $url, 'source' => $source, 'ts' => $ts];
    }

    private function extractAtomEntry(\SimpleXMLElement $entry, string $source): array
    {
        $title = trim((string) $entry->title);

        // Atom <link href="...">
        $url = '';
        if (isset($entry->link)) {
            $attrs = $entry->link->attributes();
            $url   = trim((string) ($attrs['href'] ?? $entry->link));
        }

        $published = (string) ($entry->published ?? $entry->updated ?? '');
        $ts        = $published ? (int) strtotime($published) : 0;

        return ['title' => $title, 'url' => $url, 'source' => $source, 'ts' => $ts];
    }

    // ────────────────────────────────────────────────────────────
    //  工具方法
    // ────────────────────────────────────────────────────────────
    // ────────────────────────────────────────────────────────────
    //  Google Translate 免費 API 翻譯標題為繁體中文
    // ────────────────────────────────────────────────────────────
    private function translateTitle(string $text): string
    {
        if (empty(trim($text))) {
            return $text;
        }

        // 若已是中文（含繁/簡），不重複翻譯
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $text)) {
            return $text;
        }

        try {
            $url = 'https://translate.googleapis.com/translate_a/single?' . http_build_query([
                'client' => 'gtx',
                'sl'     => 'auto',
                'tl'     => 'zh-TW',
                'dt'     => 't',
                'q'      => $text,
            ]);

            $res  = $this->client->get($url, ['timeout' => 5]);
            $json = json_decode((string) $res->getBody(), true);

            // 回傳格式：[[["翻譯結果","原文",...], ...], ...]
            $translated = '';
            if (isset($json[0]) && is_array($json[0])) {
                foreach ($json[0] as $part) {
                    if (isset($part[0])) {
                        $translated .= $part[0];
                    }
                }
            }

            return $translated ?: $text;
        } catch (\Throwable) {
            return $text;
        }
    }

    private function hasKeyword(string $text): bool
    {
        $lower = strtolower($text);
        foreach ($this->keywords as $kw) {
            if (str_contains($lower, strtolower($kw))) {
                return true;
            }
        }
        return false;
    }

    private function timeAgo(int $ts): string
    {
        $diff = time() - $ts;
        if ($diff < 60)    return "{$diff} 秒前";
        if ($diff < 3600)  return floor($diff / 60) . ' 分鐘前';
        if ($diff < 86400) return floor($diff / 3600) . ' 小時前';
        return floor($diff / 86400) . ' 天前';
    }
}
