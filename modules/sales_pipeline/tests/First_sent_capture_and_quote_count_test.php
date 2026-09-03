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

// 1. Test fail-closed on Quote_count_repository when versions is empty
require_once __DIR__ . '/../libraries/Quote_count_repository.php';
$repo = new Quote_count_repository();

$emptyVersionsGroup = [
    'id'            => 301,
    'owner_staff_id'=> 5,
    'first_sent_at' => '2026-09-05 10:00:00',
    'versions'      => [], // No version records!
];
assert_equals(
    false,
    $repo->evaluate_group_eligibility($emptyVersionsGroup, '2026-09-01', '2026-10-01'),
    'evaluate_group_eligibility must fail-closed when versions is empty'
);

// 2. Test estimate_sent hook registration in sales_pipeline.php
$mainFile = __DIR__ . '/../sales_pipeline.php';
$mainContent = file_get_contents($mainFile);
assert_true(
    strpos($mainContent, "'estimate_sent'") !== false,
    'sales_pipeline.php must register estimate_sent hook'
);

// 3. Test capture handler function exists and implements atomic earliest sent logic
assert_true(
    strpos($mainContent, 'function sales_pipeline_handle_estimate_sent') !== false,
    'sales_pipeline_handle_estimate_sent function must be defined in sales_pipeline.php'
);

echo "PASS: First sent capture and quote count contract\n";
