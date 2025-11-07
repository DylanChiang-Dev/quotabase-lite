---

description: "Catalog category management follow-up tasks"

---

# Tasks: Catalog Category Management

**Input**: Feature decisions and notes in `/specs/001-catalog-category-management/`  
**Prerequisites**: Database schema updated, helpers/functions.php category API available  
**Tests**: Manual end-to-end validation via Docker environment (no automated test harness yet)  

---

## Phase 1: Setup

 - [ ] T101 Verify Docker 開發環境可以啟動 (`docker compose up`) 並更新 README 中的啟動說明

---

## Phase 2: Tests & Diagnostics

 - [X] T201 在 `quotes/index.php` 加入臨時除錯紀錄（error_log/console）以調查「載入報價單列表失敗」

---

## Phase 3: Core - 類別資料層

 - [X] T301 更新 `schema.sql` 新增 `catalog_categories` 及 `catalog_items.category_id`
 - [X] T302 擴充 `helpers/functions.php`，提供 `get_catalog_category()` 等 CRUD 與樹狀查詢

---

## Phase 4: Core - 類別管理介面

 - [X] T401 完成 `/categories/index.php` 類別樹管理（列表、建立、編輯、刪除、階層檢視、錯誤處理）
 - [X] T402 為類別管理匯入一致的麵包屑、主題切換、底部導覽

---

## Phase 5: Core - 產品/服務表單整合

 - [X] T501 `products/new.php` 3 層類別選單（含預設與表單驗證）完成實裝
 - [X] T502 `products/edit.php` 類別選單載入與更新儲存
 - [X] T503 `services/new.php` 與 `services/edit.php` 使用 `type='service'` 類別樹並儲存類別

---

## Phase 6: Integration - 顯示與引用

 - [X] T601 在 `products/index.php`、`services/index.php` 顯示類別完整路徑
 - [ ] T602 在報價建立與報價明細頁顯示引用專案的類別路徑
 - [ ] T603 更新匯出指令碼（例如 `exports/export-products.php`）包含類別資訊

---

## Phase 7: Polish - 導航與樣式一致性

 - [ ] T701 全站麵包屑調整為 `標題 / 首頁 › 當前頁面` 格式
 - [ ] T702 移除產品/服務頁面多餘的切換按鈕，改用底部導覽
 - [ ] T703 調整報價頁面標題與資訊區塊（「首頁 / › 報價管理」與日期等資訊）位置，避免重覆區塊
 - [ ] T704 修正報價單頁面 CSS 對齊問題，確保明暗主題都一致

---

## Phase 8: Polish - 檔案與交付

 - [ ] T801 更新 `README.md`、`data-model.md`（若存在）描述類別功能與操作流程
 - [ ] T802 在 `specs/001-catalog-category-management/plan.md` 補充最終技術決策摘要

---

## Phase 9: Completion

 - [ ] T901 手動驗證：建立/編輯產品與服務、指派類別、建立報價引用專案、確認顯示與匯出正確
 - [ ] T902 清理除錯紀錄與臨時程式碼，檢查 `tasks.md` 對應任務標記為完成

---
