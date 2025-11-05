# API Contract: Data Export

**Version**: v1.0
**Base Path**: `/exports/`
**Authentication**: Required (session-based)

## GET /exports/export-customers.php

导出客户数据为 CSV 或 JSON 格式。

### Query Parameters

```json
{
  "format": "string (optional, enum: ['csv', 'json'], default: 'csv')"
}
```

### Response 200 OK

**Content-Type: text/csv** (format=csv)

```csv
ID,客户名称,税务登记号,邮箱,电话,账单地址,收货地址,备注,状态,创建时间
1,ABC 公司,12345678,info@abc.com,02-12345678,"台北市信义区","台北市信义区","重点客户",活跃,2025-01-01 10:00:00
```

**Content-Type: application/json** (format=json)

```json
{
  "success": true,
  "exported_at": "ISO 8601 datetime (UTC)",
  "total_records": "integer",
  "data": [
    {
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
  ]
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

## GET /exports/export-products.php

导出产品数据为 CSV 或 JSON 格式。

### Query Parameters

```json
{
  "format": "string (optional, enum: ['csv', 'json'], default: 'csv')"
}
```

### Response 200 OK

**Content-Type: text/csv** (format=csv)

```csv
ID,类型,SKU,名称,单位,币种,单价(分),税率,状态,创建时间
1,product,P001,产品A,pcs,TWD,10000,5.00,启用,2025-01-01 10:00:00
```

**Content-Type: application/json** (format=json)

```json
{
  "success": true,
  "exported_at": "ISO 8601 datetime (UTC)",
  "total_records": "integer",
  "data": [
    {
      "id": "integer (1-9223372036854775807)",
      "type": "string (enum: 'product' | 'service')",
      "sku": "string (required, 1-100 chars)",
      "name": "string (required, 1-255 chars)",
      "unit": "string (optional, max: 20 chars)",
      "currency": "string (optional, default: 'TWD')",
      "unit_price_cents": "integer (required, min: 0)",
      "tax_rate": "decimal (min: 0.00, max: 100.00)",
      "active": "boolean",
      "created_at": "ISO 8601 datetime (UTC)",
      "updated_at": "ISO 8601 datetime (UTC)"
    }
  ]
}
```

---

## GET /exports/export-services.php

导出服务数据为 CSV 或 JSON 格式。

### Query Parameters

```json
{
  "format": "string (optional, enum: ['csv', 'json'], default: 'csv')"
}
```

### Response 200 OK

**Content-Type: text/csv** (format=csv)

```csv
ID,类型,SKU,名称,单位,币种,单价(分),税率,状态,创建时间
1,service,S001,咨询服务,小时,TWD,50000,0.00,启用,2025-01-01 10:00:00
```

**Content-Type: application/json** (format=json)

```json
{
  "success": true,
  "exported_at": "ISO 8601 datetime (UTC)",
  "total_records": "integer",
  "data": [
    {
      "id": "integer (1-9223372036854775807)",
      "type": "string (enum: 'product' | 'service')",
      "sku": "string (required, 1-100 chars)",
      "name": "string (required, 1-255 chars)",
      "unit": "string (optional, max: 20 chars)",
      "currency": "string (optional, default: 'TWD')",
      "unit_price_cents": "integer (required, min: 0)",
      "tax_rate": "decimal (min: 0.00, max: 100.00)",
      "active": "boolean",
      "created_at": "ISO 8601 datetime (UTC)",
      "updated_at": "ISO 8601 datetime (UTC)"
    }
  ]
}
```

---

## GET /exports/export-quotes.php

导出报价单数据为 CSV 或 JSON 格式。

### Query Parameters

```json
{
  "format": "string (optional, enum: ['csv', 'json'], default: 'csv')",
  "date_from": "string (optional, ISO 8601 date format, YYYY-MM-DD)",
  "date_to": "string (optional, ISO 8601 date format, YYYY-MM-DD)"
}
```

### Response 200 OK

**Content-Type: text/csv** (format=csv)

```csv
报价单编号,客户,发出日期,状态,小计(分),税额(分),总计(分),币种
Q-2025-000001,ABC 公司,2025-01-01,草稿,10000,500,10500,TWD
```

**Content-Type: application/json** (format=json)

```json
{
  "success": true,
  "exported_at": "ISO 8601 datetime (UTC)",
  "total_records": "integer",
  "data": [
    {
      "id": "integer (1-9223372036854775807)",
      "number": "string (required, format: 'Q-YYYY-000001')",
      "customer_name": "string (required, 1-255 chars)",
      "issue_date": "string (ISO 8601 date format)",
      "status": "string (enum: 'draft' | 'sent' | 'accepted' | 'rejected' | 'expired')",
      "subtotal_cents": "integer (min: 0)",
      "tax_amount_cents": "integer (min: 0)",
      "total_cents": "integer (min: 0)",
      "currency": "string (default: 'TWD')",
      "created_at": "ISO 8601 datetime (UTC)",
      "updated_at": "ISO 8601 datetime (UTC)"
    }
  ]
}
```

### Validation Rules

- date_from: 可选，格式 YYYY-MM-DD，筛选此日期之后的报价单
- date_to: 可选，格式 YYYY-MM-DD，筛选此日期之前的报价单
- 如果同时提供 date_from 和 date_to，则 date_from <= date_to

### Export Notes

- CSV 格式使用 UTF-8 编码，支持中文字符
- JSON 格式使用 UTF-8 编码，字段名使用下划线命名
- 导出文件包含导出时间戳和总记录数
- 金额以分为单位导出，便于程序处理
