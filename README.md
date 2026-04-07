# Yamecent Admin

一套基於 Laravel 7 的後台管理系統，整合 REST API、Telegram 機器人互動、股票持倉追蹤與市場告警功能。

---

## 技術棧

| 項目 | 版本/說明 |
|------|-----------|
| 後端框架 | Laravel 7 |
| 前端 SPA | Vue 3 |
| 認證 | JWT（7 天有效） |
| 部署環境 | Docker（Laradock）|
| 資料庫 | MySQL 8 |
| 快取 | Redis |
| Web Server | Nginx |
| HTTPS | Let's Encrypt（Certbot）|

---

## 後台管理功能

### 系統管理

| 功能 | 路由 |
|------|------|
| 控制台 | `GET /console` |
| 管理員管理 | `/admin/administrator/*` |
| 角色管理 | `/admin/role/*` |
| 權限管理 | `/admin/permission/*` |
| 選單管理 | `/admin/menu/*` |
| 系統設定 | `/admin/config/*` |

### 內容管理

| 功能 | 路由 |
|------|------|
| 文章管理（CRUD） | `/admin/article/*` |
| 留言管理（審核/回覆/刪除） | `/admin/comment/*` |

### 會員管理

| 功能 | 路由 |
|------|------|
| 會員列表/新增/編輯 | `/admin/member/*` |
| 會籍啟用 | `POST /admin/member/membership/{id}/activate` |
| 會籍撤銷 | `POST /admin/member/membership/{id}/revoke` |
| 餘額記錄 | `/admin/member/balance/list` |

### TG 機器人管理

| 功能 | 路由 |
|------|------|
| 機器人列表/新增/編輯/刪除 | `/admin/tg-bot/*` |
| 手動設定 Webhook | `POST /admin/tg-bot/set-webhook/{id}` |
| 訊息記錄查詢 | `GET /admin/tg-message/list` |
| 用戶持股查詢 | `GET /admin/tg-holding/list` |
| 交易歷史查詢（含損益） | `GET /admin/tg-holding/trade-list` |

---

## REST API 文件

所有 API 路徑前綴為 `/api`。需認證的路由請在 Header 帶入：
```
Authorization: Bearer {token}
```

### 認證

| Method | 路由 | 說明 | 需認證 |
|--------|------|------|:------:|
| POST | `/api/auth/login` | 登入，回傳 JWT token | 否 |
| POST | `/api/auth/logout` | 登出 | 是 |

**登入請求範例：**
```json
POST /api/auth/login
{
  "account": "admin",
  "password": "123456"
}
```

**回應：**
```json
{
  "code": 200,
  "token": "eyJ0eXAiOiJKV1Qi..."
}
```

---

### 會員

| Method | 路由 | 說明 | 需認證 |
|--------|------|------|:------:|
| POST | `/api/members/register` | 會員註冊 | 否 |
| GET | `/api/members/{id}` | 取得會員資料 | 是 |
| POST | `/api/members/{id}/profile` | 更新個人資料 | 是 |
| GET | `/api/members/{id}/transactions` | 交易記錄 | 是 |
| POST | `/api/members/{id}/membership/apply` | 申請會籍升級 | 是 |

---

### 文章

| Method | 路由 | 說明 | 需認證 |
|--------|------|------|:------:|
| GET | `/api/articles` | 文章列表（支援分頁） | 否 |
| GET | `/api/articles/{id}` | 文章詳情 | 否 |
| GET | `/api/articles/{id}/comments` | 取得文章留言 | 否 |
| POST | `/api/articles/{id}/comments` | 新增留言 | 是 |

---

### Telegram Webhook

| Method | 路由 | 說明 |
|--------|------|------|
| POST | `/api/tg/webhook/{botId}` | Telegram 伺服器回調入口（公開，無需認證） |

---

## Telegram 機器人互動

### 鍵盤按鈕佈局

