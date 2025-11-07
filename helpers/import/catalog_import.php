<?php
/**
 * Catalog TXT Import Helper
 *
 * Parses plain-text files and bulk creates/updates catalog items.
 */

if (!defined('QUOTABASE_SYSTEM')) {
    require_once __DIR__ . '/../../config.php';
}

require_once __DIR__ . '/../functions.php';

class CatalogImportResult {
    public int $total = 0;
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public array $errors = [];
    public array $events = [];
    public array $skippedDetails = [];

    private const MAX_EVENT_LOG = 200;

    public function addError(int $line, string $message, string $raw = ''): void {
        $this->errors[] = [
            'line' => $line,
            'message' => $message,
            'raw' => $raw
        ];
    }

    public function addEvent(string $action, array $payload): void {
        if (count($this->events) >= self::MAX_EVENT_LOG) {
            return;
        }
        $this->events[] = array_merge([
            'action' => $action,
        ], $payload);
    }

    public function addSkippedDetail(int $line, string $sku, string $reason, string $type, string $name = ''): void {
        if (count($this->skippedDetails) >= 100) {
            return;
        }
        $this->skippedDetails[] = [
            'line' => $line,
            'sku' => $sku,
            'type' => $type,
            'name' => $name,
            'reason' => $reason
        ];
    }

    public function toArray(): array {
        return [
            'total' => $this->total,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'events' => $this->events,
            'skipped_details' => $this->skippedDetails,
        ];
    }
}

class CatalogImportService {
    private PDO $pdo;
    private string $defaultType;
    private string $strategy;
    private int $orgId;
    private CatalogImportResult $result;

    private const SUPPORTED_TYPES = ['product', 'service'];
    private const SUPPORTED_STRATEGIES = ['skip', 'overwrite'];
    private const COLUMN_ORDER = [
        'type',
        'sku',
        'name',
        'unit',
        'currency',
        'unit_price',
        'tax_rate',
        'category_path',
        'active',
        'description'
    ];

    public function __construct(PDO $pdo, string $defaultType = 'product', string $strategy = 'skip') {
        $defaultType = strtolower($defaultType);
        $strategy = strtolower($strategy);
        if (!in_array($defaultType, self::SUPPORTED_TYPES, true)) {
            throw new InvalidArgumentException('Invalid catalog type');
        }
        if (!in_array($strategy, self::SUPPORTED_STRATEGIES, true)) {
            throw new InvalidArgumentException('Invalid import strategy');
        }

        $this->pdo = $pdo;
        $this->defaultType = $defaultType;
        $this->strategy = $strategy;
        $this->orgId = get_current_org_id();
        $this->result = new CatalogImportResult();
    }

    public function importFromStream($handle, ?string $forcedType = null): CatalogImportResult {
        if (!is_resource($handle)) {
            throw new InvalidArgumentException('Invalid file handle');
        }

        $lineNumber = 0;
        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $rawLine = rtrim($line, "\r\n");
            $trimmed = trim($rawLine);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $this->result->total++;
            $record = $this->parseLine($trimmed, $forcedType ?? $this->defaultType, $lineNumber);
            if (!$record) {
                $this->result->skipped++;
                $this->result->addError($lineNumber, '格式錯誤或欄位缺漏', $rawLine);
                continue;
            }

            try {
                $this->processRecord($record, $lineNumber, $rawLine);
            } catch (Exception $e) {
                $this->result->addError($lineNumber, $e->getMessage(), $rawLine);
                $this->result->skipped++;
            }
        }

