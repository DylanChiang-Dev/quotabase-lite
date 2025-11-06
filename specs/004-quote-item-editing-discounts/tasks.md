---

description: "Implementation tasks for Quote Item Editing + Discounts"

---

# Tasks

## Phase 1: Schema & Baseline
- [X] T101 審查 `helpers/functions.php` 中現有報價明細流程，列出需調整的函式
- [X] T102 撰寫 SQL ALTER 腳本 (`database/migrations/20251106_quote_items_discount.sql`) 與更新 `schema.sql`

## Phase 2: Backend Core
- [X] T201 新增計算函式 (`calculate_line_gross`, `calculate_line_subtotal` with discount, `calculate_discount_percent`)
- [X] T202 更新 `get_quote_items` 回傳折扣相關欄位（含折後小計）
- [X] T203 更新 `create_quote`/`add_quote_item`/`update_quote_item`：支援折扣金額、驗證折扣 <= 行金額
- [X] T204 更新 `recalculate_quote_total` 以折扣後金額重新計算 subtotal/tax/total
- [X] T205 建立/改寫 `process_quote_edit` (或同等流程)，能一次處理全部明細的新增/刪除/更新

## Phase 3: Frontend Editing UI
- [X] T301 重構 `quotes/edit.php`：草稿狀態顯示可編輯行（含折扣欄位），非草稿維持只讀
- [X] T302 編寫 JS 支援新增/刪除行、折扣即時計算、總計顯示
- [X] T303 更新 `assets/style.css` 以支援新表單排版（含手機）

## Phase 4: Display Updates
- [X] T401 更新 `quotes/view.php` 顯示折扣金額/百分比與折後小計
- [X] T402 更新 `quotes/print.php` 顯示折扣資訊並保持版面

## Phase 5: Documentation & Testing
- [X] T501 更新 README 或專案說明，指導如何執行 DB ALTER
- [ ] T502 手動測試：建立草稿 → 編輯行 + 折扣 → 檢視/列印 → 錯誤情境（折扣過大、非草稿）
