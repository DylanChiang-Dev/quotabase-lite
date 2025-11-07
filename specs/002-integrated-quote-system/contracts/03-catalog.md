# API Contract: Catalog Management (Products & Services)

**Version**: v1.0
**Base Path**: `/products/`, `/services/`
**Authentication**: Required (session-based)

## GET /products/index.php
## GET /services/index.php

獲取產品或服務列表，支援按型別篩選。

### Query Parameters

```json
{
  "type": "string (required, enum: ['product', 'service'])",
  "page": "integer (optional, default: 1, min: 1)",
  "limit": "integer (optional, default: 20, min: 1, max: 100)",
  "active": "boolean (optional, default: null - all)",
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
      "type": "string (enum: 'product' | 'service')",
      "sku": "string (required, 1-100 chars)",
      "name": "string (required, 1-255 chars)",
      "unit": "string (optional, max: 20 chars, default: 'pcs')",
      "currency": "string (optional, default: 'TWD')",
      "unit_price_cents": "integer (required, min: 0)",
      "tax_rate": "decimal (optional, min: 0.00, max: 100.00, default: 0.00)",
      "active": "boolean (default: true)",
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

- type: 必填列舉值，'product' 或 'service'
- active: 可選布林值，null 返回所有記錄，true 只返回啟用記錄，false 只返回停用記錄

---

## POST /products/new.php
## POST /services/new.php

建立新產品或服務。

### Request

```json
{
  "type": "string (required, enum: ['product', 'service'])",
  "sku": "string (required, 1-100 chars)",
  "name": "string (required, 1-255 chars)",
  "unit": "string (optional, max: 20 chars, default: 'pcs')",
  "currency": "string (optional, default: 'TWD')",
  "unit_price_cents": "integer (required, min: 0)",
  "tax_rate": "decimal (optional, min: 0.00, max: 100.00, default: 0.00)",
  "active": "boolean (optional, default: true)",
  "csrf_token": "string (required, 64 hex)"
}
```

### Response 201 Created

```json
{
  "success": true,
  "message": "{product|service} 建立成功",
  "data": {
    "id": "integer (1-9223372036854775807)",
    "type": "string (enum: 'product' | 'service')",
    "sku": "string (required, 1-100 chars)",
    "name": "string (required, 1-255 chars)",
    "unit": "string (optional, max: 20 chars)",
    "currency": "string (optional, default: 'TWD')",
    "unit_price_cents": "integer (required, min: 0)",
    "tax_rate": "decimal (optional, min: 0.00, max: 100.00)",
    "active": "boolean (default: true)",
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
    "sku": ["SKU 已存在，請使用其他 SKU"],
    "unit_price_cents": ["單價必須大於等於 0"],
    "tax_rate": ["稅率必須在 0.00-100.00 之間"]
  }
}
```

### Validation Rules

- type: 必填列舉值，'product' 或 'service'
- sku: 必填，1-100 個字元，同一 org_id 下必須唯一
- name: 必填，1-255 個字元，不能為空白
- unit: 可選，0-20 個字元，預設 'pcs'
- currency: 可選，預設為 'TWD'，僅支援 TWD
- unit_price_cents: 必填，正整數或 0（單位：分）
- tax_rate: 可選，0.00-100.00 之間的小數，預設 0.00
- active: 可選布林值，預設 true
- csrf_token: 必填，64 位十六進位制字串

---

## GET /products/edit.php?id={id}
## GET /services/edit.php?id={id}

獲取產品或服務編輯表單資料。

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
    "type": "string (enum: 'product' | 'service')",
    "sku": "string (required, 1-100 chars)",
    "name": "string (required, 1-255 chars)",
    "unit": "string (optional, max: 20 chars)",
    "currency": "string (optional, default: 'TWD')",
    "unit_price_cents": "integer (required, min: 0)",
    "tax_rate": "decimal (optional, min: 0.00, max: 100.00)",
    "active": "boolean"
  }
}
```

### Response 404 Not Found

```json
{
  "success": false,
  "error": "ITEM_NOT_FOUND",
  "message": "{product|service} 不存在"
}
```

---

## POST /products/edit.php?id={id}
## POST /services/edit.php?id={id}

更新產品或服務資訊。

### Path Parameters

```json
{
  "id": "integer (required, 1-9223372036854775807)"
}
```

### Request

```json
{
  "sku": "string (required, 1-100 chars)",
  "name": "string (required, 1-255 chars)",
  "unit": "string (optional, max: 20 chars, default: 'pcs')",
  "currency": "string (optional, default: 'TWD')",
  "unit_price_cents": "integer (required, min: 0)",
  "tax_rate": "decimal (optional, min: 0.00, max: 100.00, default: 0.00)",
  "active": "boolean (optional, default: true)",
  "csrf_token": "string (required, 64 hex)"
}
```

### Response 200 OK

```json
{
  "success": true,
  "message": "{product|service} 資訊更新成功"
}
```

### Response 400 Bad Request

```json
{
  "success": false,
  "error": "VALIDATION_ERROR",
  "message": "輸入資料驗證失敗",
  "errors": {
    "sku": ["SKU 已存在，請使用其他 SKU"]
  }
}
```

### Response 404 Not Found

```json
{
  "success": false,
  "error": "ITEM_NOT_FOUND",
  "message": "{product|service} 不存在"
}
```

---

## GET /api/catalog/search.php

搜尋目錄項（供報價單選擇使用）。

### Query Parameters

```json
{
  "q": "string (required, min: 1, max: 100 chars)",
  "type": "string (optional, enum: ['product', 'service'], default: null - all)",
  "limit": "integer (optional, default: 20, min: 1, max: 50)"
}
```

### Response 200 OK

```json
{
  "success": true,
  "data": [
    {
      "id": "integer (1-9223372036854775807)",
      "type": "string (enum: 'product' | 'service')",
      "sku": "string (required, 1-100 chars)",
      "name": "string (required, 1-255 chars)",
      "unit": "string (optional, max: 20 chars)",
      "unit_price_cents": "integer (required, min: 0)",
      "tax_rate": "decimal (optional, min: 0.00, max: 100.00)",
      "display": "string (formatted: 'SKU - Name (¥XXX.XX)')"
    }
  ]
}
```
