# API Contract: Authentication

**Version**: v1.0
**Base Path**: `/`
**Authentication**: Session-based (cookie)

## POST /login.php

登录系统，获取会话令牌。

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
  "message": "登录成功",
  "redirect": "/quotes/"
}
```

### Response 401 Unauthorized

```json
{
  "success": false,
  "error": "INVALID_CREDENTIALS",
  "message": "用户名或密码错误"
}
```

### Validation Rules

- username: 必填，1-50 个字符
- password: 必填，6-100 个字符，区分大小写
- csrf_token: 必填，从表单生成的 CSRF 令牌

---

## POST /logout.php

登出系统，销毁会话。

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

- csrf_token: 必填，从表单生成的 CSRF 令牌
