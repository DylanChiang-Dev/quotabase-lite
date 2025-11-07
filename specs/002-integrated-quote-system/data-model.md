# Data Model: Quotabase-Lite Integrated Quote Management System

**Created**: 2025-11-05
**Based on**: Feature specification and Constitution v2.0.0

## Entities Overview

系統包含 6 個核心實體，採用單租戶架構（ORG_ID=1），所有表預留 org_id 欄位支援未來多租戶升級。

## Entity Details

### 1. Organizations (組織)

**Purpose**: 支援未來多租戶升級的預留表，當前 MVP 使用 ORG_ID=1

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 組織 ID |
| name | VARCHAR(255) | NOT NULL | 組織名稱 |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | 建立時間 |

**Note**: MVP 階段將使用硬編碼的 ORG_ID=1，無需建立多租戶表結構。

---

### 2. Customers (客戶)

**Purpose**: 儲存企業客戶基本資訊和聯絡方式

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 客戶 ID |
| org_id | BIGINT UNSIGNED | NOT NULL, INDEX | 組織 ID（預留多租戶） |
| name | VARCHAR(255) | NOT NULL | 客戶名稱（必填） |
| tax_id | VARCHAR(50) | NULL | 稅務登記號 |
| email | VARCHAR(255) | NULL | 郵箱 |
| phone | VARCHAR(50) | NULL | 電話 |
| billing_address | TEXT | NULL | 賬單地址 |
| shipping_address | TEXT | NULL | 收貨地址 |
| note | TEXT | NULL | 備註 |
| active | TINYINT(1) | DEFAULT 1 | 軟刪除標記 |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | 建立時間 |
| updated_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | 更新時間 |

**Indexes**:
- `idx_customers_org_id` ON (org_id)
- `idx_customers_active` ON (active)

**State Transitions**:
- 活躍 → 停用（soft delete, active = 0）
- 停用 → 活躍（active = 1）

---

### 3. Catalog Items (目錄項 - 產品/服務統一表)

**Purpose**: 統一儲存產品和服務資訊，透過 type 欄位區分

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 專案 ID |
| org_id | BIGINT UNSIGNED | NOT NULL, INDEX | 組織 ID（預留多租戶） |
| type | ENUM('product', 'service') | NOT NULL | 型別：產品或服務 |
| sku | VARCHAR(100) | NOT NULL | SKU 編碼（同一 org_id 下唯一） |
| name | VARCHAR(255) | NOT NULL | 名稱（必填） |
| unit | VARCHAR(20) | DEFAULT 'pcs' | 單位 |
| currency | VARCHAR(3) | DEFAULT 'TWD' | 幣種（僅支援 TWD） |
| unit_price_cents | BIGINT UNSIGNED | NOT NULL | 單價（單位：分） |
| tax_rate | DECIMAL(5,2) | DEFAULT 0.00 | 稅率（%） |
| active | TINYINT(1) | DEFAULT 1 | 狀態（啟用/停用） |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | 建立時間 |
| updated_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | 更新時間 |

**Indexes**:
- `idx_catalog_org_type` ON (org_id, type)
- `idx_catalog_sku` ON (org_id, sku) UNIQUE
- `idx_catalog_active` ON (active)

**Validation Rules**:
- SKU 在同一 org_id 下必須唯一（UNIQUE 約束）
- type 只能是 'product' 或 'service'
- unit_price_cents 必須為正整數（>= 0）
- tax_rate 範圍：0.00 - 100.00

**State Transitions**:
- 啟用 → 停用（active = 0）
- 停用 → 啟用（active = 1）

---

### 4. Quotes (報價單主檔)

**Purpose**: 儲存報價單基本資訊

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 報價單 ID |
| org_id | BIGINT UNSIGNED | NOT NULL, INDEX | 組織 ID |
| number | VARCHAR(50) | NOT NULL, UNIQUE | 報價單編號（格式：字首-YYYY-000001） |
| customer_id | BIGINT UNSIGNED | NOT NULL, INDEX | 客戶 ID（外部索引鍵） |
| issue_date | DATE | NOT NULL | 發出日期（UTC） |
| valid_until | DATE | NULL | 有效期至（UTC） |
| currency | VARCHAR(3) | DEFAULT 'TWD' | 幣種（僅支援 TWD） |
| status | ENUM('draft', 'sent', 'accepted', 'rejected', 'expired') | DEFAULT 'draft' | 狀態 |
| title | VARCHAR(255) | NULL | 報價單標題 |
| notes | TEXT | NULL | 備註 |
| subtotal_cents | BIGINT UNSIGNED | NOT NULL | 小計（分） |
| tax_amount_cents | BIGINT UNSIGNED | NOT NULL | 稅額（分） |
| total_cents | BIGINT UNSIGNED | NOT NULL | 總計（分） |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | 建立時間 |
| updated_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | 更新時間 |

