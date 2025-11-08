## ADDED Requirements

### Requirement: FR-020 QR 電子簽署
系統 MUST 提供以 QR Code 觸發的電子簽署流程，讓客戶可直接在線上完成報價單同意，並保留可稽核的同意證據（token、IP、UA、時間戳）。

#### Scenario: 列印頁生成可掃描 QR
- **WHEN** 管理員開啟 `quotes/print.php?id=...` 或匯出 PDF，**THEN** 系統若無有效 token 會自動生成一次性同意 token，並在列印頁顯示對應 QR Code 與提示文字。

#### Scenario: 客戶掃碼完成簽署
- **WHEN** 客戶掃描 QR 進入公開簽署頁並點擊「同意」，**THEN** 系統必須驗證 token、顯示報價摘要、寫入電子同意紀錄、記錄 IP/UA 及時間，並將報價狀態更新為 `accepted`。

#### Scenario: token 過期或重複使用
- **WHEN** 客戶掃描已過期/已使用 token，**THEN** 簽署頁顯示明確錯誤（例如「連結已失效」），不得重複寫入紀錄，並提供聯絡方式或重新請求的提示。

## MODIFIED Requirements

### Requirement: FR-009 報價單列印輸出
系統 MUST 提供列印功能（`quote_print.php?id=...`），自動觸發 `window.print()`，支援 A4 格式輸出，表頭固定（`thead` 設為 `table-header-group`），行內避免分頁（`break-inside: avoid`），列印頁隱藏底部導航；若報價單啟用電子簽署，列印頁底部須同時顯示電子簽署提示與對應 QR Code。

#### Scenario: 列印頁顯示電子簽署資訊
- **WHEN** 報價單啟用 QR 電子簽署並載入列印頁，**THEN** PDF/列印頁底部展示電子簽署提示區塊與 QR Code 圖像，並保持版面不破壞 A4 排版與既有條款內容。
