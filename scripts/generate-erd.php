<?php

/**
 * One-off ERD generator from Laravel migration files.
 * Outputs DBML (dbdiagram.io) and Mermaid erDiagram.
 */

$migrationsPath = dirname(__DIR__) . '/database/migrations';
$outputDir = dirname(__DIR__) . '/docs';

$files = glob($migrationsPath . '/*.php');
sort($files);

$tables = [];

function ensureTable(array &$tables, string $name): void
{
    if (! isset($tables[$name])) {
        $tables[$name] = ['columns' => [], 'pks' => [], 'fks' => []];
    }
}

function addColumn(array &$tables, string $table, string $col, string $type, bool $pk = false): void
{
    ensureTable($tables, $table);
    $tables[$table]['columns'][$col] = $type;
    if ($pk) {
        $tables[$table]['pks'][] = $col;
    }
}

function extractUpMethod(string $content): string
{
    $start = strpos($content, 'function up(');
    if ($start === false) {
        $start = strpos($content, 'function up (');
    }
    if ($start === false) {
        return $content;
    }

    $down = strpos($content, 'function down(', $start);
    if ($down === false) {
        $down = strpos($content, 'function down (', $start);
    }

    if ($down !== false) {
        return substr($content, $start, $down - $start);
    }

    return substr($content, $start);
}

function extractBalancedBlock(string $text, int $openPos): ?string
{
    $depth = 0;
    $len = strlen($text);
    for ($i = $openPos; $i < $len; $i++) {
        $char = $text[$i];
        if ($char === '{') {
            $depth++;
        } elseif ($char === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($text, $openPos + 1, $i - $openPos - 1);
            }
        }
    }

    return null;
}

function resolveTableName(string $expr): ?string
{
    $expr = trim($expr);
    if (preg_match('/^[\'"]([^\'"]+)[\'"]$/', $expr, $m)) {
        return $m[1];
    }

    $map = [
        '$tableNames[\'permissions\']' => 'permissions',
        '$tableNames["permissions"]' => 'permissions',
        '$tableNames[\'roles\']' => 'roles',
        '$tableNames["roles"]' => 'roles',
        '$tableNames[\'model_has_permissions\']' => 'model_has_permissions',
        '$tableNames["model_has_permissions"]' => 'model_has_permissions',
        '$tableNames[\'model_has_roles\']' => 'model_has_roles',
        '$tableNames["model_has_roles"]' => 'model_has_roles',
        '$tableNames[\'role_has_permissions\']' => 'role_has_permissions',
        '$tableNames["role_has_permissions"]' => 'role_has_permissions',
    ];

    return $map[$expr] ?? null;
}

function resolveVariableColumn(string $expr): ?string
{
    $expr = trim($expr);

    $map = [
        '$pivotPermission' => 'permission_id',
        '$pivotRole' => 'role_id',
        '$columnNames[\'model_morph_key\']' => 'model_id',
        '$columnNames["model_morph_key"]' => 'model_id',
    ];

    return $map[$expr] ?? null;
}

function resolveColumnName(string $expr): ?string
{
    $expr = trim($expr);

    if (preg_match('/^[\'"](\w+)[\'"]$/', $expr, $m)) {
        return $m[1];
    }

    return resolveVariableColumn($expr);
}

function resolveForeignTarget(string $expr): ?string
{
    $expr = trim($expr);

    return resolveTableName($expr) ?? (preg_match('/^[\'"](\w+)[\'"]$/', $expr, $m) ? $m[1] : null);
}

