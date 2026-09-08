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

// 1. Test Deal_bridge_calculator finance lock enforcement
require_once __DIR__ . '/../libraries/Deal_bridge_calculator.php';
$bridge = new Deal_bridge_calculator();

$groups = [
    [
        'id'                  => 10,
        'outcome'             => 'accepted',
        'decision_estimate_id'=> 5,
        'decision_value_base' => 100000000.0,
    ]
];

$resFinanceLocked = $bridge->calculate($groups, null, false, true); // 4th param: is_finance_locked = true
assert_equals('preserve', $resFinanceLocked['action'], 'Finance locked deal must be preserved');
assert_equals('finance_locked', $resFinanceLocked['reason'], 'Preserve reason must be finance_locked');

// 2. Check Estimate_revision_service.php for is_finance_locked
$revisionServiceFile = __DIR__ . '/../libraries/Estimate_revision_service.php';
$revContent = file_get_contents($revisionServiceFile);
assert_true(
    strpos($revContent, 'is_finance_locked') !== false,
    'Estimate_revision_service.php must check is_finance_locked'
);

// 3. Check permission in sales_pipeline.php
$mainFile = __DIR__ . '/../sales_pipeline.php';
$mainContent = file_get_contents($mainFile);
assert_true(
    strpos($mainContent, 'manage_finance_lock') !== false,
    'sales_pipeline.php must declare manage_finance_lock permission'
);

// 4. Check controller endpoint in Sales_pipeline.php
$controllerFile = __DIR__ . '/../controllers/Sales_pipeline.php';
$controllerContent = file_get_contents($controllerFile);
assert_true(
    strpos($controllerContent, 'function toggle_finance_lock') !== false ||
    strpos($controllerContent, 'function set_finance_lock') !== false,
    'Sales_pipeline.php controller must have finance lock management endpoint'
);

echo "PASS: Finance lock enforcement contract\n";
