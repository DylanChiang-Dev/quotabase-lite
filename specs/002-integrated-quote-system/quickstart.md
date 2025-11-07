# 快速開始指南: Quotabase-Lite 整合報價管理系統

**專案版本**: v2.0.0
**建立日期**: 2025-11-05
**適用環境**: 寶塔面板 (aaPanel/BT)

## 📋 系統要求

### 伺服器要求

- **作業系統**: Linux (Ubuntu 20.04+ / CentOS 7+)
- **Web 伺服器**: Nginx 1.18+ 或 Apache 2.4+
- **PHP 版本**: PHP 8.3 (必需)
- **資料庫**: MySQL 8.0+ 或 MariaDB 10.6+
- **記憶體**: 最低 512MB，推薦 1GB+
- **磁碟空間**: 最低 1GB 可用空間

### PHP 擴充套件要求

```bash
php8.3-cli
php8.3-fpm
php8.3-mysql
php8.3-mysqli
php8.3-pdo
php8.3-pdo_mysql
php8.3-json
php8.3-mbstring
php8.3-xml
php8.3-curl
php8.3-zip
php8.3-gd
```

## 🚀 安裝步驟

### 步驟 1: 準備環境

#### 透過寶塔面板安裝

1. **安裝寶塔面板** (如未安裝)

```bash
wget -O install.sh http://download.bt.cn/install/install-ubuntu_6.0.sh && sudo bash install.sh ed8484bec
```

2. **安裝 LNMP 元件**

   - 在寶塔面板 → 軟體商店 → 安裝
   - 選擇 **Nginx 1.22**
   - 選擇 **PHP 8.3**
   - 選擇 **MySQL 8.0** 或 **MariaDB 10.6**

3. **建立網站**

   - 面板 → 網站 → 新增站點
   - 輸入域名: `your-domain.com`
   - 根目錄: `/www/wwwroot/quotabase-lite`
   - PHP 版本: 選擇 **8.3**

### 步驟 2: 部署程式碼

#### 上傳原始碼

**方法 A: 透過寶塔檔案管理器**

1. 下載專案 ZIP 包
2. 寶塔面板 → 檔案 → 上傳 → 選擇 ZIP 檔案
3. 解壓到網站根目錄
4. 設定檔案許可權: `chmod -R 755 /www/wwwroot/quotabase-lite`

**方法 B: 透過 Git 克隆**

```bash
cd /www/wwwroot/quotabase-lite
git clone https://github.com/your-org/quotabase-lite.git .
```

### 步驟 3: 配置資料庫

#### 建立資料庫

1. 寶塔面板 → 資料庫 → 新增資料庫
2. 資料庫名: `quotabase_lite`
3. 使用者名稱: `quotabase_user`
4. 密碼: `生成強密碼`

#### 匯入資料庫結構

```bash
# 透過寶塔 phpMyAdmin
# 或透過命令列
mysql -u quotabase_user -p quotabase_lite < /www/wwwroot/quotabase-lite/schema.sql
```

### 步驟 4: 配置檔案

#### 建立 config.php

```bash
cp /www/wwwroot/quotabase-lite/config.php.sample /www/wwwroot/quotabase-lite/config.php
```

#### 編輯 config.php

```php
<?php
// 開發者配置 - 開發環境可開啟，生產環境必須關閉
define('DEBUG_MODE', false);
define('DISPLAY_ERRORS', false);

// 資料庫配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'quotabase_lite');
define('DB_USER', 'quotabase_user');
define('DB_PASS', 'your_database_password_here');

// 安全配置
define('SESSION_TIMEOUT', 3600); // 1小時
define('CSRF_TOKEN_LENGTH', 64);

// 時區配置
define('DEFAULT_TIMEZONE', 'Asia/Taipei');
define('DISPLAY_TIMEZONE', 'Asia/Taipei');

// 軟刪除配置
define('SOFT_DELETE_FIELD', 'active');
define('ACTIVE_VALUE', 1);
define('INACTIVE_VALUE', 0);
```

### 步驟 5: 配置 Nginx

#### 建立 Nginx 站點配置

在寶塔面板 → 網站 → 設定 → 配置檔案中新增:

