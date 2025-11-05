# Implementation Plan: Quotabase-Lite Integrated Quote Management System

**Branch**: `002-integrated-quote-system` | **Date**: 2025-11-05 | **Spec**: [Link to spec.md]
**Input**: Feature specification from `/specs/002-integrated-quote-system/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

构建一个完整的 iOS 风格报价单管理系统，专为中小企业设计。系统提供客户管理、产品/服务目录管理、报价单创建和管理、设置管理以及 PDF 打印功能。采用纯 PHP 8.3（零框架、零 Composer）+ MySQL/MariaDB 技术栈，部署在宝塔面板。关键特性包括：iOS 风格底部 Tab 导航、Dark Mode 支持、Safe-Area 适配、基于会话的用户认证、精确的财务数据处理（整数分存储）、事务原子性和并发安全的年度编号生成。

## Technical Context

**Language/Version**: PHP 8.3
**Primary Dependencies**: None (零框架、零 Composer，仅使用核心 PHP)
**Storage**: MySQL/MariaDB with PDO
**Testing**: Manual testing (未使用自动化测试框架)
**Target Platform**: Linux server (宝塔 aaPanel/BT 部署)
**Project Type**: single/web application
**Performance Goals**: 列表页面 P95 响应时间 ≤ 200ms；2分钟内完成包含5个项目的标准报价单创建
**Constraints**: 零框架、零 Composer、单文件可读性 ≤ ~300行、路由即文件名、金额以分存储、UTC时间存储
**Scale/Scope**: 单租户（ORG_ID=1），支持未来多租户升级；列表默认分页20笔；并发支持至少10个管理员同时创建报价单

## Constitution Check

**GATE**: Must pass before Phase 0 research. Re-check after Phase 1 design.

### Required Constitutional Principles

✅ **Principle I: Security-First Development**
- PDO 预处理语句（已满足）
- XSS 输出转义 h() 函数（已满足）
- CSRF Token 验证（已满足）
- config.php 保护（已满足）
- 错误日志不包含个资（已满足）

✅ **Principle II: Precise Financial Data Handling**
- 金额以分存储（*_cents BIGINT UNSIGNED）（已满足）
- 显示层转换两位小数（已满足）
- UTC 存储、Asia/Taipei 显示（已满足）
- DATE 类型字段（已满足）

✅ **Principle III: Transaction Atomicity**
- 报价单创建使用单一事务（已满足）
- SELECT ... FOR UPDATE 锁（已满足）
- 存储过程 next_quote_number（已满足）

✅ **Principle IV: Minimalist Architecture**
- 零框架、零 Composer（已满足）
- helpers.php 和 db.php（已满足）
- UI 组件可重用 partials（已满足）
- 路由即文件名（已满足）

✅ **Principle V: iOS-Like User Experience**
- 底部 Tab 导航（已满足）
- Dark Mode 支持（已满足）
- Safe-Area 适配（已满足）
- 内嵌 SVG 图标（已满足）
- 点击热区 ≥ 44px（已满足）

✅ **Principle VI: Print-Optimized Output**
- A4 格式（已满足）
- @media print 规则（已满足）
- thead/tfoot 固定（已满足）
- 打印页隐藏导航（已满足）

### Information Architecture Compliance

✅ **六大核心模块**: 客户、产品、服务、报价单、年度编号、设置
✅ **底部 Tab 导航**: 报价/产品/服务/客户/设置
✅ **产品/服务统一表**: type 字段区分
✅ **单租户预留**: org_id 字段

### Deployment & Security Compliance

✅ **宝塔部署**: aaPanel/BT
✅ **Nginx 配置**: 静态文件保护
✅ **HTTPS 要求**: 生产环境
✅ **错误处理**: 生产环境隐藏错误

**检查结果**: ✅ ALL GATES PASS

---

## Constitution Re-Check (Post-Design)

**Date**: 2025-11-05
**Phase**: Phase 1 Complete - Design artifacts created

### Data Model Compliance

✅ **Entity Definition**: All 7 entities fully defined with proper fields
✅ **Relationships**: Foreign keys and indexes properly specified
✅ **org_id Field**: Present in all tables (single-tenant ready)
✅ **Precision Types**: DECIMAL/BIGINT properly chosen for financial data
✅ **Timezone Handling**: UTC storage, Asia/Taipei display configured

### API Contract Compliance

✅ **Authentication**: Session-based login/logout endpoints defined
✅ **Customer Management**: Full CRUD operations with validation
✅ **Catalog Management**: Unified products/services with type field
✅ **Quote Management**: Transactional creation with numbering
✅ **Settings Management**: System configuration endpoints
✅ **Data Export**: CSV/JSON export functionality
✅ **Security**: CSRF token, input validation, error handling

### Quickstart Guide Compliance

✅ **Environment Requirements**: PHP 8.3, MySQL/MariaDB, Nginx specified
✅ **Installation Steps**: 6-step deployment process documented
✅ **Configuration**: config.php template and security checklist
✅ **Nginx Configuration**: Production-ready server block provided
✅ **Initialization**: Automated setup script referenced
✅ **Troubleshooting**: 5 common issues with solutions
✅ **Performance Optimization**: Database indexes, PHP/Nginx tuning
✅ **Maintenance**: Log monitoring, backup procedures

**Re-check Result**: ✅ ALL DESIGN ARTIFACTS COMPLIANT

## Project Structure

### Documentation (this feature)

```text
specs/002-integrated-quote-system/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
│   └── ...
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
# Single PHP Web Application
├── index.php                    # 首页重定向到报价列表
├── login.php                    # 登录页面
├── logout.php                   # 登出
├── customers/
│   ├── index.php                # 客户列表
│   ├── new.php                  # 新建客户
│   ├── edit.php?id=X            # 编辑客户
│   └── view.php?id=X            # 查看客户
├── products/
│   ├── index.php                # 产品列表
│   ├── new.php                  # 新建产品
│   └── edit.php?id=X            # 编辑产品
├── services/
│   ├── index.php                # 服务列表
│   ├── new.php                  # 新建服务
│   └── edit.php?id=X            # 编辑服务
├── quotes/
│   ├── index.php                # 报价列表（首页）
│   ├── new.php                  # 新建报价
│   ├── view.php?id=X            # 查看报价
│   ├── edit.php?id=X            # 编辑报价
│   └── print.php?id=X           # 打印报价（隐藏导航）
├── settings/
│   └── index.php                # 系统设置
├── partials/
│   └── ui.php                   # 共享 UI 组件（底部导航、页首）
├── assets/
│   ├── style.css                # 样式文件（包含 Dark Mode、打印样式）
│   └── js/
│       └── main.js              # JavaScript（可选）
├── helpers/
│   └── functions.php            # 工具函数
├── db.php                       # 数据库连接
├── config.php.sample            # 配置示例
├── schema.sql                   # 数据库结构
├── exports/
│   ├── export-customers.php     # 导出客户数据
│   ├── export-products.php      # 导出产品数据
│   └── export-quotes.php        # 导出报价数据
└── README.md                    # 项目说明
```

**Structure Decision**: Single PHP web application following routing-as-filename principle, with modular directory structure for each feature module. Shared UI components in partials/ directory. Direct database access pattern (no ORM). Assets centralized in assets/ directory.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [None] | [N/A] | [N/A] |

**Notes**: No constitutional violations detected. All features align with established principles.
