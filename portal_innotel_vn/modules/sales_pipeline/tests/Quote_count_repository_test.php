<?php

defined('BASEPATH') or define('BASEPATH', 'dummy');

function assert_true($condition, $message) {
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

function assert_equals($expected, $actual, $message) {
    if ($expected !== $actual) {
        $expStr = var_export($expected, true);
        $actStr = var_export($actual, true);
        throw new RuntimeException("Assertion failed: {$message} (Expected {$expStr}, got {$actStr})");
    }
}

$repoFile = __DIR__ . '/../libraries/Quote_count_repository.php';
assert_true(file_exists($repoFile), 'Quote_count_repository.php must exist');

require_once $repoFile;
assert_true(class_exists('Quote_count_repository'), 'Quote_count_repository class must exist');

$reflection = new ReflectionClass('Quote_count_repository');
assert_true($reflection->hasMethod('get_counts_by_staff'), 'Must have get_counts_by_staff method');
assert_true($reflection->hasMethod('get_staff_quote_count'), 'Must have get_staff_quote_count method');
assert_true($reflection->hasMethod('evaluate_group_eligibility'), 'Must have evaluate_group_eligibility pure method');

$repo = new Quote_count_repository();

// Test 1: Draft-only group is completely excluded (status = 1)
$draftOnlyGroup = [
    'id' => 101,
    'owner_staff_id' => 5,
    'first_sent_at' => null,
    'versions' => [
        ['estimate_id' => 1, 'status' => 1],
        ['estimate_id' => 2, 'status' => 1],
    ],
];
assert_equals(
    false,
    $repo->evaluate_group_eligibility($draftOnlyGroup, '2026-09-01', '2026-10-01'),
    'Draft-only group must be excluded from quote count'
);

// Test 2: Group with 3 revisions and at least one status IN (2,3,4,5) sent in period counts as exactly 1
$validGroupWithRevisions = [
    'id' => 102,
    'owner_staff_id' => 5,
    'first_sent_at' => '2026-09-15 10:00:00',
    'versions' => [
        ['estimate_id' => 3, 'status' => 2], // Sent
        ['estimate_id' => 4, 'status' => 3], // Declined
        ['estimate_id' => 5, 'status' => 4], // Accepted
    ],
];
assert_equals(
    true,
    $repo->evaluate_group_eligibility($validGroupWithRevisions, '2026-09-01', '2026-10-01'),
    'Group with valid versions sent within period must be eligible'
);

// Test 3: Group created prior period but first sent this period is counted in this period
$groupFirstSentThisPeriod = [
    'id' => 103,
    'owner_staff_id' => 5,
    'datecreated' => '2026-08-20 09:00:00',
    'first_sent_at' => '2026-09-02 14:00:00',
    'versions' => [
        ['estimate_id' => 6, 'status' => 2],
    ],
];
assert_equals(
    true,
    $repo->evaluate_group_eligibility($groupFirstSentThisPeriod, '2026-09-01', '2026-10-01'),
    'Group created in prior period but first sent in current period must count in current period'
);

// Test 4: Revision sent in current period for a group already first-sent in prior period does NOT count again
$groupFirstSentPriorPeriod = [
    'id' => 104,
    'owner_staff_id' => 5,
    'first_sent_at' => '2026-08-15 10:00:00',
    'versions' => [
        ['estimate_id' => 7, 'status' => 2],
        ['estimate_id' => 8, 'status' => 4, 'date_linked' => '2026-09-10 11:00:00'],
    ],
];
assert_equals(
    false,
    $repo->evaluate_group_eligibility($groupFirstSentPriorPeriod, '2026-09-01', '2026-10-01'),
    'Subsequent revision in current period for prior-period group must not increase quote count'
);

// Test 5: Boundary condition [period_start, period_end_exclusive)
$exactStartGroup = [
    'id' => 105,
    'owner_staff_id' => 5,
    'first_sent_at' => '2026-09-01 00:00:00',
    'versions' => [['estimate_id' => 9, 'status' => 2]],
];
assert_equals(
    true,
    $repo->evaluate_group_eligibility($exactStartGroup, '2026-09-01', '2026-10-01'),
    'Exact period start must be included'
);

$exactEndGroup = [
    'id' => 106,
    'owner_staff_id' => 5,
    'first_sent_at' => '2026-10-01 00:00:00',
    'versions' => [['estimate_id' => 10, 'status' => 2]],
];
assert_equals(
    false,
    $repo->evaluate_group_eligibility($exactEndGroup, '2026-09-01', '2026-10-01'),
    'Exact period end must be exclusive'
);

// Test 6: In-memory cohort calculation
$cohortGroups = [$validGroupWithRevisions, $groupFirstSentThisPeriod, $groupFirstSentPriorPeriod, $draftOnlyGroup];
$counts = $repo->aggregate_cohort_counts($cohortGroups, '2026-09-01', '2026-10-01');
assert_equals(2, $counts[5] ?? 0, 'Staff 5 must have exactly 2 valid quotes in period');

echo "PASS: Quote count repository contract\n";
