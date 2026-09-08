<?php

define('BASEPATH', __DIR__);

if (!function_exists('db_prefix')) {
    function db_prefix()
    {
        return 'tbl';
    }
}

class ReminderMaintenanceFakeResult
{
    private $rows;

    public function __construct(array $rows = [])
    {
        $this->rows = $rows;
    }

    public function row_array()
    {
        return $this->rows ? $this->rows[0] : [];
    }

    public function result_array()
    {
        return $this->rows;
    }
}

class ReminderMaintenanceFakeDb
{
    public $pconnect = false;
    public $queries = [];
    public $affected = 2;

    public function query($sql, array $params = [])
    {
        $this->queries[] = ['sql' => $sql, 'params' => $params];
        if (strpos($sql, 'GET_LOCK') !== false || strpos($sql, 'RELEASE_LOCK') !== false) {
            return new ReminderMaintenanceFakeResult([['lock_result' => 1]]);
        }
        if (strpos($sql, 'SELECT d.`id`') === 0) {
            return new ReminderMaintenanceFakeResult([['id' => 10], ['id' => 11]]);
        }
        return new ReminderMaintenanceFakeResult();
    }

    public function affected_rows()
    {
        return $this->affected;
    }
}

$maintenanceOptions = [
    'sp_reminder_delivery_retention_days' => '60',
    'sp_reminder_delivery_maintenance_last_run_at' => '',
];
if (!function_exists('get_option')) {
    function get_option($key)
    {
        global $maintenanceOptions;
        return $maintenanceOptions[$key] ?? false;
    }
}
if (!function_exists('update_option')) {
    function update_option($key, $value)
    {
        global $maintenanceOptions;
        $maintenanceOptions[$key] = $value;
    }
}

$maintenanceCI = new stdClass();
$maintenanceCI->db = new ReminderMaintenanceFakeDb();
if (!function_exists('get_instance')) {
    function &get_instance()
    {
        global $maintenanceCI;
        return $maintenanceCI;
    }
}

$moduleRoot = dirname(__DIR__);
require_once $moduleRoot . '/libraries/Reminder_delivery_maintenance.php';

$maintenance = new Reminder_delivery_maintenance();
$now = new DateTimeImmutable('2026-08-27 13:00:00');
if (!$maintenance->isDue($now, '')
    || $maintenance->isDue($now, '2026-08-22 13:00:00')
    || !$maintenance->isDue($now, '2026-08-20 13:00:00')) {
    fwrite(STDERR, "FAIL: weekly maintenance schedule contract failed\n");
    exit(1);
}
$result = $maintenance->run($now);
if ($result['deliveries_purged'] !== 2 || $result['rate_buckets_purged'] !== 2) {
    fwrite(STDERR, "FAIL: maintenance did not report bounded purge results\n");
    exit(1);
}
$sql = implode("\n", array_column($maintenanceCI->db->queries, 'sql'));
foreach (["d.`status` IN ('sent','expired','cancelled')", 'r.`staff_response` IS NULL',
          "NOT IN ('authentication_configuration','system_bcc_over_quota','unknown_outcome')",
          'LIMIT 500', 'DELETE d FROM', 'sales_pipeline_reminder_delivery_rate_buckets'] as $needle) {
    if (strpos($sql, $needle) === false) {
        fwrite(STDERR, "FAIL: retention safety contract missing {$needle}\n");
        exit(1);
    }
}
if (preg_match('/DELETE\s+FROM\s+`?tblsales_pipeline_reminders_log/i', $sql)) {
    fwrite(STDERR, "FAIL: maintenance must never delete reminder logs\n");
    exit(1);
}

$module = file_get_contents($moduleRoot . '/sales_pipeline.php');
if (strpos($module, 'reminder_delivery_maintenance->runIfDue()') === false) {
    fwrite(STDERR, "FAIL: weekly maintenance is not registered with Cron\n");
    exit(1);
}

fwrite(STDOUT, "PASS: Reminder delivery weekly retention and incident protection\n");