function mapColumnType(string $line): ?array
{
    $patterns = [
        '/\$table->id\s*\(\s*\)/' => ['id', 'bigint', true],
        '/\$table->bigIncrements\s*\(\s*[\'"](\w+)[\'"]\s*\)/' => ['$1', 'bigint', true],
        '/\$table->foreignId\s*\(\s*[\'"](\w+)[\'"]\s*\)/' => ['$1', 'bigint', false],
        '/\$table->unsignedBigInteger\s*\(\s*[\'"](\w+)[\'"]\s*\)/' => ['$1', 'bigint', false],
        '/\$table->unsignedInteger\s*\(\s*[\'"](\w+)[\'"]\s*\)/' => ['$1', 'int', false],
        '/\$table->integer\s*\(\s*[\'"](\w+)[\'"]\s*\)/' => ['$1', 'int', false],
        '/\$table->string\s*\(\s*[\'"](\w+)[\'"]/' => ['$1', 'varchar', false],
        '/\$table->text\s*\(\s*[\'"](\w+)[\'"]\s*\)/' => ['$1', 'text', false],
        '/\$table->boolean\s*\(\s*[\'"](\w+)[\'"]\s*\)/' => ['$1', 'boolean', false],
        '/\$table->date\s*\(\s*[\'"](\w+)[\'"]\s*\)/' => ['$1', 'date', false],
        '/\$table->dateTime\s*\(\s*[\'"](\w+)[\'"]\s*\)/' => ['$1', 'datetime', false],
        '/\$table->timestamp\s*\(\s*[\'"](\w+)[\'"]\s*\)/' => ['$1', 'timestamp', false],
        '/\$table->timestamps\s*\(\s*\)/' => ['__timestamps__', 'timestamps', false],
        '/\$table->softDeletes\s*\(\s*\)/' => ['deleted_at', 'timestamp', false],
        '/\$table->json\s*\(\s*[\'"](\w+)[\'"]\s*\)/' => ['$1', 'json', false],
        '/\$table->decimal\s*\(\s*[\'"](\w+)[\'"]/' => ['$1', 'decimal', false],
        '/\$table->float\s*\(\s*[\'"](\w+)[\'"]/' => ['$1', 'float', false],
        '/\$table->enum\s*\(\s*[\'"](\w+)[\'"]/' => ['$1', 'enum', false],
        '/\$table->morphs\s*\(\s*[\'"](\w+)[\'"]\s*\)/' => ['$1', 'morphs', false],
    ];

    foreach ($patterns as $pattern => $meta) {
        if (preg_match($pattern, $line, $m)) {
            if ($meta[0] === '__timestamps__') {
                return ['created_at', 'timestamp', false, 'extra' => ['updated_at' => 'timestamp']];
            }
            if ($meta[0] === '$1') {
                return [$m[1], $meta[1], $meta[2]];
            }

            return [$meta[0], $meta[1], $meta[2]];
        }
    }

    return null;
}

function parseBlueprintBlock(string $block, string $tableName, array &$tables): void
{
    ensureTable($tables, $tableName);
    $normalized = preg_replace('/\s+/', ' ', $block) ?? $block;

    $statements = preg_split('/;\s*/', $block) ?: [];
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }

        $oneLine = preg_replace('/\s+/', ' ', $statement) ?? $statement;

        foreach (preg_split('/\R/', $statement) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '//')) {
                continue;
            }

            $col = mapColumnType($line);
            if ($col) {
                addColumn($tables, $tableName, $col[0], $col[1], $col[2]);
                if (isset($col['extra']) && is_array($col['extra'])) {
                    foreach ($col['extra'] as $extraCol => $extraType) {
                        addColumn($tables, $tableName, $extraCol, $extraType);
                    }
                }
            } elseif (preg_match('/\$table->unsignedBigInteger\s*\(\s*([^)]+)\s*\)/', $line, $m)) {
                $colName = resolveColumnName(trim($m[1]));
                if ($colName) {
                    addColumn($tables, $tableName, $colName, 'bigint');
                }
            }

            if (preg_match('/\$table->morphs\s*\(\s*[\'"](\w+)[\'"]\s*\)/', $line, $m)) {
                addColumn($tables, $tableName, $m[1] . '_type', 'varchar');
                addColumn($tables, $tableName, $m[1] . '_id', 'bigint');
            }
        }

        if (preg_match('/\$table->foreign\s*\(\s*([^)]+)\s*\)[^;]*->references\s*\(\s*[\'"](\w+)[\'"]\s*\)[^;]*->on\s*\(\s*([^)]+)\s*\)/s', $oneLine, $m)) {
            $col = resolveColumnName(trim($m[1]));
            $ref = resolveForeignTarget(trim($m[3]));
            if ($col && $ref) {
                $tables[$tableName]['fks'][] = ['col' => $col, 'ref' => $ref, 'refCol' => $m[2]];
            }
        }

        if (preg_match('/\$table->foreignId\s*\(\s*[\'"](\w+)[\'"]\s*\)[^;]*->constrained\s*\(\s*[\'"](\w+)[\'"]\s*\)/s', $oneLine, $m)) {
            $tables[$tableName]['fks'][] = ['col' => $m[1], 'ref' => $m[2], 'refCol' => 'id'];
        }
    }
}

