# Change Proposal: add-personal-receipt-digitization

## Why
客戶需要在報價單頁面直接產出具法律效力的「個人收據」電子檔，並能提供查驗、稽核與長期保存，以符合內控要求。現有匯出僅提供列印報價單，缺乏 QR 驗證、電子簽章痕跡與同意紀錄，無法支援電子往來收據的保存與驗證作業。

## Goals
- 從報價單資料產生 A4 直式、繁體中文、金額 TWD 的 PDF 個人收據版型，右下角含圖章與簽名區。
- 在 PDF 印製 QR，內容包含 `serial/amount/date` 與 HMAC token，可透過 `/verify` 查驗頁核對。
- 保存相對人電子同意（勾選或回覆）之時間戳、IP 與來源，並讓收據紀錄可追溯 5 年。
- 在收據角落印出 `hash_short`（SHA-256 前 10 bytes 的 Base32），供人工比對；敏感欄位需遮罩。
- 管理 `server_secret` 與收據檔案權限，確保僅授權人員可讀寫，並避免引入複雜外部依賴。

## Non-Goals
- 不實作統一發票或政府 PKI／自然人憑證，以及任何進項扣抵流程。
- 不改變既有報價單計價邏輯與資料結構（僅擴充必要欄位）。
- 不建立新的工作流程（例如簽核流、付款流程），僅專注於收據生成/查驗。

## Proposed Changes
1. 在報價單詳情／列印頁提供「個人收據」匯出動作，串接 PDF 生成器，套用固定標題與「不得做進項扣抵」註記、簽名圖章區與 hash_short 區塊。
2. 新增 QR/HMAC 模組：以 Asia/Taipei 時區、固定日期格式 `YYYY/MM/DD` 將 `serial|amount|date` 與 `HMAC-SHA256(server_secret)` 生成字串，並印於 PDF；同時建立 `/verify?serial=...&token=...` 查驗結果頁。
3. 新增「電子同意紀錄」資料表或欄位，於使用者勾選／記錄同意時寫入 `quote_id`、`contact_id`、`method`、`ip`、`timestamp`、`evidence`（如 email message-id）。
4. 建立稽核與安全策略：生成 PDF 後記錄 `hash_short`、原始 hash、儲存路徑與到期日；檔案權限設為 640 並放置於 `storage/receipts/YYYY/MM/`；server_secret 存於環境變數或 config.php。
5. 撰寫 OpenSpec capability（receipts-personal、receipts-verification、receipts-compliance）與對應任務清單與測試計畫，確保需求可驗證。

## Success Metrics
- 可對任一報價單產出 PDF，檔案含固定標題、QR、圖章與 hash_short，並通過人工檢視。
- `/verify` 提供序號查詢並顯示 QR token 驗證結果、金額與日期，如 token 無效需回報錯誤。
- 每筆收據皆有電子同意紀錄與 audit log，可查到生成時間、IP、hash、檔案路徑。
- `openspec validate add-personal-receipt-digitization --strict` 無錯誤。

## Risks & Mitigations
- **PDF 失敗或字型缺失**：限定 Noto Sans TC，預載字型並在 failure 時提示 fallback HTML。
- **QR/HMAC 洩漏**：`server_secret` 只存於伺服器環境並提供輪換流程（設置版本欄位）。
- **資料外洩**：敏感欄位僅顯示後四碼，並確保檔案權限與存證路徑限制在伺服器端。
- **稽核資料膨脹**：按月分目錄並設定 5 年後可自動清理的 metadata。

## Rollout / Experimentation
- 先於 staging 測試 PDF 版型、QR 響應與 verify API，再於正式環境開啟按鈕。
- 若遇到外部圖章／簽名素材更新，可透過設定檔熱更新，無需重新部署。

## Dependencies
- 需確認現有 PDF 套件（若無則引入 mPDF 或 dompdf 類似輕量依賴）。
- 需新增 `storage/receipts` 目錄並確保備份策略包含該路徑。
