<?php

define('BASEPATH', __DIR__);

$moduleRoot = dirname(__DIR__);
require_once $moduleRoot . '/includes/reminder_rule_defaults.php';

if (!function_exists('sales_pipeline_reminder_delivery_default_options')) {
    fwrite(STDERR, "FAIL: missing reminder delivery default options contract\n");
    exit(1);
}

$expectedOptions = [
    'sp_reminder_delivery_email_batch_size'                 => '1',
    'sp_reminder_delivery_email_min_interval_ms'            => '30000',
    'sp_reminder_delivery_email_last_attempt_started_at'    => '',
    'sp_reminder_delivery_email_hourly_message_limit'       => '10',
    'sp_reminder_delivery_email_hourly_recipient_limit'     => '25',
    'sp_reminder_delivery_email_daily_message_limit'        => '50',
    'sp_reminder_delivery_email_daily_recipient_limit'      => '125',
    'sp_reminder_delivery_email_max_recipients_per_message' => '10',
    'sp_reminder_delivery_email_max_rendered_bytes'         => '1048576',
    'sp_reminder_delivery_max_attempts'                     => '5',
    'sp_reminder_delivery_rate_limit_cooldown_seconds'      => '3600',
    'sp_reminder_delivery_default_max_valid_age_hours'      => '24',
    'sp_reminder_delivery_retention_days'                   => '60',
    'sp_reminder_delivery_email_circuit_state'              => 'closed',
    'sp_reminder_delivery_email_circuit_opened_at'          => '',
    'sp_reminder_delivery_email_circuit_reason'             => '',
];

$deliveryDefaults = sales_pipeline_reminder_delivery_default_options();
foreach ($expectedOptions as $key => $expected) {
    if (!array_key_exists($key, $deliveryDefaults) || $deliveryDefaults[$key] !== $expected) {
        fwrite(STDERR, "FAIL: invalid Canary default {$key}\n");
        exit(1);
    }
}

$allDefaults = sales_pipeline_reminder_rule_default_options();
foreach (array_keys($expectedOptions) as $key) {
    if (!array_key_exists($key, $allDefaults)) {
        fwrite(STDERR, "FAIL: delivery option is not registered in reminder defaults: {$key}\n");
        exit(1);
    }
}

if (!function_exists('sales_pipeline_add_reminder_options')) {
    fwrite(STDERR, "FAIL: missing sales_pipeline_add_reminder_options()\n");
    exit(1);
}

$expectedStatuses = ['pending', 'processing', 'failed', 'sent', 'expired', 'cancelled'];
if (!function_exists('sales_pipeline_reminder_delivery_statuses')
    || sales_pipeline_reminder_delivery_statuses() !== $expectedStatuses) {
    fwrite(STDERR, "FAIL: invalid reminder delivery status contract\n");
    exit(1);
}

$schema = file_get_contents($moduleRoot . '/includes/reminder_repository_schema.php');
foreach ([
    'last_error_code',
    'last_error_class',
    'last_attempt_at',
    'expires_at',
    'expired_at',
    'idx_delivery_channel_worker',
    'idx_delivery_retention',
    'sales_pipeline_reminder_delivery_rate_buckets',
    'uq_scope_minute',
    'DATE_ADD(',
] as $needle) {
    if (strpos($schema, $needle) === false) {
        fwrite(STDERR, "FAIL: missing resilience schema contract {$needle}\n");
        exit(1);
    }
}

$migration = $moduleRoot . '/migrations/110_version_110.php';
if (!is_file($migration)) {
    fwrite(STDERR, "FAIL: missing additive migration 110\n");
    exit(1);
}
$migrationSource = file_get_contents($migration);
foreach (['Migration_Version_110', 'sales_pipeline_ensure_reminder_repository_schema', 'function down'] as $needle) {
    if (strpos($migrationSource, $needle) === false) {
        fwrite(STDERR, "FAIL: migration 110 is missing {$needle}\n");
        exit(1);
    }
}

$module = file_get_contents($moduleRoot . '/sales_pipeline.php');
preg_match('/Version:\s*(\d+\.\d+\.\d+)/', $module, $vMatches);
$modVer = $vMatches[1] ?? '0.0.0';
if (version_compare($modVer, '1.0.13', '<')) {
    fwrite(STDERR, "FAIL: reminder repository bootstrap requires at least Version: 1.0.13 (got {$modVer})\n");
    exit(1);
}
if (strpos(file_get_contents($moduleRoot . '/install.php'), 'sales_pipeline_ensure_reminder_repository_schema') === false) {
    fwrite(STDERR, "FAIL: module activation does not install the reminder repository schema\n");
    exit(1);
}

foreach ([$schema, $migrationSource, $module] as $source) {
    foreach (['smtp_password', 'smtp_user', 'raw_auth_exchange'] as $forbidden) {
        if (stripos($source, $forbidden) !== false) {
            fwrite(STDERR, "FAIL: sensitive field leaked into schema/bootstrap: {$forbidden}\n");
            exit(1);
        }
    }
}

if (!function_exists('db_prefix')) {
    function db_prefix()
    {
        return 'tbl';
    }
}

class ReminderDeliverySchemaFakeResult
{
    private $rows;

    public function __construct($rows = [])
    {
        $this->rows = $rows;
    }

    public function row_array()
    {
        return isset($this->rows[0]) ? $this->rows[0] : [];
    }

