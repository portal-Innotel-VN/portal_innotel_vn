<?php

defined('BASEPATH') or define('BASEPATH', dirname(__DIR__));

require_once dirname(__DIR__) . '/libraries/Reminder_response_guard.php';

function assert_guard_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, 'FAILED: ' . $message . PHP_EOL);
        exit(1);
    }
}

$guard = new Reminder_response_guard();
$base = [
    'response_required' => 1,
    'sent_at' => null,
    'response_due_at' => null,
    'staff_response' => null,
];

assert_guard_same('delivery_pending', $guard->check($base), 'response before delivery is rejected');

$sentWithoutDue = $base;
$sentWithoutDue['sent_at'] = '2026-08-01 08:00:00';
assert_guard_same('delivery_pending', $guard->check($sentWithoutDue), 'delivery without SLA due time is rejected');

$delivered = $sentWithoutDue;
$delivered['response_due_at'] = '2026-08-01 17:00:00';
assert_guard_same('allowed', $guard->check($delivered), 'delivered reminder accepts a response');

$already = $delivered;
$already['staff_response'] = 'Done';
assert_guard_same('already_responded', $guard->check($already), 'second or concurrent response is rejected');

$notRequired = $delivered;
$notRequired['response_required'] = 0;
assert_guard_same('response_not_required', $guard->check($notRequired), 'non-response reminder is rejected');

$modelSource = file_get_contents(dirname(__DIR__) . '/models/Sales_pipeline_model.php');
assert_guard_same(true, strpos($modelSource, 'FOR UPDATE') !== false, 'submit locks the reminder row');
assert_guard_same(true, strpos($modelSource, "where('staff_response IS NULL', null, false)") !== false, 'submit uses a compare-before-write guard');
assert_guard_same(true, strpos($modelSource, 'affected_rows() !== 1') !== false, 'only one concurrent submit may succeed');

fwrite(STDOUT, "PASS: Reminder response guard\n");
