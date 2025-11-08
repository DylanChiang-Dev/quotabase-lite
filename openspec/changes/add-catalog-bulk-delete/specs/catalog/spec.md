## ADDED Requirements
### Requirement: Bulk Delete Catalog Items
Catalog 管理者 MUST 能在產品與服務列表中同時刪除多筆項目，並在每筆操作上提供安全驗證與結果說明。

#### Scenario: 成功批量刪除
- **GIVEN** 使用者已登入且勾選 >=1 筆同型別的 catalog 項目
- **WHEN** 使用者點擊「批量刪除」並確認
- **THEN** 系統送出含 CSRF token 的 POST 請求至 `/api/catalog/bulk-delete.php`
- **AND** API 將勾選項目標記為 `active = 0` 並回傳刪除計數
- **AND** 前端顯示成功訊息並重新整理列表

#### Scenario: 項目被報價引用
- **GIVEN** 某勾選項目已被報價單引用
- **WHEN** 進行批量刪除
- **THEN** API MUST 跳過該項並回傳 `skipped` 或 `errors` 訊息，提示「存在關聯報價單」
- **AND** 其他可刪除的項目仍需成功處理

#### Scenario: 無選取或非法請求
- **GIVEN** 使用者未勾選任何項目或請求缺少有效 CSRF token
- **WHEN** 送出批量刪除請求
- **THEN** API MUST 回傳 400/422 錯誤並描述原因，前端需顯示錯誤訊息並維持列表狀態
