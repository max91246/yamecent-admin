# yamecent-admin

## 技術棧

- **後端**：Laravel (PHP) + MySQL + Redis
- **前端**：H5 → `h5/` 目錄
- **部署**：Docker（自建 docker-compose）+ GCP Compute Engine

## 資料庫

- 所有 SQL 查詢必須使用 `yamecent.ya_*` 完整前綴（GCP 環境要求）
- 例：`SELECT * FROM yamecent.ya_users`

## 環境設定

- `.env` 中 `DB_HOST=mysql`、`REDIS_HOST=redis`（Docker 內部 service name）
- MCP 工具：`mysql`、`redis` 可透過 `.mcp.json` 直接連線查詢

## 常用指令

```bash
# 本地（docker-compose.override.yml 自動套用）
docker compose up -d
docker compose exec php-fpm php artisan migrate
docker compose exec php-fpm php artisan cache:clear

# GCP（需指定 prod 設定）
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec php-fpm php artisan migrate --force
```

## GCP 升級部署指令

GCP 專案目錄在 `/home/max91246/www/yamecent-admin`。
`git pull` 在容器外執行（需 sudo -u max91246），`php artisan` 進 php-fpm 容器執行。

```bash
# Step 1：在 Host（容器外）拉取最新代碼
sudo -u max91246 git -C /home/max91246/www/yamecent-admin pull

# Step 2：在 php-fpm 容器內執行 artisan
cd /home/max91246/www/yamecent-admin
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec php-fpm php artisan migrate --force

# 若需清除快取（config / route / view）
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec php-fpm \
  bash -c "php artisan config:clear && php artisan route:clear && php artisan view:clear"
```

> **注意**：`php artisan migrate --force` 的 `--force` 是 production 環境必要參數（跳過互動確認）。

## Docker Compose 檔案說明

| 檔案 | 用途 |
| --- | --- |
| `docker-compose.yml` | 所有服務定義（無 ports，環境中立） |
| `docker-compose.override.yml` | 本地自動套用：port 80/3306/6379、HTTP-only nginx、named volume |
| `docker-compose.prod.yml` | GCP 手動指定：port 80/443/3306/6379、Let's Encrypt SSL、GCP 資料目錄 |

## 工作流程規範

- 每次功能完成後，必須自動 commit 並 push 到 git
  1. `git add` 相關檔案
  2. `git commit -m "..."` 附上清楚的 commit message
  3. `git push`
