# API Contract: Quote Management

**Version**: v1.0
**Base Path**: `/quotes/`
**Authentication**: Required (session-based)

## GET /quotes/index.php

获取报价单列表，支持分页和筛选。

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

- status: 可选枚举值，筛选特定状态的报价单
- customer_id: 可选整数，筛选特定客户的报价单
- date_from: 可选日期，格式 YYYY-MM-DD，筛选此日期之后的报价单
- date_to: 可选日期，格式 YYYY-MM-DD，筛选此日期之前的报价单

---

## GET /quotes/new.php

获取新建报价单表单数据（客户列表、默认设置）。

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

创建新报价单。

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
  "message": "报价单创建成功",
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
  "message": "输入数据验证失败",
  "errors": {
    "customer_id": ["请选择客户"],
    "items[0].qty": ["数量必须大于 0"],
    "items[0].unit_price_cents": ["单价不能为负数"]
  }
}
```

### Response 500 Internal Server Error

```json
{
  "success": false,
  "error": "TRANSACTION_FAILED",
  "message": "报价单创建失败，请重试"
}
```

### Validation Rules

- customer_id: 必填，必须为有效客户 ID
- issue_date: 必填，日期格式 YYYY-MM-DD，不能为空
- valid_until: 可选，日期格式 YYYY-MM-DD，必须 >= issue_date
- currency: 可选，默认 'TWD'，仅支持 TWD
- title: 可选，0-255 个字符
- notes: 可选，0-2000 个字符
- items: 必填数组，至少 1 个项目
  - catalog_item_id: 可选，如提供则必须是有效的目录项 ID
  - description: 必填，1-500 个字符
  - qty: 必填，大于 0 的小数，最大 9999999999.9999
  - unit: 可选，0-20 个字符
  - unit_price_cents: 必填，正整数或 0（单位：分）
  - tax_rate: 必填，0.00-100.00 之间的小数
- csrf_token: 必填，64 位十六进制字符串

### Transaction Guarantee

创建报价单使用数据库事务，确保：
- 主档和明细同时成功写入
- 任一失败时全数回滚
- 不产生不完整的报价单记录

---

## GET /quotes/view.php?id={id}

查看报价单详细信息。

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
  "message": "报价单不存在"
}
```

---

## POST /quotes/status.php

更新报价单状态。

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
  "message": "报价单状态更新成功"
}
```

### Response 400 Bad Request

```json
{
  "success": false,
  "error": "INVALID_STATUS_TRANSITION",
  "message": "不允许的状态转换"
}
```

### Status Transitions

- draft → sent
- sent → accepted/rejected/expired
- 其他状态不允许直接变更

---

## GET /quotes/print.php?id={id}

生成打印页面（HTML 格式，无导航）。

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
  <title>报价单 #Q-2025-000001</title>
  <!-- Print-optimized styles, no navigation -->
</head>
<body>
  <!-- 报价单内容，A4 格式 -->
  <!-- 包含公司抬头、客户信息、项目明细、金额计算 -->
  <!-- 自动触发 window.print() -->
</body>
</html>
```

### Response 404 Not Found

```json
{
  "success": false,
  "error": "QUOTE_NOT_FOUND",
  "message": "报价单不存在"
}
```
