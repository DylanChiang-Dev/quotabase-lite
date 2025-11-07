# API Contract: Data Export

**Version**: v1.0
**Base Path**: `/exports/`
**Authentication**: Required (session-based)

## GET /exports/export-customers.php

匯出客戶資料為 CSV 或 JSON 格式。

### Query Parameters

```json
{
  "format": "string (optional, enum: ['csv', 'json'], default: 'csv')"
}
```

### Response 200 OK

**Content-Type: text/csv** (format=csv)

```csv
ID,客戶名稱,稅務登記號,郵箱,電話,賬單地址,收貨地址,備註,狀態,建立時間
1,ABC 公司,12345678,info@abc.com,02-12345678,"臺北市信義區","臺北市信義區","重點客戶",活躍,2025-01-01 10:00:00
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
  "message": "請先登入"
}
```

---

## GET /exports/export-products.php

匯出產品資料為 CSV 或 JSON 格式。

### Query Parameters

```json
{
  "format": "string (optional, enum: ['csv', 'json'], default: 'csv')"
}
```

### Response 200 OK

**Content-Type: text/csv** (format=csv)

```csv
ID,型別,SKU,名稱,單位,幣種,單價(分),稅率,狀態,建立時間
1,product,P001,產品A,pcs,TWD,10000,5.00,啟用,2025-01-01 10:00:00
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

匯出服務資料為 CSV 或 JSON 格式。

### Query Parameters

```json
{
  "format": "string (optional, enum: ['csv', 'json'], default: 'csv')"
}
```

### Response 200 OK

**Content-Type: text/csv** (format=csv)

```csv
ID,型別,SKU,名稱,單位,幣種,單價(分),稅率,狀態,建立時間
1,service,S001,諮詢服務,小時,TWD,50000,0.00,啟用,2025-01-01 10:00:00
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

匯出報價單資料為 CSV 或 JSON 格式。

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
報價單編號,客戶,發出日期,狀態,小計(分),稅額(分),總計(分),幣種
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

- date_from: 可選，格式 YYYY-MM-DD，篩選此日期之後的報價單
- date_to: 可選，格式 YYYY-MM-DD，篩選此日期之前的報價單
- 如果同時提供 date_from 和 date_to，則 date_from <= date_to

### Export Notes

- CSV 格式使用 UTF-8 編碼，支援中文字元
- JSON 格式使用 UTF-8 編碼，欄位名使用下劃線命名
- 匯出檔案包含匯出時間戳和總記錄數
- 金額以分為單位匯出，便於程式處理
