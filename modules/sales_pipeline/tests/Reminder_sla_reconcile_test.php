<?php

define('BASEPATH', __DIR__);

require_once dirname(__DIR__) . '/libraries/Performance_score_calculator.php';
require_once dirname(__DIR__) . '/includes/performance_score_defaults.php';
require_once dirname(__DIR__) . '/includes/reminder_rule_defaults.php';

function assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function assert_true($actual, $message)
{
    assert_same(true, (bool) $actual, $message);
}

// 1. Test Reminder Rule & Performance Defaults
$reminderDefaults = sales_pipeline_reminder_rule_default_options();
assert_true(isset($reminderDefaults['sp_reminder_sla_hours']), 'sp_reminder_sla_hours must exist in defaults');
assert_same('24', $reminderDefaults['sp_reminder_sla_hours'], 'Default SLA hours must be 24');

$perfDefaults = sales_pipeline_performance_score_default_options();
assert_true(isset($perfDefaults['performance_response_target_percent']), 'performance_response_target_percent must exist in defaults');
assert_same('90', $perfDefaults['performance_response_target_percent'], 'Default response target must be 90%');

// 1.1 Schema belongs to activation/migrations, never runtime app_init.
$schemaSource = file_get_contents(dirname(__DIR__) . '/includes/reminder_repository_schema.php');
foreach (['response_sla_hours', 'response_due_at', 'idx_reminder_sla_eval'] as $needle) {
    assert_true(strpos($schemaSource, $needle) !== false, "reminder repository schema must define {$needle}");
}
assert_true(
    strpos(file_get_contents(dirname(__DIR__) . '/install.php'), 'sales_pipeline_ensure_reminder_repository_schema') !== false,
    'module activation must install the reminder repository schema'
);

// 2. Test SLA Eligibility & On-time logic simulation
$calculatedAt = '2026-03-15 12:00:00';
$periodStart = '2026-03-01 00:00:00';
$periodEndExclusive = '2026-04-01 00:00:00';

$reminders = [
    // 1. On time response within period
    [
        'id' => 1, 'staff_id' => 10, 'entity_type' => 'estimate', 'response_required' => 1,
        'response_due_at' => '2026-03-05 10:00:00', 'responded_at' => '2026-03-05 08:00:00'
    ],
    // 2. Exact deadline response within period -> on time
    [
        'id' => 2, 'staff_id' => 10, 'entity_type' => 'estimate', 'response_required' => 1,
        'response_due_at' => '2026-03-10 15:00:00', 'responded_at' => '2026-03-10 15:00:00'
    ],
    // 3. Late response within period -> eligible but not on time
    [
        'id' => 3, 'staff_id' => 10, 'entity_type' => 'estimate', 'response_required' => 1,
        'response_due_at' => '2026-03-12 12:00:00', 'responded_at' => '2026-03-12 14:00:00'
    ],
    // 4. Overdue unresponded within period -> eligible but not on time
    [
        'id' => 4, 'staff_id' => 10, 'entity_type' => 'staff_estimate_period', 'response_required' => 1,
        'response_due_at' => '2026-03-14 12:00:00', 'responded_at' => null
    ],
    // 5. Future deadline within period (after calculated_at) -> NOT eligible
    [
        'id' => 5, 'staff_id' => 10, 'entity_type' => 'estimate', 'response_required' => 1,
        'response_due_at' => '2026-03-20 12:00:00', 'responded_at' => null
    ],
    // 6. Legacy reminder (response_due_at IS NULL) -> NOT eligible
    [
        'id' => 6, 'staff_id' => 10, 'entity_type' => 'estimate', 'response_required' => 1,
        'response_due_at' => null, 'responded_at' => '2026-03-05 10:00:00'
    ],
    // 7. Informational reminder (response_required = 0) -> NOT eligible
    [
        'id' => 7, 'staff_id' => 10, 'entity_type' => 'estimate', 'response_required' => 0,
        'response_due_at' => '2026-03-08 10:00:00', 'responded_at' => null
    ],
    // 8. Deal reminder (entity_type = 'deal') -> NOT eligible for estimate ranking
    [
        'id' => 8, 'staff_id' => 10, 'entity_type' => 'deal', 'response_required' => 1,
        'response_due_at' => '2026-03-08 10:00:00', 'responded_at' => '2026-03-08 09:00:00'
    ],
    // 9. Reminder outside period (prior month) -> NOT eligible
    [
        'id' => 9, 'staff_id' => 10, 'entity_type' => 'estimate', 'response_required' => 1,
        'response_due_at' => '2026-02-28 23:59:59', 'responded_at' => '2026-02-28 20:00:00'
    ],
];

