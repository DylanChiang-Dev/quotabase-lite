# Data Model: Quotabase-Lite Integrated Quote Management System

**Created**: 2025-11-05
**Based on**: Feature specification and Constitution v2.0.0

## Entities Overview

系统包含 6 个核心实体，采用单租户架构（ORG_ID=1），所有表预留 org_id 字段支持未来多租户升级。

## Entity Details

### 1. Organizations (组织)

**Purpose**: 支持未来多租户升级的预留表，当前 MVP 使用 ORG_ID=1

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 组织 ID |
| name | VARCHAR(255) | NOT NULL | 组织名称 |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | 创建时间 |

**Note**: MVP 阶段将使用硬编码的 ORG_ID=1，无需创建多租户表结构。

---

### 2. Customers (客户)

**Purpose**: 存储企业客户基本信息和联系方式

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 客户 ID |
| org_id | BIGINT UNSIGNED | NOT NULL, INDEX | 组织 ID（预留多租户） |
| name | VARCHAR(255) | NOT NULL | 客户名称（必填） |
| tax_id | VARCHAR(50) | NULL | 税务登记号 |
| email | VARCHAR(255) | NULL | 邮箱 |
| phone | VARCHAR(50) | NULL | 电话 |
| billing_address | TEXT | NULL | 账单地址 |
| shipping_address | TEXT | NULL | 收货地址 |
| note | TEXT | NULL | 备注 |
| active | TINYINT(1) | DEFAULT 1 | 软删除标记 |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | 更新时间 |

**Indexes**:
- `idx_customers_org_id` ON (org_id)
- `idx_customers_active` ON (active)

**State Transitions**:
- 活跃 → 禁用（soft delete, active = 0）
- 禁用 → 活跃（active = 1）

---

### 3. Catalog Items (目录项 - 产品/服务统一表)

**Purpose**: 统一存储产品和服务信息，通过 type 字段区分

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 项目 ID |
| org_id | BIGINT UNSIGNED | NOT NULL, INDEX | 组织 ID（预留多租户） |
| type | ENUM('product', 'service') | NOT NULL | 类型：产品或服务 |
| sku | VARCHAR(100) | NOT NULL | SKU 编码（同一 org_id 下唯一） |
| name | VARCHAR(255) | NOT NULL | 名称（必填） |
| unit | VARCHAR(20) | DEFAULT 'pcs' | 单位 |
| currency | VARCHAR(3) | DEFAULT 'TWD' | 币种（仅支持 TWD） |
| unit_price_cents | BIGINT UNSIGNED | NOT NULL | 单价（单位：分） |
| tax_rate | DECIMAL(5,2) | DEFAULT 0.00 | 税率（%） |
| active | TINYINT(1) | DEFAULT 1 | 状态（启用/禁用） |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | 更新时间 |

**Indexes**:
- `idx_catalog_org_type` ON (org_id, type)
- `idx_catalog_sku` ON (org_id, sku) UNIQUE
- `idx_catalog_active` ON (active)

**Validation Rules**:
- SKU 在同一 org_id 下必须唯一（UNIQUE 约束）
- type 只能是 'product' 或 'service'
- unit_price_cents 必须为正整数（>= 0）
- tax_rate 范围：0.00 - 100.00

**State Transitions**:
- 启用 → 禁用（active = 0）
- 禁用 → 启用（active = 1）

---

### 4. Quotes (报价单主档)

**Purpose**: 存储报价单基本信息

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 报价单 ID |
| org_id | BIGINT UNSIGNED | NOT NULL, INDEX | 组织 ID |
| number | VARCHAR(50) | NOT NULL, UNIQUE | 报价单编号（格式：前缀-YYYY-000001） |
| customer_id | BIGINT UNSIGNED | NOT NULL, INDEX | 客户 ID（外键） |
| issue_date | DATE | NOT NULL | 发出日期（UTC） |
| valid_until | DATE | NULL | 有效期至（UTC） |
| currency | VARCHAR(3) | DEFAULT 'TWD' | 币种（仅支持 TWD） |
| status | ENUM('draft', 'sent', 'accepted', 'rejected', 'expired') | DEFAULT 'draft' | 状态 |
| title | VARCHAR(255) | NULL | 报价单标题 |
| notes | TEXT | NULL | 备注 |
| subtotal_cents | BIGINT UNSIGNED | NOT NULL | 小计（分） |
| tax_amount_cents | BIGINT UNSIGNED | NOT NULL | 税额（分） |
| total_cents | BIGINT UNSIGNED | NOT NULL | 总计（分） |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | 更新时间 |

