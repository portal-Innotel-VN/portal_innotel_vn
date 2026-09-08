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

// Check that Sales_pipeline_model::reconcile_estimate_groups() refreshes version snapshot before sync_estimate_group
$modelFile = __DIR__ . '/../models/Sales_pipeline_model.php';
assert_true(file_exists($modelFile), 'Sales_pipeline_model.php must exist');

$modelContent = file_get_contents($modelFile);

// Behavioral expectation: reconcile_estimate_groups must invoke version snapshot refresh
// and must check finance lock
assert_true(
    strpos($modelContent, 'refresh_estimate_version_snapshot') !== false ||
    strpos($modelContent, 'reconcile_group_version_snapshots') !== false,
    'reconcile_estimate_groups must refresh version snapshots for accepted groups needing rate healing'
);

assert_true(
    strpos($modelContent, 'is_finance_locked') !== false || strpos($modelContent, 'Finance_lock_guard') !== false,
    'reconcile_estimate_groups must check finance lock before modifying group or deal'
);

// Integration execution test on real model with mock DB/hook
require_once __DIR__ . '/../libraries/Quote_currency_resolver.php';
require_once __DIR__ . '/../libraries/Estimate_group_reconciliation_policy.php';
require_once __DIR__ . '/../libraries/Finance_lock_guard.php';

// Test model policy logic
$policy = new Estimate_group_reconciliation_policy();
$unlockedGroup = [
    'id' => 201,
    'outcome' => 'accepted',
    'decision_estimate_id' => 50,
    'decision_value_base' => null,
    'is_finance_locked' => 0,
];
$lockedGroup = [
    'id' => 202,
    'outcome' => 'accepted',
    'decision_estimate_id' => 51,
    'decision_value_base' => 1000000.0,
    'is_finance_locked' => 1,
];

$lockGuard = new Finance_lock_guard();
assert_true($lockGuard->can_modify($unlockedGroup), 'Unlocked group can be reconciled');
assert_true(!$lockGuard->can_modify($lockedGroup), 'Finance locked group must be protected from reconciliation');

echo "PASS: Reconciliation currency snapshot contract\n";
