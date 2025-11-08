## 1. 規格與資料設計
- [x] 撰寫 002-integrated-quote-system delta，定義 QR 電子簽署需求與情境。
- [x] 設計 quote consent token/紀錄 schema（資料表/欄位/索引）並納入 proposal。

## 2. Token 與 QR 服務
- [x] 在 helpers 新增 token 產製與驗證函式（含 hash 保存、過期檢查）。
- [ ] 建立 CLI 或背景程式碼以初始化/回收過期 token（若需求）。

## 3. 前台列印頁/PDF
- [ ] 更新 `quotes/print.php` 產生並顯示 QR Code 與電子簽署提示，確保無 token 時可自動建立。
- [ ] 驗證列印/PDF 流程仍符合 A4 版面與舊有樣式。

## 4. 客戶電子簽署流程
- [x] 新增 `quotes/consent.php`，顯示報價摘要與條款，提供同意與拒絕操作。
- [x] 完成同意寫入 receipt_consents、更新 quotes 狀態、記錄 IP/UA 與 token 狀態。
- [x] 處理 token 過期或重複使用時的提示與阻擋。

## 5. 後台可視化與測試
- [x] 在報價詳情/列表顯示最新簽署狀態與時間。
- [x] 更新/新增手動測試腳本與驗證紀錄，確保流程可重現。
