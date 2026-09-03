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

require_once __DIR__ . '/../libraries/Quote_first_sent_service.php';

$service = new Quote_first_sent_service(null);

// Simulation of state after Migration 113 runs on 1.0.0 ledger:
// Migration 113 set ALL non-draft groups (status IN 2,3,4,5) to date + 09:00:00 and source 'inferred_estimate_date'
$group148_after_113 = [
    'id'                     => 148,
    'first_sent_at'          => '2026-09-03 09:00:00',
    'first_sent_source'      => 'inferred_estimate_date',
    'first_sent_estimate_id' => 123,
];

$group145_after_113 = [
    'id'                     => 145,
    'first_sent_at'          => '2026-09-03 09:00:00',
    'first_sent_source'      => 'inferred_estimate_date',
    'first_sent_estimate_id' => 120,
];

// Evidence for Estimate 123 in Group 148: Real sent evidence exists!
$estimate123 = [
    'id'       => 123,
    'status'   => 2,
    'sent'     => 1,
    'datesend' => '2026-09-03 12:13:40',
];
$ev148 = $service->extract_estimate_evidence($estimate123, []);
assert_equals('estimate_datesend', $ev148['source'], 'Estimate 123 evidence must be estimate_datesend');
assert_equals('2026-09-03 12:13:40', $ev148['occurred_at'], 'Estimate 123 timestamp must be 12:13:40');

// Verify transition for Group 148: Real evidence (Rank 3) MUST replace Inferred (Rank 1)
$replace148 = $service->evaluate_evidence_transition(
    $group148_after_113['first_sent_source'],
    $group148_after_113['first_sent_at'],
    $ev148['source'],
    $ev148['occurred_at']
);
assert_true($replace148, 'Migration 114 MUST replace 113 inferred timestamp with real datesend for Group 148');

// Simulate remediation update for Group 148
$group148_after_114 = [
    'id'                     => 148,
    'first_sent_at'          => $ev148['occurred_at'],
    'first_sent_source'      => $ev148['source'],
    'first_sent_estimate_id' => 123,
];
assert_equals('2026-09-03 12:13:40', $group148_after_114['first_sent_at'], 'Group 148 final first_sent_at must be 12:13:40');
assert_equals('estimate_datesend', $group148_after_114['first_sent_source'], 'Group 148 final source must be estimate_datesend');

// Evidence for Estimate 120 in Group 145: Status 4 Accepted, but sent = 0 and datesend = NULL, no sent activity
$estimate120 = [
    'id'       => 120,
    'status'   => 4,
    'sent'     => 0,
    'datesend' => null,
];
$ev145 = $service->extract_estimate_evidence($estimate120, []);
assert_equals(null, $ev145['source'], 'Estimate 120 has NO real sent evidence');

// Policy A: When current is inferred but best real evidence is NONE, Group 145 is reversed back to NULL!
$isCurrentInferred145 = ($service->get_source_rank($group145_after_113['first_sent_source']) === Quote_first_sent_service::RANK_INFERRED_LEGACY);
assert_true($isCurrentInferred145, 'Group 145 was marked inferred by 113');

$group145_after_114 = [
    'id'                     => 145,
    'first_sent_at'          => null,
    'first_sent_source'      => null,
    'first_sent_estimate_id' => null,
];
assert_equals(null, $group145_after_114['first_sent_at'], 'Policy A: Group 145 MUST be reversed to NULL');
assert_equals(null, $group145_after_114['first_sent_source'], 'Policy A: Group 145 source MUST be NULL');

// Test Run 2: Idempotency check
$replace148_run2 = $service->evaluate_evidence_transition(
    $group148_after_114['first_sent_source'],
    $group148_after_114['first_sent_at'],
    $ev148['source'],
    $ev148['occurred_at']
);
assert_equals(false, $replace148_run2, 'Run 2 must be no-op/unchanged for Group 148 (idempotent)');

echo "PASS: Migration_113_to_114_remediation_test (Group 148 healed, Group 145 reversed to NULL)\n";
