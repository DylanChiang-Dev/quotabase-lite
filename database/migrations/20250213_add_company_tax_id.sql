ALTER TABLE settings
    ADD COLUMN company_tax_id VARCHAR(50) NULL COMMENT '公司统一编号' AFTER company_contact;