function processSchemaCall(string $call, array &$tables): void
{
    if (preg_match('/Schema::drop(?:IfExists)?\s*\(\s*([^)]+)\s*\)/', $call, $m)) {
        $tableName = resolveTableName(trim($m[1]));
        if ($tableName) {
            unset($tables[$tableName]);
        }

        return;
    }

    if (preg_match('/Schema::(create|table)\s*\(\s*([^,]+)\s*,/', $call, $m)) {
        $tableName = resolveTableName(trim($m[2]));
        $bracePos = strpos($call, '{');
        if ($tableName && $bracePos !== false) {
            $callbackBlock = extractBalancedBlock($call, $bracePos);
            if ($callbackBlock !== null) {
                parseBlueprintBlock($callbackBlock, $tableName, $tables);
            }
        }
    }
}

function extractSchemaCall(string $up, int $start): ?array
{
    if (! preg_match('/Schema::(create|table|drop(?:IfExists)?)\s*\(/', $up, $m, PREG_OFFSET_CAPTURE, $start)) {
        return null;
    }

    $callStart = $m[0][1];
    $method = $m[1][0];

    if (str_starts_with($method, 'drop')) {
        $semi = strpos($up, ';', $callStart);
        if ($semi === false) {
            return null;
        }

        return [
            'call' => substr($up, $callStart, $semi - $callStart + 1),
            'next' => $semi + 1,
        ];
    }

    $bracePos = strpos($up, '{', $callStart);
    if ($bracePos === false) {
        return null;
    }

    $callbackBlock = extractBalancedBlock($up, $bracePos);
    if ($callbackBlock === null) {
        return null;
    }

    $callEnd = $bracePos + strlen($callbackBlock) + 2; // closing brace + )
    $semi = strpos($up, ';', $callEnd);
    if ($semi === false) {
        return null;
    }

    return [
        'call' => substr($up, $callStart, $semi - $callStart + 1),
        'next' => $semi + 1,
    ];
}

function processUpMethodInOrder(string $up, array &$tables): void
{
    $offset = 0;
    while ($extracted = extractSchemaCall($up, $offset)) {
        processSchemaCall($extracted['call'], $tables);
        $offset = $extracted['next'];
    }
}

foreach ($files as $file) {
    $content = file_get_contents($file);
    $up = extractUpMethod($content);
    processUpMethodInOrder($up, $tables);
}

ksort($tables);

