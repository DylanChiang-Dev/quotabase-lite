-- ========================================
-- Quotabase-Lite Database Schema
-- 資料庫結構檔案
-- 版本: v2.0.0
-- 描述: 整合報價管理系統 - 7個核心實體
-- ========================================

SET FOREIGN_KEY_CHECKS = 0;
DROP DATABASE IF EXISTS quotabase_lite;
CREATE DATABASE quotabase_lite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quotabase_lite;

-- ========================================
-- 1. Organizations (組織)
-- 支援未來多租戶升級的預留表
-- ========================================

CREATE TABLE organizations (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL COMMENT '組織名稱',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
    INDEX idx_org_name (name)
) ENGINE=InnoDB COMMENT='組織表（預留多租戶）';

-- ========================================
-- 2. Customers (客戶)
-- 儲存企業客戶基本資訊和聯絡方式
-- ========================================

CREATE TABLE customers (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID',
    name VARCHAR(255) NOT NULL COMMENT '客戶名稱（必填）',
    tax_id VARCHAR(50) NULL COMMENT '稅務登記號',
    email VARCHAR(255) NULL COMMENT '郵箱',
    phone VARCHAR(50) NULL COMMENT '電話',
    billing_address TEXT NULL COMMENT '賬單地址',
    shipping_address TEXT NULL COMMENT '收貨地址',
    note TEXT NULL COMMENT '備註',
    active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '軟刪除標記（1=啟用，0=停用）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
    INDEX idx_customers_org_id (org_id),
    INDEX idx_customers_active (active),
    INDEX idx_customers_name (name)
) ENGINE=InnoDB COMMENT='客戶表';

-- ========================================
-- 3. Catalog Items (目錄項 - 產品/服務統一表)
-- 統一儲存產品和服務資訊，透過 type 欄位區分
-- ========================================

CREATE TABLE catalog_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID',
    type ENUM('product', 'service') NOT NULL COMMENT '型別：產品或服務',
    sku VARCHAR(100) NOT NULL COMMENT 'SKU編碼（同一org_id下唯一）',
    name VARCHAR(255) NOT NULL COMMENT '名稱（必填）',
    unit VARCHAR(20) NOT NULL DEFAULT 'pcs' COMMENT '單位',
    currency VARCHAR(3) NOT NULL DEFAULT 'TWD' COMMENT '幣種（僅支援TWD）',
    unit_price_cents BIGINT UNSIGNED NOT NULL COMMENT '單價（單位：分）',
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '稅率（%）',
    category_id BIGINT UNSIGNED NULL COMMENT '分類ID（三級分類）',
    active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '狀態（1=啟用，0=停用）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
    INDEX idx_catalog_org_type (org_id, type),
    INDEX idx_catalog_sku (org_id, sku),
    INDEX idx_catalog_active (active),
    INDEX idx_catalog_category (category_id),
    UNIQUE KEY uq_catalog_sku (org_id, sku)
) ENGINE=InnoDB COMMENT='目錄項表（產品/服務統一）';

-- ========================================
-- Catalog Categories (產品/服務分類)
-- 支援三級分類結構
-- ========================================

CREATE TABLE catalog_categories (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID',
    type ENUM('product', 'service') NOT NULL DEFAULT 'product' COMMENT '分類型別',
    parent_id BIGINT UNSIGNED NULL COMMENT '父級分類ID（NULL為頂級）',
    level TINYINT UNSIGNED NOT NULL COMMENT '分類層級（1-3）',
    name VARCHAR(100) NOT NULL COMMENT '分類名稱',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '排序值',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
    UNIQUE KEY uq_category_name (org_id, type, parent_id, name),
    INDEX idx_category_org_type (org_id, type),
    INDEX idx_category_parent (parent_id),
    INDEX idx_category_level (org_id, level),
    CONSTRAINT fk_category_parent FOREIGN KEY (parent_id) REFERENCES catalog_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='產品/服務分類表';

-- ========================================
-- 4. Quotes (報價單主檔)
-- 儲存報價單基本資訊
-- ========================================

CREATE TABLE quotes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID',
    quote_number VARCHAR(50) NOT NULL COMMENT '報價單編號（格式：字首-YYYY-000001）',
    customer_id BIGINT UNSIGNED NOT NULL COMMENT '客戶ID（外部索引鍵）',
    issue_date DATE NOT NULL COMMENT '發出日期（UTC）',
    valid_until DATE NULL COMMENT '有效期至（UTC）',
    currency VARCHAR(3) NOT NULL DEFAULT 'TWD' COMMENT '幣種（僅支援TWD）',
    status ENUM('draft', 'sent', 'accepted', 'rejected', 'expired') NOT NULL DEFAULT 'draft' COMMENT '狀態',
    title VARCHAR(255) NULL COMMENT '報價單標題',
    notes TEXT NULL COMMENT '備註',
    subtotal_cents BIGINT UNSIGNED NOT NULL COMMENT '小計（分）',
    tax_cents BIGINT UNSIGNED NOT NULL COMMENT '稅額（分）',
    total_cents BIGINT UNSIGNED NOT NULL COMMENT '總計（分）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
    INDEX idx_quotes_org_customer_date (org_id, customer_id, issue_date),
    INDEX idx_quotes_number (quote_number),
    INDEX idx_quotes_status (status),
    INDEX idx_quotes_issue_date (issue_date),
    UNIQUE KEY uq_quotes_number (quote_number)
) ENGINE=InnoDB COMMENT='報價單主檔表';

