ALTER TABLE settings
    ADD COLUMN company_tax_id VARCHAR(50) NULL COMMENT '公司統一編號' AFTER company_contact;
