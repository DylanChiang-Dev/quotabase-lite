CREATE TABLE IF NOT EXISTS users (
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

INSERT INTO users (org_id, username, password_hash, email, role, status)
SELECT 1, 'admin', '$2y$10$O6KFFJvolG/GpNZgbf28OeCL4ZzOogzPOIu.TQ3BM4tiTnGooy2KW', NULL, 'admin', 'active'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');
