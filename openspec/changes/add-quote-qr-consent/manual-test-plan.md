# 手動測試：報價 QR 電子簽署

## 測試前置
1. 透過 `/quotes/new.php` 建立一張報價單（需含至少 1 個項目）。
2. 於 `/quotes/view.php?id=...` 確認報價單存在，狀態維持 `sent` 或 `draft`。

## 測試 1：列印頁產生 QR
1. 造訪 `/quotes/print.php?id=<QUOTE_ID>`。
2. 頁面底部應顯示「電子簽署提示」與 QR Code 圖塊。
3. 重新整理列印頁，QR 連結需維持同一 token，並確認 `quote_consent_tokens` 表新增記錄（status=`active`，expires_at>NOW()）。

## 測試 2：客戶掃碼簽署
1. 從列印頁複製 QR 連結，或於 `/quotes/view.php` 複製「電子簽署連結」。
2. 使用無登入瀏覽器視窗開啟 `quotes/consent.php?token=...`：
   - 應顯示報價摘要、條款與「同意/拒絕」按鈕。
3. 點擊「同意並完成簽署」，預期：
   - 顯示成功提示。
   - `receipt_consents` 表新增 method=`qr` 的紀錄，`quotes.status` 更新為 `accepted`。
   - `quote_consent_tokens` status 轉為 `consumed` 並寫入 consent_id。
4. 回到 `/quotes/view.php`：
   - 電子同意紀錄出現最新條目、列表頁顯示簽署時間。
   - 電子簽署連結區塊顯示「已簽署」且無法複製新連結。

## 測試 3：拒絕流程
1. 針對另一張報價單重複測試前置與測試 1。
2. 在簽署頁改按「拒絕此報價」，預期：
   - 顯示拒絕提示，`quotes.status` 為 `rejected`。
   - `quote_consent_tokens` status 變為 `revoked`，無可用連結。
3. `/quotes/index.php` 對應列「電子簽署」欄應顯示「尚未簽署」。

## 測試 4：過期驗證（可調整系統時間或直接更新資料庫）
1. 將 `quote_consent_tokens.expires_at` 更新為過去時間。
2. 重新造訪簽署頁，應提示連結過期且無法提交；同時 `status` 自動改為 `expired`。
