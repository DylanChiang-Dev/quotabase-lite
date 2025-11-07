# API Contract: Settings Management

**Version**: v1.0
**Base Path**: `/settings/`
**Authentication**: Required (session-based)

## GET /settings/index.php

獲取系統設定。

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

儲存系統設定。

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
  "message": "設定儲存成功"
}
```

### Response 400 Bad Request

```json
{
  "success": false,
  "error": "VALIDATION_ERROR",
  "message": "輸入資料驗證失敗",
  "errors": {
    "quote_prefix": ["編號字首不能為空"],
    "default_tax_rate": ["稅率必須在 0.00-100.00 之間"]
  }
}
```

### Validation Rules

- company_name: 可選，0-255 個字元
- company_address: 可選，0-2000 個字元
- company_contact: 可選，0-255 個字元
- quote_prefix: 可選，0-10 個字元，預設 'Q'
- default_tax_rate: 可選，0.00-100.00 之間的小數，預設 0.00
- print_terms: 可選，0-5000 個字元
- timezone: 可選，0-50 個字元，預設 'Asia/Taipei'
- csrf_token: 必填，64 位十六進位制字串
