<?php

define('BASEPATH', __DIR__);

if (!function_exists('db_prefix')) {
    function db_prefix()
    {
        return 'tbl';
    }
}

class ReminderDeliverySelectorFakeResult
{
    private $rows;

    public function __construct(array $rows = [])
    {
        $this->rows = $rows;
    }

    public function result_array()
    {
        return $this->rows;
    }
}

class ReminderDeliverySelectorFakeDb
{
    public $queries = [];
    public $affected = 1;
    public $dueRows = [['id' => 91, 'channel' => 'email', 'status' => 'pending']];

    public function query($sql, array $params = [])
    {
        $this->queries[] = ['sql' => $sql, 'params' => $params];
        return new ReminderDeliverySelectorFakeResult(strpos($sql, 'SELECT d.*') === 0 ? $this->dueRows : []);
    }

    public function affected_rows()
    {
        return $this->affected;
    }
}

$selectorCI = new stdClass();
$selectorCI->db = new ReminderDeliverySelectorFakeDb();

if (!function_exists('get_instance')) {
    function &get_instance()
    {
        global $selectorCI;
        return $selectorCI;
    }
}

$moduleRoot = dirname(__DIR__);
$policyFile = $moduleRoot . '/libraries/Reminder_delivery_policy.php';
$selectorFile = $moduleRoot . '/libraries/Reminder_delivery_selector.php';
if (!is_file($policyFile) || !is_file($selectorFile)) {
    fwrite(STDERR, "FAIL: missing delivery policy or selector library\n");
    exit(1);
}

require_once $policyFile;
require_once $selectorFile;

$policy = new Reminder_delivery_policy();
$createdAt = new DateTimeImmutable('2026-08-27 10:15:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
$expiresAt = $policy->expiresAt($createdAt, 24);
if ($expiresAt->format('Y-m-d H:i:s') !== '2026-08-28 10:15:00') {
    fwrite(STDERR, "FAIL: delivery expiry is not fixed at enqueue time\n");
    exit(1);
}
if ($policy->isExpired($expiresAt, $expiresAt->modify('-1 second'))) {
    fwrite(STDERR, "FAIL: delivery expired before expires_at\n");
    exit(1);
}
if (!$policy->isExpired($expiresAt, $expiresAt)) {
    fwrite(STDERR, "FAIL: delivery must expire exactly at expires_at\n");
    exit(1);
}

$selector = new Reminder_delivery_selector();
$now = new DateTimeImmutable('2026-08-27 11:00:00');
$expired = $selector->expireStaleEmails($now);
if ($expired !== 1) {
    fwrite(STDERR, "FAIL: expiration did not report affected delivery count\n");
    exit(1);
}
$expirationQuery = $selectorCI->db->queries[0];
foreach (["`channel`='email'", "`status` IN ('pending','failed')", "`status`='expired'", '`expired_at`'] as $needle) {
    if (strpos($expirationQuery['sql'], $needle) === false) {
        fwrite(STDERR, "FAIL: expiration query missing {$needle}\n");
        exit(1);
    }
}
if (strpos($expirationQuery['sql'], '`attempt_count`') !== false) {
    fwrite(STDERR, "FAIL: expiration must not increment attempts\n");
    exit(1);
}

$crmRows = $selector->due('crm', 100, 5, $now);
$emailRows = $selector->due('email', 1, 5, $now);
if (count($crmRows) !== 1 || count($emailRows) !== 1) {
    fwrite(STDERR, "FAIL: selector did not return channel rows\n");
    exit(1);
}
$crmQuery = $selectorCI->db->queries[1];
$emailQuery = $selectorCI->db->queries[2];
if ($crmQuery['params'][0] !== 'crm' || $emailQuery['params'][0] !== 'email') {
    fwrite(STDERR, "FAIL: CRM and email selectors are not isolated by channel\n");
    exit(1);
}
if (substr($crmQuery['sql'], -9) !== 'LIMIT 100' || substr($emailQuery['sql'], -7) !== 'LIMIT 1') {
    fwrite(STDERR, "FAIL: channel selectors do not enforce independent limits\n");
    exit(1);
}
foreach (["CASE WHEN d.status='pending' THEN 0 ELSE 1 END", 'd.next_retry_at ASC', 'd.id ASC'] as $needle) {
    if (strpos($emailQuery['sql'], $needle) === false) {
        fwrite(STDERR, "FAIL: due selector ordering missing {$needle}\n");
        exit(1);
    }
}

$engine = file_get_contents($moduleRoot . '/libraries/Reminder_engine.php');
foreach (['expireStaleEmails', "due('crm'", "due('email'", '`expires_at`'] as $needle) {
    if (strpos($engine, $needle) === false) {
        fwrite(STDERR, "FAIL: Reminder engine does not use selector contract {$needle}\n");
        exit(1);
    }
}

fwrite(STDOUT, "PASS: Reminder delivery channel isolation and expiration policy\n");
