# API Contract: Settings Management

**Version**: v1.0
**Base Path**: `/settings/`
**Authentication**: Required (session-based)

## GET /settings/index.php

获取系统设置。

### Response 200 OK

```json
{
  "success": true,
  "data": {
    "id": "integer (1-9223372036854775807)",
    "org_id": "integer (1-9223372036854775807)",
    "company_name": "string (optional, max: 255 chars)",
    "company_address": "string (optional, max: 2000 chars)",
    "company_contact": "string (optional, max: 255 chars)",
    "quote_prefix": "string (optional, max: 10 chars, default: 'Q')",
    "default_tax_rate": "decimal (min: 0.00, max: 100.00, default: 0.00)",
    "print_terms": "string (optional, max: 5000 chars)",
    "timezone": "string (optional, max: 50 chars, default: 'Asia/Taipei')",
    "created_at": "ISO 8601 datetime (UTC)",
    "updated_at": "ISO 8601 datetime (UTC)"
  }
}
```

---

## POST /settings/index.php

保存系统设置。

### Request

```json
{
  "company_name": "string (optional, max: 255 chars)",
  "company_address": "string (optional, max: 2000 chars)",
  "company_contact": "string (optional, max: 255 chars)",
  "quote_prefix": "string (optional, max: 10 chars, default: 'Q')",
  "default_tax_rate": "decimal (optional, min: 0.00, max: 100.00, default: 0.00)",
  "print_terms": "string (optional, max: 5000 chars)",
  "timezone": "string (optional, max: 50 chars, default: 'Asia/Taipei')",
  "csrf_token": "string (required, 64 hex)"
}
```

### Response 200 OK

```json
{
  "success": true,
  "message": "设置保存成功"
}
```

### Response 400 Bad Request

```json
{
  "success": false,
  "error": "VALIDATION_ERROR",
  "message": "输入数据验证失败",
  "errors": {
    "quote_prefix": ["编号前缀不能为空"],
    "default_tax_rate": ["税率必须在 0.00-100.00 之间"]
  }
}
```

### Validation Rules

- company_name: 可选，0-255 个字符
- company_address: 可选，0-2000 个字符
- company_contact: 可选，0-255 个字符
- quote_prefix: 可选，0-10 个字符，默认 'Q'
- default_tax_rate: 可选，0.00-100.00 之间的小数，默认 0.00
- print_terms: 可选，0-5000 个字符
- timezone: 可选，0-50 个字符，默认 'Asia/Taipei'
- csrf_token: 必填，64 位十六进制字符串
