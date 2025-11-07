---
title: "Product/Service TXT Import — 手動測試腳本"
---

## 測試前置
1. 以系統管理員帳號登入本機環境（`http://localhost:8080`），確認 `/products/` 與 `/services/` 均可存取。
2. 準備基本資料：
   - 至 `/products/new.php` 建立 1 筆測試產品（SKU：`MANUAL-BASE`）。
   - 至 `/services/new.php` 建立 1 筆測試服務（SKU：`MANUAL-SVC`）。
3. 在本機建立 `imports/` 資料夾，用於存放測試 TXT。

## 案例一：批次成功（1000 筆）
1. 以指令產出 1000 筆資料（可用腳本或試算表），欄位順序符合 `type, sku, name, unit, currency, unit_price, tax_rate, category_path, active`。
2. 確認檔案編碼為 UTF-8，大小 < 1MB。
3. 進入 `/products/`，點擊「TXT 批次匯入」，上傳該檔案。
4. 預期結果：
   - 面板結果區顯示新增 1000 筆、錯誤 0 筆。
   - 下載完整報表檔案，檢查 `處理紀錄` 段落包含所有 SKU。
   - 列表頁重新整理後，可搜尋其中任一 SKU。

## 案例二：資料驗證錯誤
1. 在 TXT 中加入下列行：`product\tERR-001\t無效資料\tpcs\tTWD\t-10\t105\t異常分類\t1`
2. 上傳檔案。
3. 預期結果：
   - 結果區的「錯誤明細」顯示該行號與訊息「格式錯誤或欄位缺漏」。
   - 下載錯誤清單確認 raw 欄位等於原始行字串。

## 案例三：分類自動建立
1. 準備資料列：`product\tCAT-001\t測試分類\tpcs\tTWD\t500\t5\t總部 > 子分類 > 末層\t1`
2. 匯入後至 `/categories/` 驗證同層級分類已建立並對應至商品。

## 案例四：覆寫策略
1. 新增資料列與既有 SKU 相同（例如 `MANUAL-BASE`），修改名稱與單位。
2. 在面板中選擇「覆蓋重複 SKU」，上傳檔案。
3. 預期結果：
   - 統計中的「覆蓋」數 +1。
   - 「最近處理」列表顯示 `覆蓋` 標籤與對應 SKU。
   - 重整產品頁，該 SKU 的名稱/單位已更新。
4. 再次上傳相同檔案，但選擇「跳過重複 SKU」。
5. 預期結果：
   - 「跳過記錄」區出現該 SKU，並標示「SKU 已存在，依策略跳過」。

## 案例五：服務匯入與單位驗證
1. 在 `/services/` 面板匯入包含 `service` 類型、單位為 `time/hour/day` 的資料。
2. 開啟 `/quotes/edit.php` 選擇新服務，確認單位隱藏欄位正確且 UI 顯示「次/時/日」。

## 案例六：權限與檔案檢查
1. 嘗試未登入直接呼叫 `/api/catalog/import-txt.php`，預期回傳 `401 UNAUTHORIZED`。
2. 上傳 1.5MB 檔案，預期收到錯誤 `檔案不可超過 1MB`。
3. 上傳非 `.txt` 檔案，預期錯誤 `僅支援 .txt 純文字檔`。

## 案例七：日誌驗證
1. 匯入完成後檢查 PHP error log（或 Docker stdout），應存在
   ```
   [CatalogImport] user=<ID> type=<product|service> strategy=<skip|overwrite> total=<n> created=<n> updated=<n> skipped=<n> errors=<n>
   ```
2. 確認錯誤情境下也會寫入 `Catalog import error: ...` 方便追蹤。
