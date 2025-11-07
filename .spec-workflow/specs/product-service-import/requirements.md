# Requirements Document

## Introduction
為了縮短大量新增產品與服務的時間，我們需要一套以 .txt 檔案為主的匯入流程。此功能應提供一個明確的前端入口，讓使用者下載欄位說明、選擇目標類型（產品/服務），再上傳定義良好的 TXT 檔，系統則負責驗證與批次建立 Catalog Items。透過此功能，業務人員可以快速把 Excel/ERP 匯出的資料整理成指定格式後，一次導入系統。

## Alignment with Product Vision
Quotabase-Lite 主打「極簡、低門檻」的報價管理工具。目前產品與服務只能逐筆建立，當用戶從舊系統轉移或維護上百筆 SKU 時成本高。提供文字檔匯入可以加速導入流程，呼應產品「易部署、低維護成本」的願景，也支援更多 B2B 團隊搬遷到本系統。

## Requirements

### Requirement 1 — 前端匯入入口與指引

**User Story:** 作為系統管理員，我希望能在產品/服務列表看到「TXT 匯入」入口與格式說明，以便我在不用查文件的情況下就能準備好資料並上傳。

#### Acceptance Criteria
1. WHEN 使用者進入 `/products/` 或 `/services/` 頁面 THEN 系統 SHALL 提供清楚可見的「匯入」按鈕或卡片，並顯示允許的檔案類型（.txt、UTF-8）。
2. WHEN 使用者點擊匯入入口 THEN 系統 SHALL 彈出或導向一個步驟說明，內含欄位欄位順序、必填/選填、範例行（至少 2 筆不同型態）。
3. WHEN 使用者需要模板 THEN 系統 SHALL 允許下載一份示範 TXT 或複製預設內容，便於直接修改。
4. WHEN 使用者已上傳檔案但格式錯誤 THEN 系統 SHALL 在前端顯示明確錯誤（行號、欄位問題），不得只顯示通用訊息。

### Requirement 2 — TXT 解析與批次建立

**User Story:** 作為系統管理員，我希望系統可以解析我上傳的 TXT 並一次建立多筆產品或服務，包含分類與稅率設定，以免我需要重複手動輸入。

#### Acceptance Criteria
1. WHEN 後端接收到 TXT THEN SHALL 逐行解析欄位（推議順序：type, sku, name, unit, currency, unit_price, tax_rate, category_path, active），支援 tab 或 `|` 分隔，並忽略空白行與 `#` 開頭的註解。
2. IF 匯入行指定的 SKU 已存在 AND type 相同 THEN 系統 SHALL 提供覆蓋/跳過策略（至少預設跳過並回報）。
3. WHEN category_path 透過 `>` 表示多層分類 THEN 系統 SHALL 自動建立缺少的層級（限 3 層）並綁定於對應產品/服務。
4. WHEN 任一行驗證失敗（欄位缺漏、數值非法） THEN 系統 SHALL 停止該行建立，將錯誤寫入回報清單，並在回應中提供成功/失敗統計與失敗明細。
5. WHEN 匯入成功 THEN 新建 Catalog Item SHALL 立即可在列表中搜尋，並記錄操作人與時間（activity log 或至少寫入 server log）。

## Non-Functional Requirements

### Code Architecture and Modularity
- 匯入解析邏輯獨立於 controller（如 `helpers/import/catalog_import.php`），方便未來支援 CSV/JSON。
- 前端指引元件應為可重用的 modal/section，避免在產品與服務頁面維護兩份內容。
- 所有 I/O 操作需透過現有資料庫 abstraction，避免直接執行裸 SQL。

### Performance
- 單次匯入至少支援 1,000 筆行資料，整體執行時間應 < 5 秒（以本地單機測試為準）。
- 解析流程需採串流或逐行處理，避免一次載入整個檔案造成記憶體暴增。

### Security
- 僅已登入且具管理權限的使用者可匯入，需檢查 CSRF token。
- 只允許純文字檔（MIME/text/plain）且檔案大小限制 ≤ 1 MB；超過大小需立即拒絕。

### Reliability
- 匯入流程需具備「全部成功才更新」或「逐行寫入並提供詳細回報」策略；若採逐行寫入需確保資料一致性與 log。
- 伺服器出錯時須回應 JSON 錯誤並保留上傳紀錄，便於追蹤。

### Usability
- 前端匯入指引需使用繁體中文並提供清楚步驟（準備 → 選擇檔案 → 上傳），也要提示單位/稅率欄位可接受的值。
- 失敗訊息需附行號與錯誤原因，並可複製結果或下載錯誤報告。
