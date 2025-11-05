# 快速开始指南: Quotabase-Lite 集成报价管理系统

**项目版本**: v2.0.0
**创建日期**: 2025-11-05
**适用环境**: 宝塔面板 (aaPanel/BT)

## 📋 系统要求

### 服务器要求

- **操作系统**: Linux (Ubuntu 20.04+ / CentOS 7+)
- **Web 服务器**: Nginx 1.18+ 或 Apache 2.4+
- **PHP 版本**: PHP 8.3 (必需)
- **数据库**: MySQL 8.0+ 或 MariaDB 10.6+
- **内存**: 最低 512MB，推荐 1GB+
- **磁盘空间**: 最低 1GB 可用空间

### PHP 扩展要求

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

## 🚀 安装步骤

### 步骤 1: 准备环境

#### 通过宝塔面板安装

1. **安装宝塔面板** (如未安装)

```bash
wget -O install.sh http://download.bt.cn/install/install-ubuntu_6.0.sh && sudo bash install.sh ed8484bec
```

2. **安装 LNMP 组件**

   - 在宝塔面板 → 软件商店 → 安装
   - 选择 **Nginx 1.22**
   - 选择 **PHP 8.3**
   - 选择 **MySQL 8.0** 或 **MariaDB 10.6**

3. **创建网站**

   - 面板 → 网站 → 添加站点
   - 输入域名: `your-domain.com`
   - 根目录: `/www/wwwroot/quotabase-lite`
   - PHP 版本: 选择 **8.3**

### 步骤 2: 部署代码

#### 上传源代码

**方法 A: 通过宝塔文件管理器**

1. 下载项目 ZIP 包
2. 宝塔面板 → 文件 → 上传 → 选择 ZIP 文件
3. 解压到网站根目录
4. 设置文件权限: `chmod -R 755 /www/wwwroot/quotabase-lite`

**方法 B: 通过 Git 克隆**

```bash
cd /www/wwwroot/quotabase-lite
git clone https://github.com/your-org/quotabase-lite.git .
```

### 步骤 3: 配置数据库

#### 创建数据库

1. 宝塔面板 → 数据库 → 添加数据库
2. 数据库名: `quotabase_lite`
3. 用户名: `quotabase_user`
4. 密码: `生成强密码`

#### 导入数据库结构

```bash
# 通过宝塔 phpMyAdmin
# 或通过命令行
mysql -u quotabase_user -p quotabase_lite < /www/wwwroot/quotabase-lite/schema.sql
```

### 步骤 4: 配置文件

#### 创建 config.php

```bash
cp /www/wwwroot/quotabase-lite/config.php.sample /www/wwwroot/quotabase-lite/config.php
```

#### 编辑 config.php

```php
<?php
// 开发者配置 - 开发环境可开启，生产环境必须关闭
define('DEBUG_MODE', false);
define('DISPLAY_ERRORS', false);

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'quotabase_lite');
define('DB_USER', 'quotabase_user');
define('DB_PASS', 'your_database_password_here');

// 安全配置
define('SESSION_TIMEOUT', 3600); // 1小时
define('CSRF_TOKEN_LENGTH', 64);

// 时区配置
define('DEFAULT_TIMEZONE', 'Asia/Taipei');
define('DISPLAY_TIMEZONE', 'Asia/Taipei');

// 软删除配置
define('SOFT_DELETE_FIELD', 'active');
define('ACTIVE_VALUE', 1);
define('INACTIVE_VALUE', 0);
```

### 步骤 5: 配置 Nginx

#### 创建 Nginx 站点配置

在宝塔面板 → 网站 → 设置 → 配置文件中添加:

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

    # 安全头部
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    # 隐藏敏感文件
    location ~ /\. {
        deny all;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/tmp/php-cgi-83.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 保护 config.php
    location = /config.php {
        deny all;
    }

    # 静态资源缓存
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, no-transform";
    }
}
```

### 步骤 6: 初始化系统

#### 执行初始化脚本

```bash
cd /www/wwwroot/quotabase-lite
php init.php
```

这将自动:
- 创建默认组织记录 (ORG_ID=1)
- 初始化设置表
- 创建年度编号序列表初始记录
- 设置默认管理员账户

## ⚙️ 系统配置

### 基础设置

1. **访问管理界面**

   - 打开浏览器访问: `https://your-domain.com/login.php`
   - 默认管理员: `admin` / `admin123`
   - ⚠️ **首次登录后立即修改密码！**

2. **配置公司信息**

   - 导航到 **设置** Tab
   - 填写公司名称、地址、联系方式
   - 设置报价单编号前缀 (默认: Q)
   - 设置默认税率 (默认: 0.00%)
   - 填写打印条款文字

3. **测试系统**

   - 创建测试客户
   - 添加产品/服务
   - 创建第一张报价单
   - 测试打印功能

### 安全配置

#### 生产环境安全检查清单

- [ ] 修改默认管理员密码
- [ ] 设置 DEBUG_MODE = false
- [ ] 设置 DISPLAY_ERRORS = false
- [ ] 启用 HTTPS (SSL 证书)
- [ ] 配置防火墙 (仅开放 80/443 端口)
- [ ] 设置数据库访问权限 (限制本地访问)
- [ ] 定期备份数据库 (PVE VM 自动备份)
- [ ] 配置日志轮转

## 📚 基本使用

### 客户管理

1. **添加客户**

   - 导航到 **客户** Tab
   - 点击 **新增客户**
   - 填写客户信息 (姓名必填)
   - 保存

2. **编辑客户**

   - 在客户列表中点击 **编辑**
   - 修改信息后保存