```
┌──────────────────┬──────────────────┐
│  🛢 布蘭特原油   │   📈 台指期貨    │
├──────────────────┼──────────────────┤
│  😨 VIX恐慌指數  │   📊 台股查詢    │
├──────────────────┴──────────────────┤
│           💼 我的持股               │
└─────────────────────────────────────┘
```

---

### 🛢 布蘭特原油

顯示最新 5 分 K 資料：
- 當前收盤價
- 5 分鐘漲跌（價差 / 百分比）
- 資料更新時間
- VIX 恐慌指數附帶顯示

---

### 📈 台指期貨

顯示台指期最新資料：
- 當前報價
- 5 分鐘漲跌（點數 / 百分比）
- 資料更新時間
- VIX 恐慌指數附帶顯示

---

### 😨 VIX 恐慌指數

- 當前 VIX 數值
- 5 分鐘變化（價差 / 百分比）
- 資料更新時間

---

### 📊 台股查詢（互動式）

**流程：**
1. 按下按鈕後，機器人提示輸入股票代號
2. 輸入代號（例如：`2317`）
3. 機器人回覆：
   - 股票名稱、成交價、漲跌幅、成交張數
   - 近 10 日三大法人買賣超橫條圖
   - 每日明細（外資 / 投信 / 自營商）
   - 10 日合計

**範例輸出：**
```
📊 鴻海（2317.TW）
💰 成交價：192.0
📈 漲跌：+1.50（+0.41%）
📦 成交張數：23,021 張

━━ 近10日三大法人買賣超 ━━
🔴 外資 [████████░░░░] ▼9,206張
🟢 投信 [█░░░░░░░░░░░] ▲629張
🔴 自營 [██░░░░░░░░░░] ▼1,897張

📅 每日明細
04/02  外▼4,392  信▲78   營▼327
...

📊 10日合計  外▼9,206  信▲629  營▼1,897
```

> 查詢結果快取：盤中 5 分鐘，盤後至下一交易日 09:00

---

### 💼 我的持股

顯示用戶個人持倉總覽：

```
💼 我的持股

📌 威剛（3260）2張·融資　買進：NT$366.5
   現值：NT$726,000
   稅費：NT$3,120（買費+賣費+稅）　淨損益：-NT$5,620

📌 台玻（1802）15張·融資　買進：NT$54.5
   現值：NT$805,500
   稅費：NT$xx,xxx　淨損益：-NT$xx,xxx

📊 自備成本：NT$1,232,200　原始市值：NT$2,162,500
📈 現值合計：NT$2,122,500
💸 預估稅費：NT$xx,xxx
💹 淨損益：-NT$xx,xxx　自備報酬：-3.25%
```

**Inline 按鈕：**
- `➕ 添加持股`
- `💰 賣出 XXXX`（每檔各一個）

---

#### 添加持股（多步驟對話）

| 步驟 | 說明 |
|------|------|
| Step 1 | 輸入股票代號（自動查詢驗證，取得股票名稱） |
| Step 2 | 輸入持有張數（整數） |
| Step 3 | 選擇是否融資（Inline 按鈕：是/否） |
| Step 4 | 輸入買進每股價格 |
| 完成 | 系統計算成本（現股=全額，融資=40%），存入 DB，顯示最新持股列表 |

---

#### 賣出持股

| 步驟 | 說明 |
|------|------|
| Step 1 | 點擊「💰 賣出 XXXX」按鈕 |
| Step 2 | 輸入賣出張數 |
| Step 3 | 輸入賣出每股價格 |
| 完成 | 計算損益並記錄，更新剩餘持股，顯示最新持股列表 |

**損益計算公式：**
```
損益 = 賣出價值 - 買進價值 - 買進手續費(0.1425%) - 賣出手續費(0.1425%) - 證券交易稅(0.3%)
```

---

#### 通用操作

任何對話步驟輸入「取消」，立即返回主選單。

---

### 市場告警（排程自動推送）

排程每 5 分鐘執行，以下條件觸發推送：

| 標的 | 觸發條件 |
|------|----------|
| 布蘭特原油 | 5 分鐘合併振幅 ≥ 1% |
| 台指期貨 | 5 分鐘漲跌 ≥ 50 點 |
| VIX 恐慌指數 | 不觸發告警，僅附帶顯示 |

