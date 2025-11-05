# API Contract: Customer Management

**Version**: v1.0
**Base Path**: `/customers/`
**Authentication**: Required (session-based)

## GET /customers/index.php

获取客户列表，支持分页和筛选。

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
  "message": "请先登录"
}
```

---

## POST /customers/new.php

创建新客户。

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
  "message": "客户创建成功",
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
  "message": "输入数据验证失败",
  "errors": {
    "name": ["客户名称不能为空"],
    "email": ["邮箱格式不正确"]
  }
}
```

### Validation Rules

- name: 必填，1-255 个字符，不能为空白
- tax_id: 可选，0-50 个字符
- email: 可选，0-255 个字符，必须为有效邮箱格式
- phone: 可选，0-50 个字符
- billing_address: 可选，0-1000 个字符
- shipping_address: 可选，0-1000 个字符
- note: 可选，0-1000 个字符
- csrf_token: 必填，64 位十六进制字符串

---

## GET /customers/edit.php?id={id}

获取客户编辑表单数据。

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
  "message": "客户不存在"
}
```

---

## POST /customers/edit.php?id={id}

更新客户信息。

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
  "message": "客户信息更新成功"
}
```

### Response 400 Bad Request

```json
{
  "success": false,
  "error": "VALIDATION_ERROR",
  "message": "输入数据验证失败",
  "errors": {
    "name": ["客户名称不能为空"]
  }
}
```

### Response 404 Not Found

```json
{
  "success": false,
  "error": "CUSTOMER_NOT_FOUND",
  "message": "客户不存在"
}
```

---

## GET /customers/view.php?id={id}

查看客户详细信息。

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
  "message": "客户不存在"
}
```
