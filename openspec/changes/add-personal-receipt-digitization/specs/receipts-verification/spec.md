## ADDED Requirements

### Requirement: QR Payload Composition
每份個人收據 MUST 於版面印製可掃描的 QR，內容足以進行後端驗證。

#### Scenario: Encode QR data
- **GIVEN** 收據已有 `serial`、`amount_twd`、`issued_on`
- **WHEN** 系統準備 QR 內容
- **THEN** 需將 `serial|amount_twd|issued_on|secret_version|token` 以 UTF-8 編碼，並使用 300dpi 以上 QR 圖片嵌入 PDF
- **AND** `issued_on` 以 `YYYY/MM/DD` 格式表示，金額為十進位 TWD（小數兩位或整數）
- **AND** QR 旁須印出簡短說明「掃描或前往 /verify 查驗」

### Requirement: HMAC Token Generation
驗證 token MUST 可由伺服器端重建，並支援 secret 輪換。

#### Scenario: Compute QR token
- **GIVEN** 系統持有 `server_secret` 與其 `secret_version`
- **WHEN** 建立 QR payload
- **THEN** `token = HMAC-SHA256(server_secret, serial + amount_twd + issued_on)` 並以 Base64Url 或 Hex 表示
- **AND** token 長度不少於 32 bytes，整個 payload 須記錄至 `receipts` 表以供稽核
- **AND** 系統需允許多個 secret version 共存；當前版本失效時不得通過驗證

### Requirement: Verification Endpoint
系統 MUST 提供 `/verify` 查驗頁（匿名可讀），以確認序號與 token 是否有效並顯示關鍵資訊。

#### Scenario: Successful verification
- **GIVEN** 使用者掃描 QR 抵達 `/verify?serial=...&token=...`
- **WHEN** token 與資料表中的 `serial`、`amount_twd`、`issued_on`、`secret_version` 重算 HMAC 後一致
- **THEN** 頁面顯示該收據的序號、總金額（TWD）、日期、hash_short 及最新電子同意紀錄摘要
- **AND** 提供下載/檢視 PDF 的受保護連結（簽名 URL 或登入檢查）

#### Scenario: Token mismatch or expired
- **GIVEN** token 異常或超過 5 年保存期限
- **WHEN** `/verify` 比對失敗
- **THEN** 回傳錯誤狀態（例如 `TOKEN_INVALID`、`RECORD_EXPIRED`），不可洩漏實際金額或客戶資訊
- **AND** 記錄查驗事件（序號、IP、狀態）供日後檢視
