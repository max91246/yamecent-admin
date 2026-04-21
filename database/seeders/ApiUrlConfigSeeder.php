<?php

namespace Database\Seeders;

use App\AdminConfig;
use Illuminate\Database\Seeder;

class ApiUrlConfigSeeder extends Seeder
{
    public function run()
    {
        if (AdminConfig::where('config_key', 'finviz_api_url')->exists()) {
            echo "ApiUrlConfigSeeder 已執行過，跳過。" . PHP_EOL;
            return;
        }

        $configs = [
            // ── 油價 ──────────────────────────────────────────────
            [
                'name'         => 'Finviz 油價 API',
                'config_key'   => 'finviz_api_url',
                'config_value' => 'https://finviz.com/api/quote.ashx',
                'type'         => 'url',
            ],
            // ── 台指期貨 ──────────────────────────────────────────
            [
                'name'         => '台指期貨 API（Yahoo TW）',
                'config_key'   => 'tw_index_api_url',
                'config_value' => 'https://tw.stock.yahoo.com/_td-stock/api/resource/FinanceChartService.ApacLibraCharts;symbols=%5B%22WTX%26%22%5D;type=tick',
                'type'         => 'url',
            ],
            // ── VIX ───────────────────────────────────────────────
            [
                'name'         => 'VIX 恐慌指數 API（Yahoo Finance）',
                'config_key'   => 'vix_api_url',
                'config_value' => 'https://query2.finance.yahoo.com/v8/finance/chart/%5EVIX',
                'type'         => 'url',
            ],
            // ── 台股查詢（TG Bot 用）─────────────────────────────
            [
                'name'         => '台股個股行情 API 基底（Yahoo TW）',
                'config_key'   => 'yahoo_stock_chart_base',
                'config_value' => 'https://tw.stock.yahoo.com/_td-stock/api/resource/FinanceChartService.ApacLibraCharts',
                'type'         => 'url',
            ],
            [
                'name'         => '台股三大法人 API 基底（Yahoo TW）',
                'config_key'   => 'yahoo_institutional_base',
                'config_value' => 'https://tw.stock.yahoo.com/_td-stock/api/resource/StockServices.tradesWithQuoteStats',
                'type'         => 'url',
            ],
            // ── FlareSolverr ──────────────────────────────────────
            [
                'name'         => 'FlareSolverr 代理服務 URL',
                'config_key'   => 'flaresolverr_url',
                'config_value' => 'http://flaresolverr:8191/v1',
                'type'         => 'url',
            ],
            // ── 新聞 RSS（JSON 格式儲存）─────────────────────────
            [
                'name'         => '油價相關新聞 RSS 來源（JSON）',
                'config_key'   => 'oil_news_feeds',
                'config_value' => json_encode([
                    'OilPrice.com'          => 'https://oilprice.com/rss/main',
                    'Reuters Business'      => 'https://feeds.reuters.com/reuters/businessNews',
                    'CNBC Energy'           => 'https://www.cnbc.com/id/10000664/device/rss/rss.html',
                    'Investing.com'         => 'https://www.investing.com/rss/news_285.rss',
                    'MarketWatch'           => 'https://feeds.content.dowjones.io/public/rss/mw_marketpulse',
                    'Google News: 美伊油價' => 'https://news.google.com/rss/search?q=iran+oil+trump+war&hl=en-US&gl=US&ceid=US:en',
                    'Google News: 伊朗制裁' => 'https://news.google.com/rss/search?q=iran+sanctions+nuclear+oil&hl=en-US&gl=US&ceid=US:en',
                    'Reuters World'         => 'https://feeds.reuters.com/reuters/worldNews',
                    'BBC Middle East'       => 'https://feeds.bbci.co.uk/news/world/middle_east/rss.xml',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                'type'         => 'json',
            ],
        ];

        foreach ($configs as $item) {
            AdminConfig::create($item);
        }

        echo "ApiUrlConfigSeeder 新增 " . count($configs) . " 筆 API URL 設定完成。" . PHP_EOL;
    }
}
