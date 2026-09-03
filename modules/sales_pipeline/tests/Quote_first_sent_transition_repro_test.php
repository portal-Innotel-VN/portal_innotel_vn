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

require_once __DIR__ . '/../libraries/Quote_count_repository.php';
require_once __DIR__ . '/../libraries/Quote_first_sent_service.php';

$repo = new Quote_count_repository();
$service = new Quote_first_sent_service(null);

$staffId = 22; // Hờ Hờ
$periodStart = '2026-09-01 00:00:00';
$periodEndExclusive = '2026-10-01 00:00:00';

// Scenario: 3 Groups for Staff 22
// Group 146 -> Estimate 121 (Draft: status 1, sent 0)
// Group 147 -> Estimate 122 (Draft: status 1, sent 0)
// Group 148 -> Estimate 123 (Sent: status 2, sent 1, datesend = '2026-09-03 12:13:40')

$group146 = [
    'id'            => 146,
    'owner_staff_id'=> $staffId,
    'first_sent_at' => null,
    'versions'      => [
        ['estimate_id' => 121, 'status' => 1]
    ],
];

$group147 = [
    'id'            => 147,
    'owner_staff_id'=> $staffId,
    'first_sent_at' => null,
    'versions'      => [
        ['estimate_id' => 122, 'status' => 1]
    ],
];

$group148_unreconciled = [
    'id'            => 148,
    'owner_staff_id'=> $staffId,
    'first_sent_at' => null, // BUG: Was NULL before hotfix!
    'versions'      => [
        ['estimate_id' => 123, 'status' => 2]
    ],
];

// Before fix: Quote_count_repository returns 0 because first_sent_at is NULL
$cohortBefore = [$group146, $group147, $group148_unreconciled];
$countsBefore = $repo->aggregate_cohort_counts($cohortBefore, $periodStart, $periodEndExclusive);
assert_equals([], $countsBefore, 'Before hotfix: Group 148 with NULL first_sent_at results in 0 count');

// Transition: Estimate 123 transitions to Sent with datesend '2026-09-03 12:13:40'
$simulatedEstimate123 = [
    'id'       => 123,
    'status'   => 2,
    'sent'     => 1,
    'datesend' => '2026-09-03 12:13:40',
];
$extracted = $service->extract_estimate_evidence($simulatedEstimate123, null);
assert_equals('estimate_datesend', $extracted['source'], 'Should extract estimate_datesend');
assert_equals('2026-09-03 12:13:40', $extracted['occurred_at'], 'Should extract occurred_at');

// After capture/reconciliation: Group 148 receives first_sent_at = '2026-09-03 12:13:40'
$group148_healed = [
    'id'            => 148,
    'owner_staff_id'=> $staffId,
    'first_sent_at' => '2026-09-03 12:13:40',
    'versions'      => [
        ['estimate_id' => 123, 'status' => 2]
    ],
];

$cohortAfter = [$group146, $group147, $group148_healed];
$countsAfter = $repo->aggregate_cohort_counts($cohortAfter, $periodStart, $periodEndExclusive);
assert_equals(1, $countsAfter[$staffId] ?? 0, 'After hotfix: Staff 22 must have Quote Count = 1');

// Invariant: Adding revision 2 to Group 148 does NOT increase Quote Count
$group148_with_revision2 = [
    'id'            => 148,
    'owner_staff_id'=> $staffId,
    'first_sent_at' => '2026-09-03 12:13:40',
    'versions'      => [
        ['estimate_id' => 123, 'status' => 2],
        ['estimate_id' => 124, 'status' => 2], // Revision 2
    ],
];
$cohortWithRevision = [$group146, $group147, $group148_with_revision2];
$countsRevision = $repo->aggregate_cohort_counts($cohortWithRevision, $periodStart, $periodEndExclusive);
assert_equals(1, $countsRevision[$staffId] ?? 0, 'Revision 2 in same group must NOT increase Quote Count beyond 1');

// Invariant: Period boundary [2026-08-01, 2026-09-01) returns 0
$countsAugust = $repo->aggregate_cohort_counts($cohortAfter, '2026-08-01 00:00:00', '2026-09-01 00:00:00');
assert_equals(0, $countsAugust[$staffId] ?? 0, 'August period must return 0 for September event');

// Invariant: Period boundary [2026-10-01, 2026-11-01) returns 0
$countsOctober = $repo->aggregate_cohort_counts($cohortAfter, '2026-10-01 00:00:00', '2026-11-01 00:00:00');
assert_equals(0, $countsOctober[$staffId] ?? 0, 'October period must return 0 for September event');

echo "PASS: Quote_first_sent_transition_repro_test\n";
