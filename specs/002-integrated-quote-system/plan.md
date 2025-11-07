# Implementation Plan: Quotabase-Lite Integrated Quote Management System

**Branch**: `002-integrated-quote-system` | **Date**: 2025-11-05 | **Spec**: [Link to spec.md]
**Input**: Feature specification from `/specs/002-integrated-quote-system/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

構建一個完整的 iOS 風格報價單管理系統，專為中小企業設計。系統提供客戶管理、產品/服務目錄管理、報價單建立和管理、設定管理以及 PDF 列印功能。採用純 PHP 8.3（零框架、零 Composer）+ MySQL/MariaDB 技術棧，部署在寶塔面板。關鍵特性包括：iOS 風格底部 Tab 導航、Dark Mode 支援、Safe-Area 適配、基於會話的使用者認證、精確的財務資料處理（整數分儲存）、事務原子性和併發安全的年度編號生成。

## Technical Context

**Language/Version**: PHP 8.3
**Primary Dependencies**: None (零框架、零 Composer，僅使用核心 PHP)
**Storage**: MySQL/MariaDB with PDO
**Testing**: Manual testing (未使用自動化測試框架)
**Target Platform**: Linux server (寶塔 aaPanel/BT 部署)
**Project Type**: single/web application
**Performance Goals**: 列表頁面 P95 響應時間 ≤ 200ms；2分鐘內完成包含5個專案的標準報價單建立
**Constraints**: 零框架、零 Composer、單檔案可讀性 ≤ ~300行、路由即檔名、金額以分儲存、UTC時間儲存
**Scale/Scope**: 單租戶（ORG_ID=1），支援未來多租戶升級；列表預設分頁20筆；併發支援至少10個管理員同時建立報價單

## Constitution Check

**GATE**: Must pass before Phase 0 research. Re-check after Phase 1 design.

### Required Constitutional Principles

✅ **Principle I: Security-First Development**
- PDO 預處理語句（已滿足）
- XSS 輸出轉義 h() 函式（已滿足）
- CSRF Token 驗證（已滿足）
- config.php 保護（已滿足）
- 錯誤日誌不包含個資（已滿足）

✅ **Principle II: Precise Financial Data Handling**
- 金額以分儲存（*_cents BIGINT UNSIGNED）（已滿足）
- 顯示層轉換兩位小數（已滿足）
- UTC 儲存、Asia/Taipei 顯示（已滿足）
- DATE 型別欄位（已滿足）

✅ **Principle III: Transaction Atomicity**
- 報價單建立使用單一事務（已滿足）
- SELECT ... FOR UPDATE 鎖（已滿足）
- 儲存過程 next_quote_number（已滿足）

✅ **Principle IV: Minimalist Architecture**
- 零框架、零 Composer（已滿足）
- helpers.php 和 db.php（已滿足）
- UI 元件可重用 partials（已滿足）
- 路由即檔名（已滿足）

✅ **Principle V: iOS-Like User Experience**
- 底部 Tab 導航（已滿足）
- Dark Mode 支援（已滿足）
- Safe-Area 適配（已滿足）
- 內嵌 SVG 圖示（已滿足）
- 點選熱區 ≥ 44px（已滿足）

✅ **Principle VI: Print-Optimized Output**
- A4 格式（已滿足）
- @media print 規則（已滿足）
- thead/tfoot 固定（已滿足）
- 列印頁隱藏導航（已滿足）

### Information Architecture Compliance

✅ **六大核心模組**: 客戶、產品、服務、報價單、年度編號、設定
✅ **底部 Tab 導航**: 報價/產品/服務/客戶/設定
✅ **產品/服務統一表**: type 欄位區分
✅ **單租戶預留**: org_id 欄位

### Deployment & Security Compliance

✅ **寶塔部署**: aaPanel/BT
✅ **Nginx 配置**: 靜態檔案保護
✅ **HTTPS 要求**: 生產環境
✅ **錯誤處理**: 生產環境隱藏錯誤

**檢查結果**: ✅ ALL GATES PASS

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
├── index.php                    # 首頁重定向到報價列表
├── login.php                    # 登入頁面
├── logout.php                   # 登出
├── customers/
│   ├── index.php                # 客戶列表
│   ├── new.php                  # 新建客戶
│   ├── edit.php?id=X            # 編輯客戶
│   └── view.php?id=X            # 檢視客戶
├── products/
│   ├── index.php                # 產品列表
│   ├── new.php                  # 新建產品
│   └── edit.php?id=X            # 編輯產品
├── services/
│   ├── index.php                # 服務列表
│   ├── new.php                  # 新建服務
│   └── edit.php?id=X            # 編輯服務
├── quotes/
│   ├── index.php                # 報價列表（首頁）
│   ├── new.php                  # 新建報價
│   ├── view.php?id=X            # 檢視報價
│   ├── edit.php?id=X            # 編輯報價
│   └── print.php?id=X           # 列印報價（隱藏導航）
├── settings/
│   └── index.php                # 系統設定
├── partials/
│   └── ui.php                   # 共享 UI 元件（底部導航、頁首）
├── assets/
│   ├── style.css                # 樣式檔案（包含 Dark Mode、列印樣式）
│   └── js/
│       └── main.js              # JavaScript（可選）
├── helpers/
│   └── functions.php            # 工具函式
├── db.php                       # 資料庫連線
├── config.php.sample            # 配置示例
├── schema.sql                   # 資料庫結構
├── exports/
│   ├── export-customers.php     # 匯出客戶資料
│   ├── export-products.php      # 匯出產品資料
│   └── export-quotes.php        # 匯出報價資料
└── README.md                    # 專案說明
```

**Structure Decision**: Single PHP web application following routing-as-filename principle, with modular directory structure for each feature module. Shared UI components in partials/ directory. Direct database access pattern (no ORM). Assets centralized in assets/ directory.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [None] | [N/A] | [N/A] |

**Notes**: No constitutional violations detected. All features align with established principles.
