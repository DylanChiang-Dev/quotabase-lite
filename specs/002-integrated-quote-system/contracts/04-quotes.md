# API Contract: Quote Management

**Version**: v1.0
**Base Path**: `/quotes/`
**Authentication**: Required (session-based)

## GET /quotes/index.php

獲取報價單列表，支援分頁和篩選。

### Query Parameters

```json
{
  "page": "integer (optional, default: 1, min: 1)",
  "limit": "integer (optional, default: 20, min: 1, max: 100)",
  "status": "string (optional, enum: ['draft', 'sent', 'accepted', 'rejected', 'expired'])",
  "customer_id": "integer (optional, 1-9223372036854775807)",
  "date_from": "string (optional, ISO 8601 date format, YYYY-MM-DD)",
  "date_to": "string (optional, ISO 8601 date format, YYYY-MM-DD)",
  "search": "string (optional, max: 100 chars)"
}
```

### Response 200 OK

```json
{
  "success": true,
  "data": [
    {
      "id": "integer (1-9223372036854775807)",
      "number": "string (required, format: 'Q-YYYY-000001')",
      "customer_id": "integer (1-9223372036854775807)",
      "customer_name": "string (required, 1-255 chars)",
      "issue_date": "string (ISO 8601 date format)",
      "status": "string (enum: 'draft' | 'sent' | 'accepted' | 'rejected' | 'expired')",
      "title": "string (optional, max: 255 chars)",
      "subtotal_cents": "integer (min: 0)",
      "tax_amount_cents": "integer (min: 0)",
      "total_cents": "integer (min: 0)",
      "currency": "string (default: 'TWD')",
      "created_at": "ISO 8601 datetime (UTC)"
    }
  ],
  "pagination": {
    "current_page": "integer",
    "per_page": "integer",
    "total": "integer",
    "total_pages": "integer",
    "has_next": "boolean",
    "has_prev": "boolean"
  }
}
```

### Validation Rules

- status: 可選列舉值，篩選特定狀態的報價單
- customer_id: 可選整數，篩選特定客戶的報價單
- date_from: 可選日期，格式 YYYY-MM-DD，篩選此日期之後的報價單
- date_to: 可選日期，格式 YYYY-MM-DD，篩選此日期之前的報價單

---

## GET /quotes/new.php

獲取新建報價單表單資料（客戶列表、預設設定）。

### Response 200 OK

```json
{
  "success": true,
  "data": {
    "customers": [
      {
        "id": "integer (1-9223372036854775807)",
        "name": "string (required, 1-255 chars)"
      }
    ],
    "settings": {
      "default_tax_rate": "decimal (min: 0.00, max: 100.00)",
      "default_currency": "string (default: 'TWD')",
      "timezone": "string (default: 'Asia/Taipei')"
    }
  }
}
```

---

## POST /quotes/new.php

建立新報價單。

### Request

```json
{
  "customer_id": "integer (required, 1-9223372036854775807)",
  "issue_date": "string (required, ISO 8601 date format, YYYY-MM-DD)",
  "valid_until": "string (optional, ISO 8601 date format, YYYY-MM-DD)",
  "currency": "string (optional, default: 'TWD')",
  "title": "string (optional, max: 255 chars)",
  "notes": "string (optional, max: 2000 chars)",
  "items": [
    {
      "catalog_item_id": "integer (optional, 1-9223372036854775807)",
      "description": "string (required, 1-500 chars)",
      "qty": "decimal (required, min: 0.0001, max: 9999999999.9999)",
      "unit": "string (optional, max: 20 chars)",
      "unit_price_cents": "integer (required, min: 0)",
      "tax_rate": "decimal (required, min: 0.00, max: 100.00)"
    }
  ],
  "csrf_token": "string (required, 64 hex)"
}
```

### Response 201 Created

```json
{
  "success": true,
  "message": "報價單建立成功",
  "data": {
    "id": "integer (1-9223372036854775807)",
    "number": "string (required, format: 'Q-YYYY-000001')",
    "customer_id": "integer (1-9223372036854775807)",
    "issue_date": "string (ISO 8601 date format)",
    "valid_until": "string (optional, ISO 8601 date format)",
    "currency": "string (default: 'TWD')",
    "status": "string (enum: 'draft')",
    "title": "string (optional, max: 255 chars)",
    "notes": "string (optional, max: 2000 chars)",
    "subtotal_cents": "integer (min: 0)",
    "tax_amount_cents": "integer (min: 0)",
    "total_cents": "integer (min: 0)",
    "items": [
      {
        "id": "integer (1-9223372036854775807)",
        "description": "string (required, 1-500 chars)",
        "qty": "decimal (min: 0.0001, max: 9999999999.9999)",
        "unit": "string (optional, max: 20 chars)",
        "unit_price_cents": "integer (min: 0)",
        "tax_rate": "decimal (min: 0.00, max: 100.00)",
        "line_subtotal_cents": "integer (min: 0)",
        "line_tax_cents": "integer (min: 0)",
        "line_total_cents": "integer (min: 0)"
      }
    ],
    "created_at": "ISO 8601 datetime (UTC)"
  }
}
```

