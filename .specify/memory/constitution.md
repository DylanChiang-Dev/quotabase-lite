# Quotabase-Lite Constitution

## Core Principles

### I. Security-First Development (NON-NEGOTIABLE)
所有数据库操作必须使用 PDO 预处理与占位符，严禁 SQL 拼接。所有动态输出使用 h() (htmlspecialchars) 统一转义防止 XSS。所有 POST 表单必须携带 CSRF Token 并验证。config.php 不得置于可被下载的位置，Nginx 禁止存取 *.sql、config.php、隐藏档。生产环境关闭 display_errors，错误写入文件且不得包含个资（姓名、电话、地址、Email、税号）。
**理由**: 中小企业系统必须严格保护客户数据，防止 SQL 注入、XSS 攻击和数据泄露是基本底线。

### II. Precise Financial Data Handling (NON-NEGOTIABLE)
金额入库一律整数（分）：*_cents BIGINT UNSIGNED；显示层才转字符串（两位小数）。计算一致性：总金额 = Σ(行数量 × 单价) ＋ Σ(行税额)；四舍五入规则在每行与总计一致。数据库存 UTC，界面显示默认 Asia/Taipei；日期型字段统一 DATE（如 issue_date）。
**理由**: 财务数据必须精确无误，避免浮点数精度问题导致计算错误，确保合规性和准确性。

### III. Transaction Atomicity (NON-NEGOTIABLE)
建立报价单（主档＋多行项目）必须包在单一数据库交易内；任一失败全数回滚。序号生成使用 SELECT ... FOR UPDATE 保障并发下唯一性；以存储过程 next_quote_number(org_id, out_number) 产生，年度切换自动归零，格式 Q-YYYY-000123。
**理由**: 报价单是核心交易数据，必须保证数据完整性，避免部分写入导致的业务异常。

### IV. Minimalist Architecture (NON-NEGOTIABLE)
零框架、零 Composer：仅使用核心 PHP；共用工具集中于 helpers.php；数据库连接于 db.php。UI 部件可重用：底部 Tab 导航与页首为共用 Partial（例如 partials/ui.php），打印页不得载入导航。单文件可读性：视图档建议 ≤ ~300 行；复杂度过高时必须拆分。路由即文件名，动作清楚（quote_new.php、quote_view.php、quote_print.php 等）。
**理由**: 最小化依赖和复杂度，降低维护成本，便于中小企业自行维护和扩展。

### V. iOS-Like User Experience (NON-NEGOTIABLE)
UI 采用 iOS 设计风格：大标题、卡片布局、底部 Tab 导航。支持 Dark Mode（prefers-color-scheme: dark），文字对比度达标。底部导航需考虑 env(safe-area-inset-bottom)；点击热区 ≥ 44px。图标使用内嵌 SVG，不依赖外部 CDN。所有一般页面导航可见且当前 Tab 高亮；打印页隐藏导航。
**理由**: 提供一致、现代的用户体验，iOS 风格已被用户熟悉，提高操作效率和满意度。

### VI. Print-Optimized Output (NON-NEGOTIABLE)
输出格式：A4 打印／PDF（HTML + print CSS）。打印样式：@page、thead { display: table-header-group; }、break-inside: avoid 为硬性要求。@media print 隐藏导航与不必要元素。推荐使用 Noto Sans TC，浏览器兼容建议（Chrome/Edge）。列表页默认分页 20 笔；索引：quotes(org_id, customer_id, issue_date)、quote_items(quote_id)、products(org_id, sku, type)。
**理由**: 报价单最终需要打印或转 PDF 输出，必须保证 A4 格式正确分页，表头不截断。

## Information Architecture & Module Scope

系统包含六个核心模块：客户（Customers）、产品（Products）、服务（Services）、报价单（Quotes／Quote Items）、年度编号（Sequences）、设置（Settings）。

**底部 Tab 导航（iOS 风格）**：报价／产品／服务／客户／设置。全站共用，打印页不显示。

**页面映射**：
- 报价 → 列表（默认首页）、新建、详情、打印页（隐藏导航）
- 产品 → 列表、建立/编辑
- 服务 → 列表、建立/编辑（MVP 与产品共表，以 type ENUM('product','service') 或 is_service TINYINT(1) 区分）
- 客户 → 列表、建立/编辑
- 设置 → 站点/公司信息、报价编号前缀、默认币别/税率、打印条款文字

**数据模型**：MVP 采用产品/服务同一张表，通过 type 字段区分；列表页按类型过滤。预留 org_id 字段支持未来多租户升级。

**国际化**：先支持繁体中文；文案集中于单一文件以利后续抽换。

## Security & Data Requirements

安全与数据保护是系统的第一优先级。所有数据库操作必须使用 PDO 预处理与占位符，禁止字符串拼接。所有动态输出到 HTML 的内容必须使用 h() 函数进行 HTML 转义，防止 XSS 攻击。所有 POST 表单的表单必须包含 CSRF Token 并在服务器端验证。敏感配置文件不得暴露在 Web 根目录，通过 Nginx 配置禁止直接访问 *.sql 文件、config.php 和隐藏文件。生产环境必须关闭 PHP 的 display_errors，将错误写入日志文件，日志内容不得包含任何个人身份信息。

