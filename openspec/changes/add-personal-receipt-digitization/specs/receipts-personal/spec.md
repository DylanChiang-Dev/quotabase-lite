## ADDED Requirements

### Requirement: Personal Receipt Export
系統 MUST 允許已登入使用者在報價單頁面產出「個人收據」PDF，並沿用報價資料作為收據內容。

#### Scenario: Generate receipt from quote detail
- **GIVEN** 使用者在報價單詳情或列印頁操作，且該報價單狀態為可匯出
- **WHEN** 點擊「匯出個人收據」按鈕
- **THEN** 系統以報價序號作為收據 `serial`，於 Asia/Taipei 時區計算當日 `issued_on`，並生成 A4 直式、繁體中文、金額單位為 TWD 的 PDF
- **AND** PDF 標題固定為「個人收據」，在標題或抬頭區塊顯示「不得做進項扣抵」
- **AND** 流程不可呼叫統一發票或政府 PKI／自然人憑證模組

### Requirement: Layout & Field Composition
PDF 版面 MUST 包含完整的應有欄位，同時維持 KISS 的單頁設計與必要遮罩。

#### Scenario: Render receipt layout
- **GIVEN** 報價單內容含公司、客戶、品項與稅額資訊
- **WHEN** 收據 PDF 被生成
- **THEN** 版面需列出：公司抬頭、客戶姓名／聯絡方式、報價序號、開立日期 (YYYY/MM/DD)、品項表、金額（未稅、稅額、總計）與付款條件
- **AND** 身分證或統一編號僅顯示後四碼，其餘以 `＊` 代替
- **AND** 單位與金額格式需依 TWD 慣例顯示，金額數字與中文大寫可共存，但不得引入額外語系

### Requirement: Stamp & Signature Block
個人收據 MUST 提供視覺蓋章與簽名區，確保可視化驗章需求。

#### Scenario: Embed stamp and signature area
- **GIVEN** 系統擁有公司圖章 PNG 檔與可用字型
- **WHEN** PDF 被渲染
- **THEN** 右下角需顯示圖章 PNG（至少 300 dpi），旁邊保留簽名區（含簽名日期與簽署欄位）
- **AND** 圖章區塊下方保留 `hash_short` 顯示位置（實際內容於 compliance capability 定義）
- **AND** 若缺少圖章檔，系統需以文字「公司印章缺漏」提示並仍保留簽名區
