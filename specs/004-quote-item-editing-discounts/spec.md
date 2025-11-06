# Feature Specification: Quote Item Editing + Discounts

**Feature Branch**: `004-quote-item-editing-discounts`  
**Created**: 2025-11-06  
**Status**: Draft  
**Input**: User request – “需要編輯報價單明細，並在每個產品/服務明細後面加入折扣功能（輸入折扣金額，自動算百分比，並反映在報價單上）”

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 – 草稿報價單可直接編輯明細 (Priority: P1)
作為業務，我希望在「編輯報價單」頁面就能調整每一個產品/服務行（更換項目、更新數量/單價/稅率），讓草稿不必刪除重建。

**Independent Test**: 以草稿狀態的報價單登入並進入 `quotes/edit.php?id=X`，修改現有明細與新增/刪除行，儲存後總金額正確更新。

**Acceptance Scenarios**
1. **Given** 報價單為草稿，**When** 我修改某一行的數量並儲存，**Then** 該行與總計重新計算且資料庫同步更新。
2. **Given** 報價單為草稿，**When** 我新增一行並選擇目錄項，**Then** 新行被持久化並可在檢視頁看到。
3. **Given** 報價單為草稿，**When** 我刪除任一行，**Then** 行被移除且總金額更新。

### User Story 2 – 行內折扣金額 + 自動百分比 (Priority: P1)
作為業務，我希望在每一個明細行輸入折扣金額，系統自動換算折扣百分比並反映在小計/總計。

**Independent Test**: 於編輯頁修改某行折扣金額、保存後在檢視/列印頁確認折扣與總額顯示正確。

**Acceptance Scenarios**
1. **Given** 行原價 10,000，**When** 我輸入折扣 1,000，**Then** 小計顯示 9,000 且折扣% 顯示 10%。
2. **Given** 行已有折扣，**When** 我清空折扣欄位，**Then** 折扣恢復為 0 並重新計算總額。
3. **Given** 輸入折扣金額 > 行小計，**When** 保存，**Then** 系統提示錯誤且拒絕保存。

### User Story 3 – 折扣資訊顯示於檢視/列印 (Priority: P2)
作為客戶，我希望在報價單檢視及列印版上看到每個行項的折扣金額與最終價格，保持透明。

**Independent Test**: 建立/編輯含折扣行的報價單後，檢查 `quotes/view.php` 與 `quotes/print.php` 顯示欄位。

**Acceptance Scenarios**
1. **Given** 行有折扣，**When** 打開報價單檢視頁，**Then** 表格中出現折扣列或折扣欄位。
2. **Given** 行無折扣，**When** 打印頁，**Then** 折扣欄留白或顯示 0，不破壞版面。

### Edge Cases
- 折扣輸入負數或非數字 → 系統應立即提示並阻止保存。
- 折扣金額超過行小計 → 阻止提交並顯示「折扣不可超過行金額」。
- 已發送/已接受報價單 → 維持只讀，不允許編輯明細。

---

## Requirements *(mandatory)*

### Functional Requirements
- **FR-001**：系統必須允許在 `quotes/edit.php` 中增/刪/改明細行（草稿狀態）。
- **FR-002**：每個明細行需新增「折扣金額」欄位，自動計算折扣百分比並更新小計。
- **FR-003**：總金額計算需考慮折扣後的小計與稅額，確保 `quotes` 表中的 subtotal/tax/total 正確。
- **FR-004**：檢視 (`quotes/view.php`) 與列印 (`quotes/print.php`) 必須顯示折扣資訊。
- **FR-005**：僅草稿狀態可編輯明細；其他狀態保持既有限制。

### Key Entities / fields
- `quote_items`
  - 需新增欄位：`discount_cents`（行折扣金額，以分儲存）
- `quotes`
  - `subtotal_cents`、`tax_cents`、`total_cents` 計算方式更新為扣除折扣後再計稅。

### Success Criteria
- **SC-001**：草稿報價單可以在同一頁面完成明細調整且 100% 成功保存。
- **SC-002**：折扣金額與百分比在檢視/列印頁與資料庫呈現一致（無負值）。
- **SC-003**：折扣邏輯導致的計算誤差 < 1 分（cent）。
