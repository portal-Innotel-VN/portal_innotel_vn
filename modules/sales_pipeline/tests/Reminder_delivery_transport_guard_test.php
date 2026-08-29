<?php

define('BASEPATH', __DIR__);

if (!function_exists('db_prefix')) {
    function db_prefix()
    {
        return 'tbl';
    }
}

class ReminderTransportFakeResult
{
    private $row;

    public function __construct(array $row = [])
    {
        $this->row = $row;
    }

    public function row_array()
    {
        return $this->row;
    }

    public function result_array()
    {
        return $this->row ? [$this->row] : [];
    }
}

class ReminderTransportFakeDb
{
    public $pconnect = false;
    public $queries = [];
    public $usage = ['message_attempts' => 0, 'recipient_attempts' => 0, 'oldest_bucket' => null];
    public $transactions = [];

    public function query($sql, array $params = [])
    {
        $this->queries[] = ['sql' => $sql, 'params' => $params];
        if (strpos($sql, 'GET_LOCK') !== false || strpos($sql, 'RELEASE_LOCK') !== false) {
            return new ReminderTransportFakeResult(['lock_result' => 1]);
        }
        if (strpos($sql, 'SELECT `message_attempts`,`recipient_attempts`,`bucket_minute`') === 0) {
            $row = $this->usage;
            $row['bucket_minute'] = $row['oldest_bucket'];
            unset($row['oldest_bucket']);
            return new ReminderTransportFakeResult($row);
        }
        return new ReminderTransportFakeResult();
    }

    public function trans_begin()
    {
        $this->transactions[] = 'begin';
    }

    public function trans_commit()
    {
        $this->transactions[] = 'commit';
    }

    public function trans_rollback()
    {
        $this->transactions[] = 'rollback';
    }

    public function trans_status()
    {
        return true;
    }
}

$transportCI = new stdClass();
$transportCI->db = new ReminderTransportFakeDb();

if (!function_exists('get_instance')) {
    function &get_instance()
    {
        global $transportCI;
        return $transportCI;
    }
}

$moduleRoot = dirname(__DIR__);
require_once $moduleRoot . '/libraries/Reminder_delivery_throttle.php';
require_once $moduleRoot . '/libraries/Reminder_delivery_lock.php';
require_once $moduleRoot . '/libraries/Reminder_delivery_rate_limiter.php';

$clock = 100.5;
$slept = [];
$throttle = new Reminder_delivery_throttle(function () use (&$clock) {
    return $clock;
}, function ($microseconds) use (&$clock, &$slept) {
    $slept[] = $microseconds;
    $clock += $microseconds / 1000000;
});
if ($throttle->waitUntilAllowed(null, 2000) !== 100.5 || $slept) {
    fwrite(STDERR, "FAIL: first SMTP attempt must not sleep\n");
    exit(1);
}
$startedAt = $throttle->waitUntilAllowed(100.0, 2000);
if ($slept !== [1500000] || abs($startedAt - 102.0) > 0.0001) {
    fwrite(STDERR, "FAIL: throttle did not sleep only the remaining interval\n");
    exit(1);
}
$clock = 200.0;
$slept = [];
$throttle->waitUntilAllowed(300.0, 2000);
if ($slept !== [2000000]) {
    fwrite(STDERR, "FAIL: a future timestamp must not create an unbounded sleep\n");
    exit(1);
}

$lock = new Reminder_delivery_lock();
if (!$lock->acquire() || !$lock->release()) {
    fwrite(STDERR, "FAIL: advisory lock contract failed\n");
    exit(1);
}
$lockSql = implode("\n", array_column($transportCI->db->queries, 'sql'));
if (strpos($lockSql, 'GET_LOCK(?, 0)') === false || strpos($lockSql, 'RELEASE_LOCK(?)') === false) {
    fwrite(STDERR, "FAIL: lock must be non-blocking and explicitly released\n");
    exit(1);
}
$transportCI->db->pconnect = true;
if ($lock->acquire()) {
    fwrite(STDERR, "FAIL: advisory lock must fail closed with persistent DB connections\n");
    exit(1);
}
$transportCI->db->pconnect = false;

$limits = [
    'hourly_messages' => 10,
    'hourly_recipients' => 25,
    'daily_messages' => 50,
    'daily_recipients' => 125,
    'max_recipients_per_message' => 10,
];
$limiter = new Reminder_delivery_rate_limiter();
$now = new DateTimeImmutable('2026-08-27 11:23:45');
$reservation = $limiter->reserve(3, $now, $limits);
if (empty($reservation['allowed']) || $transportCI->db->transactions !== ['begin', 'commit']) {
    fwrite(STDERR, "FAIL: available quota was not pre-allocated\n");
    exit(1);
}
$allSql = implode("\n", array_column($transportCI->db->queries, 'sql'));
if (strpos($allSql, 'ON DUPLICATE KEY UPDATE') === false || strpos($allSql, 'FOR UPDATE') === false) {
    fwrite(STDERR, "FAIL: quota reservation is not atomic\n");
    exit(1);
}

$transportCI->db->usage = [
    'message_attempts' => 10,
    'recipient_attempts' => 20,
    'oldest_bucket' => '2026-08-27 10:24:00',
];
$transportCI->db->transactions = [];
$denied = $limiter->reserve(1, $now, $limits);
if (!empty($denied['allowed']) || $denied['code'] !== 'hourly_message_quota_exhausted'
    || $transportCI->db->transactions !== ['begin', 'rollback']) {
    fwrite(STDERR, "FAIL: hourly rolling quota was not enforced\n");
    exit(1);
}

$tooMany = $limiter->reserve(11, $now, $limits);
if (!empty($tooMany['allowed']) || !empty($tooMany['retryable'])
    || $tooMany['code'] !== 'recipient_count_exceeds_limit') {
    fwrite(STDERR, "FAIL: per-message recipient cap was not enforced\n");
    exit(1);
}

$engine = file_get_contents($moduleRoot . '/libraries/Reminder_engine.php');
foreach (['reminder_delivery_lock->acquire()', 'reminder_delivery_lock->release()',
          'reminder_delivery_rate_limiter->reserve(', 'waitUntilAllowed(',
          'sp_reminder_delivery_email_last_attempt_started_at'] as $needle) {
    if (strpos($engine, $needle) === false) {
        fwrite(STDERR, "FAIL: engine transport guard missing {$needle}\n");
        exit(1);
    }
}

fwrite(STDOUT, "PASS: Reminder delivery throttle, rolling quota and advisory lock\n");
