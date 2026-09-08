<?php

define('BASEPATH', __DIR__);

if (!function_exists('db_prefix')) {
    function db_prefix()
    {
        return 'tbl';
    }
}

$moduleRoot = dirname(__DIR__);
require_once $moduleRoot . '/libraries/Reminder_delivery_backoff.php';
require_once $moduleRoot . '/libraries/Reminder_delivery_operations.php';

$backoff = new Reminder_delivery_backoff(function ($min, $max) {
    return $max;
});
$now = new DateTimeImmutable('2026-08-27 12:00:00');
$expected = [72, 360, 1080, 4320, 25920];
foreach ($expected as $index => $seconds) {
    $actual = $backoff->nextRetryAt($index + 1, $now)->getTimestamp() - $now->getTimestamp();
    if ($actual !== $seconds) {
        fwrite(STDERR, "FAIL: backoff schedule/jitter mismatch at attempt " . ($index + 1) . "\n");
        exit(1);
    }
}
if ($backoff->nextRetryAt(20, $now)->getTimestamp() - $now->getTimestamp() !== 25920) {
    fwrite(STDERR, "FAIL: backoff did not cap at the final schedule step\n");
    exit(1);
}

$reflection = new ReflectionClass('Reminder_delivery_operations');
$operations = $reflection->newInstanceWithoutConstructor();
if ($operations->maskRecipient('person@example.com') !== 'p***@example.com'
    || $operations->maskRecipient('not-an-email') !== '[masked]') {
    fwrite(STDERR, "FAIL: recipient masking contract failed\n");
    exit(1);
}

$controller = file_get_contents($moduleRoot . '/controllers/Sales_pipeline.php');
$view = file_get_contents($moduleRoot . '/views/partials/reminder_delivery_health.php')
    . file_get_contents($moduleRoot . '/views/settings.php');
$engine = file_get_contents($moduleRoot . '/libraries/Reminder_engine.php');
foreach (['function reminder_delivery_retry(', 'function reminder_delivery_resume_circuit(',
          "method(true) !== 'POST'", 'is_admin()', 'is_ajax_request()'] as $needle) {
    if (strpos($controller, $needle) === false) {
        fwrite(STDERR, "FAIL: protected operations endpoint missing {$needle}\n");
        exit(1);
    }
}
foreach (['csrfData.token_name', 'sp-delivery-retry', 'sp-delivery-resume'] as $needle) {
    if (strpos($view, $needle) === false) {
        fwrite(STDERR, "FAIL: Delivery Health CSRF/action contract missing {$needle}\n");
        exit(1);
    }
}
if (strpos($engine, 'reminder_delivery_backoff->nextRetryAt(') === false) {
    fwrite(STDERR, "FAIL: engine does not use exponential backoff\n");
    exit(1);
}

fwrite(STDOUT, "PASS: Reminder delivery backoff, masking and operations access contract\n");