```nginx
server {
    listen 80;
    listen 443 ssl http2;
    server_name your-domain.com;
    index index.php index.html;
    root /www/wwwroot/quotabase-lite;

    # SSL 配置 (如使用 HTTPS)
    ssl_certificate /path/to/your/cert.pem;
    ssl_certificate_key /path/to/your/private.key;

    # 安全頭部
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    # 隱藏敏感檔案
    location ~ /\. {
        deny all;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/tmp/php-cgi-83.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 保護 config.php
    location = /config.php {
        deny all;
    }

    # 靜態資源快取
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, no-transform";
    }
}
```

### 步驟 6: 初始化系統

#### 執行初始化指令碼

```bash
cd /www/wwwroot/quotabase-lite
php init.php
```

這將自動:
- 建立預設組織記錄 (ORG_ID=1)
- 初始化設定表
- 建立年度編號序列表初始記錄
- 設定預設管理員賬戶

## ⚙️ 系統配置

### 基礎設定

1. **訪問管理介面**

   - 開啟瀏覽器訪問: `https://your-domain.com/login.php`
   - 預設管理員: `admin` / `admin123`
   - ⚠️ **首次登入後立即修改密碼！**

2. **配置公司資訊**

   - 導航到 **設定** Tab
   - 填寫公司名稱、地址、聯絡方式
   - 設定報價單編號字首 (預設: Q)
   - 設定預設稅率 (預設: 0.00%)
   - 填寫列印條款文字

3. **測試系統**

   - 建立測試客戶
   - 新增產品/服務
   - 建立第一張報價單
   - 測試列印功能

### 安全配置

#### 生產環境安全檢查清單

- [ ] 修改預設管理員密碼
- [ ] 設定 DEBUG_MODE = false
- [ ] 設定 DISPLAY_ERRORS = false
- [ ] 啟用 HTTPS (SSL 證書)
- [ ] 配置防火牆 (僅開放 80/443 埠)
- [ ] 設定資料庫訪問許可權 (限制本地訪問)
- [ ] 定期備份資料庫 (PVE VM 自動備份)
- [ ] 配置日誌輪轉

## 📚 基本使用

### 客戶管理

1. **新增客戶**

   - 導航到 **客戶** Tab
   - 點選 **新增客戶**
   - 填寫客戶資訊 (姓名必填)
   - 儲存

2. **編輯客戶**

   - 在客戶列表中點選 **編輯**
   - 修改資訊後儲存

### 產品/服務管理

1. **新增產品**

   - 導航到 **產品** Tab
   - 點選 **新增產品**
   - 填寫 SKU (唯一)
   - 填寫名稱、單價 (分)、稅率
   - 儲存

2. **新增服務**

   - 導航到 **服務** Tab
   - 點選 **新增服務**
   - 填寫資訊 (流程同產品)
   - 儲存

### 報價單管理

1. **建立報價單**

   - 導航到 **報價** Tab
   - 點選 **新增報價**
   - 選擇客戶
   - 新增專案 (可從目錄選擇或手動輸入)
   - 檢查金額計算
   - 儲存

2. **列印報價單**

   - 開啟報價單詳情
   - 點選 **列印** 連結
   - 瀏覽器自動開啟列印預覽
   - 選擇 **另存為 PDF**

## 🔧 常見問題

### Q1: 頁面顯示空白或 500 錯誤

**解決方案:**

```bash
# 檢查錯誤日誌
tail -n 100 /www/wwwroot/quotabase-lite/logs/error.log

# 檢查 PHP 錯誤
tail -n 100 /www/server/php/83/var/log/php-fpm.log

# 確認 PHP 版本
php -v

# 檢查目錄許可權
ls -la /www/wwwroot/quotabase-lite
```

### Q2: 資料庫連線失敗

**解決方案:**

```bash
# 測試資料庫連線
mysql -u quotabase_user -p -h localhost quotabase_lite

# 檢查配置檔案
cat /www/wwwroot/quotabase-lite/config.php | grep DB

# 確認資料庫服務執行
systemctl status mysql
```

### Q3: 無法上傳檔案或建立目錄

**解決方案:**

