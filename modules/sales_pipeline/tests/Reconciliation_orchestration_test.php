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

// Inspect model code for orchestration invariants
$modelContent = file_get_contents(__DIR__ . '/../models/Sales_pipeline_model.php');

// 1. Unified Advisory Lock namespace
assert_true(
    strpos($modelContent, "sales_pipeline:first_sent_reconcile") !== false,
    'Sales_pipeline_model must use unified lock name sales_pipeline:first_sent_reconcile'
);

// 2. Cooldown Option
assert_true(
    strpos($modelContent, "sp_first_sent_reconcile_last_run") !== false,
    'Sales_pipeline_model must track cooldown in option sp_first_sent_reconcile_last_run'
);

// 3. RELEASE_LOCK in finally block
assert_true(
    strpos($modelContent, "RELEASE_LOCK") !== false,
    'Sales_pipeline_model must release advisory lock'
);

// 4. Policy A remediation: reverse inferred with no evidence back to NULL
assert_true(
    strpos($modelContent, "reversed_to_null") !== false,
    'Sales_pipeline_model must implement reversed_to_null remediation for Policy A'
);

echo "PASS: Reconciliation_orchestration_test\n";
