---

description: "Mobile UI polish tasks"

---

# Tasks: Mobile UI Improvements

**Focus**: 提升手機尺寸（≤768px）下的閱讀性與操作性  
**Tests**: 以瀏覽器 DevTools 模擬 iPhone 14 / Pixel 6 手機視窗檢查排版

---

## Phase 1: 分析 & 設計

- [X] T101 檢視 `assets/style.css` 目前的排版與 media query，確認手機尺寸 (<768px) 的主要痛點

## Phase 2: 核心調整

- [X] T201 為 `.page-header`、`.card` 等區塊新增手機專用間距 / 字級 (`@media (max-width: 768px)`)
- [X] T202 優化底部導航 (`.bottom-tabs`)：加大圖示、避免因安全區域造成遮擋
- [X] T203 調整表單元素（`input`, `.form-group`）在手機上的堆疊與觸控尺寸

## Phase 3: 驗證

- [X] T301 於 DevTools 測試 quotes/products/services 等頁面，確保無橫向捲動並符合 iOS/Android 安全區域

---
