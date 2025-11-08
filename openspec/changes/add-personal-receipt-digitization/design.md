# Design: add-personal-receipt-digitization

## Overview
在既有報價單資料模型上覆用所有金額與客戶資訊，新增一個「個人收據」輸出流水：
1. 使用報價資料 + 版面模板生成 PDF。
2. 同步產出 QR payload 與 HMAC token，嵌入 PDF 並存查驗資料。
3. 將電子同意紀錄與稽核 metadata 寫入資料庫，並保存 PDF 於安全路徑。
4. 提供 `/verify` 查驗頁，以 HMAC 驗證 QR 與資料一致性。
所有流程均採單一 helper/service，避免散落在多個頁面；引用點僅在報價詳情或列印頁透過新按鈕呼叫。

## Data Model
- `receipts`（新表）：`id`, `quote_id`, `serial`, `pdf_path`, `amount_cents`, `issued_on`, `hash_full`, `hash_short`, `qr_token`, `qr_secret_version`, `created_at`, `expires_at`（+5 年）, `status`。
- `receipt_consents`：`id`, `quote_id`, `contact_id`, `method` (checkbox/email), `ip_address`, `user_agent`, `evidence_ref` (email id / upload), `consented_at` (Asia/Taipei), `notes`。
- `server_secret_versions`（若需要輪換）：`id`, `version`, `secret`, `activated_at`, `revoked_at`；或採 config 檔 + version 變數。
資料表保持簡潔：僅紀錄查驗所需欄位；items、客戶詳情仍從 quote 表查詢，避免重複。

## PDF & QR Pipeline
1. 在 helper 中載入 quote、company、customer 資料並計算 totals（沿用既有報價邏輯）。
2. 版型以 HTML → PDF（mPDF/dompdf）生成：固定 A4 portrait，標題「個人收據」，副標「不得做進項扣抵」，內容使用 Noto Sans TC。
3. 敏感欄位處理：身份證/統編僅顯示後四碼，地址仍全顯示。
4. 圖章：將 PNG 置於右下角 40mm×40mm，旁邊保留手寫簽名區。
5. `hash_short`: 以 JSON 化的收據 payload（序號、金額、日期、客戶、items）計算 SHA-256 → 取前 10 bytes → Base32，大寫字母與數字；全長 hash 存 DB，不印在 PDF。
6. QR payload：`serial|amount|date|token_version|token`，其中 token = `HMAC-SHA256(server_secret, serial+amount+date)`；版本號便於輪換。

## Verification Flow
- `/verify` 接收 `serial` 與 `token`（可從 QR query string 解析）。
- 從 `receipts` 以序號查詢該筆資料、比對 `token_version` 取得對應 secret，再重算 HMAC。
- 顯示核驗結果（成功/失敗）、金額、日期、hash_short、最近一次電子同意紀錄；失敗時提供錯誤碼（過期、token mismatch、序號不存在）。
- 提供下載/檢視 PDF 連結，僅限具備登入 session 或受保護的 signed URL（短期）。

## Consent Capture
- 報價詳情頁新增「已取得電子同意」勾選；若透過 email 回覆，讓使用者輸入信件 metadata 或上傳截圖。
- 後端寫入 `receipt_consents`，並與 receipt 生成流程關聯：沒有同意紀錄時阻擋生成或提示確認。
- 保留多筆同意紀錄（e.g., 勾選+email），`/verify` 頁僅顯示最新一筆。

## Storage & Security
- 新增 `storage/receipts`，內含 `YYYY/MM/serial.pdf`；Apache/Nginx 限制直接瀏覽，僅透過受控下載。
- 檔案權限 640，目錄 750；CLI/cron 具備讀權限，用於備份與清除過期檔。
- `server_secret` 透過 `config.php` 讀取 `RECEIPT_SERVER_SECRET`，另存 `RECEIPT_SECRET_VERSION`。
- 設置 background job（或 cron 指令）每月檢查超過 5 年的收據並輸出報表供人工審核後刪除。

## Alternatives Considered
- **外部 PDF SaaS**：考量依賴與成本，改採內建 HTML→PDF。
- **PKI/簽章**：需求僅需可驗證與稽核，採 HMAC 與 hash_short 即可，避免複雜度。
- **儲存於 DB BLOB**：不利備份與 PDF 快速提供，因此選擇檔案系統儲存並存 metadata。