**Indexes**:
- `idx_quotes_org_customer_date` ON (org_id, customer_id, issue_date)
- `idx_quotes_number` ON (number) UNIQUE
- `idx_quotes_status` ON (status)
- `idx_quotes_issue_date` ON (issue_date)

**Foreign Keys**:
- `FOREIGN KEY (customer_id) REFERENCES customers(id)` (需要時啟用)

**State Transitions**:
- draft → sent
- sent → accepted/rejected/expired
- 其他狀態不允許直接變更

**Validation Rules**:
- 金額欄位（subtotal_cents、tax_amount_cents、total_cents）必須 >= 0
- 金額計算公式：total_cents = subtotal_cents + tax_amount_cents
- valid_until 必須 >= issue_date
- 編號格式：字首-YYYY-000001（如 Q-2025-000001）

---

### 5. Quote Items (報價專案明細)

**Purpose**: 儲存報價單明細行專案

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 專案 ID |
| quote_id | BIGINT UNSIGNED | NOT NULL, INDEX | 報價單 ID（外部索引鍵） |
| catalog_item_id | BIGINT UNSIGNED | NULL | 關聯的目錄項 ID（可為空，支援手工輸入描述） |
| description | VARCHAR(500) | NOT NULL | 專案描述 |
| qty | DECIMAL(18,4) | NOT NULL | 數量（精確到 0.0001） |
| unit | VARCHAR(20) | NULL | 單位 |
| unit_price_cents | BIGINT UNSIGNED | NOT NULL | 單價（分） |
| tax_rate | DECIMAL(5,2) | NOT NULL | 稅率（%） |
| line_subtotal_cents | BIGINT UNSIGNED | NOT NULL | 行小計（分） |
| line_tax_cents | BIGINT UNSIGNED | NOT NULL | 行稅額（分） |
| line_total_cents | BIGINT UNSIGNED | NOT NULL | 行總計（分） |
| line_order | INT | NOT NULL | 行順序（排序用） |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | 建立時間 |

**Indexes**:
- `idx_quote_items_quote_id` ON (quote_id)
- `idx_quote_items_order` ON (quote_id, line_order)

**Foreign Keys**:
- `FOREIGN KEY (quote_id) REFERENCES quotes(id)` (需要時啟用)
- `FOREIGN KEY (catalog_item_id) REFERENCES catalog_items(id)` (需要時啟用)

**Calculation Formula**:
- 行小計 = qty × unit_price_cents
- 行稅額 = 行小計 × tax_rate
- 行總計 = 行小計 + 行稅額

**Validation Rules**:
- qty 必須 > 0
- unit_price_cents 必須 >= 0
- tax_rate 範圍：0.00 - 100.00
- line_order 用於排序，必須在同一報價單內唯一

---

### 6. Quote Sequences (年度編號序列表)

**Purpose**: 管理年度報價單編號計數器

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 序號 ID |
| org_id | BIGINT UNSIGNED | NOT NULL, UNIQUE | 組織 ID（每個組織一條記錄） |
| prefix | VARCHAR(10) | DEFAULT 'Q' | 編號字首 |
| year | INT | NOT NULL | 年度 |
| current_number | INT | DEFAULT 0 | 當前編號 |
| updated_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | 更新時間 |

**Indexes**:
- `idx_quote_sequences_org_year` ON (org_id, year) UNIQUE

**Storage Procedure**: next_quote_number(org_id, OUT out_number)

```sql
DELIMITER //
CREATE PROCEDURE next_quote_number(
    IN p_org_id BIGINT,
    OUT p_out_number VARCHAR(50)
)
BEGIN
    DECLARE v_year INT;
    DECLARE v_prefix VARCHAR(10);
    DECLARE v_current INT;
    DECLARE v_next INT;

    -- 獲取當前年份
    SET v_year = YEAR(NOW());
    SET v_prefix = (SELECT prefix FROM quote_sequences WHERE org_id = p_org_id);

    -- 鎖定行防止併發
    SELECT current_number INTO v_current
    FROM quote_sequences
    WHERE org_id = p_org_id AND year = v_year
    FOR UPDATE;

    -- 如果不存在則建立新記錄
    IF v_current IS NULL THEN
        INSERT INTO quote_sequences (org_id, year, prefix, current_number)
        VALUES (p_org_id, v_year, v_prefix, 0);
        SET v_current = 0;
    END IF;

    -- 遞增編號
    SET v_next = v_current + 1;

    -- 更新計數器
    UPDATE quote_sequences
    SET current_number = v_next
    WHERE org_id = p_org_id AND year = v_year;

    -- 構造返回編號：字首-YYYY-000001
    SET p_out_number = CONCAT(v_prefix, '-', v_year, '-', LPAD(v_next, 6, '0'));
END//
DELIMITER ;
```

---

### 7. Settings (系統設定)