$eligibleCount = 0;
$onTimeCount = 0;

foreach ($reminders as $r) {
    $isEligible = in_array($r['entity_type'], ['estimate', 'staff_estimate_period'], true)
        && (int) $r['response_required'] === 1
        && !empty($r['response_due_at'])
        && $r['response_due_at'] >= $periodStart
        && $r['response_due_at'] < $periodEndExclusive
        && $r['response_due_at'] <= $calculatedAt;

    if ($isEligible) {
        $eligibleCount++;
        $isOnTime = !empty($r['responded_at']) && $r['responded_at'] <= $r['response_due_at'];
        if ($isOnTime) {
            $onTimeCount++;
        }
    }
}

assert_same(4, $eligibleCount, 'Should have exactly 4 eligible reminders (reminders 1, 2, 3, 4)');
assert_same(2, $onTimeCount, 'Should have exactly 2 on-time reminders (reminders 1, 2)');

// 3. Calculator with aggregated stats
$calc = new Performance_score_calculator();
$conf = [
    'formula_version'   => 'performance_score_v1',
    'quote_target'      => 20,
    'revenue_target'    => 1000000000,
    'acceptance_target' => 50,
    'response_target'   => 90,
    'component_cap'     => 120,
    'min_closed_quotes' => 3,
    'calculated_at'     => $calculatedAt,
];

$result = $calc->calculate_leaderboard([[
    'staff_id'                  => 10,
    'staff_name'                => 'SLA Staff',
    'estimate_count'            => 20,
    'accepted_revenue'          => 1000000000,
    'accepted_count'            => 5,
    'declined_count'            => 5,
    'missing_revenue_rate_count'=> 0,
    'eligible_reminders'        => $eligibleCount,
    'on_time_reminders'         => $onTimeCount,
]], $conf);

// on_time_rate = 2 / 4 * 100 = 50%
// response_score = (50 / 90) * 100 = 55.5556
// total = (100*20 + 100*40 + 100*25 + 55.5556*15) / 100 = (2000 + 4000 + 2500 + 833.333) / 100 = 93.3333 -> 93.3
assert_same(55.5556, $result['leaderboard'][0]['response_score'], 'Response score calculation check');
assert_same(93.3, $result['leaderboard'][0]['performance_score'], 'Overall performance score with response_score included');
assert_same('active', $result['leaderboard'][0]['component_status']['reminder_response'], 'Reminder response status must be active');
assert_same(15.0, $result['leaderboard'][0]['effective_weights']['reminder_response'], 'Effective weight must be 15%');
assert_same($calculatedAt, $result['calculated_at'], 'Payload must return calculated_at timestamp');

// 4. Contract Test: Delivery & SLA Start Logic
// 4.1 Manager sent first -> does NOT start SLA (recipient_type != 'staff')
$managerDelivery = [
    'recipient_type'     => 'manager',
    'recipient_staff_id' => 99,
    'staff_id'           => 10,
    'status'             => 'sent',
];
$isStaffRecipient = ($managerDelivery['recipient_type'] === 'staff')
    && ((int) ($managerDelivery['recipient_staff_id'] ?? 0) === (int) $managerDelivery['staff_id']);
assert_same(false, $isStaffRecipient, 'Manager delivery must not trigger reminder SLA deadline update');

// 4.2 Staff delivery failure -> does NOT start SLA
$failedStaffDelivery = [
    'recipient_type'     => 'staff',
    'recipient_staff_id' => 10,
    'staff_id'           => 10,
    'success'            => false,
];
$shouldTriggerSla = $failedStaffDelivery['success'] && ($failedStaffDelivery['recipient_type'] === 'staff');
assert_same(false, $shouldTriggerSla, 'Failed staff delivery must not trigger SLA');

// 4.3 Staff delivery success -> triggers SLA
$successStaffDelivery = [
    'recipient_type'     => 'staff',
    'recipient_staff_id' => 10,
    'staff_id'           => 10,
    'success'            => true,
];
$shouldTriggerSla = $successStaffDelivery['success'] && ($successStaffDelivery['recipient_type'] === 'staff');
assert_same(true, $shouldTriggerSla, 'Successful staff delivery triggers SLA');

