<?php

/**
 * Disposable Database Migration 113 Full Test
 *
 * Runs on an isolated, temporary database created specifically for this test,
 * executing Migration 113 twice to verify idempotency, DDL creation, backfill,
 * and data integrity without touching production or developer databases.
 */

define('BASEPATH', 'dummy');
require_once __DIR__ . '/../../../application/config/app-config.php';

function run_disposable_migration_test()
{
    $testDbName = 'portal_18_disp_' . time() . '_' . rand(100, 999);
    echo "1. Creating disposable database: {$testDbName}...\n";

    $rootMysqli = new mysqli('127.0.0.1', 'root', '', '', 3306);
    if ($rootMysqli->connect_error) {
        throw new RuntimeException("Cannot connect as root to MySQL: " . $rootMysqli->connect_error);
    }

    // Create disposable database and grant permissions
    $rootMysqli->query("CREATE DATABASE `{$testDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $rootMysqli->query("GRANT ALL PRIVILEGES ON `{$testDbName}`.* TO '" . APP_DB_USERNAME . "'@'localhost'");
    $rootMysqli->query("FLUSH PRIVILEGES");
    $rootMysqli->close();

    try {
        // Connect with application user
        $appMysqli = new mysqli('127.0.0.1', APP_DB_USERNAME, APP_DB_PASSWORD, $testDbName, 3306);
        if ($appMysqli->connect_error) {
            throw new RuntimeException("Cannot connect to disposable DB with app credentials: " . $appMysqli->connect_error);
        }

        echo "2. Initializing pre-migration tables & fixtures...\n";
        // Core Perfex tables
        $appMysqli->query("
            CREATE TABLE `tbloptions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(191) UNIQUE NOT NULL,
                `value` LONGTEXT NOT NULL,
                `autoload` INT DEFAULT 1
            ) ENGINE=InnoDB
        ");

        $appMysqli->query("
            CREATE TABLE `tblestimates` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `clientid` INT NOT NULL,
                `total` DECIMAL(15,2) NOT NULL,
                `currency` INT NOT NULL,
                `status` INT NOT NULL,
                `date` DATE NOT NULL,
                `datecreated` DATETIME NOT NULL
            ) ENGINE=InnoDB
        ");

        $appMysqli->query("
            CREATE TABLE `tblsales_activity` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `rel_type` VARCHAR(50) NOT NULL,
                `rel_id` INT NOT NULL,
                `description` VARCHAR(191) NOT NULL,
                `additional_data` TEXT NULL,
                `date` DATETIME NOT NULL
            ) ENGINE=InnoDB
        ");

        $appMysqli->query("
            CREATE TABLE `tblsales_pipeline_estimate_groups` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `client_id` INT NOT NULL,
                `owner_staff_id` INT NOT NULL,
                `current_estimate_id` INT NOT NULL,
                `outcome` VARCHAR(50) DEFAULT 'pending',
                `datecreated` DATETIME NOT NULL,
                `last_reconciled_at` DATETIME NULL
            ) ENGINE=InnoDB
        ");

        $appMysqli->query("
            CREATE TABLE `tblsales_pipeline_estimate_versions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `estimate_group_id` INT NOT NULL,
                `estimate_id` INT NOT NULL,
                `revision_no` INT NOT NULL,
                `source_currency_id` INT NOT NULL,
                `base_currency_id` INT NOT NULL,
                `source_total` DECIMAL(15,2) NOT NULL,
                `exchange_rate_to_base` DECIMAL(15,6) NULL,
                `base_total` DECIMAL(15,2) NULL,
                `date_linked` DATETIME NOT NULL
            ) ENGINE=InnoDB
        ");

        $appMysqli->query("
            CREATE TABLE `tblsales_pipeline_deals` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `deal_name` VARCHAR(191) NOT NULL,
                `deal_value` DECIMAL(15,2) DEFAULT 0.00,
                `status` INT NOT NULL,
                `is_manual_lock` TINYINT(1) DEFAULT 0,
                `dateadded` DATETIME NOT NULL
            ) ENGINE=InnoDB
        ");

        $appMysqli->query("
            CREATE TABLE `tblsales_pipeline_reminders_log` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `rule_key` VARCHAR(100) NOT NULL,
                `staff_id` INT NOT NULL,
                `sent_at` DATETIME NULL,
                `response_due_at` DATETIME NULL,
                `staff_response` TEXT NULL,
                `responded_at` DATETIME NULL
            ) ENGINE=InnoDB
        ");

        // Insert legacy fixtures
        // Group 1: Has sales_activity sent_to_client event
        $appMysqli->query("INSERT INTO tblsales_pipeline_estimate_groups (id, client_id, owner_staff_id, current_estimate_id, outcome, datecreated) VALUES (1, 10, 5, 101, 'pending', '2026-08-01 10:00:00')");
        $appMysqli->query("INSERT INTO tblestimates (id, clientid, total, currency, status, date, datecreated) VALUES (101, 10, 5000000, 1, 2, '2026-08-02', '2026-08-01 10:00:00')");
        $appMysqli->query("INSERT INTO tblsales_pipeline_estimate_versions (estimate_group_id, estimate_id, revision_no, source_currency_id, base_currency_id, source_total, exchange_rate_to_base, base_total, date_linked) VALUES (1, 101, 1, 1, 1, 5000000, 1.0, 5000000, '2026-08-01 10:00:00')");
        $appMysqli->query("INSERT INTO tblsales_activity (rel_type, rel_id, description, date) VALUES ('estimate', 101, 'invoice_estimate_activity_sent_to_client', '2026-08-02 09:15:00')");

        // Group 2: Has no activity, but status = 4 (accepted). Fallback to estimate date
        $appMysqli->query("INSERT INTO tblsales_pipeline_estimate_groups (id, client_id, owner_staff_id, current_estimate_id, outcome, datecreated) VALUES (2, 20, 6, 201, 'accepted', '2026-08-10 14:00:00')");
        $appMysqli->query("INSERT INTO tblestimates (id, clientid, total, currency, status, date, datecreated) VALUES (201, 20, 20000000, 1, 4, '2026-08-11', '2026-08-10 14:00:00')");
        $appMysqli->query("INSERT INTO tblsales_pipeline_estimate_versions (estimate_group_id, estimate_id, revision_no, source_currency_id, base_currency_id, source_total, exchange_rate_to_base, base_total, date_linked) VALUES (2, 201, 1, 1, 1, 20000000, 1.0, 20000000, '2026-08-10 14:00:00')");

        // Reminder 1: Unverified response (responded but sent_at was null)
        $appMysqli->query("INSERT INTO tblsales_pipeline_reminders_log (id, rule_key, staff_id, sent_at, response_due_at, staff_response, responded_at) VALUES (1, 'deal_stale', 5, NULL, NULL, 'Phản hồi test', '2026-08-15 10:00:00')");

        // Mock CodeIgniter environment for architecture helper
        require_once __DIR__ . '/../includes/architecture_113_schema.php';

        // Build adapter CI object with real mysqli wrapper
        $ciMock = new stdClass();
        $ciMock->db = new class($appMysqli) {
            private $m;
            public function __construct($mysqli) { $this->m = $mysqli; }
            public function query($sql) {
                $res = $this->m->query($sql);
                if ($res === false) {
                    throw new RuntimeException("SQL error: " . $this->m->error . "\nSQL: " . $sql);
                }
                return new class($res) {
                    private $r;
                    public function __construct($r) { $this->r = $r; }
                    public function row_array() { return is_object($this->r) ? $this->r->fetch_assoc() : null; }
                    public function result_array() {
                        $rows = [];
                        if (is_object($this->r)) {
                            while ($row = $this->r->fetch_assoc()) $rows[] = $row;
                        }
                        return $rows;
                    }
                };
            }
            public function table_exists($t) {
                $res = $this->m->query("SHOW TABLES LIKE '{$t}'");
                return $res && $res->num_rows > 0;
            }
            public function field_exists($f, $t) {
                $res = $this->m->query("SHOW COLUMNS FROM `{$t}` LIKE '{$f}'");
                return $res && $res->num_rows > 0;
            }
        };

        $ciMock->db->char_set = 'utf8mb4';

        echo "3. Running Migration 113 Run 1...\n";
        sales_pipeline_ensure_architecture_113_schema($ciMock);
        sales_pipeline_run_architecture_113_backfill($ciMock);
        sales_pipeline_upgrade_target_options_113();

        // Verify Run 1 assertions
        echo "4. Verifying DDL & Backfill after Run 1...\n";
        $tables = [
            'tblsales_pipeline_score_snapshots',
            'tblsales_pipeline_exchange_rates',
            'tblsales_pipeline_sanitization_batches',
            'tblsales_pipeline_sanitization_items',
        ];
        foreach ($tables as $t) {
            if (!$ciMock->db->table_exists($t)) {
                throw new RuntimeException("Table {$t} was not created!");
            }
        }

        // Verify backfill values
        $resGroup1 = $appMysqli->query("SELECT first_sent_at, first_sent_source FROM tblsales_pipeline_estimate_groups WHERE id = 1")->fetch_assoc();
        if ($resGroup1['first_sent_at'] !== '2026-08-02 09:15:00' || $resGroup1['first_sent_source'] !== 'activity') {
            throw new RuntimeException("Group 1 backfill failed: " . json_encode($resGroup1));
        }

        $resGroup2 = $appMysqli->query("SELECT first_sent_at, first_sent_source FROM tblsales_pipeline_estimate_groups WHERE id = 2")->fetch_assoc();
        if (strpos($resGroup2['first_sent_at'], '2026-08-11') !== 0 || $resGroup2['first_sent_source'] !== 'inferred_estimate_date') {
            throw new RuntimeException("Group 2 backfill failed: " . json_encode($resGroup2));
        }

        $resRem1 = $appMysqli->query("SELECT data_quality_status FROM tblsales_pipeline_reminders_log WHERE id = 1")->fetch_assoc();
        if ($resRem1['data_quality_status'] !== 'legacy_unverified') {
            throw new RuntimeException("Reminder 1 backfill failed: " . json_encode($resRem1));
        }

        echo "5. Running Migration 113 Run 2 (Idempotency check)...\n";
        sales_pipeline_ensure_architecture_113_schema($ciMock);
        sales_pipeline_run_architecture_113_backfill($ciMock);
        sales_pipeline_upgrade_target_options_113();

        // Verify Run 2: No errors, counts identical
        $resGroup1Run2 = $appMysqli->query("SELECT first_sent_at, first_sent_source FROM tblsales_pipeline_estimate_groups WHERE id = 1")->fetch_assoc();
        if ($resGroup1Run2['first_sent_at'] !== '2026-08-02 09:15:00') {
            throw new RuntimeException("Run 2 altered Group 1 data!");
        }

        $appMysqli->close();
        echo "PASS: Disposable database migration 113 test completed successfully.\n";
    } finally {
        // Clean up disposable database always!
        echo "6. Dropping disposable database {$testDbName}...\n";
        $cleanupMysqli = new mysqli('127.0.0.1', 'root', '', '', 3306);
        $cleanupMysqli->query("DROP DATABASE IF EXISTS `{$testDbName}`");
        $cleanupMysqli->close();
    }
}

if (!function_exists('db_prefix')) {
    function db_prefix() { return 'tbl'; }
}
if (!function_exists('get_option')) {
    function get_option($n) { return false; }
}
if (!function_exists('add_option')) {
    function add_option($n, $v) { return true; }
}
if (!function_exists('update_option')) {
    function update_option($n, $v) { return true; }
}

run_disposable_migration_test();
