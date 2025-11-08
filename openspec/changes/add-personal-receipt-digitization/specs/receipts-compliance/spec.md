## ADDED Requirements

### Requirement: Electronic Consent Logging
系統 MUST 保存相對人對電子收據的同意紀錄，以備稽核。

#### Scenario: Capture checkbox consent
- **GIVEN** 使用者在報價單頁勾選「已取得相對人電子同意」，並輸入必要資訊
- **WHEN** 表單送出
- **THEN** `receipt_consents` 表需新增紀錄，內容至少包含 `quote_id`、`contact_id`、`method`（checkbox/email）、`ip_address`、`user_agent`、`evidence_ref` 以及 Asia/Taipei 時區的 `consented_at`
- **AND** 若缺少同意紀錄，系統需阻擋收據生成或要求再次確認

#### Scenario: Log email reply consent
- **GIVEN** 使用者建立以 email 回覆為證據的同意
- **WHEN** 儲存紀錄
- **THEN** `evidence_ref` 可為 email Message-ID 或附件路徑；敏感內容不寫入 PDF，只存 metadata

### Requirement: Audit Hash & Imprint
系統 MUST 提供可供人工稽核的 hash_short 以及完整 hash 條目。

#### Scenario: Generate hash_short
- **GIVEN** 收據生成程序擁有構成資料（序號、客戶、金額、品項等）
- **WHEN** 完成 PDF 產生
- **THEN** 系統計算 `hash_full = SHA-256(json_payload)`，並將前 10 bytes 轉為 Base32（大寫）形成 `hash_short`
- **AND** `hash_short` 需印於 PDF 角落與 `/verify` 頁面，`hash_full` 只存於資料庫
- **AND** 任何後續重新生成 PDF 需重新計算 hash，並於 audit log 記錄原因

### Requirement: Retention & Security Controls
電子收據與關聯資料 MUST 保存 5 年並限制存取。

#### Scenario: Enforce retention and permissions
- **GIVEN** 收據 PDF 存放於 `storage/receipts/YYYY/MM/`
- **WHEN** 系統寫入檔案
- **THEN** 檔案權限設為 640，目錄 750，並僅允許後端程式／受信任帳戶讀取
- **AND** `receipts` 表需記錄 `expires_at = issued_on + 5 years`，到期後標記為過期且預設查驗失敗
- **AND** server_secret 不可硬編碼於 repo，需自環境變數或安全設定載入，且輪換時保留舊版本供驗證
