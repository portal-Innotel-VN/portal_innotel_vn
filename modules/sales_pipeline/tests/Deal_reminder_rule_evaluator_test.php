<?php

define('BASEPATH', __DIR__);

require_once dirname(__DIR__) . '/libraries/Deal_reminder_rule_evaluator.php';

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$evaluator = new Deal_reminder_rule_evaluator();
$config = [
    'enabled' => true,
    'minimum_count' => 2,
    'check_time' => '09:00',
    'channels' => ['crm', 'email'],
];
$staff = ['staff_id' => 7, 'open_deal_count' => 0, 'open_pipeline_value' => 0];

$monday = $evaluator->evaluatePipelineMinimum($staff, new DateTimeImmutable('2026-08-24 09:00:00'), $config);
assertSameValue('START_WEEK', $monday['checkpoint'] ?? null, 'Monday must create the start-week checkpoint');
assertSameValue('warning', $monday['severity'] ?? null, 'Monday checkpoint must warn staff');
assertSameValue(false, $monday['manager_cc'] ?? null, 'Monday must not CC managers');

$wednesday = $evaluator->evaluatePipelineMinimum($staff, new DateTimeImmutable('2026-08-26 09:00:00'), $config);
assertSameValue('MIDWEEK', $wednesday['checkpoint'] ?? null, 'Wednesday must create the midweek checkpoint');
assertSameValue(false, $wednesday['manager_cc'] ?? null, 'Wednesday must not CC managers');

$friday = $evaluator->evaluatePipelineMinimum($staff, new DateTimeImmutable('2026-08-28 09:00:00'), $config);
assertSameValue('FINAL', $friday['checkpoint'] ?? null, 'Friday must create the final checkpoint');
assertSameValue('critical', $friday['severity'] ?? null, 'Friday checkpoint must be critical');
assertSameValue(true, $friday['manager_cc'] ?? null, 'Friday must CC managers on the staff email');
assertSameValue(['staff'], $friday['recipients'] ?? null, 'Manager must not become a separate delivery recipient');

assertSameValue(null, $evaluator->evaluatePipelineMinimum($staff, new DateTimeImmutable('2026-08-25 09:00:00'), $config), 'Tuesday must not evaluate the weekly rule');
assertSameValue(null, $evaluator->evaluatePipelineMinimum(['staff_id' => 7, 'open_deal_count' => 2], new DateTimeImmutable('2026-08-24 09:00:00'), $config), 'Staff meeting the threshold must not be reminded');
assertSameValue(null, $evaluator->evaluatePipelineMinimum($staff, new DateTimeImmutable('2026-08-24 08:59:00'), $config), 'Rule must wait until the configured time');

$stale = $evaluator->evaluateStaleFollowUp([
    'id' => 41,
    'staff_id' => 7,
    'deal_name' => 'ERP Renewal',
    'customer_name' => 'ACME',
    'reminder_frequency' => 3,
    'last_meaningful_activity_at' => '2026-08-20 10:00:00',
], new DateTimeImmutable('2026-08-24 09:00:00'), ['enabled' => true, 'channels' => ['crm', 'email'], 'cutoff_days' => 30, 'max_per_run' => 5]);
assertSameValue('DEAL_STALE_FOLLOW_UP', $stale['rule_code'] ?? null, 'Stale Deal must produce the new rule');
assertSameValue(4, $stale['snapshot']['inactive_days'] ?? null, 'Inactivity age must be captured for audit');
assertSameValue(false, $stale['manager_cc'] ?? null, 'Stale follow-up does not CC managers');

$fresh = $evaluator->evaluateStaleFollowUp([
    'id' => 41,
    'staff_id' => 7,
    'reminder_frequency' => 3,
    'last_meaningful_activity_at' => '2026-08-23 10:00:00',
], new DateTimeImmutable('2026-08-24 09:00:00'), ['enabled' => true, 'channels' => ['crm', 'email'], 'cutoff_days' => 30, 'max_per_run' => 5]);
assertSameValue(null, $fresh, 'Recently followed Deal must not be reminded');

