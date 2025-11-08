# Tasks: add-personal-receipt-digitization

## 1. 調查與規劃
- [ ] 1.1 盤點 `quotes/*.php` 與匯出功能，確認可重用的報價欄位與現有列印樣式
- [ ] 1.2 選定/確認 PDF 產生方案（既有或新增套件）、字型與圖章檔案來源
- [ ] 1.3 定義 `server_secret` 管理方式與輪換策略，評估現有 config/ENV 支援

## 2. PDF 與 QR 生產
- [ ] 2.1 建立產生個人收據 PDF 的 service/helper，套用 A4 直式版型、固定標題與敏感欄位遮罩
- [ ] 2.2 將 QR 模組整合至 PDF：序號/金額/日期 + HMAC token，並在版面右下角呈現圖章與簽名區
- [ ] 2.3 在報價詳情或列印頁增加「個人收據」動作與 UI 提示，強調「不得做進項扣抵」

## 3. 查驗、同意與稽核
- [ ] 3.1 新增電子同意資料表/欄位與寫入流程（含 IP、時間戳、方式/evidence）
- [ ] 3.2 建立 `/verify` 查驗頁（含 API 與 UI），驗證 QR token 並顯示序號、金額、日期與 hash_short
- [ ] 3.3 記錄 `hash_short`、原始 SHA-256、PDF 路徑、存證到期日與檔案權限設定；確保儲存結構支援 5 年保存

## 4. 驗證
- [ ] 4.1 `php -l` 驗證新增或修改之 PHP 檔
- [ ] 4.2 手動測試：產生 PDF、掃描 QR → `/verify` 成功/失敗案例、電子同意紀錄可查
- [ ] 4.3 `openspec validate add-personal-receipt-digitization --strict`
