<?php

define('BASEPATH', __DIR__);

if (!function_exists('db_prefix')) {
    function db_prefix()
    {
        return 'tbl';
    }
}

if (!function_exists('_l')) {
    function _l($key, $arg = '')
    {
        return $key;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($url = '')
    {
        return 'http://localhost/admin/' . $url;
    }
}

if (!function_exists('time_ago')) {
    function time_ago($date)
    {
        return '1 hour ago';
    }
}

if (!function_exists('get_staff_user_id')) {
    function get_staff_user_id()
    {
        return 1;
    }
}

$moduleRoot = dirname(__DIR__);

function assert_contains($content, $needle, $message)
{
    if (strpos($content, $needle) === false) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function assert_not_contains($content, $needle, $message)
{
    if (strpos($content, $needle) !== false) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

// 1. Migration/release contract
$migrationFile = $moduleRoot . '/migrations/111_version_111.php';
if (!is_file($migrationFile)) {
    fwrite(STDERR, "FAIL: missing migration 111_version_111.php\n");
    exit(1);
}
$migrationContent = file_get_contents($migrationFile);
assert_contains($migrationContent, "add_option('sp_reminder_crm_inbox_enabled', '0')", 'migration 111 must seed a safe rollout flag when absent');
$moduleContent = file_get_contents($moduleRoot . '/sales_pipeline.php');
preg_match('/Version:\s*(\d+\.\d+\.\d+)/', $moduleContent, $vMatches);
$modVer = $vMatches[1] ?? '0.0.0';
if (version_compare($modVer, '1.0.13', '<')) {
    fwrite(STDERR, "FAIL: module version must activate migration 113 (got {$modVer})\n");
    exit(1);
}
// 2. Schema creation belongs to activation/migrations, never app_init.
$schemaFile = $moduleRoot . '/includes/reminder_repository_schema.php';
$schemaContent = file_get_contents($schemaFile);
if (strpos($schemaContent, 'acknowledged_at') === false || strpos($schemaContent, 'acknowledged_by') === false) {
    fwrite(STDERR, "FAIL: reminder_repository_schema.php missing acknowledged columns\n");
    exit(1);
}
if (strpos($schemaContent, 'idx_reminder_inbox_queue') === false) {
    fwrite(STDERR, "FAIL: reminder_repository_schema.php missing idx_reminder_inbox_queue index\n");
    exit(1);
}

// 3. Kiểm tra Default Options chứa sp_reminder_crm_inbox_enabled
require_once $moduleRoot . '/includes/reminder_rule_defaults.php';
$defaults = sales_pipeline_reminder_rule_default_options();
if (!isset($defaults['sp_reminder_crm_inbox_enabled']) || $defaults['sp_reminder_crm_inbox_enabled'] !== '0') {
    fwrite(STDERR, "FAIL: Reminder Inbox feature flag must default to 0\n");
    exit(1);
}

// 4. Kiểm tra Asset CSS và JS
$cssFile = $moduleRoot . '/assets/css/reminder_bell.css';
$jsFile = $moduleRoot . '/assets/js/reminder_bell.js';
if (!is_file($cssFile)) {
    fwrite(STDERR, "FAIL: missing reminder_bell.css\n");
    exit(1);
}
if (!is_file($jsFile)) {
    fwrite(STDERR, "FAIL: missing reminder_bell.js\n");
    exit(1);
}

$jsContent = file_get_contents($jsFile);
assert_contains($jsContent, 'has-sp-reminder-pending', 'missing pulse-dot state');
assert_contains($jsContent, 'MutationObserver', 'missing MutationObserver');
assert_contains($jsContent, 'aria-live="polite"', 'Inbox state must be announced to assistive technology');
assert_contains($jsContent, 'restoreLegacyItems', 'feed failure must restore Core fallback');
assert_contains($jsContent, 'unmountInbox', 'feed failure or an empty feed must remove the module panel from Core Bell');
assert_contains($jsContent, 'isActionable && item.quick_response_url', 'only Actionable reminders may render Quick Response');
assert_contains($jsContent, "severity === 'danger'", 'legacy danger severity must normalize to critical');
assert_contains($jsContent, "['critical', 'warning']", 'Bell must only expose severity values emitted by current Reminder rules');
assert_contains($jsContent, 'class="sr-only sp-reminder-severity-label"', 'severity stripe must have a screen-reader label');
assert_contains($jsContent, '<small class="text-muted sp-reminder-item-time">', 'Reminder timestamp must reuse Core notification typography');
assert_not_contains($jsContent, '<i class="fa fa-clock-o"', 'Reminder timestamp must not add an icon absent from Core notifications');
assert_not_contains($jsContent, "inboxTitle: 'Việc cần xử lý'", 'visible Vietnamese fallback must not be hardcoded in JS');
assert_not_contains($jsContent, 'sp-reminder-inbox-header', 'Reminder items must not add a second visual header inside Core Bell');

$cssContent = file_get_contents($cssFile);
assert_contains($cssContent, 'prefers-reduced-motion: reduce', 'Reminder Bell must respect reduced-motion preference');
assert_contains($cssContent, '--sp-reminder-dot-top:', 'Pulse Dot must sit immediately below the bell glyph');
assert_contains($cssContent, '--sp-reminder-dot-left: 50%;', 'Pulse Dot must use the bell anchor horizontal center');
assert_contains($cssContent, 'top: var(--sp-reminder-dot-top);', 'Pulse Dot must use its module-owned position token');
assert_contains($cssContent, 'left: var(--sp-reminder-dot-left);', 'Pulse Dot must use its module-owned center token');
assert_contains($cssContent, 'transform: translateX(-50%);', 'Pulse Dot must be centered below the bell glyph');
assert_not_contains($cssContent, '--sp-reminder-dot-right', 'Pulse Dot must not be anchored to the bell right edge');
assert_not_contains($cssContent, 'transform: scale(', 'Pulse animation must not override horizontal centering');
assert_contains($cssContent, 'box-shadow: 0 0 0 3px var(--sp-bell-transparent);', 'Pulse halo must remain restrained around the centered dot');
assert_not_contains($cssContent, '.icon-notifications', 'Reminder CSS must not reposition, hide or restyle the Core numeric badge');
assert_contains(
    $cssContent,
    '.sp-reminder-inbox-scroll>.sp-reminder-item:last-child',
    'Last Reminder item must override the Core notification last-child formatting'
);
assert_contains($cssContent, 'text-align: left;', 'Reminder items must remain left-aligned, including the final item');
foreach (['--sp-bell-core-text: #333333;', '--sp-bell-core-muted: #777777;', '--sp-bell-core-border: #f0f0f0;', '--sp-bell-core-hover: #fbfbfb;'] as $coreToken) {
    assert_contains($cssContent, $coreToken, 'Reminder Bell must reuse Core notification token ' . $coreToken);
}
assert_contains($cssContent, 'font-weight: 400;', 'Reminder title must use the same regular weight as Core notifications');
assert_contains($cssContent, "gap: 8px;\n    font-size: 13px;", 'Reminder footer context must preserve the Core 13px typography scale');
assert_contains($cssContent, 'line-height: 1.42857;', 'Reminder timestamp must preserve the Core text line height');
foreach (['sp-severity-critical', 'sp-severity-warning'] as $severityClass) {
    assert_contains($cssContent, $severityClass, 'Reminder Bell must render severity stripe for ' . $severityClass);
}
assert_not_contains($cssContent, 'sp-severity-info', 'Bell must not invent an info severity absent from current Reminder rules');
assert_not_contains($cssContent, '--sp-reminder-severity-color: var(--sp-bell-success);', 'Bell must not invent a green severity mapping');
assert_contains($cssContent, 'border-left: 3px solid var(--sp-reminder-severity-color);', 'severity stripe must use the semantic reminder color token');

// 5. Kiểm tra Language Files
$viLang = file_get_contents($moduleRoot . '/language/vietnamese/sales_pipeline_lang.php');
$enLang = file_get_contents($moduleRoot . '/language/english/sales_pipeline_lang.php');
if (strpos($viLang, 'sales_pipeline_reminder_inbox_ack_tooltip') === false || strpos($enLang, 'sales_pipeline_reminder_inbox_ack_tooltip') === false) {
    fwrite(STDERR, "FAIL: missing translation keys for reminder bell inbox\n");
    exit(1);
}

// 6. Kiểm tra Reminder_engine CRM dispatch cutover
$engineContent = file_get_contents($moduleRoot . '/libraries/Reminder_engine.php');
assert_contains($engineContent, "option('sp_reminder_crm_inbox_enabled', '0')", 'dispatcher fallback must be canary-safe');
assert_contains($engineContent, "has_permission('sales_pipeline', (string) \$recipientStaffId, 'view_own')", 'dispatcher must not publish Inbox delivery to a Staff without module access');

// 7. API/feed safety contract
$controllerContent = file_get_contents($moduleRoot . '/controllers/Sales_pipeline.php');
assert_contains($controllerContent, 'reminder_bell_is_enabled()', 'Bell endpoints must be disabled when the rollout flag is off');
assert_contains($controllerContent, 'is_ajax_request()', 'acknowledge endpoint must require AJAX');
assert_contains($controllerContent, 'can_access_reminder_bell()', 'Bell endpoints must enforce Sales Pipeline permission');

$modelContent = file_get_contents($moduleRoot . '/models/Sales_pipeline_model.php');
assert_contains($modelContent, "!\$this->db->table_exists(\$deliveriesTable)", 'feed must fail closed when delivery audit is unavailable');
assert_contains($modelContent, 'recipient_staff_id', 'feed must scope CRM delivery to the current recipient');
assert_contains($modelContent, 'user_can_view_estimate', 'Estimate URL must be permission-aware');
assert_not_contains($modelContent, "r.acknowledged_at = \\'0000-00-00 00:00:00\\'", 'feed SQL must be valid under MySQL strict DATETIME mode');
assert_not_contains($modelContent, "order_by('r.response_required'", 'Actionable backlog must not starve recent Informational reminders with acknowledge controls');
assert_contains($modelContent, "WHEN r.severity = 'critical' THEN 1", 'Critical reminders must sort before warning reminders');
assert_not_contains($modelContent, "WHEN r.severity = 'danger' THEN 1", 'Bell ordering must use the persisted critical severity value');

echo "PASS: Reminder Bell Inbox test suite passed successfully.\n";
exit(0);