### 产品/服务管理

1. **添加产品**

   - 导航到 **产品** Tab
   - 点击 **新增产品**
   - 填写 SKU (唯一)
   - 填写名称、单价 (分)、税率
   - 保存

2. **添加服务**

   - 导航到 **服务** Tab
   - 点击 **新增服务**
   - 填写信息 (流程同产品)
   - 保存

### 报价单管理

1. **创建报价单**

   - 导航到 **报价** Tab
   - 点击 **新增报价**
   - 选择客户
   - 添加项目 (可从目录选择或手动输入)
   - 检查金额计算
   - 保存

2. **打印报价单**

   - 打开报价单详情
   - 点击 **打印** 链接
   - 浏览器自动打开打印预览
   - 选择 **另存为 PDF**

## 🔧 常见问题

### Q1: 页面显示空白或 500 错误

**解决方案:**

```bash
# 检查错误日志
tail -n 100 /www/wwwroot/quotabase-lite/logs/error.log

# 检查 PHP 错误
tail -n 100 /www/server/php/83/var/log/php-fpm.log

# 确认 PHP 版本
php -v

# 检查目录权限
ls -la /www/wwwroot/quotabase-lite
```

### Q2: 数据库连接失败

**解决方案:**

```bash
# 测试数据库连接
mysql -u quotabase_user -p -h localhost quotabase_lite

# 检查配置文件
cat /www/wwwroot/quotabase-lite/config.php | grep DB

# 确认数据库服务运行
systemctl status mysql
```

### Q3: 无法上传文件或创建目录

**解决方案:**

```bash
# 设置正确的所有者
chown -R www:www /www/wwwroot/quotabase-lite

# 设置正确的权限
find /www/wwwroot/quotabase-lite -type d -exec chmod 755 {} \;
find /www/wwwroot/quotabase-lite -type f -exec chmod 644 {} \;

# 创建必要的目录
mkdir -p /www/wwwroot/quotabase-lite/logs
mkdir -p /www/wwwroot/quotabase-lite/uploads
chmod 777 /www/wwwroot/quotabase-lite/logs
chmod 777 /www/wwwroot/quotabase-lite/uploads
```

### Q4: 打印样式不正确

**解决方案:**

1. 使用 Chrome 或 Edge 浏览器
2. 确认已启用 JavaScript
3. 检查 CSS 文件是否正确加载
4. 清除浏览器缓存

### Q5: 年度编号不归零

**解决方案:**

```sql
-- 手动重置年度编号 (谨慎操作!)
UPDATE quote_sequences
SET current_number = 0
WHERE org_id = 1 AND year = YEAR(NOW());
```

## 📊 性能优化

### 数据库优化

```sql
-- 创建必要的索引
CREATE INDEX idx_customers_org_active ON customers(org_id, active);
CREATE INDEX idx_catalog_org_type ON catalog_items(org_id, type);
CREATE INDEX idx_quotes_org_customer_date ON quotes(org_id, customer_id, issue_date);

-- 分析表结构
ANALYZE TABLE customers, catalog_items, quotes, quote_items;
```

### PHP 配置优化

在宝塔面板 → 软件商店 → PHP 8.3 → 设置 → 配置修改:

```ini
; 内存限制
memory_limit = 256M

; 执行时间
max_execution_time = 60

; 文件上传
upload_max_filesize = 10M
post_max_size = 10M

; OPcache 优化
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
```

### Nginx 优化

```nginx
# 启用 gzip 压缩
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

# 浏览器缓存
location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
    expires 1y;
    add_header Cache-Control "public, no-transform";
}
```

## 📈 监控与维护

### 日志监控

```bash
# 错误日志
tail -f /www/wwwroot/quotabase-lite/logs/error.log

# 访问日志
tail -f /www/wwwlogs/your-domain.com.log

# 数据库日志
tail -f /www/server/mysql/data/mysql-error.log
```

### 定期维护

```bash
# 每周执行一次

# 清理日志文件
find /www/wwwroot/quotabase-lite/logs -name "*.log" -mtime +30 -delete

# 数据库优化
mysql -u root -p -e "OPTIMIZE TABLE quotabase_lite.customers, quotabase_lite.quotes, quotabase_lite.quote_items;"

# 检查磁盘空间
df -h

# 检查数据库大小
mysql -u root -p -e "SELECT table_schema AS 'Database', ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.tables WHERE table_schema = 'quotabase_lite' GROUP BY table_schema;"
```

### 数据备份

由于依赖 PVE 虚拟机每日 4 点自动备份，额外建议:

```bash
# 每日导出重要数据
mysqldump -u quotabase_user -p quotabase_lite > /backup/quotabase_lite_$(date +%Y%m%d).sql

# 保留最近 7 天的备份
find /backup -name "quotabase_lite_*.sql" -mtime +7 -delete
```

## 🎯 下一步

1. **熟悉系统功能** - 创建测试数据，熟练操作各项功能
2. **配置公司信息** - 根据实际情况填写公司抬头和条款
3. **导入现有数据** - 如有旧系统，可通过 CSV 导出功能迁移数据
4. **培训用户** - 为团队成员提供系统使用培训
5. **性能调优** - 根据实际使用情况调整系统配置

## 📞 技术支持

- **项目文档**: 查看 `/docs` 目录
- **API 文档**: 查看 `/specs/002-integrated-quote-system/contracts/`
- **数据模型**: 查看 `/specs/002-integrated-quote-system/data-model.md`

## 📄 许可证

MIT License - 详见项目根目录 `LICENSE` 文件
