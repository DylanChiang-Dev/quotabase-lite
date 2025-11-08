# Quotabase-Lite 整合報價管理系統

> 專為中小企業設計的簡潔、高效、可信賴的報價管理解決方案

## 🌟 專案概述

Quotabase-Lite 是一個專為中小企業設計的 iOS 風格報價單管理系統，採用 **零框架、僅少量 Composer 依賴** 的極簡架構，提供完整的報價與收據管理功能。

## 🤝 開源共建

Quotabase-Lite 以 MIT 授權釋出，任何人都可以自由使用、複製與修改。若你想貢獻程式碼或文件，請依循以下流程：

1. 先在 GitHub Issue 中描述問題、需求或提案，方便討論與追蹤。
2. 建立分支實作並撰寫必要測試／手動驗證步驟，提交 Pull Request。
3. Commit 訊息建議遵循 Conventional Commits，內容以繁體中文撰寫，保持 SRP/KISS/DRY/YAGNI 原則。
4. PR 說明需包含變更範圍、測試方式與可能影響，方便維護者審閱。

我們歡迎任何改善（包括文件、翻譯、UI/UX、效能、安全等領域），也希望共建者在貢獻前先閱讀 `.spec-workflow` 相關流程，確保與現有架構一致。

### ✨ 核心特性

- 🎨 **iOS 風格介面** - 現代化設計，底部 Tab 導航，Dark Mode 支援
- 💰 **精確財務處理** - 金額以分儲存，避免浮點精度問題
- 🔒 **安全可靠** - XSS 防護、CSRF 驗證、PDO 預處理、事務安全
- 📊 **完整業務流** - 客戶管理、產品/服務目錄、報價單建立、狀態跟蹤
- 🖨️ **專業列印** - A4 格式列印頁，瀏覽器直接列印，表頭固定
- 🧾 **個人收據** - 一鍵產出含 QR/hash 的個人收據列印頁，可供查驗與保存
- 📤 **資料匯出** - 支援 CSV/JSON 格式匯出
- ⚡ **高效能** - P95 響應時間 ≤ 200ms，支援 10+ 併發使用者

## 🏗️ 技術架構

### 技術棧

- **後端**: PHP 8.3 (零框架)
- **資料庫**: MySQL 8.0+ / MariaDB 10.6+
- **前端**: HTML/CSS/JavaScript (原生)
- **部署**: 寶塔面板 (aaPanel/BT)
- **Web 伺服器**: Nginx / Apache

## 🚀 快速開始

### 環境要求

- **PHP**: 8.3 或更高版本
- **MySQL**: 8.0+ / MariaDB: 10.6+
- **Web 伺服器**: Nginx 或 Apache

### 安裝步驟

1. **克隆專案**
   ```bash
   git clone <repository-url>
   cd quotabase-lite
   ```

   安裝必要套件（需已安裝 Composer）：
   ```bash
   composer install --no-dev --prefer-dist
   ```

2. **建立資料庫**
   ```bash
   mysql -u root -p
   CREATE DATABASE quotabase_lite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   EXIT;
   ```
   > 資料庫只需建立為空的 `quotabase_lite`，資料表稍後會透過初始化精靈自動生成。

3. **配置並初始化應用**
   - 直接訪問 `https://你的域名/init.php`，系統會先引導你輸入資料庫連線資訊並自動生成 `config.php`，隨後進入初始化精靈完成建表與匯入預設資料。
   - 若伺服器無法寫入 `config.php`，可退回手動方式：
     ```bash
     cp config.php.sample config.php
     # 編輯 config.php 配置資料庫連線、加密金鑰等資訊
     ```
   - 偏好命令列時可執行：
     ```bash
     php init.php install   # 建立 / 更新資料表
     php init.php init      # 匯入預設資料
     ```
   - 若仍需手動匯入完整 Schema，可使用 `mysql -u root -p quotabase_lite < schema.sql`（該操作將重建資料庫結構）。
   - 初次啟動會建立預設管理員帳號：`admin` / `Admin1234`（可透過環境變數 `DEFAULT_ADMIN_PASSWORD` 自定義），請儘快在「設定 → 賬號與安全」頁面更改密碼。

4. **設定許可權**
   ```bash
   chown -R www:www /path/to/quotabase-lite
   chmod -R 755 /path/to/quotabase-lite
   ```

## 🛠️ 部署指南

