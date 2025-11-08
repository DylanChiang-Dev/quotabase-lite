# Tasks: add-catalog-bulk-delete

## 1. 調查
- [ ] 1.1 盤點 `helpers/functions.php` 現有刪除流程與 catalog API
- [ ] 1.2 確認產品、服務列表頁與匯入 UI 的互動限制

## 2. 實作
- [ ] 2.1 新增 `bulk_delete_catalog_items()` 等 helper，確保逐筆驗證與統計輸出
- [ ] 2.2 建立 `api/catalog/bulk-delete.php`，實作 CSRF/登入檢查與 JSON 回應
- [ ] 2.3 更新產品、服務列表頁，加入複選框、工具列按鈕與提示
- [ ] 2.4 撰寫共用前端腳本（含全選、確認、錯誤處理）

## 3. 驗證
- [ ] 3.1 手動測試：成功刪除、部分引用被阻擋、無選取時不可送出
- [ ] 3.2 `php -l` 驗證變更檔案
- [ ] 3.3 `openspec validate add-catalog-bulk-delete --strict`
