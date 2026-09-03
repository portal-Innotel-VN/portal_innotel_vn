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

// 1. Verify module hook registrations in sales_pipeline.php
$mainFile = __DIR__ . '/../sales_pipeline.php';
$mainContent = file_get_contents($mainFile);

// Route 1: Email send -> hook estimate_sent
assert_true(
    strpos($mainContent, "'estimate_sent'") !== false,
    'sales_pipeline.php must hook estimate_sent'
);

// Route 2: Form update -> hook after_estimate_updated
assert_true(
    strpos($mainContent, "'after_estimate_updated'") !== false,
    'sales_pipeline.php must hook after_estimate_updated'
);

// Route 5: Create directly as Sent -> hook after_estimate_added
assert_true(
    strpos($mainContent, "'after_estimate_added'") !== false,
    'sales_pipeline.php must hook after_estimate_added'
);

// Route 6: Deal pipeline must NOT be hooked to Quote capture
$dealMethodRegex = '/function update_deal_status.*?Quote_first_sent_service/s';
assert_equals(
    0,
    preg_match($dealMethodRegex, $mainContent),
    'Deal pipeline update must NOT invoke Quote capture service'
);

// 2. Verify model reconciliation exists for Route 3 & 4 (Admin mark & Core Pipeline)
$modelFile = __DIR__ . '/../models/Sales_pipeline_model.php';
$modelContent = file_get_contents($modelFile);

assert_true(
    strpos($modelContent, 'reconcile_missing_first_sent_groups') !== false
    || strpos($modelContent, 'Quote_first_sent_service') !== false,
    'Sales_pipeline_model must provide reconciliation for unhooked Core paths'
);

echo "PASS: Route_matrix_write_path_test\n";