告警訊息包含：
- 當前價格與漲跌方向
- 區間高低點
- 相關新聞（Google News RSS，近 1 小時）

> 若無新 K 棒寫入（休市或資料未更新），自動跳過全部處理。

---

## 資料庫結構

| 表名 | 說明 |
|------|------|
| `admin_users` | 後台管理員帳號 |
| `admin_roles` | 角色 |
| `admin_permissions` | 權限（對應路由） |
| `admin_menus` | 後台側邊欄選單 |
| `admin_configs` | 系統設定鍵值 |
| `members` | 前台會員資料 |
| `member_balance_logs` | 會員餘額變動記錄 |
| `articles` | 文章 |
| `article_comments` | 文章留言 |
| `oil_prices` | 5分K棒（ticker: `QA`=原油 / `WTX`=台指 / `VIX`=恐慌指數）|
| `tg_bots` | TG 機器人設定（token、webhook 狀態）|
| `tg_messages` | TG 對話記錄（收/發）|
| `tg_states` | TG 多步驟對話狀態機（含暫存資料）|
| `tg_holdings` | 用戶持股記錄 |
| `tg_holding_trades` | 歷史交易記錄（含損益）|

---

## 部署說明（GCP + Docker）

### 環境需求

- GCP Compute Engine（Debian）
- Docker + Docker Compose
- Laradock

### 首次部署

```bash
# 1. Clone 專案
git clone <repo-url> /var/www/yamecent-admin

# 2. 啟動 Docker 服務
cd ~/laradock
docker compose up -d nginx mysql redis workspace php-fpm

# 3. 進入 workspace
docker compose exec -it workspace bash

# 4. 安裝依賴
cd /var/www/yamecent-admin
composer install --ignore-platform-reqs

# 5. 環境設定
cp .env.example .env
# 編輯 .env：設定 DB、Redis、JWT、APP_URL、TG 等

# 6. 初始化
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan config:cache
```

### 升級部署

```bash
cd /var/www/yamecent-admin
git pull
php artisan migrate
php artisan db:seed
php artisan config:cache
```

> `db:seed` 所有 Seeder 均有冪等保護，重複執行安全。

### HTTPS 設定（Let's Encrypt）

```bash
# 停止 nginx 釋放 80 port
docker compose stop nginx

# 申請憑證
sudo certbot certonly --standalone -d yourdomain.com

# 掛載憑證至 nginx（docker-compose.yml nginx volumes 加入）
- /etc/letsencrypt:/etc/letsencrypt:ro

# 重啟
docker compose up -d nginx
```

### .env 重要設定

```env
APP_URL=https://yourdomain.com
CACHE_DRIVER=redis
REDIS_HOST=redis
REDIS_PASSWORD=your_redis_password

TG_BOT_TOKEN=       # 告警推送用的 Bot Token
TG_CHAT_ID=         # 告警推送的 Chat ID
```

### 排程設定（Crontab）

```bash
# 主機 crontab
* * * * * docker exec laradock-workspace-1 php /var/www/yamecent-admin/artisan schedule:run >> /dev/null 2>&1
```

`app/Console/Kernel.php` 中已設定每 5 分鐘執行 `fetch:oil-price`。

---

## 專案結構

```
app/
├── Console/Commands/
│   └── FetchOilPrice.php          # 油價/台指/VIX 抓取與告警
├── Http/Controllers/
│   ├── Admin/                     # 後台控制器
│   │   ├── TgBotController.php
│   │   ├── TgMessageController.php
│   │   └── TgHoldingController.php
│   └── Api/
│       └── TgWebhookController.php # TG Webhook 主控制器
├── TgBot.php
├── TgMessage.php
├── TgState.php
├── TgHolding.php
└── TgHoldingTrade.php
database/
├── migrations/
└── seeds/
routes/
├── web.php                        # 後台路由
└── api.php                        # API 路由
```