1. **伺服器就緒**：確保系統具備 PHP 8.3、MySQL 8+/MariaDB 10.6+、Nginx/Apache 以及 `php-cli`。
2. **拉取程式碼**：在 `/var/www/quotabase-lite`（或自訂目錄）執行 `git clone`，並設定所有者為 Web 服務帳號（如 `www-data`）。
3. **建立配置**：複製 `config.php.sample` 為 `config.php`，填入資料庫憑證、加密金鑰、SMTP 等生產環境參數；秘密資訊建議改從環境變數讀取。
4. **初始化資料庫**：
   ```bash
   php init.php install   # 建表或同步結構
   php init.php init      # 匯入預設資料與管理員
   ```
   亦可透過 `https://your-domain/init.php` 的初始化精靈完成上述流程。
5. **設定 Web Server**：Nginx 範例可參考 `docker/nginx` 配置，將根目錄指向專案根並允許 `index.php`。部署完成後建議封鎖 `init.php`。
6. **健康檢查**：登入系統、建立測試產品/報價單並檢視 `logs/error.log`。若日後更新版本，只需重新 `git pull`、執行 `composer install` 並跑 `php init.php install` 以套用新結構。

### 使用 Docker 快速啟動

1. 確保已複製配置檔案
   ```bash
   cp config.php.sample config.php
   ```
   > 若使用 docker compose，`DB_HOST` 將自動指向 `db` 容器，其餘賬號密碼可沿用 `docker-compose.yml` 中的環境變數。

2. 構建並啟動容器
   ```bash
   docker compose up -d --build
   ```

3. 安裝 PHP 依賴
   ```bash
   docker compose exec app composer install --no-dev --prefer-dist
   ```

4. 初始化資料庫（第一次執行）
   ```bash
   docker compose exec app php init.php install
   docker compose exec app php init.php init
   ```
   > 亦可直接訪問 `http://localhost:8080/init.php`，使用前端初始化精靈完成同樣流程。

5. 訪問應用
   - 應用：http://localhost:8080
   - MySQL：`localhost:3306`（使用者 `quotabase_user` / 密碼 `strong_password`）

6. 停止服務
   ```bash
   docker compose down
   ```

開發過程中程式碼會透過 volume 對映到容器內，修改後直接重新整理瀏覽器即可；如需檢視記錄，可執行 `docker compose logs -f app`。

### 預設管理員帳號

- 帳號：`admin`
- 密碼：`Admin1234`（可用環境變數 `DEFAULT_ADMIN_PASSWORD` 覆寫；上線後務必於「設定 → 帳號與安全」立即修改）

## 🔄 升級指南

### 新增報價折扣欄位（v2.1.0+）

已上線環境需先執行資料庫 ALTER：

```bash
# 本地環境
mysql -u <db_user> -p<db_password> quotabase_lite < database/migrations/20251106_quote_items_discount.sql

# Docker 環境（先 docker cp 遷移檔案至 /tmp 或其它可訪問目錄）
docker compose cp database/migrations/20251106_quote_items_discount.sql db:/tmp/quote_discount.sql
docker compose exec db mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" < /tmp/quote_discount.sql
```

執行完成後即可在報價編輯頁使用折扣功能。

> 自 v2.1.0 起，也可以在伺服器上執行 `php init.php install` 或透過初始化精靈補齊 `discount_cents` 欄位，適合無法離線執行 SQL 時使用。

## 📖 使用指南

### 首次使用

1. 登入系統
2. 設定公司資訊
3. 建立客戶
4. 新增產品/服務
5. 前往「設定 → 賬號與安全」修改管理員密碼或新增團隊帳號
6. 建立報價單

## 📦 更新日誌

### v2.0.1 (2025-11-06)

- ✅ 系統設定欄位與資料庫結構對齊，新增時區與聯絡方式支援
- ✅ 報價單編號流程引用最新設定字首並修正儲存過程呼叫
- ✅ 列印版面啟用自動列印並補齊 Noto Sans TC 字型
- ✅ CSV / JSON 匯出遵循契約格式，時間戳改用 ISO 8601 UTC

### v0.1.0 (2025-11-05)

- ✨ 初始版本釋出
- ✨ 完整的報價管理功能
- ✨ iOS 風格介面設計
- ✨ Dark Mode 支援
- ✨ A4 格式列印輸出
- ✨ 資料匯出功能
- ✨ 安全特性完整實現

## 📄 許可證

MIT License

## 📞 支援

- 專案型別: 單體 Web 應用
- 部署環境: Linux + 寶塔面板
- 技術棧: PHP 8.3 + MySQL + Nginx

**快速連結**:
- 📖 [完整文件](specs/002-integrated-quote-system/)
- 🎯 [任務清單](specs/002-integrated-quote-system/tasks.md)
- 🚀 [快速開始](specs/002-integrated-quote-system/quickstart.md)
