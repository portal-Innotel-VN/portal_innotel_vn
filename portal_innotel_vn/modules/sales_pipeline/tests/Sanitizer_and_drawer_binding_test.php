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

// 1. Sanitizer Library Check
$sanitizerFile = __DIR__ . '/../libraries/Currency_data_sanitizer.php';
assert_true(file_exists($sanitizerFile), 'Currency_data_sanitizer.php must exist');

require_once $sanitizerFile;
assert_true(class_exists('Currency_data_sanitizer'), 'Currency_data_sanitizer class must exist');

$sanitizer = new Currency_data_sanitizer();

// Test 1a: Dry-run calculation
$sampleManifest = [
    'batch_uuid'         => 'uuid-batch-001',
    'approved_by'        => 1,
    'approval_reference' => 'FIN-MAN-2026-09',
    'items' => [
        [
            'entity_type'             => 'estimate_version',
            'entity_id'               => 101,
            'source_currency_id'      => 2, // USD
            'base_currency_id'        => 1, // VND
            'source_total'            => 1000.0,
            'expected_before_rate'    => 1.0,
            'expected_before_base'    => 1000.0,
            'approved_rate'           => 25000.0,
            'rate_date'               => '2026-09-01',
        ],
    ],
];

$dryRunResult = $sanitizer->dry_run($sampleManifest);
assert_equals(true, $dryRunResult['success'], 'Dry-run must succeed');
assert_equals(1, $dryRunResult['summary']['total_items'], 'Total items must be 1');
assert_equals(1000.0, (float) $dryRunResult['items'][0]['before_base_total'], 'Before base total is 1000');
assert_equals(25000000.0, (float) $dryRunResult['items'][0]['after_base_total'], 'After base total is 25,000,000');

// Test 1b: Optimistic lock mismatch must reject item
$mismatchedManifest = $sampleManifest;
$mismatchedManifest['items'][0]['expected_before_rate'] = 24000.0; // Current is 1.0
$validation = $sanitizer->validate_manifest_item($mismatchedManifest['items'][0], ['exchange_rate_to_base' => 1.0, 'base_total' => 1000.0]);
assert_equals(false, $validation['valid'], 'Mismatched before-value must be rejected');

// 2. Drawer View Data-Binding contract test
$viewFile = __DIR__ . '/../views/_dashboard_staff_pipeline.php';
assert_true(file_exists($viewFile), 'View _dashboard_staff_pipeline.php must exist');
$viewContent = file_get_contents($viewFile);

// The estimates branch in _dashboard_staff_pipeline.php MUST bind to performance_metric['accepted_revenue']
// and NOT to metric['period_revenue']
assert_true(
    strpos($viewContent, 'accepted_revenue') !== false,
    'Drawer view must bind to accepted_revenue for estimate dashboard'
);
assert_true(
    strpos($viewContent, 'performance_metric') !== false,
    'Drawer view must reference performance_metric'
);

echo "PASS: Sanitizer and drawer binding contract\n";
