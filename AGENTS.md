<!-- OPENSPEC:START -->
# OpenSpec Instructions

These instructions are for AI assistants working in this project.

Always open `@/openspec/AGENTS.md` when the request:
- Mentions planning or proposals (words like proposal, spec, change, plan)
- Introduces new capabilities, breaking changes, architecture shifts, or big performance/security work
- Sounds ambiguous and you need the authoritative spec before coding

Use `@/openspec/AGENTS.md` to learn:
- How to create and apply change proposals
- Spec format and conventions
- Project structure and guidelines

Keep this managed block so 'openspec update' can refresh the instructions.

<!-- OPENSPEC:END -->

# Repository Guidelines

## 專案結構與模組配置
- `index.php`、`products/`、`services/`、`quotes/`：主要頁面與 CRUD 流程。
- `helpers/`：共用函式與匯入服務 (`helpers/import/`)、資料庫包裝 (`helpers/functions.php`)。
- `partials/` 與 `assets/`：UI 組件、樣式與腳本；`partials/catalog-import-ui.php` 為可重用匯入面板。
- `.spec-workflow/` 與 `specs/`：需求、設計、任務與測試腳本，實作前務必閱讀對應 spec。
- `database/`、`schema.sql`：資料表定義與遷移腳本；`init.php` 提供 CLI/瀏覽器初始化。

## 建置、測試與開發指令
- `docker compose up -d --build`：啟動本機 LEMP + app 服務。
- `docker compose exec app php init.php install|init`：建表與匯入預設資料。
- `docker compose exec app php -l <file>`：針對指定 PHP 檔進行語法檢查。
- `docker compose exec app vendor/bin/phpunit`（如有安裝）或撰寫 `php tests/*.php`：執行自訂測試腳本。
- `docker compose logs -f app`：觀察匯入或 API 錯誤日誌。

## 程式風格與命名
- PHP、JS 採 4 空白縮排，CSS 採 2 空白；嚴禁 Tab。
- 檔案/函式命名遵循 snake_case，類別使用 PascalCase，環境變數大寫並用底線。
- Commit/PR 文案使用繁體中文；字串、UI 文案亦需保持繁體。
- 優先套用 SOLID、KISS、DRY、YAGNI 原則；共用邏輯抽至 `helpers/`。

## 測試準則
- 主要以 `php -l`、手動腳本與匯入測試 (`.spec-workflow/specs/*/manual-test-plan.md`) 為主。
- 新功能需更新相應的測試計畫，並在 PR 描述中寫明手動測試步驟。
- 若新增 CLI/Service，提供簡易 `tests/` 腳本或 `bin/` 驗證指令以便重現。

## Commit 與 Pull Request
- Commit 採 Conventional Commits，例如 `feat(catalog): 新增 TXT 匯入`、`fix(quotes): 修正稅率計算`。
- 每個 PR 應包含：變更摘要、關聯 Issue/Spec、測試結果（指令輸出或螢幕截圖）、已知風險。
- 禁止直接推送未經審查的大改（特別是資料庫／i18n），請使用分支 + PR 流程。
- 若涉及資料庫變更，必須附上對應遷移腳本與回滾指示。