    public function result_array()
    {
        return $this->rows;
    }
}

class ReminderDeliverySchemaFakeDb
{
    public $char_set = 'utf8mb4';
    public $queries = [];
    private $tables;
    private $fields;
    private $indexes;

    public function __construct()
    {
        $reminders = 'tblsales_pipeline_reminders_log';
        $deliveries = 'tblsales_pipeline_reminder_deliveries';
        $this->tables = [$reminders => true, $deliveries => true];
        $this->fields = [
            $reminders => array_fill_keys([
                'pipeline_id', 'reminder_type', 'rule_code', 'entity_type', 'entity_id',
                'period_key', 'checkpoint', 'severity', 'response_required', 'title',
                'message', 'snapshot_json', 'dedupe_key', 'created_at', 'sent_at',
            ], true),
            $deliveries => array_fill_keys([
                'id', 'reminder_id', 'channel', 'recipient_type', 'recipient_staff_id',
                'recipient_key', 'cc_recipients', 'status', 'attempt_count',
                'provider_message_id', 'last_error', 'next_retry_at', 'sent_at',
                'delivered_at', 'read_at', 'created_at', 'updated_at',
            ], true),
        ];
        $this->indexes = [
            $reminders => array_fill_keys([
                'uq_sales_pipeline_reminder_dedupe',
                'idx_reminder_staff_period',
                'idx_reminder_rule_period',
            ], true),
            $deliveries => array_fill_keys([
                'PRIMARY', 'uq_reminder_channel_recipient', 'idx_delivery_worker',
                'idx_delivery_reminder', 'idx_delivery_provider',
            ], true),
        ];
    }

    public function table_exists($table)
    {
        return !empty($this->tables[$table]);
    }

    public function field_exists($field, $table)
    {
        return !empty($this->fields[$table][$field]);
    }

    public function escape($value)
    {
        return "'" . addslashes($value) . "'";
    }

    public function query($sql)
    {
        $this->queries[] = $sql;

        if (preg_match('/SHOW COLUMNS FROM `([^`]+)` LIKE/', $sql)) {
            return new ReminderDeliverySchemaFakeResult([['Null' => 'YES']]);
        }
        if (preg_match('/SHOW INDEX FROM `([^`]+)`/', $sql, $matches)) {
            $rows = [];
            foreach (array_keys(isset($this->indexes[$matches[1]]) ? $this->indexes[$matches[1]] : []) as $name) {
                $rows[] = ['Key_name' => $name];
            }
            return new ReminderDeliverySchemaFakeResult($rows);
        }
        if (preg_match('/ALTER TABLE `([^`]+)` ADD `([^`]+)`/', $sql, $matches)) {
            $this->fields[$matches[1]][$matches[2]] = true;
            return new ReminderDeliverySchemaFakeResult();
        }
        if (preg_match('/ALTER TABLE `([^`]+)` ADD KEY `([^`]+)`/', $sql, $matches)) {
            $this->indexes[$matches[1]][$matches[2]] = true;
            return new ReminderDeliverySchemaFakeResult();
        }
        if (preg_match('/CREATE TABLE `([^`]+)`/', $sql, $matches)) {
            $table = $matches[1];
            $this->tables[$table] = true;
            if (substr($table, -strlen('sales_pipeline_reminder_delivery_rate_buckets'))
                === 'sales_pipeline_reminder_delivery_rate_buckets') {
                $this->fields[$table] = array_fill_keys([
                    'scope_key', 'bucket_minute', 'message_attempts',
                    'recipient_attempts', 'updated_at',
                ], true);
                $this->indexes[$table] = array_fill_keys(['uq_scope_minute', 'idx_rate_bucket_minute'], true);
            }
            return new ReminderDeliverySchemaFakeResult();
        }

        return new ReminderDeliverySchemaFakeResult();
    }
}

require_once $moduleRoot . '/includes/reminder_repository_schema.php';
$fakeCI = new stdClass();
$fakeCI->db = new ReminderDeliverySchemaFakeDb();
sales_pipeline_ensure_reminder_repository_schema($fakeCI);
$firstPassQueryCount = count($fakeCI->db->queries);
sales_pipeline_ensure_reminder_repository_schema($fakeCI);
$secondPassQueries = array_slice($fakeCI->db->queries, $firstPassQueryCount);

foreach (['last_error_code', 'last_error_class', 'last_attempt_at', 'expires_at', 'expired_at'] as $column) {
    if (!$fakeCI->db->field_exists($column, 'tblsales_pipeline_reminder_deliveries')) {
        fwrite(STDERR, "FAIL: schema bootstrap did not add {$column}\n");
        exit(1);
    }
}
if (!$fakeCI->db->table_exists('tblsales_pipeline_reminder_delivery_rate_buckets')) {
    fwrite(STDERR, "FAIL: schema bootstrap did not create rate bucket table\n");
    exit(1);
}
foreach ($secondPassQueries as $sql) {
    if (strpos($sql, ' ADD `') !== false
        || strpos($sql, ' ADD KEY ') !== false
        || strpos($sql, 'CREATE TABLE') !== false) {
        fwrite(STDERR, "FAIL: schema bootstrap repeated an additive operation\n");
        exit(1);
    }
}

fwrite(STDOUT, "PASS: Reminder delivery foundation schema, options, and status contract\n");
