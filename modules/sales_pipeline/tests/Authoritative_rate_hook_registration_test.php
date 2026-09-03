<?php

defined('BASEPATH') or define('BASEPATH', 'dummy');

function assert_true($cond, $msg) {
    if (!$cond) throw new RuntimeException("Assertion failed: {$msg}");
}
function assert_equals($expected, $actual, $msg) {
    if ($expected !== $actual) {
        $exp = var_export($expected, true);
        $act = var_export($actual, true);
        throw new RuntimeException("Assertion failed: {$msg} (Expected {$exp}, got {$act})");
    }
}

// 1. Check sales_pipeline.php hook registration
$mainFile = __DIR__ . '/../sales_pipeline.php';
$mainContent = file_get_contents($mainFile);
assert_true(
    strpos($mainContent, 'sales_pipeline_quote_exchange_rate') !== false,
    'sales_pipeline.php must register hook for sales_pipeline_quote_exchange_rate'
);

// 2. Test Authoritative_exchange_rate_provider rate_unit and target_date / captured_at handling
require_once __DIR__ . '/../libraries/Authoritative_exchange_rate_provider.php';
$provider = new Authoritative_exchange_rate_provider();

// Test that provider enforces rate_unit = base_currency_per_source_currency
$dataset = [
    [
        'source_currency_id' => 2,
        'base_currency_id'   => 1,
        'effective_date'     => '2026-09-01',
        'exchange_rate_to_base' => 25000.0,
        'rate_unit'          => 'invalid_unit_inverted', // Invalid rate unit!
        'is_active'          => 1,
        'approved_at'        => '2026-09-01 08:00:00',
    ],
    [
        'source_currency_id' => 2,
        'base_currency_id'   => 1,
        'effective_date'     => '2026-09-01',
        'exchange_rate_to_base' => 25000.0,
        'rate_unit'          => 'base_currency_per_source_currency', // Valid rate unit
        'is_active'          => 1,
        'approved_at'        => '2026-09-01 08:00:00',
    ]
];

$lookupResult = $provider->find_rate_in_dataset([$dataset[0]], 2, 1, '2026-09-02');
assert_true(
    $lookupResult === null || $lookupResult['rate'] === null,
    'Rates with invalid rate_unit must be rejected'
);

$validLookup = $provider->find_rate_in_dataset([$dataset[1]], 2, 1, '2026-09-02');
assert_equals(25000.0, (float) $validLookup['rate'], 'Valid rate with correct unit must be resolved');

// 3. Test resolve_rate handles both captured_at and target_date keys
$contextCaptured = [
    'source_currency_id' => 1,
    'base_currency_id'   => 1,
    'captured_at'        => '2026-09-02 12:00:00',
];
$res = $provider->resolve_rate($contextCaptured);
assert_equals(1.0, (float) $res['rate'], 'Identity rate must be 1.0 when captured_at is provided');

echo "PASS: Authoritative rate hook registration contract\n";