数据存储采用精确的整数表示法：所有货币金额使用分（cents）为单位存储为 BIGINT UNSIGNED 整数字段（如 price_cents、total_cents），避免浮点数精度问题。显示层才转换为带两位小数的字符串格式。计算规则必须保持一致性：报价单总金额 = Σ(行数量 × 单价) ＋ Σ(行税额)，四舍五入规则在每一行项目和总计算中保持统一。数据库统一存储 UTC 时间，界面显示采用 Asia/Taipei 时区，所有日期型字段统一使用 DATE 类型（如 issue_date、valid_until）。

错误处理采用分级策略：开发环境可以显示详细错误信息便于调试，生产环境必须隐藏错误详情并将错误记录到安全的日志文件中。日志文件定期轮转，不记录敏感客户信息。

## Development Workflow & Deployment

开发流程严格遵循最小化原则。技术栈限制为：PHP 8.3（无框架、无 Composer）、MySQL/MariaDB、Nginx。代码组织结构：页面（UI + 少量业务）直接调用数据库（SQL + Stored Procedure），不引入额外的中间层。共用工具函数集中在 helpers.php，数据库连接逻辑集中于 db.php，单个视图文件行数建议不超过 300 行，超出时必须拆分为多个文件。

文件命名规范采用路由即文件名原则，功能动作明确（customers.php、products.php、quote_new.php、quote_save.php、quote_view.php、quote_print.php）。所有查询操作必须包含 org_id 字段以支持未来多租户升级，当前 MVP 使用 ORG_ID=1。

UI 组件复用：底部 Tab 导航与页首为共用 Partial（例如 partials/ui.php），打印页不得载入导航。Safe-Area 支持底部导航，Dark Mode 支持系统偏好设置。

部署环境要求：使用宝塔（aaPanel/BT）面板进行一键部署，PHP 8.3 必须开启 pdo_mysql 扩展，站点的 Nginx 根目录指向项目根目录。Nginx 配置仅用于静态文件保护和访问控制，不实现复杂的 URL 重写路由魔法。生产环境必须配置 HTTPS，隐藏服务器版本信息。

备份与维护：必须建立每日数据库自动备份机制（保留 7 天），提供 schema.sql 结构和最小数据导出脚本（CSV/JSON 格式）。性能预算：列表页面默认分页 20 条记录，关键字段必须建立索引以保证查询性能。数据库索引策略：quotes 表 (org_id, customer_id, issue_date)、quote_items 表 (quote_id)、products 表 (org_id, sku, type)。

版本管理采用语义化版本号 v0.x.y 格式，变更必须通过 Issue 跟踪，提交消息使用 feat: / fix: / docs: 前缀规范。重大架构决策必须记录在 ADR（Architecture Decision Record）文档中，说明取舍理由。

## Governance

章程是项目的最高准则，所有开发实践、代码规范、部署流程都必须遵循本章程。任何与章程冲突的做法都必须提供充分的业务理由和风险评估，并在 ADR 中记录。

**修正程序**：章程修改需要遵循以下流程：(1) 在 Issue 中提出修改建议，说明修改原因和影响范围；(2) 评估修改的向后兼容性，影响现有功能的修改为 MAJOR 版本升级；(3) 新增原则或扩展现有指导为 MINOR 版本升级；(4) 纯文字修订、措辞优化为 PATCH 版本升级；(5) 获得项目维护者批准后更新版本号和修改日期。

**合规检查**：所有功能开发前必须进行章程合规性检查，确保设计符合安全、数据处理、架构原则和 UI/UX 要求。代码审查必须验证是否遵守 PDO 预处理、XSS 转义、CSRF 防护等安全要求。部署前必须验证环境配置符合最小暴露面原则。UI 组件必须验证 iOS 风格一致性、Dark Mode 支持和导航可见性。

**治理纪律**：项目维护者负责章程的解释和执行。违反章程的代码必须整改，严重的架构偏离需要回滚。鼓励通过 ADR 记录重要的技术决策和取舍理由。版本升级必须同步更新 README.md、schema.sql 等相关文档，确保文档与实际实现保持一致。

**完成定义（DoD）**：所有功能必须通过验收测试（A4 打印输出正确分页且表头不截断、数据库交易回滚测试通过、导航在所有一般页面可见且当前 Tab 高亮、打印页不显示导航），无 P1/P2 级别缺陷且支持回滚，README（安装、设定、部署、备份）保持最新状态。功能完成后必须更新相关文档和变更日志。

**风险与对策**：
- 序号并发：SELECT ... FOR UPDATE 保证唯一；如高并发，改以 AUTO_INCREMENT + 年度前缀逻辑。
- 误删：MVP 禁硬删；以 active 标记或隐藏。
- 印表差异：推荐 Noto Sans TC；README 注明浏览器兼容性（Chrome/Edge）。

**Version**: 2.0.0 | **Ratified**: TODO: 需要确认初始批准日期 | **Last Amended**: 2025-11-05
