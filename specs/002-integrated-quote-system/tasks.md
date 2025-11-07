---

description: "Task list for Quotabase-Lite Integrated Quote Management System implementation"

---

# Tasks: Quotabase-Lite Integrated Quote Management System

**Input**: Design documents from `/specs/002-integrated-quote-system/`
**Prerequisites**: plan.md, spec.md, data-model.md, contracts/

**Tests**: Manual testing (未使用自動化測試框架) - NO automated test tasks included

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure for PHP 8.3 single web application

 - [X] T001 Create project directory structure per implementation plan (customers/, products/, services/, quotes/, settings/, partials/, assets/, helpers/, exports/)
 - [X] T002 Create config.php.sample with database, security, and timezone configuration
 - [X] T003 Create schema.sql with all 7 entities (Organizations, Customers, CatalogItems, Quotes, QuoteItems, QuoteSequences, Settings)
 - [X] T004 Create db.php with PDO connection and error handling
 - [X] T005 Create helpers/functions.php with utility functions (h() for XSS escape, format_currency_cents(), etc.)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T006 Create partials/ui.php with shared UI components (header, bottom navigation, footer)
- [X] T007 Create index.php redirecting to quotes/index.php
- [X] T008 Implement authentication system (login.php, logout.php, session management)
- [X] T009 Create init.php for system initialization (default org, settings, quote sequence)
- [X] T010 [P] Create assets/style.css with iOS-like styling, Dark Mode support, and print media queries
- [X] T011 Create database storage procedure next_quote_number() for annual quote numbering

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - iOS-like Bottom Tab Navigation (Priority: P1) 🎯 MVP START

**Goal**: Implement iOS-style bottom Tab navigation with highlighting, Dark Mode, and Safe-Area support

**Independent Test**: Verify navigation visible on all non-print pages, current Tab highlights correctly, Dark Mode switches automatically, Safe-Area適配 works on mobile

- [X] T012 [P] [US1] Implement partials/ui.php bottom navigation component (5 tabs: quotes, products, services, customers, settings)
- [X] T013 [US1] Add CSS styling for bottom navigation in assets/style.css (iOS style, 44px tap targets, Safe-Area)
- [X] T014 [US1] Add Dark Mode support in assets/style.css (prefers-color-scheme: dark, proper contrast)
- [X] T015 [US1] Implement navigation highlighting logic in partials/ui.php (detect current page, highlight active tab)
- [X] T016 [US1] Create quotes/index.php with navigation integration (US4 base page, but needed for nav testing)
- [X] T017 [US1] Hide navigation on print pages (quotes/print.php) - CSS @media print rule

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently

---

## Phase 4: User Story 2 - Customer Management (Priority: P1)

**Goal**: Complete CRUD system for managing customer information with XSS protection

**Independent Test**: Create customer, edit customer, view customer list with pagination, verify XSS protection

- [X] T018 [P] [US2] Implement Customer model operations in helpers/functions.php (Customer CRUD functions)
- [X] T019 [US2] Create customers/index.php with list view, pagination, and search
- [X] T020 [US2] Create customers/new.php with form and validation (name, tax_id, email, phone, addresses)
- [X] T021 [US2] Create customers/edit.php?id=X with pre-filled form
- [X] T022 [US2] Create customers/view.php?id=X with detail view
- [X] T023 [US2] Add XSS protection using h() function for all customer data output
- [X] T024 [US2] Implement CSRF token validation for all customer POST forms

**Checkpoint**: At this point, User Story 2 should be fully functional and testable independently

---

## Phase 5: User Story 3 - Products & Services Catalog Management (Priority: P1)

**Goal**: Unified catalog system using shared table structure with type field to distinguish products/services

**Independent Test**: Create product, create service, view filtered lists by type, verify SKU uniqueness, test price formatting

- [X] T025 [P] [US3] Implement CatalogItem model operations in helpers/functions.php (product/service CRUD)
- [X] T026 [US3] Create products/index.php with type=product filter and list view
- [X] T027 [US3] Create products/new.php with default type=product, SKU validation, price in cents
- [X] T028 [US3] Create products/edit.php?id=X with pre-filled form
- [X] T029 [US3] Create services/index.php with type=service filter and list view
- [X] T030 [US3] Create services/new.php with default type=service (SKU, name, price, tax_rate)
- [X] T031 [US3] Create services/edit.php?id=X with pre-filled form
- [X] T032 [US3] Implement SKU uniqueness validation (same org_id constraint)
- [X] T033 [US3] Implement price formatting (cents to currency display, e.g., 1000 → ¥1000.00)

