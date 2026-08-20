<?php

define('BASEPATH', __DIR__);

$moduleRoot = dirname(__DIR__);
require_once $moduleRoot . '/includes/reminder_rule_defaults.php';

$defaults = sales_pipeline_reminder_rule_default_options();
foreach (['sp_reminder_global_enabled', 'sp_reminder_est_daily_time', 'sp_reminder_est_weekly_target', 'sp_reminder_lc_declined_days'] as $key) {
    if (!array_key_exists($key, $defaults)) {
        fwrite(STDERR, "FAIL: missing reminder setting {$key}\n");
        exit(1);
    }
}

$engine = file_get_contents($moduleRoot . '/libraries/Reminder_engine.php');
foreach ([
    'dispatchPendingDeliveries',
    'nextAllowedDeliveryAt',
    "'title' => \$content['title']",
    'SET `status` = ?, `updated_at` = NOW()',
    '`recipient_staff_id`',
    "'cc_recipients' => \$ccString",
    "WHEN d.channel='crm' AND d.status='pending' THEN 0",
    "WHEN d.status='pending' THEN 2",
] as $needle) {
    if (strpos($engine, $needle) === false) {
        fwrite(STDERR, "FAIL: missing delivery safety invariant {$needle}\n");
        exit(1);
    }
}

$schema = file_get_contents($moduleRoot . '/includes/reminder_repository_schema.php');
foreach (['recipient_staff_id', 'cc_recipients'] as $column) {
    if (strpos($schema, $column) === false) {
        fwrite(STDERR, "FAIL: missing reminder delivery audit column {$column}\n");
        exit(1);
    }
}

$module = file_get_contents($moduleRoot . '/sales_pipeline.php');
foreach (['field_exists(\'recipient_staff_id\', $deliveries)', 'field_exists(\'cc_recipients\', $deliveries)'] as $needle) {
    if (strpos($module, $needle) === false) {
        fwrite(STDERR, "FAIL: reminder repository bootstrap does not verify {$needle}\n");
        exit(1);
    }
}

fwrite(STDOUT, "PASS: Reminder rule settings, delivery safety, and audit invariants\n");
