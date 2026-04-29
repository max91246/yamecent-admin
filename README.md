# Yamecent Admin

一套基於 Laravel 9 的後台管理系統，整合 REST API、多類型 Telegram 機器人、股票持倉追蹤、市場告警與 AV 內容管理功能。

> 本專案由 **max91246** 與 **Claude（Anthropic）** 協作完成。
> 從需求討論、架構設計、程式碼實作到部署除錯，全程以 Vibe Coding 方式推進——
> 用自然語言描述需求，Claude 負責思考與實作，人負責驗證與決策。

---

## 技術棧

| 項目 | 版本/說明 |
|------|-----------|
| 後端框架 | Laravel 9 |
| 認證 | Session-based RBAC（後台）/ JWT（API） |
| 部署環境 | Docker（Laradock）+ GCP Compute Engine |
| 資料庫 | MySQL 8（DB prefix: `ya_`） |
| 快取/Queue | Redis（Predis） |
| Web Server | Nginx + Let's Encrypt |
| 爬蟲代理 | FlareSolverr（繞 Cloudflare） |

---

## 後台選單結構

```
控制台
系統設定
  ├ 管理員管理 / 角色 / 權限 / 選單 / 系統設定
系統日誌（Log Viewer，僅超級管理員）
文章管理 / 會員管理
機器人管理
  ├ 機器人管理（列表 / 設定 Webhook）
  └ 訊息記錄
股票功能
  ├ 持股管理     - 各用戶持倉總覽
  ├ 交易記錄     - 歷史買賣含損益
  ├ 處置股查詢   - TPEX / TWSE 當前處置股名單
  └ 台股查詢     - 即時股價 + K線 + 三大法人 + 月營收 + 新聞 + 近10週大戶持股分散表
AV 管理
  ├ 新片速報     - MissAV 最新影片（按標籤/女優/片商篩選）
  └ 女優管理     - AV 女優資料庫（搜尋/篩選）
```

---

## Telegram 機器人

### 機器人類型

| type | 說明 |
|------|------|
| 1（股票指數）| 股票行情查詢 + 持倉管理 |
| 2（AV 查詢）| AV 新片速報 + 喜好推播 |

### 股票機器人主選單

```
📈 台指期貨  |  🛡 避險商品指數
📊 台股查詢  |  💼 我的持股
⚙️ 設置
```

**避險商品指數** 一次顯示：布蘭特原油 / VIX 恐慌指數 / 黃金期貨（GC=F）

**台股查詢** 流程：輸入股票代號 → 回覆即時股價、三大法人（含橫條圖）、月營收、最新新聞、處置股標記、近10週大戶持股（>1000張人數 / 持有% / 收盤價）

**我的持股** 功能：
- 添加持股（多步驟：代號 → 股數 → 融資選擇 → 買進價）
- 賣出持股（FIFO 多筆合併）
- T+2 交割款追蹤
- 帳戶資金設定（總資金 / 剩餘資金兩種模式）
- 處置股自動標記（買進立即扣款，不走 T+2）

### AV 機器人主選單

```
🎬 今日新片  |  ⭐ 喜好設定
```

**今日新片**：以昨日（D-1）為基準，優先推薦符合用戶喜好 tag 的影片，最多 5 部。

**喜好設定**：Inline keyboard 選擇喜好標籤（動態從 DB 統計前 30 個熱門 tag），開關每日推播。

**每日 09:00 自動推播**：符合用戶喜好 tag 的昨日新片。

---

## 排程任務

| 時間 | 指令 | 說明 |
|------|------|------|
| 每 5 分鐘 | `fetch:oil-price` | 布蘭特原油 5分K，振幅 ≥ 1% 推送告警；同步台指 / VIX / 黃金現價 |
| 每分鐘 | `fetch:tw-index` | 台指期 1分K，漲跌 ≥ 50 點推送告警 |
| 每日 00:00 | `settle:payments` | 結算 T+2 到期交割款 |
| 每日 08:00 | `fetch:disposal-stocks` | 抓取 TPEX / TWSE 最新處置股，寫入 DB + Redis |
| 每日 14:00 | `notify:holdings` | 台股收盤後推送持股漲跌通知 |
| 每 6 小時 | `scrape:wantgoo` | 爬取玩股網精選文章 |
| 每日 01:00 | `scrape:av-actresses --new-only` | 爬取 MissAV 最新出道女優 |
| 每日 01:30 / 13:30 | `scrape:av-videos --pages=3` | 爬取 MissAV 最新影片 |
| 每日 09:00 | `notify:av-daily` | 推送 AV 新片給訂閱用戶 |

---

## Log 系統

