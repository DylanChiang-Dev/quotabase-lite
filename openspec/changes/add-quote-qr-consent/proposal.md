# Change: 報價單 QR 電子簽署

## Why
現有流程需列印並人工簽名回傳，耗時且不利遠端客戶作業。需要在 PDF 報價單中提供可掃碼的電子簽署流程，並保留可稽核的同意紀錄，降低回傳阻力。

## What Changes
- 為每張報價單產生一次性同意 token 與 QR Code，於列印頁/PDF 中顯示掃碼提示。
- 新增公開的電子簽署確認頁面，客戶掃碼後可檢視報價摘要並點擊「同意/拒絕」。
- 透過 `receipt_consents` 或新 token 表維護同意證據、IP、UA 與時間，並自動更新報價單狀態。
- 後台報價詳情增加電子簽署紀錄與狀態檢視，以利營運追蹤。

## Impact
- Affected specs: `002-integrated-quote-system`（列印/簽署需求）
- Affected code: `quotes/print.php`, 新增 `quotes/consent.php`, `helpers/` 內 token 產生與簽署服務、資料表（quote_consent_tokens + quotes 欄位）及相關設定頁面/詳細頁。
