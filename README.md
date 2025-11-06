# Quotabase-Lite 集成报价管理系统

> 专为中小企业设计的简洁、高效、可信赖的报价管理解决方案

## 🌟 项目概述

Quotabase-Lite 是一个专为中小企业设计的 iOS 风格报价单管理系统，采用 **零框架、零 Composer** 的极简架构，提供完整的报价管理功能。

### ✨ 核心特性

- 🎨 **iOS 风格界面** - 现代化设计，底部 Tab 导航，Dark Mode 支持
- 💰 **精确财务处理** - 金额以分存储，避免浮点精度问题
- 🔒 **安全可靠** - XSS 防护、CSRF 验证、PDO 预处理、事务安全
- 📊 **完整业务流** - 客户管理、产品/服务目录、报价单创建、状态跟踪
- 🖨️ **专业打印** - A4 格式，支持 PDF 导出，表头固定
- 📤 **数据导出** - 支持 CSV/JSON 格式导出
- ⚡ **高性能** - P95 响应时间 ≤ 200ms，支持 10+ 并发用户

## 🏗️ 技术架构

### 技术栈

- **后端**: PHP 8.3 (零框架)
- **数据库**: MySQL 8.0+ / MariaDB 10.6+
- **前端**: HTML/CSS/JavaScript (原生)
- **部署**: 宝塔面板 (aaPanel/BT)
- **Web 服务器**: Nginx / Apache

## 🚀 快速开始

### 环境要求

- **PHP**: 8.3 或更高版本
- **MySQL**: 8.0+ / MariaDB: 10.6+
- **Web 服务器**: Nginx 或 Apache

### 安装步骤

1. **克隆项目**
   ```bash
   git clone <repository-url>
   cd quotabase-lite
   ```

2. **创建数据库**
   ```bash
   mysql -u root -p
   CREATE DATABASE quotabase_lite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   EXIT;
   ```
   > 数据库只需创建为空的 `quotabase_lite`，数据表稍后会通过初始化精灵自动生成。

3. **配置并初始化应用**
   - 直接访问 `https://你的域名/init.php`，系统会先引导你输入数据库连接信息并自动生成 `config.php`，随后进入初始化精灵完成建表与导入预设资料。
   - 若服务器无法写入 `config.php`，可退回手动方式：
     ```bash
     cp config.php.sample config.php
     # 编辑 config.php 配置数据库连接、加密密钥等信息
     ```
   - 偏好命令行时可执行：
     ```bash
     php init.php install   # 建立 / 更新数据表
     php init.php init      # 导入默认数据
     ```
   - 若仍需手动导入完整 Schema，可使用 `mysql -u root -p quotabase_lite < schema.sql`（该操作将重建数据库结构）。
   - 初次启动会建立默认管理员帐号：`admin` / `Admin@123`（可通过环境变量 `DEFAULT_ADMIN_PASSWORD` 自定义），请尽快在「设置 → 账号与安全」页面更改密码。

4. **设置权限**
   ```bash
   chown -R www:www /path/to/quotabase-lite
   chmod -R 755 /path/to/quotabase-lite
   ```

### 使用 Docker 快速启动

1. 确保已复制配置文件
   ```bash
   cp config.php.sample config.php
   ```
   > 若使用 docker compose，`DB_HOST` 将自动指向 `db` 容器，其余账号密码可沿用 `docker-compose.yml` 中的环境变量。

2. 构建并启动容器
   ```bash
   docker compose up -d --build
   ```

3. 初始化数据库（第一次执行）
   ```bash
   docker compose exec app php init.php install
   docker compose exec app php init.php init
   ```
   > 亦可直接访问 `http://localhost:8080/init.php`，使用前端初始化精灵完成同样流程。

4. 访问应用
   - 应用：http://localhost:8080
   - MySQL：`localhost:3306`（用户 `quotabase_user` / 密码 `strong_password`）

5. 停止服务
   ```bash
   docker compose down
   ```

开发过程中代码会透过 volume 映射到容器内，修改后直接刷新浏览器即可；如需查看记录，可执行 `docker compose logs -f app`。

## 🔄 升级指南

### 新增报价折扣栏位（v2.1.0+）

已上线环境需先执行数据库 ALTER：

```bash
# 本地环境
mysql -u <db_user> -p<db_password> quotabase_lite < database/migrations/20251106_quote_items_discount.sql

# Docker 环境（先 docker cp 迁移文件至 /tmp 或其它可访问目录）
docker compose cp database/migrations/20251106_quote_items_discount.sql db:/tmp/quote_discount.sql
docker compose exec db mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" < /tmp/quote_discount.sql
```

执行完成后即可在报价编辑页使用折扣功能。

> 自 v2.1.0 起，也可以在服务器上执行 `php init.php install` 或通过初始化精灵补齐 `discount_cents` 字段，适合无法离线执行 SQL 时使用。

## 📖 使用指南

### 首次使用

1. 登录系统
2. 设置公司信息
3. 创建客户
4. 添加产品/服务
5. 前往「设置 → 账号与安全」修改管理员密码或新增团队帐号
6. 创建报价单

## 📦 更新日志

### v2.0.1 (2025-11-06)

- ✅ 系统设置栏位与资料库结构对齐，新增时区与联系方式支持
- ✅ 报价单编号流程引用最新设定前缀并修正存储过程调用
- ✅ 打印版面启用自动列印并补齐 Noto Sans TC 字体
- ✅ CSV / JSON 匯出遵循契约格式，时间戳改用 ISO 8601 UTC

### v0.1.0 (2025-11-05)

- ✨ 初始版本发布
- ✨ 完整的报价管理功能
- ✨ iOS 风格界面设计
- ✨ Dark Mode 支持
- ✨ A4 格式打印输出
- ✨ 数据导出功能
- ✨ 安全特性完整实现

## 📄 许可证

MIT License

## 📞 支持

- 项目类型: 单体 Web 应用
- 部署环境: Linux + 宝塔面板
- 技术栈: PHP 8.3 + MySQL + Nginx

**快速链接**:
- 📖 [完整文档](specs/002-integrated-quote-system/)
- 🎯 [任务清单](specs/002-integrated-quote-system/tasks.md)
- 🚀 [快速开始](specs/002-integrated-quote-system/quickstart.md)
