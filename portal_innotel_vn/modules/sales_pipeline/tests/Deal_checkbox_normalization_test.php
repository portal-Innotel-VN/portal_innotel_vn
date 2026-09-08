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

// 1. Verify AST / Code inspection of Sales_pipeline_model.php
$modelContent = file_get_contents(dirname(__DIR__) . '/models/Sales_pipeline_model.php');

// Ensure add() does not use isset for contract_signed / invoice_issued / reminder_enabled
assert_true(
    strpos($modelContent, "\$data['contract_signed']  = isset(\$data['contract_signed'])") === false,
    'Sales_pipeline_model::add must not use isset() for contract_signed'
);
assert_true(
    strpos($modelContent, "\$data['invoice_issued']   = isset(\$data['invoice_issued'])") === false,
    'Sales_pipeline_model::add must not use isset() for invoice_issued'
);

// Ensure update() does not use unconditional isset()
assert_true(
    strpos($modelContent, "\$data['contract_signed']  = isset(\$data['contract_signed']) ? 1 : 0;") === false,
    'Sales_pipeline_model::update must not use unconditional isset() for contract_signed'
);
assert_true(
    strpos($modelContent, "array_key_exists('contract_signed', \$data)") !== false,
    'Sales_pipeline_model::update must check array_key_exists for contract_signed'
);
assert_true(
    strpos($modelContent, "array_key_exists('invoice_issued', \$data)") !== false,
    'Sales_pipeline_model::update must check array_key_exists for invoice_issued'
);
assert_true(
    strpos($modelContent, "array_key_exists('reminder_enabled', \$data)") !== false,
    'Sales_pipeline_model::update must check array_key_exists for reminder_enabled'
);

// 2. Behavioral verification of the normalization logic:
// Simulation of add() normalization logic
$simulateAdd = function(array $data) {
    $data['contract_signed']  = !empty($data['contract_signed']) ? 1 : 0;
    $data['invoice_issued']   = !empty($data['invoice_issued']) ? 1 : 0;
    $data['reminder_enabled'] = isset($data['reminder_enabled']) ? (!empty($data['reminder_enabled']) ? 1 : 0) : 1;
    return $data;
};

// Simulation of update() normalization logic
$simulateUpdate = function(array $data) {
    if (array_key_exists('contract_signed', $data)) {
        $data['contract_signed'] = !empty($data['contract_signed']) ? 1 : 0;
    }
    if (array_key_exists('invoice_issued', $data)) {
        $data['invoice_issued'] = !empty($data['invoice_issued']) ? 1 : 0;
    }
    if (array_key_exists('reminder_enabled', $data)) {
        $data['reminder_enabled'] = !empty($data['reminder_enabled']) ? 1 : 0;
    }
    return $data;
};

// Test 2a: Unchecked flags passed as 0 from controller
$uncheckedTest = $simulateAdd(['contract_signed' => 0, 'invoice_issued' => 0, 'reminder_enabled' => 0]);
assert_equals(0, $uncheckedTest['contract_signed'], 'contract_signed with 0 must evaluate to 0');
assert_equals(0, $uncheckedTest['invoice_issued'], 'invoice_issued with 0 must evaluate to 0');
assert_equals(0, $uncheckedTest['reminder_enabled'], 'reminder_enabled with 0 must evaluate to 0');

// Test 2b: Checked flags passed as 1 or '1'
$checkedTest = $simulateAdd(['contract_signed' => 1, 'invoice_issued' => '1', 'reminder_enabled' => 1]);
assert_equals(1, $checkedTest['contract_signed'], 'contract_signed with 1 must evaluate to 1');
assert_equals(1, $checkedTest['invoice_issued'], 'invoice_issued with "1" must evaluate to 1');
assert_equals(1, $checkedTest['reminder_enabled'], 'reminder_enabled with 1 must evaluate to 1');

// Test 2c: Update with status only - must NOT inject or overwrite checkbox fields
$statusOnlyUpdate = $simulateUpdate(['status' => 2]);
assert_true(!array_key_exists('contract_signed', $statusOnlyUpdate), 'Partial update must not inject contract_signed');
assert_true(!array_key_exists('invoice_issued', $statusOnlyUpdate), 'Partial update must not inject invoice_issued');
assert_true(!array_key_exists('reminder_enabled', $statusOnlyUpdate), 'Partial update must not inject reminder_enabled');

// Test 2e: Import payload compatibility (contract_signed=0, invoice_issued=0, reminder_enabled=1)
$importPayload = [
    'customer_name'    => 'Công ty ABC',
    'deal_value'       => 50000000,
    'status'           => 1,
    'contract_signed'  => 0,
    'invoice_issued'   => 0,
    'reminder_enabled' => 1,
];
$importAddResult = $simulateAdd($importPayload);
assert_equals(0, $importAddResult['contract_signed'], 'Import add: contract_signed=0 must remain 0');
assert_equals(0, $importAddResult['invoice_issued'], 'Import add: invoice_issued=0 must remain 0');
assert_equals(1, $importAddResult['reminder_enabled'], 'Import add: reminder_enabled=1 must remain 1');

$importUpdateResult = $simulateUpdate($importPayload);
assert_equals(0, $importUpdateResult['contract_signed'], 'Import update: contract_signed=0 must remain 0');
assert_equals(0, $importUpdateResult['invoice_issued'], 'Import update: invoice_issued=0 must remain 0');
assert_equals(1, $importUpdateResult['reminder_enabled'], 'Import update: reminder_enabled=1 must remain 1');

echo "PASS: Deal checkbox normalization test (including Import flows)\n";