| Channel | 檔案 | 記錄內容 |
|---------|------|----------|
| `tg_webhook` | `logs/tg-webhook-YYYY-MM-DD.log` | TG Bot 收發事件（股票機器人） |
| `oil_price` | `logs/oil-price-YYYY-MM-DD.log` | 油價 K棒 / 告警 |
| `tw_index` | `logs/tw-index-YYYY-MM-DD.log` | 台指震盪告警 |
| `notify_holdings` | `logs/notify-holdings-YYYY-MM-DD.log` | 持股通知 |
| `settle_payments` | `logs/settle-payments-YYYY-MM-DD.log` | 交割款結算 |
| `scrape_wantgoo` | `logs/scrape-wantgoo-YYYY-MM-DD.log` | 玩股網爬蟲 |
| `av_scraper` | `logs/av-scraper-YYYY-MM-DD.log` | AV 影片 / 女優爬蟲 / 推播 |

---

## 資料庫主要表結構

### 系統
| 表名 | 說明 |
|------|------|
| `ya_admin_users` | 後台管理員 |
| `ya_admin_roles` / `ya_admin_permissions` / `ya_admin_menus` | RBAC |
| `ya_admin_configs` | 系統設定（API URL 等，統一用 `getConfig()` 讀取） |
| `ya_migrations` | Migration 記錄 |

### 股票機器人
| 表名 | 說明 |
|------|------|
| `ya_tg_bots` | 機器人設定（type: 1=股票, 2=AV） |
| `ya_tg_messages` | 對話記錄 |
| `ya_tg_states` | 多步驟狀態機（JSON 暫存資料） |
| `ya_tg_holdings` | 用戶持股（以股為單位，支援零股） |
| `ya_tg_holding_trades` | 歷史交易含損益 |
| `ya_tg_wallets` | 用戶帳戶資金 |
| `ya_tg_settlements` | T+2 交割款明細 |
| `ya_oil_prices` | 5分K棒（QA=原油 / WTX=台指 / VIX=恐慌 / GOLD=黃金） |
| `ya_disposal_stocks` | 處置股名單（市場 / 起訖日 / 原因） |

### AV 系統
| 表名 | 說明 |
|------|------|
| `ya_av_videos` | AV 影片（番號 / 標題 / 封面 / 片商 / 演員 / 標籤） |
| `ya_av_actresses` | AV 女優（姓名 / 三圍 / 生日 / 出道年） |
| `ya_av_video_actresses` | 影片 ↔ 女優 多對多關聯 |
| `ya_av_user_prefs` | 用戶喜好標籤 + 推播開關 |
| `ya_av_video_clicks` | 影片點擊記錄（熱門排序用） |

---

## Redis Cache 設計

| Key | 說明 | TTL |
|-----|------|-----|
| `disposal:{code}` | 處置股個股資料 | 至隔日 07:59 |
| `disposal:cache_ready` | 處置股快取旗標 | 至隔日 07:59 |
| `tw-{code}` | 台股即時報價 | 5 分鐘 |
| `tw-news-{code}` | 台股新聞 | 10 分鐘 |
| `av_popular_tags` | 熱門 AV 標籤統計 | 1 小時（新片爬取後自動失效） |
| `av_pref_{bot}_{chat}` | 用戶喜好 tag | 10 分鐘 |
| `tg_upd_{bot}_{updateId}` | TG update 去重 | 60 秒 |
| `shareholder_dist:{code}` | 大戶持股分散表（近10週，來源：神秘金字塔） | 1 天 |

---

## 色系規範

| 情境 | 上漲 / 獲利 | 下跌 / 虧損 | 平盤 |
|------|------------|------------|------|
| 台股（後台 + TG） | 紅色 `#fc8181` `.tw-up` | 綠色 `#68d391` `.tw-dn` | 白色 `.tw-flat` |

全站統一使用 `.tw-up` / `.tw-dn` / `.tw-flat` class（定義於 `base.blade.php`）。

---

## 重要開發規範

1. **所有外部 API URL** 存入 `ya_admin_configs`，透過 `getConfig('key')` 讀取，不得寫死在程式碼
2. **頻繁讀取的資料** 走 `Cache::remember()`，更新時 `Cache::forget()` 讓快取失效
3. **SQL 查詢** 使用 `yamecent.ya_*` 完整前綴（GCP 環境要求）
4. **Model `$table`** 不含 `ya_` 前綴（`DB_PREFIX=ya_` 已設定，重複會雙重前綴）
5. **新方案** 先用 test command 或 tinker 驗證可行，再寫正式 migration / code
6. **FlareSolverr** 用於繞過 Cloudflare；`getConfig('flaresolverr_url')` 已含 `/v1`，不需再拼接

---

## GCP 部署

### 環境
- GCP Compute Engine + Laradock（`~/laradock`）
- 專案目錄：`/var/www`（Docker 容器內）

### 升級流程

```bash
# 1. Host 拉取代碼
cd ~/www/yamecent-admin && git pull

# 2. 進入容器執行 artisan
cd ~/laradock && docker compose exec -it workspace bash
php artisan migrate --force
php artisan config:clear && php artisan view:clear
```

### 排程（Crontab）

```bash
* * * * * docker exec laradock-workspace-1 php /var/www/artisan schedule:run >> /dev/null 2>&1
```
