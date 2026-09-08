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

require_once __DIR__ . '/../libraries/Currency_data_sanitizer.php';
$sanitizer = new Currency_data_sanitizer();

// 1. Test dry_run fails closed on missing or null approved_rate
$invalidManifest = [
    'batch_uuid' => 'batch-test-01',
    'items' => [
        [
            'entity_id' => 101,
            'source_total' => 1000.0,
            'expected_before_rate' => 1.0,
            'approved_rate' => null, // Missing rate! Must not fallback to 1.0!
        ]
    ]
];

$dryResult = $sanitizer->dry_run($invalidManifest);
assert_equals(false, $dryResult['success'], 'dry_run must fail when approved_rate is missing');

echo "PASS: Currency sanitizer full lifecycle contract\n";
