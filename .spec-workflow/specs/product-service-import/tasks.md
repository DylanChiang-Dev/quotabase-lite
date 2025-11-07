---

description: "Implementation tasks for Product/Service TXT Import"

---

# Tasks

## Phase 1: Backend Infrastructure
- [ ] T101 建立 `helpers/import/catalog_import.php`：解析 TXT、欄位驗證、錯誤回報結構
- [ ] T102 建立 CategoryPathResolver：根據 `A > B > C` 層級建立/取得分類並回傳 ID
- [ ] T103 新增 API 端點 `/api/catalog/import-txt.php`：權限/CSRF 驗證、檔案檢查、呼叫匯入服務並回傳 JSON

## Phase 2: Frontend UI & Guidance
- [x] T201 在 `products/index.php` 新增匯入入口（含說明、範本下載、上傳表單），並可重用於服務頁
- [x] T202 建立共用 JS（或 inline 模組）負責 modal/抽屜顯示、檔案選擇與 AJAX 上傳、結果顯示
- [x] T203 於前端顯示匯入結果（成功/跳過/錯誤）並允許下載錯誤清單或複製資訊

## Phase 3: Validation & Reporting
- [x] T301 實作覆寫策略（預設跳過，提供參數決定是否覆寫），並在結果中列出跳過原因
- [x] T302 寫入 server log 或 activity log：記錄匯入者、類型、CSV 行為統計
- [x] T303 手動測試腳本：1000 筆成功、包含錯誤行、分類自動建立、權限/檔案大小檢查