### Response 400 Bad Request

```json
{
  "success": false,
  "error": "VALIDATION_ERROR",
  "message": "輸入資料驗證失敗",
  "errors": {
    "customer_id": ["請選擇客戶"],
    "items[0].qty": ["數量必須大於 0"],
    "items[0].unit_price_cents": ["單價不能為負數"]
  }
}
```

### Response 500 Internal Server Error

```json
{
  "success": false,
  "error": "TRANSACTION_FAILED",
  "message": "報價單建立失敗，請重試"
}
```

### Validation Rules

- customer_id: 必填，必須為有效客戶 ID
- issue_date: 必填，日期格式 YYYY-MM-DD，不能為空
- valid_until: 可選，日期格式 YYYY-MM-DD，必須 >= issue_date
- currency: 可選，預設 'TWD'，僅支援 TWD
- title: 可選，0-255 個字元
- notes: 可選，0-2000 個字元
- items: 必填陣列，至少 1 個專案
  - catalog_item_id: 可選，如提供則必須是有效的目錄項 ID
  - description: 必填，1-500 個字元
  - qty: 必填，大於 0 的小數，最大 9999999999.9999
  - unit: 可選，0-20 個字元
  - unit_price_cents: 必填，正整數或 0（單位：分）
  - tax_rate: 必填，0.00-100.00 之間的小數
- csrf_token: 必填，64 位十六進位制字串

### Transaction Guarantee

建立報價單使用資料庫事務，確保：
- 主檔和明細同時成功寫入
- 任一失敗時全數回滾
- 不產生不完整的報價單記錄

---

## GET /quotes/view.php?id={id}

檢視報價單詳細資訊。

### Path Parameters

```json
{
  "id": "integer (required, 1-9223372036854775807)"
}
```

### Response 200 OK

```json
{
  "success": true,
  "data": {
    "id": "integer (1-9223372036854775807)",
    "number": "string (required, format: 'Q-YYYY-000001')",
    "customer_id": "integer (1-9223372036854775807)",
    "customer": {
      "name": "string (required, 1-255 chars)",
      "tax_id": "string (optional, max: 50 chars)",
      "email": "string (optional, max: 255 chars)",
      "phone": "string (optional, max: 50 chars)",
      "billing_address": "string (optional, max: 1000 chars)"
    },
    "issue_date": "string (ISO 8601 date format)",
    "valid_until": "string (optional, ISO 8601 date format)",
    "currency": "string (default: 'TWD')",
    "status": "string (enum: 'draft' | 'sent' | 'accepted' | 'rejected' | 'expired')",
    "title": "string (optional, max: 255 chars)",
    "notes": "string (optional, max: 2000 chars)",
    "subtotal_cents": "integer (min: 0)",
    "tax_amount_cents": "integer (min: 0)",
    "total_cents": "integer (min: 0)",
    "items": [
      {
        "id": "integer (1-9223372036854775807)",
        "description": "string (required, 1-500 chars)",
        "qty": "decimal (min: 0.0001, max: 9999999999.9999)",
        "unit": "string (optional, max: 20 chars)",
        "unit_price_cents": "integer (min: 0)",
        "tax_rate": "decimal (min: 0.00, max: 100.00)",
        "line_subtotal_cents": "integer (min: 0)",
        "line_tax_cents": "integer (min: 0)",
        "line_total_cents": "integer (min: 0)"
      }
    ],
    "created_at": "ISO 8601 datetime (UTC)",
    "updated_at": "ISO 8601 datetime (UTC)"
  }
}
```

### Response 404 Not Found

```json
{
  "success": false,
  "error": "QUOTE_NOT_FOUND",
  "message": "報價單不存在"
}
```

---

## POST /quotes/status.php

更新報價單狀態。

### Request

```json
{
  "id": "integer (required, 1-9223372036854775807)",
  "status": "string (required, enum: ['draft', 'sent', 'accepted', 'rejected', 'expired'])",
  "csrf_token": "string (required, 64 hex)"
}
```

### Response 200 OK

```json
{
  "success": true,
  "message": "報價單狀態更新成功"
}
```

### Response 400 Bad Request

```json
{
  "success": false,
  "error": "INVALID_STATUS_TRANSITION",
  "message": "不允許的狀態轉換"
}
```

### Status Transitions

- draft → sent
- sent → accepted/rejected/expired
- 其他狀態不允許直接變更

---

## GET /quotes/print.php?id={id}

生成列印頁面（HTML 格式，無導航）。

### Path Parameters

```json
{
  "id": "integer (required, 1-9223372036854775807)"
}
```

### Response 200 OK

```html
<!DOCTYPE html>
<html>
<head>
  <title>報價單 #Q-2025-000001</title>
  <!-- Print-optimized styles, no navigation -->
</head>
<body>
  <!-- 報價單內容，A4 格式 -->
  <!-- 包含公司抬頭、客戶資訊、專案明細、金額計算 -->
  <!-- 自動觸發 window.print() -->
</body>
</html>
```

### Response 404 Not Found

```json
{
  "success": false,
  "error": "QUOTE_NOT_FOUND",
  "message": "報價單不存在"
}
```