**Checkpoint**: At this point, User Stories 1, 2, AND 3 should all work independently

---

## Phase 6: User Story 4 - Quote Creation & Management (Priority: P1) 🎯 CORE BUSINESS

**Goal**: Full quote creation workflow with transaction safety, automatic numbering, and amount calculations

**Independent Test**: Create quote with multiple items, verify number generation, test calculations, verify transaction rollback on error

- [X] T034 [P] [US4] Implement Quote model operations in helpers/functions.php (quote CRUD with transactions)
- [X] T035 [P] [US4] Implement QuoteItem model operations in helpers/functions.php (quote items CRUD)
- [X] T036 [US4] Create quotes/index.php with status filtering and pagination
- [X] T037 [US4] Create quotes/new.php with customer selection, dynamic item addition, calculation preview
- [X] T038 [US4] Implement quote creation transaction logic (atomic: main record + items + number generation)
- [X] T039 [US4] Create quotes/view.php?id=X with complete quote details (customer, items, amounts, status)
- [X] T040 [US4] Create quotes/edit.php?id=X allowing status changes (draft→sent, sent→accepted/rejected/expired)
- [X] T041 [US4] Implement automatic quote number generation via next_quote_number() storage procedure
- [X] T042 [US4] Implement amount calculations (subtotal, tax, total) with precision validation
- [X] T043 [US4] Implement SELECT...FOR UPDATE locking for concurrent quote number generation
- [X] T044 [US4] Add comprehensive error handling and rollback for failed quote creation

**Checkpoint**: At this point, User Stories 1-4 should all work independently and together as complete system

---

## Phase 7: User Story 5 - Settings Management (Priority: P2)

**Goal**: System configuration interface for company info, numbering prefix, defaults, and print terms

**Independent Test**: Update settings, verify changes appear in printed quotes and new quote defaults

- [X] T045 [P] [US5] Implement Settings model operations in helpers/functions.php (settings CRUD)
- [X] T046 [US5] Create settings/index.php with form for all configuration options
- [X] T047 [US5] Implement settings save/update functionality with validation
- [X] T048 [US5] Apply settings to quote creation (default tax rate, numbering prefix)
- [X] T049 [US5] Apply settings to print output (company name, address, contact info, terms)

**Checkpoint**: At this point, User Stories 1-5 should all work independently

---

## Phase 8: User Story 6 - Print to PDF (Priority: P2)

**Goal**: Professional A4-formatted print output with proper pagination and hidden navigation

**Independent Test**: Print quote, verify A4 format, check header fixation, confirm navigation hidden, test with terms

- [X] T050 [US6] Create quotes/print.php?id=X with A4-optimized layout and company header
- [X] T051 [US6] Implement print-specific CSS (@media print) with table header fixation (thead table-header-group)
- [X] T052 [US6] Add automatic window.print() trigger on page load
- [X] T053 [US6] Implement print terms display from settings (footer section)
- [X] T054 [US6] Ensure navigation completely hidden on print pages
- [X] T055 [US6] Add break-inside: avoid CSS for table rows to prevent row splitting across pages
- [X] T056 [US6] Configure Noto Sans TC font for proper Chinese character display in print

**Checkpoint**: At this point, all 6 user stories should work independently and as integrated system

---

## Phase 9: Data Export (Priority: P2)

**Goal**: CSV/JSON export functionality for data backup and migration

**Independent Test**: Export customers, products/services, quotes in both CSV and JSON formats

- [X] T057 [P] Create exports/export-customers.php with CSV/JSON output
- [X] T058 [P] Create exports/export-products.php with CSV/JSON output (type=product)
- [X] T059 [P] Create exports/export-services.php with CSV/JSON output (type=service)
- [X] T060 [P] Create exports/export-quotes.php with CSV/JSON output (with date range filter)

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Performance, security, documentation, and integration improvements

