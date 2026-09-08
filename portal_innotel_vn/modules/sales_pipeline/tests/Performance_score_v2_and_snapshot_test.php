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

// 1. Check required library files
$v1File = __DIR__ . '/../libraries/Performance_score_calculator_v1.php';
$v2File = __DIR__ . '/../libraries/Performance_score_calculator_v2.php';
$dispatcherFile = __DIR__ . '/../libraries/Performance_score_dispatcher.php';
$serviceFile = __DIR__ . '/../libraries/Performance_score_service.php';

assert_true(file_exists($v1File), 'Performance_score_calculator_v1.php must exist');
assert_true(file_exists($v2File), 'Performance_score_calculator_v2.php must exist');
assert_true(file_exists($dispatcherFile), 'Performance_score_dispatcher.php must exist');
assert_true(file_exists($serviceFile), 'Performance_score_service.php must exist');

require_once $v1File;
require_once $v2File;
require_once $dispatcherFile;
require_once $serviceFile;

assert_true(class_exists('Performance_score_calculator_v1'), 'V1 class must exist');
assert_true(class_exists('Performance_score_calculator_v2'), 'V2 class must exist');
assert_true(class_exists('Performance_score_dispatcher'), 'Dispatcher class must exist');
assert_true(class_exists('Performance_score_service'), 'Service class must exist');

// 2. V1 Golden Fixture: Denominator fixed 85, reminders completely ignored
$calcV1 = new Performance_score_calculator_v1();
assert_equals('performance_score_v1', $calcV1::FORMULA_VERSION, 'V1 formula version must be performance_score_v1');

$v1Metrics = [
    'estimate_count'      => 30, // 100% of 30 target -> 20 * 1.0 = 20.0
    'accepted_revenue'    => 100000000, // 100% of 100m target -> 40 * 1.0 = 40.0
    'closed_count'        => 10,
    'accepted_count'      => 5, // 50% acceptance vs 40% target -> 125% capped at 120% -> 25 * 1.2 = 30.0
    // Even if reminder metrics are passed in, V1 must ignore them
    'eligible_reminders'  => 10,
    'on_time_reminders'   => 10,
];
$v1Targets = [
    'target_quotes'       => 30,
    'target_revenue'      => 100000000,
    'target_acceptance'   => 40.0,
    'target_response_sla' => 90.0,
    'min_closed_quotes'   => 5,
];
$v1Result = $calcV1->calculate_score($v1Metrics, $v1Targets);
// Sum of components: 20.0 + 40.0 + 30.0 = 90.0. Denominator fixed at 85.
// Raw score = (90.0 / 85.0) * 100 = 105.882... capped at 120. Final rounded = 105.9
assert_equals(85.0, (float) $v1Result['effective_denominator'], 'V1 effective denominator must be strictly 85');
assert_true(!isset($v1Result['components']['reminder_response']), 'V1 must not contain reminder_response component');
assert_equals(105.9, (float) $v1Result['performance_score'], 'V1 score must match golden fixture');

// 3. V2 Calculator: Reminder included, dynamic denominator, confidence weighting
$calcV2 = new Performance_score_calculator_v2();
assert_equals('performance_score_v2', $calcV2::FORMULA_VERSION, 'V2 formula version must be performance_score_v2');

// Case 3a: Small sample closed quotes (e.g. 1 closed quote won, target 5 min closed quotes)
$v2SmallSample = [
    'estimate_count'      => 30,
    'accepted_revenue'    => 100000000,
    'closed_count'        => 1, // < 5 -> confidence = 1/5 = 0.2
    'accepted_count'      => 1, // 100% acceptance vs 40% target -> raw = 250%
    'eligible_reminders'  => 0, // -> dynamic denom 85
    'on_time_reminders'   => 0,
];
$v2ResultSmall = $calcV2->calculate_score($v2SmallSample, $v1Targets);
assert_equals(85.0, (float) $v2ResultSmall['effective_denominator'], 'V2 with 0 reminders must use 85 denominator');
assert_true(!empty($v2ResultSmall['is_provisional']), 'V2 with small sample must set is_provisional = true');
// Raw acceptance component = (100 / 40) * 25 = 62.5
// Confidence = 1/5 = 0.2
// Final component before cap = 62.5 * 0.2 = 12.5 (well under cap of 30.0)
assert_equals(12.5, round((float) $v2ResultSmall['components']['acceptance_rate']['score'], 4), 'Acceptance score must reflect confidence weighting');

// 4. Dispatcher Cutover test
$dispatcher = new Performance_score_dispatcher();

// Month cutover: before 2026-09 -> V1, from 2026-09 -> V2
assert_equals('performance_score_v1', $dispatcher->resolve_formula_version('month', '2026-08-01', '2026-08-31'), 'August 2026 must use V1');
assert_equals('performance_score_v2', $dispatcher->resolve_formula_version('month', '2026-09-01', '2026-09-30'), 'September 2026 must use V2');

// Week cutover: 31/08 - 06/09 starts in Aug -> V1. 07/09 - 13/09 starts in Sep -> V2
assert_equals('performance_score_v1', $dispatcher->resolve_formula_version('week', '2026-08-31', '2026-09-06'), 'Week starting Aug 31 must use V1');
assert_equals('performance_score_v2', $dispatcher->resolve_formula_version('week', '2026-09-07', '2026-09-13'), 'Week starting Sep 07 must use V2');

// Quarter & Year cutover
assert_equals('performance_score_v1', $dispatcher->resolve_formula_version('quarter', '2026-07-01', '2026-09-30'), 'Q3 2026 must use V1');
assert_equals('performance_score_v2', $dispatcher->resolve_formula_version('quarter', '2026-10-01', '2026-12-31'), 'Q4 2026 must use V2');
assert_equals('performance_score_v1', $dispatcher->resolve_formula_version('year', '2026-01-01', '2026-12-31'), 'Year 2026 must use V1');
assert_equals('performance_score_v2', $dispatcher->resolve_formula_version('year', '2027-01-01', '2027-12-31'), 'Year 2027 must use V2');

echo "PASS: Performance score V2, dispatcher, and snapshot contract\n";
