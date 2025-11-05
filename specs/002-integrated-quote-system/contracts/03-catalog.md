# API Contract: Catalog Management (Products & Services)

**Version**: v1.0
**Base Path**: `/products/`, `/services/`
**Authentication**: Required (session-based)

## GET /products/index.php
## GET /services/index.php

获取产品或服务列表，支持按类型筛选。

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

- type: 必填枚举值，'product' 或 'service'
- active: 可选布尔值，null 返回所有记录，true 只返回启用记录，false 只返回禁用记录

---

## POST /products/new.php
## POST /services/new.php

创建新产品或服务。

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
  "message": "{product|service} 创建成功",
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
  "message": "输入数据验证失败",
  "errors": {
    "sku": ["SKU 已存在，请使用其他 SKU"],
    "unit_price_cents": ["单价必须大于等于 0"],
    "tax_rate": ["税率必须在 0.00-100.00 之间"]
  }
}
```

### Validation Rules

- type: 必填枚举值，'product' 或 'service'
- sku: 必填，1-100 个字符，同一 org_id 下必须唯一
- name: 必填，1-255 个字符，不能为空白
- unit: 可选，0-20 个字符，默认 'pcs'
- currency: 可选，默认为 'TWD'，仅支持 TWD
- unit_price_cents: 必填，正整数或 0（单位：分）
- tax_rate: 可选，0.00-100.00 之间的小数，默认 0.00
- active: 可选布尔值，默认 true
- csrf_token: 必填，64 位十六进制字符串

---

## GET /products/edit.php?id={id}
## GET /services/edit.php?id={id}

获取产品或服务编辑表单数据。

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

更新产品或服务信息。

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
  "message": "{product|service} 信息更新成功"
}
```

### Response 400 Bad Request

```json
{
  "success": false,
  "error": "VALIDATION_ERROR",
  "message": "输入数据验证失败",
  "errors": {
    "sku": ["SKU 已存在，请使用其他 SKU"]
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

搜索目录项（供报价单选择使用）。

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
