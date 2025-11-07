# API Contract: Customer Management

**Version**: v1.0
**Base Path**: `/customers/`
**Authentication**: Required (session-based)

## GET /customers/index.php

獲取客戶列表，支援分頁和篩選。

### Query Parameters

```json
{
  "page": "integer (optional, default: 1, min: 1)",
  "limit": "integer (optional, default: 20, min: 1, max: 100)",
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
      "name": "string (required, 1-255 chars)",
      "tax_id": "string (optional, max: 50 chars)",
      "email": "string (optional, max: 255 chars, email format)",
      "phone": "string (optional, max: 50 chars)",
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

### Response 401 Unauthorized

```json
{
  "success": false,
  "error": "UNAUTHORIZED",
  "message": "請先登入"
}
```

---

## POST /customers/new.php

建立新客戶。

### Request

```json
{
  "name": "string (required, 1-255 chars)",
  "tax_id": "string (optional, max: 50 chars)",
  "email": "string (optional, max: 255 chars, email format)",
  "phone": "string (optional, max: 50 chars)",
  "billing_address": "string (optional, max: 1000 chars)",
  "shipping_address": "string (optional, max: 1000 chars)",
  "note": "string (optional, max: 1000 chars)",
  "csrf_token": "string (required, 64 hex)"
}
```

### Response 201 Created

```json
{
  "success": true,
  "message": "客戶建立成功",
  "data": {
    "id": "integer (1-9223372036854775807)",
    "name": "string (1-255 chars)",
    "tax_id": "string (optional, max: 50 chars)",
    "email": "string (optional, max: 255 chars)",
    "phone": "string (optional, max: 50 chars)",
    "active": true,
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
    "name": ["客戶名稱不能為空"],
    "email": ["郵箱格式不正確"]
  }
}
```

### Validation Rules

- name: 必填，1-255 個字元，不能為空白
- tax_id: 可選，0-50 個字元
- email: 可選，0-255 個字元，必須為有效郵箱格式
- phone: 可選，0-50 個字元
- billing_address: 可選，0-1000 個字元
- shipping_address: 可選，0-1000 個字元
- note: 可選，0-1000 個字元
- csrf_token: 必填，64 位十六進位制字串

---

## GET /customers/edit.php?id={id}

獲取客戶編輯表單資料。

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
    "name": "string (required, 1-255 chars)",
    "tax_id": "string (optional, max: 50 chars)",
    "email": "string (optional, max: 255 chars)",
    "phone": "string (optional, max: 50 chars)",
    "billing_address": "string (optional, max: 1000 chars)",
    "shipping_address": "string (optional, max: 1000 chars)",
    "note": "string (optional, max: 1000 chars)",
    "active": "boolean"
  }
}
```

### Response 404 Not Found

```json
{
  "success": false,
  "error": "CUSTOMER_NOT_FOUND",
  "message": "客戶不存在"
}
```

---

## POST /customers/edit.php?id={id}

更新客戶資訊。

### Path Parameters

```json
{
  "id": "integer (required, 1-9223372036854775807)"
}
```

### Request

```json
{
  "name": "string (required, 1-255 chars)",
  "tax_id": "string (optional, max: 50 chars)",
  "email": "string (optional, max: 255 chars, email format)",
  "phone": "string (optional, max: 50 chars)",
  "billing_address": "string (optional, max: 1000 chars)",
  "shipping_address": "string (optional, max: 1000 chars)",
  "note": "string (optional, max: 1000 chars)",
  "csrf_token": "string (required, 64 hex)"
}
```

### Response 200 OK

```json
{
  "success": true,
  "message": "客戶資訊更新成功"
}
```

### Response 400 Bad Request

```json
{
  "success": false,
  "error": "VALIDATION_ERROR",
  "message": "輸入資料驗證失敗",
  "errors": {
    "name": ["客戶名稱不能為空"]
  }
}
```

### Response 404 Not Found

```json
{
  "success": false,
  "error": "CUSTOMER_NOT_FOUND",
  "message": "客戶不存在"
}
```

---

## GET /customers/view.php?id={id}

檢視客戶詳細資訊。

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
    "name": "string (required, 1-255 chars)",
    "tax_id": "string (optional, max: 50 chars)",
    "email": "string (optional, max: 255 chars)",
    "phone": "string (optional, max: 50 chars)",
    "billing_address": "string (optional, max: 1000 chars)",
    "shipping_address": "string (optional, max: 1000 chars)",
    "note": "string (optional, max: 1000 chars)",
    "active": "boolean",
    "created_at": "ISO 8601 datetime (UTC)",
    "updated_at": "ISO 8601 datetime (UTC)"
  }
}
```

### Response 404 Not Found

```json
{
  "success": false,
  "error": "CUSTOMER_NOT_FOUND",
  "message": "客戶不存在"
}
```
