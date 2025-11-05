-- ========================================
-- Quotabase-Lite Database Schema
-- 数据库结构文件
-- 版本: v2.0.0
-- 描述: 集成报价管理系统 - 7个核心实体
-- ========================================

SET FOREIGN_KEY_CHECKS = 0;
DROP DATABASE IF EXISTS quotabase_lite;
CREATE DATABASE quotabase_lite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quotabase_lite;

-- ========================================
-- 1. Organizations (组织)
-- 支持未来多租户升级的预留表
-- ========================================

CREATE TABLE organizations (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL COMMENT '组织名称',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    INDEX idx_org_name (name)
) ENGINE=InnoDB COMMENT='组织表（预留多租户）';

-- ========================================
-- 2. Customers (客户)
-- 存储企业客户基本信息和联系方式
-- ========================================

CREATE TABLE customers (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '组织ID',
    name VARCHAR(255) NOT NULL COMMENT '客户名称（必填）',
    tax_id VARCHAR(50) NULL COMMENT '税务登记号',
    email VARCHAR(255) NULL COMMENT '邮箱',
    phone VARCHAR(50) NULL COMMENT '电话',
    billing_address TEXT NULL COMMENT '账单地址',
    shipping_address TEXT NULL COMMENT '收货地址',
    note TEXT NULL COMMENT '备注',
    active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '软删除标记（1=激活，0=禁用）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    INDEX idx_customers_org_id (org_id),
    INDEX idx_customers_active (active),
    INDEX idx_customers_name (name)
) ENGINE=InnoDB COMMENT='客户表';

-- ========================================
-- 3. Catalog Items (目录项 - 产品/服务统一表)
-- 统一存储产品和服务信息，通过 type 字段区分
-- ========================================

CREATE TABLE catalog_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '组织ID',
    type ENUM('product', 'service') NOT NULL COMMENT '类型：产品或服务',
    sku VARCHAR(100) NOT NULL COMMENT 'SKU编码（同一org_id下唯一）',
    name VARCHAR(255) NOT NULL COMMENT '名称（必填）',
    unit VARCHAR(20) NOT NULL DEFAULT 'pcs' COMMENT '单位',
    currency VARCHAR(3) NOT NULL DEFAULT 'TWD' COMMENT '币种（仅支持TWD）',
    unit_price_cents BIGINT UNSIGNED NOT NULL COMMENT '单价（单位：分）',
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '税率（%）',
    category_id BIGINT UNSIGNED NULL COMMENT '分类ID（三级分类）',
    active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态（1=启用，0=禁用）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    INDEX idx_catalog_org_type (org_id, type),
    INDEX idx_catalog_sku (org_id, sku),
    INDEX idx_catalog_active (active),
    INDEX idx_catalog_category (category_id),
    UNIQUE KEY uq_catalog_sku (org_id, sku)
) ENGINE=InnoDB COMMENT='目录项表（产品/服务统一）';

-- ========================================
-- Catalog Categories (产品/服务分类)
-- 支持三级分类结构
-- ========================================