-- ========================================
-- 5. Quote Items (報價專案明細)
-- 儲存報價單明細行專案
-- ========================================

CREATE TABLE quote_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    quote_id BIGINT UNSIGNED NOT NULL COMMENT '報價單ID（外部索引鍵）',
    catalog_item_id BIGINT UNSIGNED NULL COMMENT '關聯的目錄項ID（可為空）',
    description VARCHAR(500) NOT NULL COMMENT '專案描述',
    qty DECIMAL(18,4) NOT NULL COMMENT '數量（精確到0.0001）',
    unit VARCHAR(20) NULL COMMENT '單位',
    unit_price_cents BIGINT UNSIGNED NOT NULL COMMENT '單價（分）',
    discount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '行折扣金額（分）',
    tax_rate DECIMAL(5,2) NOT NULL COMMENT '稅率（%）',
    line_subtotal_cents BIGINT UNSIGNED NOT NULL COMMENT '行小計（分）',
    line_tax_cents BIGINT UNSIGNED NOT NULL COMMENT '行稅額（分）',
    line_total_cents BIGINT UNSIGNED NOT NULL COMMENT '行總計（分）',
    line_order INT NOT NULL COMMENT '行順序（排序用）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
    INDEX idx_quote_items_quote_id (quote_id),
    INDEX idx_quote_items_order (quote_id, line_order),
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='報價專案明細表';

-- ========================================
-- 6. Quote Sequences (年度編號序列表)
-- 管理年度報價單編號計數器
-- ========================================

CREATE TABLE quote_sequences (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID（每個組織一條記錄）',
    prefix VARCHAR(10) NOT NULL DEFAULT 'Q' COMMENT '編號字首',
    year INT NOT NULL COMMENT '年度',
    current_number INT NOT NULL DEFAULT 0 COMMENT '當前編號',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
    UNIQUE KEY uq_quote_sequences_org_year (org_id, year)
) ENGINE=InnoDB COMMENT='年度編號序列表';

-- ========================================
-- 7. Settings (系統設定)
-- 儲存系統全域性配置
-- ========================================

CREATE TABLE settings (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID（每個組織一條記錄）',
    company_name VARCHAR(255) NULL COMMENT '公司名稱',
    company_address TEXT NULL COMMENT '公司地址',
    company_contact VARCHAR(255) NULL COMMENT '公司聯絡方式',
    company_tax_id VARCHAR(50) NULL COMMENT '公司統一編號',
    quote_prefix VARCHAR(10) NOT NULL DEFAULT 'Q' COMMENT '報價單編號字首',
    default_tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '預設稅率（%）',
    print_terms TEXT NULL COMMENT '列印條款文字',
    timezone VARCHAR(50) NOT NULL DEFAULT 'Asia/Taipei' COMMENT '時區',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
    UNIQUE KEY uq_settings_org_id (org_id)
) ENGINE=InnoDB COMMENT='系統設定表';

-- ========================================
-- 8. Users (使用者)
-- 儲存系統使用者及登入資訊
-- ========================================

CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '組織ID',
    username VARCHAR(100) NOT NULL COMMENT '登入帳號（唯一）',
    password_hash VARCHAR(255) NOT NULL COMMENT '密碼雜湊',
    email VARCHAR(255) NULL COMMENT '電子郵件',
    role ENUM('admin', 'staff') NOT NULL DEFAULT 'admin' COMMENT '角色',
    status ENUM('active', 'suspended') NOT NULL DEFAULT 'active' COMMENT '狀態',
    last_login_at DATETIME NULL COMMENT '最後登入時間',
    last_login_ip VARCHAR(45) NULL COMMENT '最後登入IP',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    INDEX idx_users_org (org_id),
    INDEX idx_users_status (status),
    INDEX idx_users_role (role)
) ENGINE=InnoDB COMMENT='系統使用者';

