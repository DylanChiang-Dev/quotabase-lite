# Design Document

## Overview
新增「產品/服務 TXT 匯入」功能，讓管理員在 `/products/` 與 `/services/` 頁面直接上傳文字檔並批次建立 Catalog Items。前端提供指引、模板下載與上傳表單；後端則解析 TXT、比對 SKU（跳過或記錄覆蓋請求）、自動建立分類並回傳詳細匯入結果。

## Steering Document Alignment

### Technical Standards (tech.md)
- 沿用現有零框架 PHP 架構，所有請求仍以單檔 PHP 作為入口。
- 後端邏輯封裝在 helpers 層（`helpers/import/catalog_import.php`），controller 僅負責驗證與回應。
- 儲存層使用既有 `db.php` / helpers 內的資料存取函式（`get_catalog_item`, `add_catalog_item` 等）。

### Project Structure (structure.md)
- 前端 UI 直接修改 `products/index.php`、`services/index.php`：新增匯入卡片與 modal。
- API 放在 `products/import-txt.php` / `services/import-txt.php` 或共用的 `/api/catalog/import-txt.php`（採後者，利於共用 type 參數）。
- 新增 `helpers/import/catalog_import.php` 專責解析 + 驗證 + 寫入。

## Code Reuse Analysis

### Existing Components to Leverage
- **`helpers/functions.php` Catalog helpers：** 使用 `get_catalog_item`, `get_catalog_category_path`, `create_or_get_category`（若缺少會於新 helper 中呼叫既有邏輯或擴充）。
- **`render_category_selector`** 的分類資料來源可重用；匯入時建立分類可沿用 `ensure_category_hierarchy`（若不存在則新增輔助函式）。
- **CSRF / Auth 驗證**：沿用 `verify_csrf_token()` 與 `is_logged_in()`。

### Integration Points
- **產品/服務列表頁**：新增匯入 UI 與前端 JS，提出 `POST /api/catalog/import-txt.php`。
- **資料庫**：寫入 `catalog_items`、視需要建立 `catalog_categories`，與現有 Quote 連動無需變更。

## Architecture

```
products/services page
  └─ ImportDrawer (HTML + JS)
        ├─ shows instructions + template download
        ├─ <input type=file accept=.txt> + type radio
        └─ submit → /api/catalog/import-txt.php (multipart)

/api/catalog/import-txt.php
  ├─ verify login + CSRF + permissions
  ├─ validate file size/type
  ├─ stream file → CatalogImportService
  │      └─ parse_line() → normalize payload
  │      └─ CatalogCategoryResolver (ensure hierarchy)
  │      └─ CatalogItemWriter (insert / skip) within transaction per line
  └─ aggregate result {total, created, skipped, errors[]} → JSON
```

### Modular Design Principles
- 前端：小型 modal/section 元件，避免干擾既有列表排版。
- 後端：`CatalogImportService` 封裝 parsing + validation；`CatalogImportReporter` 收集成功/失敗資訊。
- 分離分類解析（`CategoryPathResolver`）與匯入主流程，後續可重複利用於 CSV/JSON。

## Components and Interfaces

### Component 1 — ImportDrawer (前端)
- **Purpose:** 在產品/服務列表展示說明、下載範本與上傳鈕。
- **Interfaces:**
  - `data-import-open` 按鈕開啟 `<dialog>` / `<section>`。
  - 表單欄位：`type` (product/service)、`strategy` (skip/overwrite)、`file` (txt)。
  - `fetch('/api/catalog/import-txt.php', { body: FormData })`。
- **Dependencies:** 原有頁面 CSS/JS，`csrf_token` hidden field。
- **Reuses:** `csrf_input()` 產生 token、既有通知/alert 元件顯示結果。

### Component 2 — CatalogImportService (PHP)
- **Purpose:** 解析 TXT、驗證欄位、寫入 catalog_items/category。
- **Interfaces:**
  - `__construct(PDO $pdo, string $type, string $strategy)`
  - `importFromStream(resource $handle): CatalogImportResult`
- **Dependencies:** `get_catalog_item`, `create_catalog_item`, 新的 `resolve_category_path($path, $type)`。
- **Reuses:** 既有 catalog helper 做查詢/插入；使用 `dbExecute`、`dbQueryOne`。

### Component 3 — CategoryPathResolver
- **Purpose:** 將 `設備 > 高速 > 切割機` 類的字串轉成層級，若不存在即建立。
- **Interfaces:** `ensure(string $path, string $type): int` 回傳 category_id。
- **Dependencies:** `catalog_categories` table。

### Component 4 — Import Result Reporter
- **Purpose:** 聚合成功/失敗資訊，供 API 回傳。
- **Interfaces:** `addSuccess($sku)`、`addError($line, $message)`、`toArray()`。

## Data Models

### CatalogImportResult
```
CatalogImportResult
- totalLines: int
- success: int
- skipped: int
- errors: array<{ line:int, message:string, raw:string }>
```

### ParsedLine (暫存結構)
```
ParsedLine
- type: 'product'|'service'
- sku: string
- name: string
- unit: string
- currency: string
- unit_price_cents: int
- tax_rate: float
- category_path: string|null
- active: bool
- description?: string
```

## Error Handling

### Error Scenario 1 — 檔案驗證失敗
- **Handling:** API 直接回傳 400 與 `{error: 'INVALID_FILE', detail: '僅接受 .txt, <=1MB'}`。
- **User Impact:** 匯入面板顯示錯誤訊息，不寫入資料。

### Error Scenario 2 — 單行欄位錯誤
- **Handling:** `CatalogImportService` 記錄 `line` + `message`，跳過該行，繼續下一筆。
- **User Impact:** 上傳完成後，結果區顯示失敗行與原因，可下載錯誤列表。

### Error Scenario 3 — 資料庫異常
- **Handling:** 捕捉例外，若為單行寫入錯誤則記錄 error；若為嚴重錯誤則終止匯入並回傳 500。
- **User Impact:** 顯示「匯入失敗，請稍後再試」，同時伺服器 log 詳細錯誤。

## Testing Strategy

### Unit Testing
- 對 `CatalogImportService` 的 `parseLine`、`validateFields`、`ensureCategory` 撰寫純 PHP 測試（可用 `phpunit` 或手寫 CLI 腳本），覆蓋各欄位驗證與策略。
- 測試 `CategoryPathResolver` 在空資料庫/既有資料下的建立行為。

### Integration Testing
- 於本地環境手動上傳範例 TXT，驗證成功建立、重複 SKU 跳過、分類自動建立。
- 測試 overwrite vs skip 策略（若設計中允許覆蓋）。

### End-to-End Testing
- 使用實際瀏覽器在 `/products/`、`/services/` 完整走匯入流程，包含錯誤檔案/錯誤欄位情境。必要時錄製影片或截圖做為 QA 證據。
