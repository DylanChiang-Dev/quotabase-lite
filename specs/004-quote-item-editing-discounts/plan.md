# Implementation Plan: Quote Item Editing + Discounts

**Branch**: `004-quote-item-editing-discounts` | **Date**: 2025-11-06 | **Spec**: `specs/004-quote-item-editing-discounts/spec.md`
**Input**: Feature specification referenced above

---

## Summary
讓草稿報價單可直接在編輯頁調整明細（新增/刪除/修改），並為每行新增折扣金額欄位，系統自動換算折扣百分比、重算小計/稅額/總額，且在檢視與列印頁呈現折扣資訊。

---

## Technical Context
**Language/Version**: PHP 8.3  
**Primary Dependencies**: PDO (MySQL), 原生 PHP templates, 自訂 JS/CSS  
**Storage**: MySQL 8 (DB: `quotabase_lite`)  
**Testing**: 手動瀏覽器測試 + MySQL 查詢  
**Target Platform**: Docker 開發環境（app + db）  
**Project Type**: Server-rendered web app（單存 PHP 構造）  
**Constraints**:
- 只有草稿報價單可編輯
- DB schema 無 migration framework → 需提供手動 ALTER
- 金額以「分」為單位儲存，避免浮點誤差

---

## Constitution Check
- 提案維持現有架構（單 repo / 單應用），無新增專案或重度框架 → **通過**

---

## Project Structure

### Documentation
```
specs/004-quote-item-editing-discounts/
├── spec.md
├── plan.md
└── tasks.md
```

### Source Code (受此功能影響)
```
helpers/functions.php        # quote CRUD & calculations
quotes/edit.php              # 編輯頁 UI & 表單
quotes/view.php              # 檢視頁
quotes/print.php             # 列印頁
assets/style.css             # 新增/調整樣式
database/migrations/         # 新增 ALTER SQL (manual)
schema.sql                   # 更新完整 schema
README.md / docs             # 若需註記 DB 變更
```

**Structure Decision**: 維持既有目錄，不新增新模組；集中修改既有檔案。

---

## Implementation Plan

### Phase 0 – 現況分析
1. 評估 `helpers/functions.php` 相關函式 (`get_quote`, `get_quote_items`, `create_quote`, `update_quote_item`, `add_quote_item`, `recalculate_quote_total`)
2. 確認 `quote_items` 表目前欄位（缺少折扣欄位、description/unit 已有）

### Phase 1 – Schema 更新
1. 提供 SQL 腳本 `database/migrations/20251106_quote_items_discount.sql`
   ```sql
   ALTER TABLE quote_items
       ADD COLUMN discount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '行折扣金額（分）';
   ```
2. 更新 `schema.sql` 反映新欄位
3. 通知需手動執行 ALTER（README 或 PR 說明）

### Phase 2 – Backend 調整
1. `helpers/functions.php`
   - 新增計算函式：`calculate_line_gross`, `calculate_discount_percent`
   - 更新 `calculate_line_subtotal` → 接受折扣
   - `get_quote_items` 回傳折扣資料（含折後金額與折扣%）
   - `create_quote` / `add_quote_item` / `update_quote_item`：
     - 接受折扣輸入、驗證折扣 <= 行原勢
     - 計算折後小計/稅額/總額
     - 保存 `discount_cents`
   - `recalculate_quote_total` 使用折扣後資料重算
   - 新增協助函式 `process_quote_edit`（或等效）處理編輯頁表單
2. 建立表單產生資料 helper（必要時）以統一輸入驗證

### Phase 3 – 編輯頁 UI 重構
1. `quotes/edit.php`
   - 草稿狀態呈現可編輯表單（含產品選擇、描述、數量、單價、稅率、折扣）
   - 使用 JS 模板/按鈕支援新增/刪除行
   - 表單提交為 POST，呼叫後端處理全部明細（建議全部送上 → 後端刪舊建新或 diff-based）
   - 非草稿保持原只讀模式
2. `assets/style.css`
   - 編輯表格、折扣欄位、mobile 排版
3. JS
   - 即時計算折扣% 與行小計
   - 更新金額總計顯示

### Phase 4 – 檢視/列印顯示
1. `quotes/view.php`
   - 行表格顯示折扣金額、折扣%（有折扣時才顯示）
   - 統計區可顯示折扣總額（選擇性）
2. `quotes/print.php`
   - 同步顯示折扣資訊，保持排版

### Phase 5 – 測試 & 文件
1. 手動測試流程：
   - 新建草稿 → 編輯行（含折扣）→ 儲存 → 檢視 & 列印
   - 折扣 > 行金額 → 應出錯
   - 非草稿 → 仍不可編輯
2. 更新 README 或 docs 指示手動執行 ALTER

---

## Complexity Tracking
無違反憲章項目，系統複雜度未大幅提升，保持單專案架構。
