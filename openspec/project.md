# Project Context

## Purpose
Quotabase-Lite 是一套面向中小企業的報價管理系統，目標是以零框架 PHP 打造輕量、易部署、自帶 iOS 風格介面的工具，涵蓋客戶、產品/服務、報價單與列印匯出等完整流程，並保障金流資料的準確與安全。

## Tech Stack
- PHP 8.3（無框架、PDO）
- MySQL 8.0 / MariaDB 10.6
- 原生 HTML / CSS / JavaScript（iOS 風格 UI）
- Docker Compose（php-apache + mysql）
- Nginx / Apache 反向代理（寶塔面板部署）

## Project Conventions

### Code Style
- PHP/JS 為 4 空白縮排、CSS 為 2 空白，禁用 Tab。
- 函式與檔案採 snake_case，類別使用 PascalCase，環境變數全大寫底線分隔。
- UI 文案、註解與字串以繁體中文撰寫；對外列印/輸出維持繁體。
- 優先遵循 SOLID、KISS、DRY、YAGNI，重用 helpers/ 中的共用函式，避免重複商業邏輯。

### Architecture Patterns
- 單體式 PHP 應用，以 `config.php + helpers/functions.php + db.php` 組成基礎層。
- 頁面層 (`index.php`, `products/`, `quotes/` 等) 直接 require 配置與 helpers，透過 PDO + 封裝函式操作資料庫。
- UI 採用 partials（例如 `partials/ui.php`、`partials/catalog-import-ui.php`）重複使用元件。
- 匯入/匯出、初始化等流程拆至 `helpers/import/`、`exports/`、`init.php`，避免頁面耦合。

### Testing Strategy
- 以 `php -l <file>` 進行語法檢查，CI/本地皆需確保無語法錯誤。
- 重要流程提供手動測試腳本，位於 `.spec-workflow/specs/*/manual-test-plan.md`。
- 針對新 CLI / Service 功能，可於 `tests/` 或臨時腳本中撰寫驗證步驟；尚無自動化框架。
- 匯入、報價計算等關鍵流程需附操作步驟與預期輸出於 PR 描述中。

### Git Workflow
- 主幹為 `main`，功能開發可使用臨時分支，完成後透過 Pull Request 合併並刪除分支。
- Commit 訊息遵循 Conventional Commits，內容使用繁體中文描述。
- 在開始實作前先建立 OpenSpec 變更提案；待 proposal 核准後方可編碼。

## Domain Context
- 報價單須支援產品與服務兩種型別，金額以「分」儲存，稅率獨立欄位。
- UI 走 iOS 風格，具底部 Tab、Dark Mode，列印樣式需符合 A4 與 Noto Sans TC。
- 匯入支援 TXT（Tab / `|` 分隔），匯出支援 CSV/JSON，且要記錄操作日誌。
- 需遵循安全原則：CSRF token、XSS 轉義 (`h()`)、PDO 預處理、session 管理。

## Important Constraints
- 不可引入大型框架或 Composer 依賴，保持零框架設計。
- 任何新功能必須更新對應 spec / manual test plan，並附手動驗證步驟。
- UI/字串必須為繁體中文；金額相關運算需維持整數分精度。
- 生產環境所有錯誤需寫入 `logs/error.log`，避免直接輸出詳細錯誤。

## External Dependencies
- MySQL/MariaDB：核心資料庫，透過 PDO 連線。
- Docker Compose：本地啟動 `php-apache` 與 `mysql` 服務。
- 寶塔面板 (aaPanel/BT)：常見部署環境，提供 Nginx/Apache 管理。
- SMTP（可於 config.php 設定）：用於寄送通知或匯出檔案（若啟用）。