CREATE TABLE catalog_categories (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '组织ID',
    type ENUM('product', 'service') NOT NULL DEFAULT 'product' COMMENT '分类类型',
    parent_id BIGINT UNSIGNED NULL COMMENT '父级分类ID（NULL为顶级）',
    level TINYINT UNSIGNED NOT NULL COMMENT '分类层级（1-3）',
    name VARCHAR(100) NOT NULL COMMENT '分类名称',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '排序值',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    UNIQUE KEY uq_category_name (org_id, type, parent_id, name),
    INDEX idx_category_org_type (org_id, type),
    INDEX idx_category_parent (parent_id),
    INDEX idx_category_level (org_id, level),
    CONSTRAINT fk_category_parent FOREIGN KEY (parent_id) REFERENCES catalog_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='产品/服务分类表';

-- ========================================
-- 4. Quotes (报价单主档)
-- 存储报价单基本信息
-- ========================================

CREATE TABLE quotes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '组织ID',
    quote_number VARCHAR(50) NOT NULL COMMENT '报价单编号（格式：前缀-YYYY-000001）',
    customer_id BIGINT UNSIGNED NOT NULL COMMENT '客户ID（外键）',
    issue_date DATE NOT NULL COMMENT '发出日期（UTC）',
    valid_until DATE NULL COMMENT '有效期至（UTC）',
    currency VARCHAR(3) NOT NULL DEFAULT 'TWD' COMMENT '币种（仅支持TWD）',
    status ENUM('draft', 'sent', 'accepted', 'rejected', 'expired') NOT NULL DEFAULT 'draft' COMMENT '状态',
    title VARCHAR(255) NULL COMMENT '报价单标题',
    notes TEXT NULL COMMENT '备注',
    subtotal_cents BIGINT UNSIGNED NOT NULL COMMENT '小计（分）',
    tax_cents BIGINT UNSIGNED NOT NULL COMMENT '税额（分）',
    total_cents BIGINT UNSIGNED NOT NULL COMMENT '总计（分）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    INDEX idx_quotes_org_customer_date (org_id, customer_id, issue_date),
    INDEX idx_quotes_number (quote_number),
    INDEX idx_quotes_status (status),
    INDEX idx_quotes_issue_date (issue_date),
    UNIQUE KEY uq_quotes_number (quote_number)
) ENGINE=InnoDB COMMENT='报价单主档表';

-- ========================================
-- 5. Quote Items (报价项目明细)
-- 存储报价单明细行项目
-- ========================================

CREATE TABLE quote_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    quote_id BIGINT UNSIGNED NOT NULL COMMENT '报价单ID（外键）',
    catalog_item_id BIGINT UNSIGNED NULL COMMENT '关联的目录项ID（可为空）',
    description VARCHAR(500) NOT NULL COMMENT '项目描述',
    qty DECIMAL(18,4) NOT NULL COMMENT '数量（精确到0.0001）',
    unit VARCHAR(20) NULL COMMENT '单位',
    unit_price_cents BIGINT UNSIGNED NOT NULL COMMENT '单价（分）',
    tax_rate DECIMAL(5,2) NOT NULL COMMENT '税率（%）',
    line_subtotal_cents BIGINT UNSIGNED NOT NULL COMMENT '行小计（分）',
    line_tax_cents BIGINT UNSIGNED NOT NULL COMMENT '行税额（分）',
    line_total_cents BIGINT UNSIGNED NOT NULL COMMENT '行总计（分）',
    line_order INT NOT NULL COMMENT '行顺序（排序用）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    INDEX idx_quote_items_quote_id (quote_id),
    INDEX idx_quote_items_order (quote_id, line_order),
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='报价项目明细表';

-- ========================================
-- 6. Quote Sequences (年度编号序列表)
-- 管理年度报价单编号计数器
-- ========================================

CREATE TABLE quote_sequences (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '组织ID（每个组织一条记录）',
    prefix VARCHAR(10) NOT NULL DEFAULT 'Q' COMMENT '编号前缀',
    year INT NOT NULL COMMENT '年度',
    current_number INT NOT NULL DEFAULT 0 COMMENT '当前编号',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    UNIQUE KEY uq_quote_sequences_org_year (org_id, year)
) ENGINE=InnoDB COMMENT='年度编号序列表';

-- ========================================
-- 7. Settings (系统设置)
-- 存储系统全局配置
-- ========================================

CREATE TABLE settings (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    org_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '组织ID（每个组织一条记录）',
    company_name VARCHAR(255) NULL COMMENT '公司名称',
    company_address TEXT NULL COMMENT '公司地址',
    company_contact VARCHAR(255) NULL COMMENT '公司联系方式',
    quote_prefix VARCHAR(10) NOT NULL DEFAULT 'Q' COMMENT '报价单编号前缀',
    default_tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '默认税率（%）',
    print_terms TEXT NULL COMMENT '打印条款文字',
    timezone VARCHAR(50) NOT NULL DEFAULT 'Asia/Taipei' COMMENT '时区',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    UNIQUE KEY uq_settings_org_id (org_id)
) ENGINE=InnoDB COMMENT='系统设置表';