**Purpose**: 儲存系統全域性配置

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 設定 ID |
| org_id | BIGINT UNSIGNED | NOT NULL, UNIQUE | 組織 ID（每個組織一條記錄） |
| company_name | VARCHAR(255) | NULL | 公司名稱 |
| company_address | TEXT | NULL | 公司地址 |
| company_contact | VARCHAR(255) | NULL | 公司聯絡方式 |
| quote_prefix | VARCHAR(10) | DEFAULT 'Q' | 報價單編號字首 |
| default_tax_rate | DECIMAL(5,2) | DEFAULT 0.00 | 預設稅率（%） |
| print_terms | TEXT | NULL | 列印條款文字 |
| timezone | VARCHAR(50) | DEFAULT 'Asia/Taipei' | 時區 |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | 建立時間 |
| updated_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | 更新時間 |

**Indexes**:
- `idx_settings_org_id` ON (org_id) UNIQUE

---

## Relationships

```
Organizations (1) ── (N) Customers
Organizations (1) ── (N) Catalog Items
Organizations (1) ── (N) Quotes
Organizations (1) ── (1) Quote Sequences
Organizations (1) ── (1) Settings

Customers (1) ── (N) Quotes
Quotes (1) ── (N) Quote Items
Catalog Items (1) ── (N) Quote Items
```

---

## Database Index Strategy

### Primary Indexes (基於查詢模式)

1. **Customers**
   - `idx_customers_org_id` - 支援按組織查詢
   - `idx_customers_active` - 支援按狀態篩選

2. **Catalog Items**
   - `idx_catalog_org_type` - 支援按組織+型別查詢（產品列表、服務列表）
   - `idx_catalog_sku` (UNIQUE) - SKU 唯一性約束
   - `idx_catalog_active` - 支援按狀態篩選

3. **Quotes**
   - `idx_quotes_org_customer_date` - 支援報價列表按客戶、日期篩選
   - `idx_quotes_number` (UNIQUE) - 編號唯一性
   - `idx_quotes_status` - 支援按狀態篩選
   - `idx_quotes_issue_date` - 支援按日期篩選和排序

4. **Quote Items**
   - `idx_quote_items_quote_id` - 查詢報價明細
   - `idx_quote_items_order` - 支援按順序排序

5. **Quote Sequences**
   - `idx_quote_sequences_org_year` (UNIQUE) - 年度編號管理

---

## Data Types Justification

### Precision Choices

- **BIGINT UNSIGNED for monetary amounts**: 避免浮點數精度問題，支援最大約 9 萬億元（足夠中小企業使用）
- **DECIMAL(18,4) for quantities**: 支援精確到 0.0001 的數量（如 0.1234 個產品）
- **DECIMAL(5,2) for tax rates**: 支援 0.00% 到 999.99% 的稅率範圍
- **TINYINT(1) for boolean flags**: 節省儲存空間，語義清晰

### Timezone Handling

- **Database**: 所有時間戳儲存 UTC 時間
- **Application**: 介面顯示使用 Asia/Taipei 時區
- **Date Fields**: issue_date、valid_until 使用 DATE 型別（不帶時區）

---

## Constraints Summary

### NOT NULL Constraints
- 所有核心業務欄位（name、sku、price 等）
- 外部索引鍵關聯欄位
- 計算結果欄位（subtotal、tax、total）

### UNIQUE Constraints
- CatalogItems: (org_id, sku)
- Quotes: number
- QuoteSequences: (org_id, year)
- Settings: org_id

### CHECK Constraints (建議)
- unit_price_cents >= 0
- tax_rate BETWEEN 0.00 AND 100.00
- qty > 0
- total_cents = subtotal_cents + tax_amount_cents

---

## Migration Notes

### Initial Schema Version
- 所有表需要新增 `org_id` 欄位（預留多租戶）
- MVP 階段所有記錄使用 ORG_ID=1
- 未來升級多租戶時，此欄位將啟用使用

### Stored Procedures
1. `next_quote_number(org_id, OUT out_number)` - 年度編號生成
2. 需要在 MySQL 中建立這些儲存過程

### Data Seeding
1. 建立預設組織記錄（ORG_ID=1）
2. 建立預設設定記錄
3. 初始化 QuoteSequence 記錄

---

## Security Considerations

### SQL Injection Prevention
- 所有使用者輸入欄位必須使用 PDO 預處理
- 嚴禁字串拼接 SQL
- 數字欄位嚴格型別轉換

### XSS Prevention
- 所有動態輸出使用 h() 函式轉義
- 特別處理客戶名稱、地址等文字欄位

### CSRF Protection
- 所有 POST 表單新增 CSRF Token
- 驗證 Token 有效性

### Data Privacy
- 錯誤日誌不得包含客戶姓名、電話、地址、郵箱、稅號
- 生產環境關閉 display_errors
