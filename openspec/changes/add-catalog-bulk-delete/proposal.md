# Change Proposal: add-catalog-bulk-delete

## Why
Product/Service catalog 管理目前僅支援單筆刪除。面對大量匯入或清理測試資料時需要反覆開啟詳情頁，效率低且易出錯。支援批量刪除能加速資料治理，也能降低舊資料污染報價單的風險。

## Goals
- 從產品與服務列表中多選項目並一次刪除。
- 確保符合既有安全性（CSRF、登入驗證、權限檢查），並記錄成功與失敗原因。
- 維持軟刪除策略（active=0），避免破壞已引用於報價單的資料。

## Non-Goals
- 不處理真正的硬刪除或回收站機制。
- 不改動報價單或匯入匯出流程。

## Proposed Changes
1. 在 `helpers/functions.php` 提供批量刪除的 service 函式，對每筆資料檢查型別、關聯報價以及權限。
2. 新增 `api/catalog/bulk-delete.php`，僅接受 POST + CSRF token，回傳刪除／跳過／失敗統計。
3. 產品與服務列表頁加入複選框、全選、批量刪除按鈕，以及前端 JS 控制流程，成功後提示訊息。
4. 在 OpenSpec 中新增 catalog capability 的需求條目與情境，確保未來有測試依據。

## Success Metrics
- 可在單頁選取 ≥1 項目並透過一次 API 呼叫完成刪除。
- 已被報價引用的 SKU 會被阻擋並提示明確原因。
- 透過 `openspec validate add-catalog-bulk-delete --strict` 通過驗證。

## Risks & Mitigations
- **誤刪**：維持軟刪除並加入確認視窗。
- **併發刪除**：API 根據實際刪除結果回報 skipped/failed，避免 silent failure。
- **權限遺漏**：僅允許已登入使用者操作並沿用既有 CSRF token。

## Rollout / Experimentation
- 功能切換：無 feature flag，直接上線。
- 驗證：於 staging/本地完成手動測試（批量刪除成功、引用被拒、自選/全選流程）。

## Dependencies
- 需同步更新 `assets/style.css`（如需新的樣式）與 `partials/ui.php`（若有全域注入 JS）。