```bash
# 設定正確的所有者
chown -R www:www /www/wwwroot/quotabase-lite

# 設定正確的許可權
find /www/wwwroot/quotabase-lite -type d -exec chmod 755 {} \;
find /www/wwwroot/quotabase-lite -type f -exec chmod 644 {} \;

# 建立必要的目錄
mkdir -p /www/wwwroot/quotabase-lite/logs
mkdir -p /www/wwwroot/quotabase-lite/uploads
chmod 777 /www/wwwroot/quotabase-lite/logs
chmod 777 /www/wwwroot/quotabase-lite/uploads
```

### Q4: 列印樣式不正確

**解決方案:**

1. 使用 Chrome 或 Edge 瀏覽器
2. 確認已啟用 JavaScript
3. 檢查 CSS 檔案是否正確載入
4. 清除瀏覽器快取

### Q5: 年度編號不歸零

**解決方案:**

```sql
-- 手動重置年度編號 (謹慎操作!)
UPDATE quote_sequences
SET current_number = 0
WHERE org_id = 1 AND year = YEAR(NOW());
```

## 📊 效能最佳化

### 資料庫最佳化

```sql
-- 建立必要的索引
CREATE INDEX idx_customers_org_active ON customers(org_id, active);
CREATE INDEX idx_catalog_org_type ON catalog_items(org_id, type);
CREATE INDEX idx_quotes_org_customer_date ON quotes(org_id, customer_id, issue_date);

-- 分析表結構
ANALYZE TABLE customers, catalog_items, quotes, quote_items;
```

### PHP 配置最佳化

在寶塔面板 → 軟體商店 → PHP 8.3 → 設定 → 配置修改:

```ini
; 記憶體限制
memory_limit = 256M

; 執行時間
max_execution_time = 60

; 檔案上傳
upload_max_filesize = 10M
post_max_size = 10M

; OPcache 最佳化
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
```

### Nginx 最佳化

```nginx
# 啟用 gzip 壓縮
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

# 瀏覽器快取
location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
    expires 1y;
    add_header Cache-Control "public, no-transform";
}
```

## 📈 監控與維護

### 日誌監控

```bash
# 錯誤日誌
tail -f /www/wwwroot/quotabase-lite/logs/error.log

# 訪問日誌
tail -f /www/wwwlogs/your-domain.com.log

# 資料庫日誌
tail -f /www/server/mysql/data/mysql-error.log
```

### 定期維護

```bash
# 每週執行一次

# 清理日誌檔案
find /www/wwwroot/quotabase-lite/logs -name "*.log" -mtime +30 -delete

# 資料庫最佳化
mysql -u root -p -e "OPTIMIZE TABLE quotabase_lite.customers, quotabase_lite.quotes, quotabase_lite.quote_items;"

# 檢查磁碟空間
df -h

# 檢查資料庫大小
mysql -u root -p -e "SELECT table_schema AS 'Database', ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.tables WHERE table_schema = 'quotabase_lite' GROUP BY table_schema;"
```

### 資料備份

由於依賴 PVE 虛擬機器每日 4 點自動備份，額外建議:

```bash
# 每日匯出重要資料
mysqldump -u quotabase_user -p quotabase_lite > /backup/quotabase_lite_$(date +%Y%m%d).sql

# 保留最近 7 天的備份
find /backup -name "quotabase_lite_*.sql" -mtime +7 -delete
```

## 🎯 下一步

1. **熟悉系統功能** - 建立測試資料，熟練操作各項功能
2. **配置公司資訊** - 根據實際情況填寫公司抬頭和條款
3. **匯入現有資料** - 如有舊系統，可透過 CSV 匯出功能遷移資料
4. **培訓使用者** - 為團隊成員提供系統使用培訓
5. **效能調優** - 根據實際使用情況調整系統配置

## 📞 技術支援

- **專案文件**: 檢視 `/docs` 目錄
- **API 文件**: 檢視 `/specs/002-integrated-quote-system/contracts/`
- **資料模型**: 檢視 `/specs/002-integrated-quote-system/data-model.md`

## 📄 許可證

MIT License - 詳見專案根目錄 `LICENSE` 檔案
