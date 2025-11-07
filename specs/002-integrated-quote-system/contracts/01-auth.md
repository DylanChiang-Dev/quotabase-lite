# API Contract: Authentication

**Version**: v1.0
**Base Path**: `/`
**Authentication**: Session-based (cookie)

## POST /login.php

登入系統，獲取會話令牌。

### Request

```json
{
  "username": "string (required, 1-50 chars)",
  "password": "string (required, 6-100 chars)",
  "csrf_token": "string (required, 64 hex)"
}
```

### Response 200 OK

```json
{
  "success": true,
  "message": "登入成功",
  "redirect": "/quotes/"
}
```

### Response 401 Unauthorized

```json
{
  "success": false,
  "error": "INVALID_CREDENTIALS",
  "message": "使用者名稱或密碼錯誤"
}
```

### Validation Rules

- username: 必填，1-50 個字元
- password: 必填，6-100 個字元，區分大小寫
- csrf_token: 必填，從表單生成的 CSRF 令牌

---

## POST /logout.php

登出系統，銷燬會話。

### Request

```json
{
  "csrf_token": "string (required, 64 hex)"
}
```

### Response 200 OK

```json
{
  "success": true,
  "message": "已成功登出",
  "redirect": "/login.php"
}
```

### Validation Rules

- csrf_token: 必填，從表單生成的 CSRF 令牌
