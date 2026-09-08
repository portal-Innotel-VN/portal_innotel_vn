<?php

define('BASEPATH', __DIR__);

$moduleRoot = dirname(__DIR__);
require_once $moduleRoot . '/includes/reminder_rule_defaults.php';

$defaults = sales_pipeline_reminder_rule_default_options();
foreach ([
    'sp_reminder_global_enabled',
    'sp_reminder_deal_pipeline_enabled',
    'sp_reminder_deal_pipeline_min_count',
    'sp_reminder_deal_pipeline_check_time',
    'sp_reminder_deal_stale_enabled',
    'sp_reminder_deal_stale_cutoff_days',
    'sp_reminder_deal_stale_max_per_run',
    'sp_reminder_est_daily_time',
    'sp_reminder_est_weekly_target',
    'sp_reminder_lc_declined_days',
] as $key) {
    if (!array_key_exists($key, $defaults)) {
        fwrite(STDERR, "FAIL: missing reminder setting {$key}\n");
        exit(1);
    }
}

foreach (['sp_reminder_deal_frequency_enabled', 'sp_reminder_deal_frequency_channels'] as $retiredKey) {
    if (array_key_exists($retiredKey, $defaults)) {
        fwrite(STDERR, "FAIL: retired Deal frequency setting is still active: {$retiredKey}\n");
        exit(1);
    }
}

$engine = file_get_contents($moduleRoot . '/libraries/Reminder_engine.php');
foreach (['DEAL_FREQUENCY_REMINDER', 'processDeals', 'sp_reminder_deal_frequency'] as $retiredNeedle) {
    if (strpos($engine, $retiredNeedle) !== false) {
        fwrite(STDERR, "FAIL: retired Deal frequency evaluator is still reachable: {$retiredNeedle}\n");
        exit(1);
    }
}
foreach ([
    'dispatchPendingDeliveries',
    'nextAllowedDeliveryAt',
    "'title' => \$content['title']",
    '`recipient_staff_id`',
    "'cc_recipients' => \$ccString",
    'expireStaleEmails',
    "due('crm'",
    "due('email'",
    'processDealPipelineMinimum',
    'processDealStaleFollowUps',
    "DEAL_PIPELINE_MIN_COUNT",
    "DEAL_STALE_FOLLOW_UP",
    "DEAL_STALE_BACKLOG",
    "evaluateStaleFollowUps",
    "Phản hồi nhắc nhở:",
    "Reminder response:",
    "sales_pipeline_activity'",
    "'manager_cc'",
] as $needle) {
    if (strpos($engine, $needle) === false) {
        fwrite(STDERR, "FAIL: missing delivery safety invariant {$needle}\n");
        exit(1);
    }
}

$selector = file_get_contents($moduleRoot . '/libraries/Reminder_delivery_selector.php');
foreach ([
    "`status`='processing'",
    "d.channel=?",
    "CASE WHEN d.status='pending' THEN 0 ELSE 1 END",
    "`status`='expired'",
] as $needle) {
    if (strpos($selector, $needle) === false) {
        fwrite(STDERR, "FAIL: missing delivery selector invariant {$needle}\n");
        exit(1);
    }
}

$dealEvaluator = file_get_contents($moduleRoot . '/libraries/Deal_reminder_rule_evaluator.php');
foreach (["'staff_deal_period'", "'START_WEEK'", "'MIDWEEK'", "'FINAL'", "'manager_cc' => \$managerCC"] as $needle) {
    if (strpos($dealEvaluator, $needle) === false) {
        fwrite(STDERR, "FAIL: missing Deal evaluator invariant {$needle}\n");
        exit(1);
    }
}

$model = file_get_contents($moduleRoot . '/models/Sales_pipeline_model.php');
foreach (["staff_deal_period", "staff_deal_backlog", "DEAL_PIPELINE_MIN_COUNT"] as $needle) {
    if (strpos($model, $needle) === false) {
        fwrite(STDERR, "FAIL: Deal period reminder is not exposed in response/drawer flow: {$needle}\n");
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

$install = file_get_contents($moduleRoot . '/install.php');
if (strpos($install, 'sales_pipeline_ensure_reminder_repository_schema') === false) {
    fwrite(STDERR, "FAIL: module activation does not install the reminder repository schema\n");
    exit(1);
}

fwrite(STDOUT, "PASS: Reminder rule settings, delivery safety, and audit invariants\n");