-- ========================================
-- 外部索引鍵約束 (Foreign Key Constraints)
-- ========================================

ALTER TABLE quotes
    ADD FOREIGN KEY (customer_id) REFERENCES customers(id);

-- ========================================
-- 儲存過程 (Stored Procedures)
-- ========================================

-- 年度編號生成儲存過程
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
    SET v_prefix = (
        SELECT COALESCE(s.quote_prefix, 'Q')
        FROM settings s
        WHERE s.org_id = p_org_id
        LIMIT 1
    );

    IF v_prefix IS NULL OR v_prefix = '' THEN
        SET v_prefix = 'Q';
    END IF;

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
    SET current_number = v_next,
        prefix = v_prefix
    WHERE org_id = p_org_id AND year = v_year;

    -- 構造返回編號：字首-YYYY-000001
    SET p_out_number = CONCAT(v_prefix, '-', v_year, '-', LPAD(v_next, 6, '0'));
END//

DELIMITER ;

-- ========================================
-- 初始資料 (Initial Data)
-- ========================================

-- 插入預設組織
INSERT INTO organizations (id, name) VALUES (1, '預設組織');

-- 插入預設設定
INSERT INTO settings (
    org_id, company_name, company_address, company_contact, company_tax_id,
    quote_prefix, default_tax_rate, print_terms, timezone
) VALUES (
    1,
    '您的公司名稱',
    '公司地址',
    '聯絡電話',
    '',
    'Q',
    5.00,
    '',
    'Asia/Taipei'
);

-- 插入年度編號序列初始記錄
INSERT INTO quote_sequences (org_id, prefix, year, current_number) VALUES
(1, 'Q', YEAR(NOW()), 0);

-- 插入預設管理員帳號（預設密碼：admin123）
INSERT INTO users (
    org_id, username, password_hash, email, role, status
) VALUES (
    1,
    'admin',
    '$2y$10$O6KFFJvolG/GpNZgbf28OeCL4ZzOogzPOIu.TQ3BM4tiTnGooy2KW',
    NULL,
    'admin',
    'active'
);

SET FOREIGN_KEY_CHECKS = 1;

-- ========================================
-- 索引總結 (Index Summary)
-- ========================================

-- Customers: 3 個索引 (org_id, active, name)
-- Catalog Items: 4 個索引 (org_id+type, org_id+sku UNIQUE, active)
-- Quotes: 5 個索引 (org_id+customer_id+issue_date, number UNIQUE, status, issue_date)
-- Quote Items: 2 個索引 (quote_id, quote_id+line_order)
-- Quote Sequences: 1 個索引 (org_id+year UNIQUE)
-- Settings: 1 個索引 (org_id UNIQUE)
-- Users: 4 個索引 (username UNIQUE, email UNIQUE, org_id, status)

-- ========================================
-- 資料型別說明 (Data Type Justification)
-- ========================================

-- BIGINT UNSIGNED: 避免浮點精度問題，支援最大約9萬億元
-- DECIMAL(18,4): 精確到0.0001的數量（如0.1234個產品）
-- DECIMAL(5,2): 支援0.00%-999.99%的稅率範圍
-- TINYINT(1): 節省儲存空間，語義清晰（布林值）

-- ========================================
-- 安全考慮 (Security Considerations)
-- ========================================

-- 1. 所有金額欄位使用整數（分）儲存，避免浮點數精度問題
-- 2. 軟刪除使用 active 欄位，避免物理刪除
-- 3. org_id 欄位預留多租戶支援
-- 4. 外部索引鍵約束保證資料完整性
-- 5. 索引最佳化查詢效能
-- 6. 儲存過程保證併發安全（SELECT...FOR UPDATE）

-- ========================================
-- Schema 版本資訊 (Schema Version Info)
-- ========================================

/*
版本: v2.0.0
建立日期: 2025-11-05
相容性: PHP 8.3+, MySQL 8.0+/MariaDB 10.6+
字元集: utf8mb4_unicode_ci
引擎: InnoDB

遷移說明:
- 初始版本：7個實體完整實現
- 支援單租戶（MVP使用ORG_ID=1）
- 預留多租戶欄位支援未來升級
- 年度編號自動歸零機制
- 完整索引策略保證效能
*/