**Indexes**:
- `idx_quotes_org_customer_date` ON (org_id, customer_id, issue_date)
- `idx_quotes_number` ON (number) UNIQUE
- `idx_quotes_status` ON (status)
- `idx_quotes_issue_date` ON (issue_date)

**Foreign Keys**:
- `FOREIGN KEY (customer_id) REFERENCES customers(id)` (需要时启用)

**State Transitions**:
- draft → sent
- sent → accepted/rejected/expired
- 其他状态不允许直接变更

**Validation Rules**:
- 金额字段（subtotal_cents、tax_amount_cents、total_cents）必须 >= 0
- 金额计算公式：total_cents = subtotal_cents + tax_amount_cents
- valid_until 必须 >= issue_date
- 编号格式：前缀-YYYY-000001（如 Q-2025-000001）

---

### 5. Quote Items (报价项目明细)

**Purpose**: 存储报价单明细行项目

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 项目 ID |
| quote_id | BIGINT UNSIGNED | NOT NULL, INDEX | 报价单 ID（外键） |
| catalog_item_id | BIGINT UNSIGNED | NULL | 关联的目录项 ID（可为空，支持手工输入描述） |
| description | VARCHAR(500) | NOT NULL | 项目描述 |
| qty | DECIMAL(18,4) | NOT NULL | 数量（精确到 0.0001） |
| unit | VARCHAR(20) | NULL | 单位 |
| unit_price_cents | BIGINT UNSIGNED | NOT NULL | 单价（分） |
| tax_rate | DECIMAL(5,2) | NOT NULL | 税率（%） |
| line_subtotal_cents | BIGINT UNSIGNED | NOT NULL | 行小计（分） |
| line_tax_cents | BIGINT UNSIGNED | NOT NULL | 行税额（分） |
| line_total_cents | BIGINT UNSIGNED | NOT NULL | 行总计（分） |
| line_order | INT | NOT NULL | 行顺序（排序用） |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | 创建时间 |

**Indexes**:
- `idx_quote_items_quote_id` ON (quote_id)
- `idx_quote_items_order` ON (quote_id, line_order)

**Foreign Keys**:
- `FOREIGN KEY (quote_id) REFERENCES quotes(id)` (需要时启用)
- `FOREIGN KEY (catalog_item_id) REFERENCES catalog_items(id)` (需要时启用)

**Calculation Formula**:
- 行小计 = qty × unit_price_cents
- 行税额 = 行小计 × tax_rate
- 行总计 = 行小计 + 行税额

**Validation Rules**:
- qty 必须 > 0
- unit_price_cents 必须 >= 0
- tax_rate 范围：0.00 - 100.00
- line_order 用于排序，必须在同一报价单内唯一

---

### 6. Quote Sequences (年度编号序列表)

**Purpose**: 管理年度报价单编号计数器

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 序号 ID |
| org_id | BIGINT UNSIGNED | NOT NULL, UNIQUE | 组织 ID（每个组织一条记录） |
| prefix | VARCHAR(10) | DEFAULT 'Q' | 编号前缀 |
| year | INT | NOT NULL | 年度 |
| current_number | INT | DEFAULT 0 | 当前编号 |
| updated_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | 更新时间 |

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

    -- 获取当前年份
    SET v_year = YEAR(NOW());
    SET v_prefix = (SELECT prefix FROM quote_sequences WHERE org_id = p_org_id);

    -- 锁定行防止并发
    SELECT current_number INTO v_current
    FROM quote_sequences
    WHERE org_id = p_org_id AND year = v_year
    FOR UPDATE;

    -- 如果不存在则创建新记录
    IF v_current IS NULL THEN
        INSERT INTO quote_sequences (org_id, year, prefix, current_number)
        VALUES (p_org_id, v_year, v_prefix, 0);
        SET v_current = 0;
    END IF;

    -- 递增编号
    SET v_next = v_current + 1;

    -- 更新计数器
    UPDATE quote_sequences
    SET current_number = v_next
    WHERE org_id = p_org_id AND year = v_year;

    -- 构造返回编号：前缀-YYYY-000001
    SET p_out_number = CONCAT(v_prefix, '-', v_year, '-', LPAD(v_next, 6, '0'));
