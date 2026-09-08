<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

function hardening_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$moduleRoot = dirname(__DIR__);
$bootstrap = file_get_contents($moduleRoot . '/sales_pipeline.php');
$dashboard = file_get_contents($moduleRoot . '/views/dashboard.php');
$model = file_get_contents($moduleRoot . '/models/Sales_pipeline_model.php');
$engine = file_get_contents($moduleRoot . '/libraries/Reminder_engine.php');
$revisionService = file_get_contents($moduleRoot . '/libraries/Estimate_revision_service.php');
$appEmail = file_get_contents(dirname($moduleRoot, 2) . '/application/libraries/App_Email.php');
$apexCharts = $moduleRoot . '/assets/js/apexcharts.min.js';
$apexChartsLicense = $moduleRoot . '/assets/js/apexcharts.LICENSE.txt';

hardening_assert(
    strpos($bootstrap, "hooks()->add_action('app_init', 'sales_pipeline_estimate_group_schema_bootstrap')") === false,
    'Estimate Group DDL bootstrap must not run during app_init'
);
hardening_assert(
    strpos($bootstrap, "hooks()->add_action('app_init', 'sales_pipeline_reminder_repository_schema_bootstrap')") === false,
    'Reminder Repository DDL bootstrap must not run during app_init'
);
hardening_assert(
    strpos($bootstrap, 'function sales_pipeline_estimate_group_schema_bootstrap') === false
        && strpos($bootstrap, 'function sales_pipeline_reminder_repository_schema_bootstrap') === false,
    'Removed runtime schema bootstrap functions must not remain as zombie code'
);
hardening_assert(
    strpos(file_get_contents($moduleRoot . '/install.php'), 'sales_pipeline_ensure_estimate_group_schema') !== false
        && strpos(file_get_contents($moduleRoot . '/install.php'), 'sales_pipeline_ensure_reminder_repository_schema') !== false,
    'Activation install must remain authoritative for schema creation'
);

hardening_assert(strpos($dashboard, 'cdn.jsdelivr.net/npm/apexcharts') === false, 'Dashboard must not depend on ApexCharts CDN');
hardening_assert(
    strpos($dashboard, "module_dir_url('sales_pipeline', 'assets/js/apexcharts.min.js')") !== false,
    'Dashboard must load the module-local ApexCharts asset'
);
hardening_assert(is_file($apexCharts), 'Vendored ApexCharts asset must exist');
hardening_assert(
    is_file($apexCharts) && strpos(file_get_contents($apexCharts), 'ApexCharts v7.1.0') !== false,
    'Vendored ApexCharts version must be pinned to 7.1.0'
);
hardening_assert(
    is_file($apexCharts) && hash_file('sha256', $apexCharts) === '44f6a2129104bf6ee9f29f0399ecc725990d007d8a89e754ddc250ce27d3f47b',
    'Vendored ApexCharts checksum must match the reviewed jsDelivr artifact'
);
hardening_assert(
    is_file($apexChartsLicense) && strpos(file_get_contents($apexChartsLicense), 'dual-license model') !== false,
    'Vendored ApexCharts distribution must retain its license notice'
);

hardening_assert(
    preg_match('/function\s+reconcile_estimate_groups\s*\(\s*\$limit\s*=\s*100\s*\)/', $model) === 1,
    'Reconcile model default batch must be 100'
);
hardening_assert(
    strpos($bootstrap, 'reconcile_estimate_groups(100);') !== false
        && strpos($bootstrap, 'reconcile_estimate_groups(1000);') === false,
    'Cron must reconcile at most 100 Estimate Groups per cycle'
);

hardening_assert(
    strpos($engine, "group_by('staff_id')") !== false
        && strpos($engine, '$summariesByStaff') !== false,
    'Deal Pipeline minimum rule must aggregate all staff summaries in one grouped query'
);
hardening_assert(
    strpos($engine, 'private $staffCache = [];') !== false
        && strpos($engine, 'array_key_exists($staffId, $this->staffCache)') !== false,
    'Reminder Engine must memoize repeated Staff lookups during one run'
);
hardening_assert(
    strpos($engine, 'private function insertDeliveryBatch(array $rows)') !== false
        && strpos($engine, 'array_fill(0, count($rows), \'(?,?,?,?,?,?,?,?)\')') !== false,
    'Reminder delivery materialization must issue a parameter-bound multi-row insert'
);
hardening_assert(
    strpos($engine, "INSERT IGNORE INTO `") !== false,
    'Batch materialization must preserve INSERT IGNORE idempotency semantics'
);

preg_match_all('/WHERE (?:`id`|id) = ([^\s]+) FOR UPDATE/', $revisionService, $lockMatches);
hardening_assert(
    !empty($lockMatches[1]) && count(array_filter($lockMatches[1], function ($value) {
        return $value !== '?';
    })) === 0,
    'Estimate revision row locks must bind every group ID parameter'
);

// The production Reminder Engine runs from after_cron_run. App_Email bypasses
// Core Mail Queue in that context, so send_simple_email() means an SMTP attempt,
// not merely a queue insert. A future queued state requires a queue-row mapping
// and a sent/failed synchronizer; changing the status string alone is unsafe.
hardening_assert(
    strpos($bootstrap, "hooks()->add_action('after_cron_run', 'sales_pipeline_cron_reminder')") !== false
        && strpos($appEmail, "defined('CRON') && !is_staff_logged_in()") !== false,
    'Production Reminder dispatch must retain direct SMTP semantics under CRON'
);
$usesQueuedStatus = strpos($engine, "'queued'") !== false || strpos($engine, '"queued"') !== false;
hardening_assert(
    !$usesQueuedStatus
        || (strpos($engine, 'core_mail_queue_id') !== false && strpos($engine, 'syncQueued') !== false),
    'A queued delivery state must not exist without Core Queue correlation and reconciliation'
);

echo "PASS: Production readiness hardening contracts\n";
