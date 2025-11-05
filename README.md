# Quotabase-Lite: 集成报价管理系统

> 专为中小企业设计的 iOS 风格报价单管理系统

[![质量检查](https://img.shields.io/badge/质量检查-100%20通过-brightgreen)](checklists/comprehensive-quality.md)
[![规范完成](https://img.shields.io/badge/规范完成-100%25-blue)](specs/002-integrated-quote-system/)
[![任务清单](https://img.shields.io/badge/实施任务-67个-orange)](specs/002-integrated-quote-system/tasks.md)

## ✨ 核心特性

### 🎯 核心业务
- 📋 **报价单管理**: 快速创建、编辑、管理报价单
- 👥 **客户管理**: 维护完整的客户资料库
- 📦 **产品/服务目录**: 统一管理产品和服务
- ⚙️ **系统设置**: 可配置的公司信息和默认设置

### 🎨 用户体验
- 📱 **iOS 风格**: 现代化界面设计
- 🌙 **Dark Mode**: 自动深色主题切换
- 🎯 **底部 Tab 导航**: 直观的导航体验
- 📄 **A4 打印**: 专业格式输出，支持 PDF 导出

### 🔒 安全特性
- 🔐 **会话认证**: 基于会话的安全认证
- 🛡️ **XSS 防护**: h() 函数输出转义
- 🔒 **CSRF 验证**: 所有 POST 表单验证
- 💾 **事务安全**: 原子性操作，并发安全

### 💰 精确财务
- 💵 **分精度存储**: 避免浮点精度问题
- 🔢 **自动计算**: 小计、税额、总计精确计算
- 📊 **年度编号**: 自动归零的唯一编号系统

## 🏗️ 技术架构

### 技术栈
- **语言**: PHP 8.3 (零框架、零 Composer)
- **数据库**: MySQL 8.0+ / MariaDB 10.6+
- **前端**: 纯 HTML/CSS/JavaScript
- **部署**: 宝塔面板 (aaPanel/BT)

### 架构原则
- 🎯 **极简架构**: 仅使用 PHP 核心功能
- 🗂️ **路由即文件名**: URL 直接映射文件路径
- 📦 **模块化组织**: 每个功能独立目录
- 🔧 **单文件 ≤300 行**: 保持代码可读性

## 📊 项目统计

| 指标 | 数值 |
|------|------|
| 规范文档 | 14 个文件 |
| 用户故事 | 6 个 (P1×4, P2×2) |
| 功能需求 | 12 个 (FR-001 到 FR-012) |
| 成功标准 | 14 个 (SC-001 到 SC-014) |
| 数据实体 | 7 个 |
| API 端点 | 20+ |
| 实施任务 | 67 个 (T001-T067) |
| 质量检查 | 100 项 |
| 并行机会 | 28 个 |

## 🚀 快速开始

### 环境要求
- PHP 8.3+
- MySQL 8.0+ / MariaDB 10.6+
- Nginx / Apache
- 宝塔面板 (aaPanel/BT)

### 安装步骤

```bash
# 1. 克隆项目
git clone https://github.com/YOUR_USERNAME/quotabase-lite.git
cd quotabase-lite

# 2. 配置数据库
cp config.php.sample config.php
# 编辑 config.php 中的数据库配置

# 3. 导入数据库
mysql -u username -p database_name < schema.sql

# 4. 设置权限
chmod 755 .
chmod 644 *.php
```

详细安装指南请参考: [快速开始指南](specs/002-integrated-quote-system/quickstart.md)

## 📚 文档导航

### 核心文档
| 文档 | 描述 |
|------|------|
| [需求规格](specs/002-integrated-quote-system/spec.md) | 用户故事、功能需求、验收标准 |
| [技术计划](specs/002-integrated-quote-system/plan.md) | 架构决策、合规检查 |
| [数据模型](specs/002-integrated-quote-system/data-model.md) | 7 实体设计、索引策略 |
| [实施任务](specs/002-integrated-quote-system/tasks.md) | 67 个实施任务 |

### 支撑文档
| 文档 | 描述 |
|------|------|
| [API 契约](specs/002-integrated-quote-system/contracts/) | 6 模块接口定义 |
| [快速开始](specs/002-integrated-quote-system/quickstart.md) | 部署指南 |
| [质量检查](specs/002-integrated-quote-system/checklists/) | 100 项质量验证 |

## 🎯 实施路线图

### Phase 1-2: 基础设施
- 项目结构、配置、数据库
- 基础组件、认证、UI

### Phase 3-8: 用户故事
- **US1**: iOS 导航 (6 任务)
- **US2**: 客户管理 (7 任务)
- **US3**: 目录管理 (9 任务)
- **US4**: 报价系统 (11 任务) 🎯 核心
- **US5**: 设置管理 (5 任务)
- **US6**: 打印功能 (7 任务)

### Phase 9-10: 增强功能
- 数据导出、优化

## 📈 质量保证

### 质量检查结果
- ✅ **需求完整性**: 100% (10/10)
- ✅ **需求清晰度**: 100% (10/10)
- ✅ **需求一致性**: 100% (8/8)
- ✅ **验收标准**: 100% (7/7)
- ✅ **场景覆盖**: 100% (6/6)
- ✅ **UX 要求**: 100% (10/10)
- ✅ **实施任务**: 100% (9/9)
- ✅ **任务追踪**: 100% (6/6)
- ✅ **非功能需求**: 100% (7/7)
- ✅ **可测量性**: 100% (6/6)
- ✅ **依赖关系**: 100% (6/6)
- ✅ **歧义冲突**: 100% (5/5)
- ✅ **技术清晰度**: 100% (5/5)
- ✅ **集成关注**: 100% (5/5)

**总计**: 100/100 项 ✅ **全部通过**

### 成功指标
- 🎯 导航可用性: 100%
- 🛡️ XSS 防护: 100%
- 🔢 SKU 唯一性: 100%
- 💾 事务完整性: 100%
- 🔒 并发安全: 10+ 用户
- ⚡ 列表加载: P95 ≤ 200ms
- 💰 金额计算: 100% 精确

## 🤝 贡献指南

欢迎提交 Issue 和 Pull Request！

### 开发流程
1. Fork 项目
2. 创建特性分支
3. 提交更改
4. 推送到分支
5. 创建 Pull Request

### 代码规范
- 遵循 [项目宪法](.specify/memory/constitution.md)
- 使用路由即文件名原则
- 保持单文件 ≤ 300 行
- 添加中文注释

## 📄 许可证

MIT License

## 🙏 致谢

感谢所有为这个项目做出贡献的开发者！

---

**项目状态**: ✅ 规范完成，质量检查通过，准备实施

**质量验证**: 100/100 项质量检查通过

**最后更新**: 2025-11-05
