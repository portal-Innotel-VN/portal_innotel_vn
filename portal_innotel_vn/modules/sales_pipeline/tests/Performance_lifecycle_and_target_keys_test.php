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

// 1. Check target option keys in architecture_113_schema.php
$schemaFile = __DIR__ . '/../includes/architecture_113_schema.php';
$schemaContent = file_get_contents($schemaFile);
assert_true(
    strpos($schemaContent, 'performance_quote_target_this_quarter') !== false,
    'Target options migration must use performance_quote_target_this_quarter'
);
assert_true(
    strpos($schemaContent, 'performance_quote_target_this_year') !== false,
    'Target options migration must use performance_quote_target_this_year'
);

// 2. Check Performance_score_service methods
require_once __DIR__ . '/../libraries/Performance_score_service.php';
$service = new Performance_score_service();
$reflection = new ReflectionClass($service);

assert_true($reflection->hasMethod('persist_provisional'), 'Must have persist_provisional method');
assert_true($reflection->hasMethod('reconstruct_legacy_v1'), 'Must have reconstruct_legacy_v1 method');
assert_true($reflection->hasMethod('finalize_period'), 'Must have finalize_period method');

// 3. Test reconstruct_legacy_v1 behavior with legacy defaults
$period = ['type' => 'month', 'start' => '2026-07-01', 'end' => '2026-07-31'];
$cohort = [
    10 => [
        'staff_id' => 10,
        'estimate_count' => 20,
        'accepted_revenue' => 50000000,
        'closed_count' => 5,
        'accepted_count' => 2,
    ]
];
$reconstructed = $service->reconstruct_legacy_v1($period, $cohort);
assert_equals('performance_score_v1', $reconstructed['formula_version'], 'Reconstructed must be V1');
assert_equals('reconstructed', $reconstructed['cohort'][10]['snapshot_status'], 'Must be marked reconstructed');

// 4. Check get_estimate_performance_ranking integration
$modelFile = __DIR__ . '/../models/Sales_pipeline_model.php';
$modelContent = file_get_contents($modelFile);
assert_true(
    strpos($modelContent, 'Performance_score_service') !== false ||
    strpos($modelContent, 'Performance_score_dispatcher') !== false,
    'get_estimate_performance_ranking must use Performance_score_service or dispatcher'
);

echo "PASS: Performance lifecycle and target keys contract\n";