        return $this->result;
    }

    private function parseLine(string $line, string $fallbackType, int $lineNumber): ?array {
        $delimiter = $this->detectDelimiter($line);
        if ($delimiter === null) {
            return null;
        }

        $parts = array_map('trim', explode($delimiter, $line));
        $columns = [];
        foreach (self::COLUMN_ORDER as $index => $column) {
            $columns[$column] = $parts[$index] ?? null;
        }

        $type = strtolower($columns['type'] ?: $fallbackType);
        if (!in_array($type, self::SUPPORTED_TYPES, true)) {
            return null;
        }

        $sku = $columns['sku'] ?? '';
        $name = $columns['name'] ?? '';
        $unit = $columns['unit'] ?? '';
        $currency = strtoupper($columns['currency'] ?: 'TWD');
        $unitPrice = $columns['unit_price'] ?? '';
        $taxRate = $columns['tax_rate'] ?? '';
        $categoryPath = $columns['category_path'] ?? null;
        $active = $columns['active'] ?? '1';
        $description = $columns['description'] ?? '';

        if ($sku === '' || !is_valid_sku($sku)) {
            return null;
        }
        if ($name === '') {
            return null;
        }

        $unitPriceCents = $this->normalizeAmount($unitPrice);
        if ($unitPriceCents === null) {
            return null;
        }

        $taxRateValue = $this->normalizeTaxRate($taxRate);
        if ($taxRateValue === null) {
            return null;
        }

        $isActive = $this->normalizeBoolean($active);

        return [
            'type' => $type,
            'sku' => $sku,
            'name' => $name,
            'unit' => $this->normalizeUnit($type, $unit),
            'currency' => $currency ?: 'TWD',
            'unit_price_cents' => $unitPriceCents,
            'tax_rate' => $taxRateValue,
            'category_path' => $categoryPath,
            'active' => $isActive,
            'description' => $description
        ];
    }

    private function detectDelimiter(string $line): ?string {
        if (str_contains($line, "\t")) {
            return "\t";
        }
        if (str_contains($line, '|')) {
            return '|';
        }
        return null;
    }

    private function normalizeAmount(?string $input): ?int {
        if ($input === null || $input === '') {
            return null;
        }
        $normalized = preg_replace('/[^\d\.\-]/', '', $input);
        if ($normalized === '' || !is_numeric($normalized)) {
            return null;
        }
        $value = floatval($normalized);
        if ($value < 0) {
            return null;
        }
        return (int)round($value * 100);
    }

    private function normalizeTaxRate(?string $input): ?float {
        if ($input === null || $input === '') {
            return 0.0;
        }
        if (!is_numeric($input)) {
            return null;
        }
        $value = floatval($input);
        if ($value < 0 || $value > 100) {
            return null;
        }
        return $value;
    }

    private function normalizeBoolean(?string $input): bool {
        $value = strtolower(trim((string)$input));
        if ($value === '' || $value === '1' || $value === 'true' || $value === 'yes' || $value === 'y') {
            return true;
        }
        if ($value === '0' || $value === 'false' || $value === 'no' || $value === 'n') {
            return false;
        }
        return true;
    }

    private function normalizeUnit(string $type, ?string $unit): string {
        $unit = trim((string)$unit);
        if ($unit === '') {
            return $type === 'service' ? 'time' : 'pcs';
        }
        $unit = strtolower($unit);
        if (isset(SERVICE_UNITS[$unit]) || isset(PRODUCT_UNITS[$unit]) || isset(UNITS[$unit])) {
            return $unit;
        }
        return $type === 'service' ? 'time' : 'pcs';
    }

    private function processRecord(array $record, int $lineNumber, string $rawLine): void {
        $existing = $this->findCatalogItem($record['sku'], $record['type']);

        if ($existing) {
            if ($this->strategy === 'skip') {
                $this->result->skipped++;
                $reason = 'SKU 已存在，依策略跳過';
                $this->result->addSkippedDetail($lineNumber, $record['sku'], $reason, $record['type'], $record['name']);
                $this->result->addEvent('skipped', [
                    'line' => $lineNumber,
                    'sku' => $record['sku'],
                    'type' => $record['type'],
                    'name' => $record['name'],
                    'message' => $reason
                ]);
                return;
            }
            $this->updateCatalogItem((int)$existing['id'], $record);
            $this->result->updated++;
            $this->result->addEvent('updated', [
                'line' => $lineNumber,
                'sku' => $record['sku'],
                'type' => $record['type'],
                'name' => $record['name'],
                'message' => '已覆蓋既有資料'
            ]);
        } else {
            $this->createCatalogItem($record);
            $this->result->created++;
            $this->result->addEvent('created', [
                'line' => $lineNumber,
                'sku' => $record['sku'],
                'type' => $record['type'],
                'name' => $record['name'],
                'message' => '新增成功'
            ]);
        }
    }

    private function findCatalogItem(string $sku, string $type): ?array {
        $sql = "SELECT id FROM catalog_items WHERE org_id = ? AND sku = ? AND type = ? LIMIT 1";
        return dbQueryOne($sql, [$this->orgId, $sku, $type]);
    }

    private function createCatalogItem(array $record): void {
        $categoryId = $this->resolveCategory($record['category_path'], $record['type']);
        $payload = [
            'type' => $record['type'],
            'sku' => $record['sku'],
            'name' => $record['name'],
            'unit' => $record['unit'],
            'currency' => $record['currency'],
            'unit_price_cents' => $record['unit_price_cents'],
            'tax_rate' => $record['tax_rate'],
            'category_id' => $categoryId
        ];

        $result = create_catalog_item($payload);
        if (!$result['success']) {
            throw new Exception($result['error']);
        }

        if ($record['active'] === false) {
            dbExecute("UPDATE catalog_items SET active = 0 WHERE id = ?", [$result['id']]);
        }
    }

    private function updateCatalogItem(int $id, array $record): void {
        $categoryId = $this->resolveCategory($record['category_path'], $record['type']);
        $payload = [
            'sku' => $record['sku'],
            'name' => $record['name'],
            'unit' => $record['unit'],
            'currency' => $record['currency'],
            'unit_price_cents' => $record['unit_price_cents'],
            'tax_rate' => $record['tax_rate'],
            'category_id' => $categoryId
        ];

        $result = update_catalog_item($id, $payload);
        if (!$result['success']) {
            throw new Exception($result['error']);
        }

        dbExecute("UPDATE catalog_items SET active = ? WHERE id = ?", [$record['active'] ? 1 : 0, $id]);
    }

    private function resolveCategory(?string $path, string $type): ?int {
        if (!$path) {
            return null;
        }
        $parts = array_values(array_filter(array_map('trim', explode('>', $path))));
        if (empty($parts)) {
            return null;
        }

        $parentId = null;
        $level = 1;
        foreach ($parts as $name) {
            if ($level > 3) {
                break;
            }
            $category = $this->findCategory($name, $type, $parentId);
            if (!$category) {
                $categoryId = $this->createCategory($name, $type, $parentId, $level);
            } else {
                $categoryId = (int)$category['id'];
            }
            $parentId = $categoryId;
            $level++;
        }

        return $parentId;
    }

    private function findCategory(string $name, string $type, ?int $parentId): ?array {
        $sql = "SELECT id FROM catalog_categories WHERE org_id = ? AND type = ? AND name = ?";
        $params = [$this->orgId, $type, $name];
        if ($parentId) {
            $sql .= " AND parent_id = ?";
            $params[] = $parentId;
        } else {
            $sql .= " AND parent_id IS NULL";
        }
        $sql .= " LIMIT 1";

        return dbQueryOne($sql, $params);
    }

    private function createCategory(string $name, string $type, ?int $parentId, int $level): int {
        dbExecute(
            "INSERT INTO catalog_categories (org_id, type, parent_id, level, name, sort_order)
             VALUES (?, ?, ?, ?, ?, 0)",
            [$this->orgId, $type, $parentId, $level, $name]
        );
        return (int)dbLastInsertId();
    }
}
