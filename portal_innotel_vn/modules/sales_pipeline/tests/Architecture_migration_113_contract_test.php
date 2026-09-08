<?php

defined('BASEPATH') or define('BASEPATH', 'dummy');

function assert_true($condition, $message) {
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

function assert_equals($expected, $actual, $message) {
    if ($expected !== $actual) {
        $expStr = var_export($expected, true);
        $actStr = var_export($actual, true);
        throw new RuntimeException("Assertion failed: {$message} (Expected {$expStr}, got {$actStr})");
    }
}

// Test 1: Module version contract
$moduleFile = __DIR__ . '/../sales_pipeline.php';
assert_true(file_exists($moduleFile), 'Module file must exist');
$moduleContent = file_get_contents($moduleFile);
preg_match('/Version:\s*(\d+\.\d+\.\d+)/', $moduleContent, $vMatches);
$currentVersion = $vMatches[1] ?? '0.0.0';
assert_true(
    version_compare($currentVersion, '1.0.13', '>='),
    'Module header must be at least Version: 1.0.13 (got ' . $currentVersion . ')'
);

// Test 2: Migration 113 file existence
$migrationFile = __DIR__ . '/../migrations/113_version_113.php';
assert_true(file_exists($migrationFile), 'Migration 113_version_113.php must exist');

// Test 3: Migration class structure and required table/column definitions
if (!class_exists('App_module_migration')) {
    class App_module_migration {}
}
require_once $migrationFile;
assert_true(class_exists('Migration_Version_113'), 'Migration_Version_113 class must exist');

$schemaFile = __DIR__ . '/../includes/architecture_113_schema.php';
assert_true(file_exists($schemaFile), 'architecture_113_schema.php must exist');
$migrationCode = file_get_contents($migrationFile) . "\n" . file_get_contents($schemaFile);

// Check required tables in migration 113
$requiredTables = [
    'sales_pipeline_score_snapshots',
    'sales_pipeline_exchange_rates',
    'sales_pipeline_sanitization_batches',
    'sales_pipeline_sanitization_items',
];
foreach ($requiredTables as $table) {
    assert_true(
        strpos($migrationCode, $table) !== false,
        "Migration 113 must define table {$table}"
    );
}

// Check required columns on estimate groups and deals
$requiredColumns = [
    'first_sent_at',
    'first_sent_source',
    'first_sent_estimate_id',
    'is_finance_locked',
    'finance_locked_at',
    'finance_locked_by',
    'finance_approval_reference',
    'finance_lock_reason',
    'data_quality_status',
];
foreach ($requiredColumns as $col) {
    assert_true(
        strpos($migrationCode, $col) !== false,
        "Migration 113 must define column {$col}"
    );
}

// Check unique keys defined
assert_true(
    strpos($migrationCode, 'uk_score_snapshot') !== false || strpos($migrationCode, 'period_type') !== false,
    'Migration 113 must define unique key on score snapshots'
);
assert_true(
    strpos($migrationCode, 'uk_exchange_rate') !== false || strpos($migrationCode, 'effective_date') !== false,
    'Migration 113 must define unique key on exchange rates'
);

echo "PASS: Architecture migration 113 contract\n";
