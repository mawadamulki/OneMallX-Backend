<?php

/**
 * Generate a one-page, report-friendly ERD image from docs/erd.dbml relationships.
 */

$root = dirname(__DIR__);
$docs = $root . '/docs';
$dbmlPath = $docs . '/erd.dbml';

if (! file_exists($dbmlPath)) {
    fwrite(STDERR, "Missing docs/erd.dbml — run scripts/generate-erd.php first.\n");
    exit(1);
}

$content = file_get_contents($dbmlPath);
preg_match_all('/^Ref:\s+(\w+)\.(\w+)\s+>\s+(\w+)\.(\w+)/m', $content, $matches, PREG_SET_ORDER);

$allRefs = [];
foreach ($matches as $m) {
    $allRefs[] = [
        'from' => $m[1],
        'col' => $m[2],
        'to' => $m[3],
    ];
}

// Core entities only — readable on a single report page.
$coreTables = [
    'users',
    'malls', 'floors', 'areas', 'business_categories', 'locations',
    'stores', 'products', 'product_variants', 'categories', 'collections',
    'services', 'service_items', 'employees', 'bookings',
    'baskets', 'orders', 'order_items',
    'payment_methods', 'customer_payments',
    'store_subscriptions', 'service_subscriptions',
    'permissions', 'roles',
];

$coreSet = array_flip($coreTables);
$coreRefs = array_values(array_filter($allRefs, fn ($r) => isset($coreSet[$r['from']]) && isset($coreSet[$r['to']])));

// --- Simplified DBML (table names + PK only) for optional re-export ---
$dbmlReport = "// OneMallX Report ERD (simplified for one-page export)\n";
$dbmlReport .= "// Import at https://dbdiagram.io and export as PNG (scale 2x, landscape)\n\n";

$tableGroups = [
    'Auth' => ['users', 'permissions', 'roles'],
    'Mall' => ['malls', 'floors', 'areas', 'business_categories', 'locations'],
    'Commerce' => ['stores', 'products', 'product_variants', 'categories', 'collections'],
    'Services' => ['services', 'service_items', 'employees', 'bookings'],
    'Orders' => ['baskets', 'orders', 'order_items', 'payment_methods', 'customer_payments'],
    'Subscriptions' => ['store_subscriptions', 'service_subscriptions'],
];

foreach ($coreTables as $table) {
    $dbmlReport .= "Table {$table} {\n  id int [pk, note: 'PK']\n}\n\n";
}

foreach ($tableGroups as $name => $tables) {
    $dbmlReport .= "TableGroup {$name} {\n";
    foreach ($tables as $table) {
        $dbmlReport .= "  {$table}\n";
    }
    $dbmlReport .= "}\n\n";
}

foreach ($coreRefs as $ref) {
    $dbmlReport .= "Ref: {$ref['from']}.id > {$ref['to']}.id\n";
}

file_put_contents($docs . '/erd-report.dbml', $dbmlReport);

// --- Mermaid flowchart (best one-page layout) ---
$mmd = <<<'MMD'
---
title: OneMallX Database — Entity Overview
config:
  theme: neutral
  flowchart:
    curve: basis
    padding: 18
    nodeSpacing: 28
    rankSpacing: 40
  themeVariables:
    fontSize: 18px
    fontFamily: Arial, Helvetica, sans-serif
    lineColor: "#334155"
    primaryTextColor: "#0f172a"
    primaryBorderColor: "#334155"
---
flowchart TB
    subgraph AUTH["Users & Access"]
        users((users))
        roles((roles))
        permissions((permissions))
        roles --- permissions
    end

    subgraph MALL["Mall Structure"]
        malls((malls))
        floors((floors))
        areas((areas))
        business_categories((business_categories))
        locations((locations))
        malls --> floors --> areas
        business_categories --> areas
    end

    subgraph STORE["Store Commerce"]
        stores((stores))
        products((products))
        product_variants((product_variants))
        categories((categories))
        collections((collections))
        stores --> products --> product_variants
        stores --> categories
        stores --> collections
    end

    subgraph SERVICE["Services & Bookings"]
        services((services))
        service_items((service_items))
        employees((employees))
        bookings((bookings))
        services --> service_items
        services --> employees
        services --> bookings
        service_items --> bookings
        employees --> bookings
    end

    subgraph ORDER["Cart & Orders"]
        baskets((baskets))
        orders((orders))
        order_items((order_items))
        payment_methods((payment_methods))
        customer_payments((customer_payments))
        baskets --> orders --> order_items
        payment_methods --> customer_payments
    end

    subgraph SUBS["Subscriptions"]
        store_subscriptions((store_subscriptions))
        service_subscriptions((service_subscriptions))
    end

    users --> malls
    users --> stores
    users --> services
    users --> baskets
    users --> orders
    users --> bookings
    users --> customer_payments

    areas --> stores
    areas --> services
    locations --> services
    locations --> orders

    stores --> order_items
    services --> order_items
    employees --> order_items

    stores --> store_subscriptions
    services --> service_subscriptions

    classDef entity fill:#ffffff,stroke:#334155,stroke-width:2px,color:#0f172a;
    class users,malls,floors,areas,business_categories,locations,stores,products,product_variants,categories,collections,services,service_items,employees,bookings,baskets,orders,order_items,payment_methods,customer_payments,store_subscriptions,service_subscriptions,roles,permissions entity;
MMD;

file_put_contents($docs . '/erd-report.mmd', $mmd);

// --- Mermaid erDiagram variant (relationship-centric) ---
$erLines = ["erDiagram"];
foreach ($coreRefs as $ref) {
    $erLines[] = "    {$ref['to']} ||--o{ {$ref['from']} : \"{$ref['col']}\"";
}
$erMmd = "---\ntitle: OneMallX Database — Core Relationships\nconfig:\n  theme: neutral\n  er:\n    layoutDirection: LR\n  themeVariables:\n    fontSize: 16px\n    fontFamily: Arial, Helvetica, sans-serif\n---\n" . implode("\n", $erLines) . "\n";
file_put_contents($docs . '/erd-report-er.mmd', $erMmd);

// --- Mermaid config for high-DPI PNG export ---
$config = [
    'theme' => 'neutral',
    'flowchart' => [
        'curve' => 'basis',
        'padding' => 18,
        'nodeSpacing' => 28,
        'rankSpacing' => 40,
    ],
    'themeVariables' => [
        'fontSize' => '18px',
        'fontFamily' => 'Arial, Helvetica, sans-serif',
    ],
];
file_put_contents($docs . '/erd-report-config.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Generated report ERD sources:\n";
echo "  - docs/erd-report.dbml\n";
echo "  - docs/erd-report.mmd\n";
echo "  - docs/erd-report-er.mmd\n";
echo "  - docs/erd-report-config.json\n";