$tierConfig = ['enabled' => true, 'channels' => ['crm', 'email'], 'cutoff_days' => 30, 'max_per_run' => 5];
$notDue = $evaluator->evaluateStaleFollowUp([
    'id' => 42, 'staff_id' => 7, 'reminder_frequency' => 3,
    'last_meaningful_activity_at' => '2026-08-22 10:00:00',
], new DateTimeImmutable('2026-08-24 09:00:00'), $tierConfig);
assertSameValue(null, $notDue, 'Inactive days below reminder frequency must not notify');

$dueThirty = $evaluator->evaluateStaleFollowUp([
    'id' => 43, 'staff_id' => 7, 'reminder_frequency' => 3,
    'last_meaningful_activity_at' => '2026-07-25 10:00:00',
], new DateTimeImmutable('2026-08-24 09:00:00'), $tierConfig);
assertSameValue('DEAL_STALE_FOLLOW_UP:43:2026-07-25T10:00:00:3', $dueThirty['dedupe_key'] ?? null, 'Dedupe must use activity cycle, not stale age');

$longStale = $evaluator->evaluateStaleFollowUps([[
    'id' => 44, 'staff_id' => 7, 'deal_name' => 'Long stale', 'customer_name' => 'ACME',
    'deal_value' => 100, 'reminder_frequency' => 3,
    'last_meaningful_activity_at' => '2026-07-21 10:00:00',
]], new DateTimeImmutable('2026-08-24 09:00:00'), $tierConfig);
assertSameValue(1, count($longStale), 'A Deal over cutoff must create one backlog reminder');
assertSameValue('DEAL_STALE_BACKLOG', $longStale[0]['rule_code'] ?? null, 'Long stale Deal must use backlog rule');
assertSameValue('staff_deal_backlog', $longStale[0]['entity_type'] ?? null, 'Backlog must be a staff-level entity');
assertSameValue(1, $longStale[0]['snapshot']['long_stale_count'] ?? null, 'Backlog must count long-stale Deals');
assertSameValue(false, $longStale[0]['manager_cc'] ?? null, 'Backlog must not CC managers');

$manyStale = [];
for ($i = 1; $i <= 20; $i++) {
    $manyStale[] = [
        'id' => 100 + $i, 'staff_id' => 7, 'deal_name' => 'Deal ' . $i, 'customer_name' => 'ACME',
        'deal_value' => $i * 100, 'reminder_frequency' => 3,
        'last_meaningful_activity_at' => (new DateTimeImmutable('2026-08-24 09:00:00'))->modify('-' . ($i + 3) . ' days')->format('Y-m-d H:i:s'),
    ];
}
$manyEvents = $evaluator->evaluateStaleFollowUps($manyStale, new DateTimeImmutable('2026-08-24 09:00:00'), $tierConfig);
$individualEvents = array_values(array_filter($manyEvents, function ($event) { return $event['rule_code'] === 'DEAL_STALE_FOLLOW_UP'; }));
$backlogEvents = array_values(array_filter($manyEvents, function ($event) { return $event['rule_code'] === 'DEAL_STALE_BACKLOG'; }));
assertSameValue(5, count($individualEvents), 'Only max_per_run individual stale reminders may be emitted');
assertSameValue(1, count($backlogEvents), 'Overflow must be represented by one summary/backlog reminder');
assertSameValue(15, $backlogEvents[0]['snapshot']['stale_overflow_count'] ?? null, 'Overflow count must be preserved');
assertSameValue(20, $backlogEvents[0]['snapshot']['total_attention_required'] ?? null, 'Summary must preserve all attention-required Deals');
assertSameValue(120, $individualEvents[0]['entity_id'] ?? null, 'Deterministic ordering must prioritize the stalest Deal');

$sameCycle = $evaluator->evaluateStaleFollowUp([
    'id' => 41, 'staff_id' => 7, 'reminder_frequency' => 3,
    'last_meaningful_activity_at' => '2026-08-20 10:00:00',
], new DateTimeImmutable('2026-08-29 09:00:00'), $tierConfig);
assertSameValue($stale['dedupe_key'], $sameCycle['dedupe_key'], 'Stale age changes must not create a new dedupe cycle');

fwrite(STDOUT, "PASS: Deal reminder rule evaluator\n");