-- ========================================
-- 外键约束 (Foreign Key Constraints)
-- ========================================

ALTER TABLE quotes
    ADD FOREIGN KEY (customer_id) REFERENCES customers(id);

-- ========================================
-- 存储过程 (Stored Procedures)
-- ========================================

-- 年度编号生成存储过程
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
    SET v_prefix = (
        SELECT COALESCE(s.quote_prefix, 'Q')
        FROM settings s
        WHERE s.org_id = p_org_id
        LIMIT 1
    );

    IF v_prefix IS NULL OR v_prefix = '' THEN
        SET v_prefix = 'Q';
    END IF;

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
    SET current_number = v_next,
        prefix = v_prefix
    WHERE org_id = p_org_id AND year = v_year;

    -- 构造返回编号：前缀-YYYY-000001
    SET p_out_number = CONCAT(v_prefix, '-', v_year, '-', LPAD(v_next, 6, '0'));
END//

DELIMITER ;

-- ========================================
-- 初始数据 (Initial Data)
-- ========================================

-- 插入默认组织
INSERT INTO organizations (id, name) VALUES (1, '默认组织');

-- 插入默认设置
INSERT INTO settings (
    org_id, company_name, company_address, company_contact,
    quote_prefix, default_tax_rate, timezone
) VALUES (
    1,
    '您的公司名称',
    '公司地址',
    '联系电话',
    'Q',
    5.00,
    'Asia/Taipei'
);

-- 插入年度编号序列初始记录
INSERT INTO quote_sequences (org_id, prefix, year, current_number) VALUES
(1, 'Q', YEAR(NOW()), 0);

SET FOREIGN_KEY_CHECKS = 1;

-- ========================================
-- 索引总结 (Index Summary)
-- ========================================

-- Customers: 3 个索引 (org_id, active, name)
-- Catalog Items: 4 个索引 (org_id+type, org_id+sku UNIQUE, active)
-- Quotes: 5 个索引 (org_id+customer_id+issue_date, number UNIQUE, status, issue_date)
-- Quote Items: 2 个索引 (quote_id, quote_id+line_order)
-- Quote Sequences: 1 个索引 (org_id+year UNIQUE)
-- Settings: 1 个索引 (org_id UNIQUE)

-- ========================================
-- 数据类型说明 (Data Type Justification)
-- ========================================

-- BIGINT UNSIGNED: 避免浮点精度问题，支持最大约9万亿元
-- DECIMAL(18,4): 精确到0.0001的数量（如0.1234个产品）
-- DECIMAL(5,2): 支持0.00%-999.99%的税率范围
-- TINYINT(1): 节省存储空间，语义清晰（布尔值）

-- ========================================
-- 安全考虑 (Security Considerations)
-- ========================================

-- 1. 所有金额字段使用整数（分）存储，避免浮点数精度问题
-- 2. 软删除使用 active 字段，避免物理删除
-- 3. org_id 字段预留多租户支持
-- 4. 外键约束保证数据完整性
-- 5. 索引优化查询性能
-- 6. 存储过程保证并发安全（SELECT...FOR UPDATE）

-- ========================================
-- Schema 版本信息 (Schema Version Info)
-- ========================================

/*
版本: v2.0.0
创建日期: 2025-11-05
兼容性: PHP 8.3+, MySQL 8.0+/MariaDB 10.6+
字符集: utf8mb4_unicode_ci
引擎: InnoDB

迁移说明:
- 初始版本：7个实体完整实现
- 支持单租户（MVP使用ORG_ID=1）
- 预留多租户字段支持未来升级
- 年度编号自动归零机制
- 完整索引策略保证性能
*/