END//
DELIMITER ;
```

---

### 7. Settings (系统设置)

**Purpose**: 存储系统全局配置

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | 设置 ID |
| org_id | BIGINT UNSIGNED | NOT NULL, UNIQUE | 组织 ID（每个组织一条记录） |
| company_name | VARCHAR(255) | NULL | 公司名称 |
| company_address | TEXT | NULL | 公司地址 |
| company_contact | VARCHAR(255) | NULL | 公司联系方式 |
| quote_prefix | VARCHAR(10) | DEFAULT 'Q' | 报价单编号前缀 |
| default_tax_rate | DECIMAL(5,2) | DEFAULT 0.00 | 默认税率（%） |
| print_terms | TEXT | NULL | 打印条款文字 |
| timezone | VARCHAR(50) | DEFAULT 'Asia/Taipei' | 时区 |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | 更新时间 |

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

### Primary Indexes (基于查询模式)

1. **Customers**
   - `idx_customers_org_id` - 支持按组织查询
   - `idx_customers_active` - 支持按状态筛选

2. **Catalog Items**
   - `idx_catalog_org_type` - 支持按组织+类型查询（产品列表、服务列表）
   - `idx_catalog_sku` (UNIQUE) - SKU 唯一性约束
   - `idx_catalog_active` - 支持按状态筛选

3. **Quotes**
   - `idx_quotes_org_customer_date` - 支持报价列表按客户、日期筛选
   - `idx_quotes_number` (UNIQUE) - 编号唯一性
   - `idx_quotes_status` - 支持按状态筛选
   - `idx_quotes_issue_date` - 支持按日期筛选和排序

4. **Quote Items**
   - `idx_quote_items_quote_id` - 查询报价明细
   - `idx_quote_items_order` - 支持按顺序排序

5. **Quote Sequences**
   - `idx_quote_sequences_org_year` (UNIQUE) - 年度编号管理

---

## Data Types Justification

### Precision Choices

- **BIGINT UNSIGNED for monetary amounts**: 避免浮点数精度问题，支持最大约 9 万亿元（足够中小企业使用）
- **DECIMAL(18,4) for quantities**: 支持精确到 0.0001 的数量（如 0.1234 个产品）
- **DECIMAL(5,2) for tax rates**: 支持 0.00% 到 999.99% 的税率范围
- **TINYINT(1) for boolean flags**: 节省存储空间，语义清晰

### Timezone Handling

- **Database**: 所有时间戳存储 UTC 时间
- **Application**: 界面显示使用 Asia/Taipei 时区
- **Date Fields**: issue_date、valid_until 使用 DATE 类型（不带时区）

---

## Constraints Summary

### NOT NULL Constraints
- 所有核心业务字段（name、sku、price 等）
- 外键关联字段
- 计算结果字段（subtotal、tax、total）

### UNIQUE Constraints
- CatalogItems: (org_id, sku)
- Quotes: number
- QuoteSequences: (org_id, year)
- Settings: org_id

### CHECK Constraints (建议)
- unit_price_cents >= 0
- tax_rate BETWEEN 0.00 AND 100.00
- qty > 0
- total_cents = subtotal_cents + tax_amount_cents

---

## Migration Notes

### Initial Schema Version
- 所有表需要添加 `org_id` 字段（预留多租户）
- MVP 阶段所有记录使用 ORG_ID=1
- 未来升级多租户时，此字段将激活使用

### Stored Procedures
1. `next_quote_number(org_id, OUT out_number)` - 年度编号生成
2. 需要在 MySQL 中创建这些存储过程

### Data Seeding
1. 创建默认组织记录（ORG_ID=1）
2. 创建默认设置记录
3. 初始化 QuoteSequence 记录

---

## Security Considerations

### SQL Injection Prevention
- 所有用户输入字段必须使用 PDO 预处理
- 严禁字符串拼接 SQL
- 数字字段严格类型转换

### XSS Prevention
- 所有动态输出使用 h() 函数转义
- 特别处理客户名称、地址等文本字段

### CSRF Protection
- 所有 POST 表单添加 CSRF Token
- 验证 Token 有效性

### Data Privacy
- 错误日志不得包含客户姓名、电话、地址、邮箱、税号
- 生产环境关闭 display_errors