if (! is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$dbml = "// OneMallX Database ERD\n";
$dbml .= "// Generated from Laravel migrations on " . date('Y-m-d') . "\n";
$dbml .= "// Import at https://dbdiagram.io\n\n";

$refLines = [];

foreach ($tables as $tableName => $meta) {
    if ($meta['columns'] === []) {
        continue;
    }

    $dbml .= "Table {$tableName} {\n";
    foreach ($meta['columns'] as $col => $type) {
        $flags = [];
        if (in_array($col, $meta['pks'], true) || ($col === 'id' && empty($meta['pks']))) {
            $flags[] = 'pk';
        }
        $flagStr = $flags ? ' [' . implode(', ', $flags) . ']' : '';
        $dbml .= "  {$col} {$type}{$flagStr}\n";
    }
    $dbml .= "}\n\n";
}

foreach ($tables as $tableName => $meta) {
    foreach ($meta['fks'] as $fk) {
        if (isset($tables[$fk['ref']])) {
            $refLines[] = "Ref: {$tableName}.{$fk['col']} > {$fk['ref']}.{$fk['refCol']}";
        }
    }
}

$dbml .= implode("\n", array_unique($refLines)) . "\n";
file_put_contents($outputDir . '/erd.dbml', $dbml);

$groups = [
    'Core & Auth' => ['users', 'password_reset_tokens', 'sessions', 'personal_access_tokens', 'permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions'],
    'Mall Structure' => ['malls', 'floors', 'areas', 'business_categories', 'locations'],
    'Stores & Products' => ['stores', 'products', 'product_variants', 'categories', 'product_category', 'attributes', 'attribute_values', 'product_variant_attribute_value', 'collections', 'collection_product', 'inventory_movements'],
    'Services & Bookings' => ['services', 'service_items', 'employees', 'employee_service_item', 'employee_working_days', 'service_working_days', 'bookings'],
    'Cart & Orders' => ['baskets', 'basket_items', 'orders', 'order_items', 'customer_payments', 'payment_methods'],
    'Subscriptions' => ['store_subscription_plans', 'store_plan_prices', 'store_subscriptions', 'store_subscription_payments', 'store_subscription_requests', 'store_subscription_extension_requests', 'store_subscription_new_requests', 'service_subscription_plans', 'service_plan_prices', 'service_subscriptions', 'service_subscription_payments', 'service_subscription_requests', 'service_subscription_extension_requests', 'service_subscription_new_requests'],
    'Engagement' => ['favorite_products', 'rates', 'rate_reports', 'advertisements', 'media'],
    'Analytics' => ['store_daily_stats', 'service_daily_stats', 'platform_daily_stats'],
    'System' => ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'],
];

$mermaid = "# OneMallX Entity Relationship Diagram\n\n";
$mermaid .= "> Generated from Laravel migrations on " . date('Y-m-d') . ".\n\n";
$mermaid .= "Import **`docs/erd.dbml`** at [dbdiagram.io](https://dbdiagram.io/d) for the full interactive diagram.\n\n";

$allFkRefs = [];
foreach ($tables as $tableName => $meta) {
    foreach ($meta['fks'] as $fk) {
        if (isset($tables[$fk['ref']])) {
            $allFkRefs[] = ['from' => $tableName, 'col' => $fk['col'], 'to' => $fk['ref']];
        }
    }
}

foreach ($groups as $groupName => $groupTables) {
    $present = array_values(array_unique(array_filter($groupTables, fn ($t) => isset($tables[$t]))));
    if ($present === []) {
        continue;
    }

    $mermaid .= "## {$groupName}\n\n```mermaid\nerDiagram\n";

    foreach ($present as $t) {
        $cols = array_slice(array_keys($tables[$t]['columns']), 0, 6);
        $lines = [];
        foreach ($cols as $c) {
            $lines[] = "{$c} {$tables[$t]['columns'][$c]}";
        }
        if (count($tables[$t]['columns']) > 6) {
            $lines[] = '...';
        }
        $mermaid .= "    {$t} {\n        " . implode("\n        ", $lines) . "\n    }\n";
    }

    foreach ($allFkRefs as $fk) {
        if (in_array($fk['from'], $present, true) && in_array($fk['to'], $present, true)) {
            $mermaid .= "    {$fk['to']} ||--o{ {$fk['from']} : \"{$fk['col']}\"\n";
        }
    }

    $mermaid .= "```\n\n";
}

$mermaid .= "## All Foreign Key Relationships\n\n";
$mermaid .= "| From Table | Column | To Table |\n";
$mermaid .= "|------------|--------|----------|\n";
foreach ($allFkRefs as $fk) {
    $mermaid .= "| `{$fk['from']}` | `{$fk['col']}` | `{$fk['to']}` |\n";
}

$mermaid .= "\n## All Tables (" . count($tables) . ")\n\n";
foreach (array_keys($tables) as $t) {
    $colCount = count($tables[$t]['columns']);
    $fkCount = count($tables[$t]['fks']);
    $mermaid .= "- `{$t}` — {$colCount} columns, {$fkCount} outgoing FKs\n";
}

file_put_contents($outputDir . '/erd.md', $mermaid);

// Cleanup temp sqlite if created
$tempDb = dirname(__DIR__) . '/database/erd_temp.sqlite';
if (file_exists($tempDb)) {
    @unlink($tempDb);
}

echo "Generated:\n";
echo "  - docs/erd.dbml (" . count($tables) . " tables, " . count(array_unique($refLines)) . " refs)\n";
echo "  - docs/erd.md\n";
