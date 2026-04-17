# yamecent-admin

## 技術棧

- **後端**：Laravel (PHP) + MySQL + Redis
- **前端**：H5 → `h5/` 目錄
- **部署**：Docker (laradock)

## 資料庫

- 所有 SQL 查詢必須使用 `yamecent.ya_*` 完整前綴（GCP 環境要求）
- 例：`SELECT * FROM yamecent.ya_users`

## 環境設定

- `.env` 中 `DB_HOST=mysql`、`REDIS_HOST=redis`（Docker 內部 service name）
- MCP 工具：`mysql`、`redis` 可透過 `.mcp.json` 直接連線查詢

## 常用指令

```bash
php artisan migrate        # 執行 migration
php artisan cache:clear    # 清除快取
php artisan route:list     # 列出所有路由
```

## GCP 升級部署指令

每次有 migration 或程式碼異動要升版到 GCP，需在 Docker workspace 容器內執行：

```bash
# 進入容器執行（本機無 php 指令，需走 Docker）
docker exec laradock_workspace_1 bash -c "cd /var/www/yamecent-admin && php artisan migrate --force"

# 若需清除快取（config / route / view）
docker exec laradock_workspace_1 bash -c "cd /var/www/yamecent-admin && php artisan config:clear && php artisan route:clear && php artisan view:clear"
```

> **注意**：`php artisan migrate --force` 的 `--force` 是 production 環境必要參數（跳過互動確認）。

## 工作流程規範

- 每次功能完成後，必須自動 commit 並 push 到 git
  1. `git add` 相關檔案
  2. `git commit -m "..."` 附上清楚的 commit message
  3. `git push`