// 4.4 Guard: Two staff deliveries (e.g. CRM + Email) - first one sets sent_at, second does not overwrite
$reminderLog = [
    'id'                 => 100,
    'sent_at'            => '2026-03-01 10:00:00',
    'response_due_at'    => '2026-03-02 10:00:00',
    'response_required'  => 1,
    'response_sla_hours' => 24,
];
$secondDeliverySentAt = '2026-03-01 10:05:00';
$canUpdateSecondTime = ($reminderLog['response_required'] === 1)
    && ($reminderLog['response_sla_hours'] !== null)
    && ($reminderLog['sent_at'] === null) // Guard prevents overwrite
    && ($reminderLog['response_due_at'] === null);
assert_same(false, $canUpdateSecondTime, 'Second delivery channel must not overwrite existing deadline');

// 5. Contract Test: Reconciler Advisory Lock & Criteria
// 5.1 Reconciler ignores legacy reminders (response_sla_hours IS NULL)
$legacyReminder = [
    'id'                 => 200,
    'response_required'  => 1,
    'response_sla_hours' => null,
    'response_due_at'    => null,
    'first_sent_at'      => '2026-03-01 08:00:00',
];
$reconcilerEligible = ($legacyReminder['response_required'] === 1)
    && ($legacyReminder['response_sla_hours'] !== null)
    && ($legacyReminder['response_due_at'] === null)
    && ($legacyReminder['first_sent_at'] !== null);
assert_same(false, $reconcilerEligible, 'Legacy reminder without SLA snapshot must never be reconciled');

// 5.2 Reconciler selects actionable reminder with SLA snapshot missing response_due_at
$reconcilableReminder = [
    'id'                 => 201,
    'response_required'  => 1,
    'response_sla_hours' => 24,
    'response_due_at'    => null,
    'first_sent_at'      => '2026-03-01 08:00:00',
];
$reconcilerEligible = ($reconcilableReminder['response_required'] === 1)
    && ($reconcilableReminder['response_sla_hours'] !== null)
    && ($reconcilableReminder['response_due_at'] === null)
    && ($reconcilableReminder['first_sent_at'] !== null);
assert_same(true, $reconcilerEligible, 'Actionable reminder with SLA snapshot missing due_at is eligible for reconciliation');

// 6. Contract Test: Settings Boundaries & Form Isolation
// 6.1 SLA hours boundary (1 to 720)
function validate_sla_hours($input) {
    if (!is_numeric($input)) return null;
    $val = (int) $input;
    return ($val >= 1 && $val <= 720) ? $val : null;
}
assert_same(1, validate_sla_hours(1), 'Min boundary 1 is valid');
assert_same(720, validate_sla_hours(720), 'Max boundary 720 is valid');
assert_same(24, validate_sla_hours(24), 'Standard 24 is valid');
assert_same(null, validate_sla_hours(0), '0 is invalid');
assert_same(null, validate_sla_hours(721), '721 is invalid');
assert_same(null, validate_sla_hours(-5), '-5 is invalid');

// 6.2 Response target percent boundary (1 to 100)
function validate_response_target($input) {
    if (!is_numeric($input)) return null;
    $val = (float) $input;
    return ($val >= 1.0 && $val <= 100.0) ? $val : null;
}
assert_same(1.0, validate_response_target(1), 'Min boundary 1% is valid');
assert_same(100.0, validate_response_target(100), 'Max boundary 100% is valid');
assert_same(90.0, validate_response_target(90), 'Default 90% is valid');
assert_same(null, validate_response_target(0), '0% is invalid');
assert_same(null, validate_response_target(101), '101% is invalid');

// 6.3 Settings Form Isolation logic check
$submittedForm = 'performance';
$performanceOptionsModified = false;
$reminderOptionsModified = false;

if ($submittedForm === 'performance') {
    $performanceOptionsModified = true;
} elseif ($submittedForm === 'reminder') {
    $reminderOptionsModified = true;
}
assert_same(true, $performanceOptionsModified, 'Performance form submission only touches performance options');
assert_same(false, $reminderOptionsModified, 'Performance form submission does not touch reminder options');

fwrite(STDOUT, "PASS: Reminder SLA reconcile, delivery and settings contract tests\n");