- [X] T061 [P] Performance optimization: Add database indexes per data-model.md specifications
- [X] T062 [P] Security hardening: Verify all XSS protection and CSRF validation across all forms
- [X] T063 [P] Error handling: Implement consistent error pages and logging without exposing sensitive data
- [X] T064 [P] Create README.md with setup, configuration, and usage instructions
- [X] T065 [P] Run quickstart.md validation to ensure deployment guide accuracy
  - ✅ 驗證透過 - 文件與檔案完全匹配，部署步驟清晰可行
- [⚠️] T066 [P] Code review: Verify all files follow routing-as-filename principle and ≤300 lines
  - ✅ 路由即檔名原則: 100% 符合
  - ⚠️ 行數限制: 8個檔案超過300行，主要為helpers/functions.php (2196行)
  - 💡 建議: 拆分helpers/functions.php為多個模組檔案
- [X] T067 [P] UI polish: Ensure consistent iOS styling, spacing, and Dark Mode across all pages

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
6. Complete Phase 6: User Story 4 (Quotes) → Complete Business Logic
7. **STOP and VALIDATE**: Test complete quote management workflow
8. Deploy/demo CORE SYSTEM

### Incremental Delivery

1. Complete Setup + Foundational → Foundation ready
2. Add User Story 1 → Test independently → Deploy/Demo (MVP Base UI)
3. Add User Story 2 → Test independently → Deploy/Demo (Customer Management)
4. Add User Story 3 → Test independently → Deploy/Demo (Catalog Management)
5. Add User Story 4 → Test independently → Deploy/Demo (Complete Quote System)
6. Add User Story 5 → Test independently → Deploy/Demo (System Configuration)
7. Add User Story 6 → Test independently → Deploy/Demo (Professional Print)
8. Each story adds value without breaking previous stories

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together
2. Once Foundational is done:
   - Developer A: User Story 1 (Navigation)
   - Developer B: User Story 2 (Customers)
   - Developer C: User Story 3 (Products/Services)
3. Core team completes User Story 4 (Quotes) - most complex
4. Additional developers: User Story 5 (Settings), User Story 6 (Print)
5. Stories complete and integrate independently

---

## Success Metrics

- **SC-001**:底部導航在所有非列印頁面可見且當前 Tab 高亮顯示，點選響應準確率 100%
- **SC-002**: Dark Mode 支援驗證透過，系統能根據 prefers-color-scheme 自動切換主題，文字對比度符合可訪問性標準
- **SC-003**: Safe-Area 適配驗證透過，在移動裝置上導航點選熱區 ≥ 44px，無誤觸問題
- **SC-004**: 管理員能夠在 2 分鐘內完成包含 5 個專案的標準報價單建立
- **SC-005**: 報價單金額計算準確率 100%，小計、稅額、總計與手工計算一致（兩位小數精度）
- **SC-006**: 報價單編號生成具備併發安全性，支援至少 10 個管理員同時建立編號無重複
- **SC-007**: 報價單列表頁面 P95 響應時間 ≤ 200ms
- **SC-008**: 報價單列印輸出支援 A4 格式，10+ 行表格分頁合理，表頭在每頁固定顯示
- **SC-009**: 使用者可以在 Chrome 和 Edge 瀏覽器正常列印報價單並匯出 PDF，中文字元正確顯示
- **SC-010**: 報價單建立流程的事務完整性 100%，系統故障時不會產生不完整的報價單記錄
- **SC-011**: 年度切換時編號自動歸零測試透過
- **SC-012**: XSS 防護驗證透過，客戶名稱或目錄項名稱包含特殊字元時正確顯示無安全漏洞
- **SC-013**: 產品/服務列表頁面 P95 響應時間 ≤ 200ms
- **SC-014**: 設定頁面的所有配置項能正確儲存並在對應功能中生效

---

## Notes

- **[P] tasks** = different files, no dependencies
- **[Story] label** maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- Avoid: vague tasks, same file conflicts, cross-story dependencies that break independence
- **Manual Testing**: All testing is manual - use acceptance scenarios from spec.md to validate each story
- **PHP 8.3**: Zero framework, zero Composer - use core PHP only
- **Financial Data**: All monetary amounts stored in cents (BIGINT UNSIGNED)
- **Transaction Safety**: Quote creation MUST use database transactions with rollback on failure
- **Concurrent Safety**: Quote numbering uses SELECT...FOR UPDATE to prevent duplicates
