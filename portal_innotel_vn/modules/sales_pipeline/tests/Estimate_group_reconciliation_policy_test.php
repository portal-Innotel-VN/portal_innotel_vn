<?php

defined('BASEPATH') or define('BASEPATH', dirname(__DIR__));

require_once dirname(__DIR__) . '/libraries/Estimate_group_reconciliation_policy.php';

function assert_reconcile_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, 'FAILED: ' . $message . PHP_EOL);
        exit(1);
    }
}

$policy = new Estimate_group_reconciliation_policy();
$acceptedMissing = [
    'outcome' => 'accepted',
    'decision_at' => '2026-08-01 10:00:00',
    'decision_estimate_id' => 42,
    'decision_value_base' => null,
];

assert_reconcile_same(
    true,
    $policy->should_persist($acceptedMissing, 'accepted', 42, '260000000.00'),
    'accepted Group self-heals when a rate becomes available'
);

$healed = $acceptedMissing;
$healed['decision_value_base'] = '260000000.00';
assert_reconcile_same(
    false,
    $policy->should_persist($healed, 'accepted', 42, '260000000.00'),
    'second reconciliation is idempotent'
);

assert_reconcile_same(
    true,
    $policy->should_persist($healed, 'declined', 42, null),
    'outcome changes still persist'
);

$modelSource = file_get_contents(dirname(__DIR__) . '/models/Sales_pipeline_model.php');
assert_reconcile_same(true, strpos($modelSource, 'Estimate_group_reconciliation_policy') !== false, 'normal reconciliation uses the self-heal policy');

fwrite(STDOUT, "PASS: Estimate Group reconciliation policy\n");
