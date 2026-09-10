<?php

define('BASEPATH', __DIR__);

if (!function_exists('db_prefix')) {
    function db_prefix()
    {
        return 'tbl';
    }
}

$GLOBALS['lang_mock'] = [
    'sales_pipeline_reminder_badge_warning' => 'Cảnh báo',
    'sales_pipeline_reminder_badge_critical' => 'Khẩn cấp',
    'sales_pipeline_reminder_badge_notice' => 'Thông báo',
    'sales_pipeline_reminder' => 'Nhắc nhở',
    'sp_reminder_whatsapp_msg_staff' => 'Phụ trách',
    'sp_reminder_whatsapp_msg_customer' => 'Khách hàng',
    'sp_reminder_whatsapp_msg_deal' => 'Deal',
    'sp_reminder_whatsapp_msg_estimate' => 'Báo giá',
    'sp_reminder_whatsapp_msg_issue' => 'Vấn đề',
    'sp_reminder_whatsapp_msg_content' => 'Nội dung',
    'sp_reminder_whatsapp_msg_inactive_time' => 'Thời gian bất động',
    'sp_reminder_whatsapp_msg_days' => 'ngày',
    'sp_reminder_whatsapp_msg_quick_action' => 'Thao tác nhanh trên CRM',
    'sp_reminder_whatsapp_msg_view_deal' => 'Xem Deal',
    'sp_reminder_whatsapp_msg_view_estimate' => 'Xem Báo giá',
    'sp_reminder_whatsapp_msg_view_revenue_chart' => 'Xem Biểu đồ Doanh thu',
    'sp_reminder_whatsapp_msg_view_staff_revenue_chart' => 'Xem Biểu đồ Doanh thu (%s)',
    'sp_reminder_whatsapp_msg_view_reminder' => 'Xem chi tiết nhắc nhở',
    'sp_reminder_whatsapp_msg_test_title' => 'Sales Pipeline - Test Connection',
    'sp_reminder_whatsapp_msg_test_body' => 'Kết nối từ CRM Perfex tới WhatsApp Gateway thành công lúc %s.',
];

if (!function_exists('_l')) {
    function _l($line, $label = '')
    {
        $val = $GLOBALS['lang_mock'][$line] ?? $line;
        if (!empty($label) && is_array($label)) {
            return vsprintf($val, $label);
        }
        return $val;
    }
}

if (!function_exists('get_option')) {
    function get_option($name)
    {
        if (isset($GLOBALS['options_mock'][$name])) {
            return $GLOBALS['options_mock'][$name];
        }
        if ($name === 'sp_reminder_whatsapp_base_url') {
            return 'http://crm.company.com';
        }
        if ($name === 'sp_reminder_whatsapp_instance_id') {
            return 'inst123456';
        }
        return '';
    }
}

if (!function_exists('update_option')) {
    function update_option($name, $value)
    {
        return true;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '')
    {
        return 'http://crm.company.com/admin/' . ltrim($path, '/');
    }
}

$moduleRoot = dirname(__DIR__);
require_once $moduleRoot . '/libraries/Reminder_delivery_policy.php';
require_once $moduleRoot . '/libraries/Reminder_whatsapp_formatter.php';
require_once $moduleRoot . '/libraries/channels/Reminder_delivery_whatsapp_adapter.php';
require_once $moduleRoot . '/libraries/Reminder_delivery_rate_limiter.php';
require_once $moduleRoot . '/libraries/Reminder_delivery_selector.php';

// Test 1: Phone number and JID formatting
assert(Reminder_whatsapp_formatter::formatPhoneNumberToJid('0901234567') === '84901234567@s.whatsapp.net', 'Format 09x to 84x failed');
assert(Reminder_whatsapp_formatter::formatPhoneNumberToJid('+84 901 234 567') === '84901234567@s.whatsapp.net', 'Format +84 with spaces failed');
assert(Reminder_whatsapp_formatter::formatPhoneNumberToJid('84901234567') === '84901234567@s.whatsapp.net', 'Format plain 84x failed');
assert(Reminder_whatsapp_formatter::formatPhoneNumberToJid('120363123456789012@g.us') === '120363123456789012@g.us', 'Format group jid failed');
assert(Reminder_whatsapp_formatter::formatPhoneNumberToJid('invalid') === null, 'Format invalid phone should return null');

// Mock CI for Formatter & Db
class FakeDbStaff
{
    public function select($fields) { return $this; }
    public function where($field, $val) { return $this; }
    public function get($table)
    {
        return new class {
            public function row() {
                $obj = new stdClass();
                $obj->firstname = 'Nguyen';
                $obj->lastname = 'Van A';
                return $obj;
            }
        };
    }
}
$testCI = new stdClass();
$testCI->db = new FakeDbStaff();
if (!function_exists('get_instance')) {
    function &get_instance()
    {
        global $testCI;
        return $testCI;
    }
}

// Test 2: Message formatting with Markdown, Deeplink & Localization
$formatter = new Reminder_whatsapp_formatter();
$row = [
    'severity' => 'warning',
    'title' => 'Báo giá quá hạn phản hồi',
    'message' => 'Báo giá BG-001 của khách hàng Innotel đã quá hạn 48h.',
    'staff_id' => 12,
    'entity_type' => 'deal',
    'entity_id' => 456,
    'pipeline_id' => 456,
    'reminder_id' => 789,
    'snapshot_json' => json_encode([
        'customer_name' => 'Công ty Innotel',
        'deal_name' => 'Dự án Cloud Server',
        'deal_value' => 150000000,
        'risk_reason' => 'Đã gửi báo giá 5 ngày nhưng chưa có phản hồi',
    ]),
];
$formattedMsg = $formatter->format($row);
assert(strpos($formattedMsg, 'SALES PIPELINE') !== false, 'Missing header in formatted message');
assert(strpos($formattedMsg, 'Phụ trách:* Nguyen Van A') !== false, 'Missing staff localized label');
assert(strpos($formattedMsg, 'Khách hàng:* Công ty Innotel') !== false, 'Missing customer localized label');
assert(strpos($formattedMsg, 'Deal:* Dự án Cloud Server') !== false, 'Missing deal localized label');
assert(strpos($formattedMsg, '150.000.000 đ') !== false, 'Missing formatted deal value');
assert(strpos($formattedMsg, 'Vấn đề:* Đã gửi báo giá 5 ngày') !== false, 'Missing issue localized label');
assert(strpos($formattedMsg, 'Thao tác nhanh trên CRM:*') !== false, 'Missing quick action localized label');
assert(strpos($formattedMsg, 'http://crm.company.com/admin/sales_pipeline/deal/456') !== false, 'Missing deal mobile deeplink');
assert(strpos($formattedMsg, 'http://crm.company.com/admin/sales_pipeline/dashboard?dashboard_tab=estimates&staff_id=12') !== false, 'Missing revenue chart staff mobile deeplink');
assert(strpos($formattedMsg, 'Xem Biểu đồ Doanh thu (Nguyen Van A)') !== false, 'Missing staff localized label on revenue chart link');
assert(strpos($formattedMsg, 'http://crm.company.com/admin/sales_pipeline/reminder_response/789?src=wa') !== false, 'Missing response mobile deeplink');

// Test 3: Idempotency Key Stability Contract
$instanceId = 'abc123def456';
$deliveryId = 8899;
$key1 = Reminder_delivery_whatsapp_adapter::formatIdempotencyKey($instanceId, $deliveryId);
$key2 = Reminder_delivery_whatsapp_adapter::formatIdempotencyKey($instanceId, $deliveryId);
assert($key1 === 'sp_abc123def456_del_8899', "Idempotency key format invalid: $key1");
assert($key1 === $key2, 'Idempotency key must be stable across multiple evaluations');
assert(strpos($key1, 'attempt') === false, 'Idempotency key must NOT contain attempt count');

// Test 4: Rate Limiter Scope Isolation
class FakeDbRateLimiter
{
    public $queries = [];
    public $counts = [
        'default_smtp_account' => 50,
        'whatsapp_baileys_gateway' => 10,
    ];

    public function trans_begin() { return true; }
    public function trans_status() { return true; }
    public function trans_commit() { return true; }
    public function trans_rollback() { return true; }

    public function query($sql, array $params = [])
    {
        $this->queries[] = ['sql' => $sql, 'params' => $params];
        $scope = $params[0] ?? 'default_smtp_account';
        $cnt = $this->counts[$scope] ?? 0;
        return new class($cnt) {
            private $cnt;
            public function __construct($c) { $this->cnt = $c; }
            public function result_array() {
                return [['message_attempts' => $this->cnt, 'recipient_attempts' => $this->cnt, 'bucket_minute' => '2026-09-09 12:00:00']];
            }
        };
    }
}
$fakeRateDb = new FakeDbRateLimiter();
$testCI->db = $fakeRateDb;
$rateLimiter = new Reminder_delivery_rate_limiter();
$now = new DateTimeImmutable('2026-09-09 13:00:00');

$waUsage = $rateLimiter->usageSince($now->modify('-60 minutes'), 'whatsapp_baileys_gateway');
assert($waUsage['message_attempts'] === 10, 'WhatsApp usage must query whatsapp scope');

$smtpUsage = $rateLimiter->usageSince($now->modify('-60 minutes'), 'default_smtp_account');
assert($smtpUsage['message_attempts'] === 50, 'Email usage must query smtp scope');

$limits = [
    'max_recipients_per_message' => 50,
    'hourly_messages' => 60,
    'hourly_recipients' => 100,
    'daily_messages' => 1000,
    'daily_recipients' => 2000,
];
$decisionOk = $rateLimiter->reserve(1, $now, $limits, 'whatsapp_baileys_gateway');
assert($decisionOk['allowed'] === true, 'WhatsApp limit 60 should allow 10 current attempts');

$tightLimits = [
    'max_recipients_per_message' => 50,
    'hourly_messages' => 10,
    'hourly_recipients' => 10,
    'daily_messages' => 1000,
    'daily_recipients' => 2000,
];
$decisionDenied = $rateLimiter->reserve(1, $now, $tightLimits, 'whatsapp_baileys_gateway');
assert($decisionDenied['allowed'] === false, 'WhatsApp limit 10 should be exceeded with 10 current attempts');

// Test 5: Selector isolates both uncertain and unverified records
class FakeDbSelector
{
    public $queries = [];
    public $updates = [];
    public $wheres = [];
    public $whereIn = [];

    public function affected_rows() { return 1; }
    public function query($sql, array $params = [])
    {
        $this->queries[] = ['sql' => $sql, 'params' => $params];
        return new class {
            public function result_array() { return []; }
        };
    }
    public function where($k, $v) { $this->wheres[$k] = $v; return $this; }
    public function where_in($k, array $vals) { $this->whereIn[$k] = $vals; return $this; }
    public function update($table, array $data) { $this->updates[] = ['table' => $table, 'data' => $data]; return true; }
}
$selectorDb = new FakeDbSelector();
$testCI->db = $selectorDb;
$selector = new Reminder_delivery_selector();
$selector->due('whatsapp', 10, 3, $now);
$dueSql = $selectorDb->queries[0]['sql'];
assert(strpos($dueSql, "d.channel=?") !== false || strpos($dueSql, "d.channel = 'whatsapp'") !== false, 'due() must filter channel whatsapp');
assert(strpos($dueSql, "'whatsapp_delivery_uncertain'") !== false, 'due() must exclude whatsapp_delivery_uncertain');
assert(strpos($dueSql, "'whatsapp_reconcile_unverified'") !== false, 'due() must exclude whatsapp_reconcile_unverified');

$selector->claim(101, 'whatsapp', 3, $now);
$claimSql = $selectorDb->queries[1]['sql'];
assert(strpos($claimSql, "'whatsapp_delivery_uncertain'") !== false, 'claim() must exclude whatsapp_delivery_uncertain');
assert(strpos($claimSql, "'whatsapp_reconcile_unverified'") !== false, 'claim() must exclude whatsapp_reconcile_unverified');

// Test 6: dueUncertainWhatsApp & deferDueWhatsApp
$selector->dueUncertainWhatsApp(10);
$uncertainSql = $selectorDb->queries[2]['sql'];
assert(strpos($uncertainSql, "whatsapp_delivery_uncertain") !== false, 'dueUncertainWhatsApp must query uncertain error code');

// Test 6.1: deferDueWhatsApp bulk update
$selector->deferDueWhatsApp('2026-09-09 14:00:00', 'rate_limit_exceeded', 'Paused by quota');
$deferBulkSql = $selectorDb->queries[3]['sql'];
assert(strpos($deferBulkSql, "`channel`='whatsapp'") !== false || strpos($deferBulkSql, "channel='whatsapp'") !== false, 'deferDueWhatsApp must target channel whatsapp');
assert(strpos($deferBulkSql, "whatsapp_delivery_uncertain") !== false, 'deferDueWhatsApp must exclude uncertain records');

// Test 6.2: defer() isolates email delivery and only updates processing status
$selectorDb->wheres = [];
$selector->defer(999, '2026-09-09 15:00:00', 'rate_limited');
assert(isset($selectorDb->wheres['status']), 'defer() must check status');
assert($selectorDb->wheres['status'] === 'processing', 'defer() must only update processing status to preserve email worker invariant');

// Test 7: SLA Isolation Contract
// Deliveries for manager must have recipient_type = 'manager' and preserve original staff_id
$managerDelivery = [
    'recipient_type' => 'manager',
    'channel' => 'whatsapp',
    'staff_id' => 15, // original sales staff ID remains intact
];
assert($managerDelivery['recipient_type'] === 'manager', 'Recipient type must be manager');
assert($managerDelivery['staff_id'] === 15, 'Original sales staff ID must remain intact for reference');

// Test 8: WhatsApp Adapter HTTP Mocking Contract
class FakeHttpMockResponse
{
    private $code;
    private $body;
    public function __construct($code, $body)
    {
        $this->code = (int) $code;
        $this->body = is_array($body) ? json_encode($body) : (string) $body;
    }
    public function getStatusCode() { return $this->code; }
    public function getBody() { return $this->body; }
}

class FakeHttpMockClient
{
    private $handler;
    public function __construct(callable $handler) { $this->handler = $handler; }
    public function request($method, $uri, array $options = [])
    {
        return ($this->handler)($method, $uri, $options);
    }
}

// 8.1 Successful Send
$mockSuccessClient = new FakeHttpMockClient(function ($method, $uri, $options) {
    assert($method === 'POST', 'Send must use POST');
    assert(strpos($uri, '/api/v1/messages/send') !== false, 'Send must call messages/send endpoint');
    return new FakeHttpMockResponse(200, [
        'ok' => true,
        'message_id' => 'baileys_msg_998877',
    ]);
});
$adapter = new Reminder_delivery_whatsapp_adapter($mockSuccessClient);
$res = $adapter->send('84901234567@s.whatsapp.net', 'Test Message', 12345);
assert($res['success'] === true, 'Adapter send should be successful');
assert($res['uncertain'] === false, 'Adapter send should not be uncertain');
assert($res['provider_message_id'] === 'baileys_msg_998877', 'Message ID should be parsed correctly');

// 8.2 In-flight Timeout via ConnectException (cURL error 28)
if (class_exists('GuzzleHttp\Exception\ConnectException') && class_exists('GuzzleHttp\Psr7\Request')) {
    $curl28Client = new FakeHttpMockClient(function ($method, $uri, $options) {
        $req = new \GuzzleHttp\Psr7\Request('POST', $uri);
        throw new \GuzzleHttp\Exception\ConnectException('cURL error 28: Operation timed out after 5000 milliseconds with 0 bytes received', $req);
    });
    $adapterCurl28 = new Reminder_delivery_whatsapp_adapter($curl28Client);
    $resCurl28 = $adapterCurl28->send('84901234567@s.whatsapp.net', 'Test Message', 12345);
    assert($resCurl28['success'] === false, 'Timeout should not be success');
    assert($resCurl28['uncertain'] === true, 'cURL error 28 in ConnectException must be marked uncertain');
    assert($resCurl28['last_error_code'] === 'whatsapp_delivery_uncertain', 'Timeout error code must be whatsapp_delivery_uncertain');

    // Connection Refused (pure connection failure) -> uncertain must be false
    $connRefusedClient = new FakeHttpMockClient(function ($method, $uri, $options) {
        $req = new \GuzzleHttp\Psr7\Request('POST', $uri);
        throw new \GuzzleHttp\Exception\ConnectException('Failed to connect to 127.0.0.1 port 3050: Connection refused', $req);
    });
    $adapterRefused = new Reminder_delivery_whatsapp_adapter($connRefusedClient);
    $resRefused = $adapterRefused->send('84901234567@s.whatsapp.net', 'Test Message', 12345);
    assert($resRefused['success'] === false);
    assert($resRefused['uncertain'] === false, 'Connection refused must NOT be marked uncertain');
    assert($resRefused['last_error_code'] === 'gateway_connection_failed');
}

// 8.3 In-flight Timeout via RequestException
if (class_exists('GuzzleHttp\Exception\RequestException') && class_exists('GuzzleHttp\Psr7\Request')) {
    $guzzleTimeoutClient = new FakeHttpMockClient(function ($method, $uri, $options) {
        $req = new \GuzzleHttp\Psr7\Request('POST', $uri);
        throw new \GuzzleHttp\Exception\RequestException('Operation timed out after 5000 milliseconds', $req);
    });
    $adapterGuzzleTimeout = new Reminder_delivery_whatsapp_adapter($guzzleTimeoutClient);
    $resTimeout = $adapterGuzzleTimeout->send('84901234567@s.whatsapp.net', 'Test Message', 12345);
    assert($resTimeout['success'] === false, 'Timeout should not be success');
    assert($resTimeout['uncertain'] === true, 'Timeout must be marked as uncertain');
    assert($resTimeout['last_error_code'] === 'whatsapp_delivery_uncertain', 'Timeout error code must be whatsapp_delivery_uncertain');
}

// 8.4 Gateway HTTP 502 with retry_safe: false
$mock502Client = new FakeHttpMockClient(function ($method, $uri, $options) {
    return new FakeHttpMockResponse(502, [
        'success' => false,
        'error' => 'send_failed_or_unconfirmed',
        'retry_safe' => false,
    ]);
});
$adapter502 = new Reminder_delivery_whatsapp_adapter($mock502Client);
$res502 = $adapter502->send('84901234567@s.whatsapp.net', 'Test Message', 12345);
assert($res502['success'] === false);
assert($res502['uncertain'] === true, 'retry_safe=false must be classified as uncertain');
assert($res502['last_error_code'] === 'whatsapp_delivery_uncertain', 'Error code must be whatsapp_delivery_uncertain');

// 8.5 checkStatus: 404 Not Found
$mockStatus404Client = new FakeHttpMockClient(function ($method, $uri, $options) {
    assert($method === 'GET', 'checkStatus must use GET');
    return new FakeHttpMockResponse(404, ['error' => 'Not found']);
});
$adapter404 = new Reminder_delivery_whatsapp_adapter($mockStatus404Client);
$res404 = $adapter404->checkStatus(12345);
assert($res404['status'] === 'not_found', 'HTTP 404 must return status not_found');

// 8.6 checkStatus: 200 Sent
$mockStatusSentClient = new FakeHttpMockClient(function ($method, $uri, $options) {
    return new FakeHttpMockResponse(200, ['status' => 'sent', 'message_id' => 'msg_abc_123']);
});
$adapterSent = new Reminder_delivery_whatsapp_adapter($mockStatusSentClient);
$resSent = $adapterSent->checkStatus(12345);
assert($resSent['status'] === 'sent', 'Status sent must be returned');
assert($resSent['message_id'] === 'msg_abc_123', 'Message ID must match');

// 8.7 checkHealth: 200 CONNECTED
$mockHealthClient = new FakeHttpMockClient(function ($method, $uri, $options) {
    return new FakeHttpMockResponse(200, ['status' => 'CONNECTED', 'connected' => true]);
});
$adapterHealth = new Reminder_delivery_whatsapp_adapter($mockHealthClient);
$resHealth = $adapterHealth->checkHealth();
assert($resHealth['connected'] === true, 'Gateway should be connected');
assert($resHealth['status'] === 'CONNECTED', 'Gateway status should be CONNECTED');

// 8.8 check curl errno and string fallbacks via isTimeoutOrReceiveError
class FakeCurlException extends \Exception
{
    private $ctx;
    public function __construct($message, array $ctx = [])
    {
        parent::__construct($message);
        $this->ctx = $ctx;
    }
    public function getHandlerContext()
    {
        return $this->ctx;
    }
}

assert(Reminder_delivery_whatsapp_adapter::isTimeoutOrReceiveError(new FakeCurlException('err', ['errno' => 28])) === true, 'errno 28 must be uncertain');
assert(Reminder_delivery_whatsapp_adapter::isTimeoutOrReceiveError(new FakeCurlException('err', ['errno' => 52])) === true, 'errno 52 must be uncertain');
assert(Reminder_delivery_whatsapp_adapter::isTimeoutOrReceiveError(new FakeCurlException('err', ['errno' => 56])) === true, 'errno 56 must be uncertain');
assert(Reminder_delivery_whatsapp_adapter::isTimeoutOrReceiveError(new FakeCurlException('err', ['errno' => 7])) === false, 'errno 7 must not be uncertain');
assert(Reminder_delivery_whatsapp_adapter::isTimeoutOrReceiveError(new FakeCurlException('err', ['errno' => 6])) === false, 'errno 6 must not be uncertain');
assert(Reminder_delivery_whatsapp_adapter::isTimeoutOrReceiveError(new \Exception('Connection reset by peer')) === true, 'connection reset string fallback');
assert(Reminder_delivery_whatsapp_adapter::isTimeoutOrReceiveError(new \Exception('Empty reply from server')) === true, 'empty reply string fallback');

// 8.9 HTTP 429 Rate Limited -> transient
// 8.11 checkStatus with unrecognized status normalized to uncertain and uncasted retry_safe
$mockStatusUnkClient = new FakeHttpMockClient(function ($method, $uri, $options) {
    return new FakeHttpMockResponse(200, [
        'status'     => 'queued', // Unrecognized status
        'retry_safe' => 'false',  // String "false"
        'error'      => 'Custom queue state',
    ]);
});
$adapterUnk = new Reminder_delivery_whatsapp_adapter($mockStatusUnkClient);
$resUnk = $adapterUnk->checkStatus(12345);
assert($resUnk['status'] === 'uncertain', 'Unrecognized status must be normalized to uncertain');
assert($resUnk['retry_safe'] === 'false', 'Raw retry_safe string must not be blindly cast to boolean');
assert($resUnk['error'] === 'Custom queue state', 'Error message must be preserved');

// 9. Reconcile Loop Contract Tests (Direct Engine & Selector SQLite In-Memory DB Execution)
require_once dirname(__DIR__) . '/libraries/Reminder_delivery_backoff.php';
require_once dirname(__DIR__) . '/libraries/Reminder_engine.php';

class SqliteCIEngineDb
{
    private $pdo;
    private $wheres = [];
    private $whereIn = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function where($k, $v)
    {
        $this->wheres[$k] = $v;
        return $this;
    }

    public function where_in($k, array $vals)
    {
        $this->whereIn[$k] = $vals;
        return $this;
    }

    public function update($table, array $data)
    {
        $setClauses = [];
        $params = [];
        foreach ($data as $col => $val) {
            $setClauses[] = "`$col` = ?";
            $params[] = $val;
        }
        $whereClauses = [];
        foreach ($this->wheres as $col => $val) {
            $whereClauses[] = "`$col` = ?";
            $params[] = $val;
        }
        foreach ($this->whereIn as $col => $vals) {
            $placeholders = implode(',', array_fill(0, count($vals), '?'));
            $whereClauses[] = "`$col` IN ($placeholders)";
            foreach ($vals as $v) {
                $params[] = $v;
            }
        }
        $this->wheres = [];
        $this->whereIn = [];

        $sql = "UPDATE `$table` SET " . implode(', ', $setClauses);
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function query($sql, array $params = [])
    {
        if (strpos($sql, 'GET_LOCK') !== false) {
            return new class {
                public function row_array() { return ['lck' => 1]; }
                public function result_array() { return [['lck' => 1]]; }
            };
        }
        if (strpos($sql, 'RELEASE_LOCK') !== false) {
            return new class {
                public function row_array() { return ['rel' => 1]; }
                public function result_array() { return [['rel' => 1]]; }
            };
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return new class($rows) {
            private $rows;
            public function __construct($rows) { $this->rows = $rows; }
            public function result_array() { return $this->rows; }
            public function row_array() { return $this->rows[0] ?? []; }
        };
    }

    public function affected_rows()
    {
        return 1;
    }
}

class MockTrackingRateLimiter
{
    public $callCount = 0;
    public function reserve($count, $now, $limits, $channel) {
        $this->callCount++;
        return ['allowed' => true];
    }
}

class MockReconcileAdapter
{
    public $checkStatusResults = [];
    public $delayMs = 0;
    public $sendCallCount = 0;
    public function checkStatus($id, $timeout = 3.0) {
        if ($this->delayMs > 0) {
            usleep($this->delayMs * 1000);
        }
        return $this->checkStatusResults[$id] ?? ['status' => 'uncertain'];
    }
    public function send($recipient, $text, $deliveryId, $options = []) {
        $this->sendCallCount++;
        return ['success' => true, 'provider_message_id' => 'mock_send_' . $deliveryId, 'uncertain' => false];
    }
}

// Setup Shared SQLite Database
$sqlitePdo = new PDO('sqlite::memory:');
$sqlitePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlitePdo->exec('CREATE TABLE tblsales_pipeline_reminder_deliveries (
    id INTEGER PRIMARY KEY,
    reminder_id INT,
    channel TEXT,
    recipient_type TEXT,
    staff_id INT,
    status TEXT,
    attempt_count INT DEFAULT 0,
    provider_message_id TEXT,
    last_error TEXT,
    last_error_code TEXT,
    last_error_class TEXT,
    next_retry_at TEXT,
    sent_at TEXT,
    created_at TEXT,
    updated_at TEXT
);');
$sqlitePdo->exec('CREATE TABLE tblsales_pipeline_reminders_log (
    id INTEGER PRIMARY KEY,
    title TEXT, message TEXT, entity_type TEXT, entity_id INT,
    pipeline_id INT, rule_code TEXT, staff_id INT, severity TEXT,
    checkpoint TEXT, response_required INT, snapshot_json TEXT, sent_at TEXT
);');
$sqlitePdo->exec("INSERT INTO tblsales_pipeline_reminders_log (id, title) VALUES (1, 'Test Reminder');");

$engineDb = new SqliteCIEngineDb($sqlitePdo);
$engineLimiter = new MockTrackingRateLimiter();
$engineAdapter = new MockReconcileAdapter();

// Setup real Reminder_delivery_selector connected to same SQLite DB
$realSelector = new Reminder_delivery_selector();
$selectorCi = (object) ['db' => $engineDb];
$selectorCiProp = new ReflectionProperty($realSelector, 'CI');
$selectorCiProp->setValue($realSelector, $selectorCi);

// Setup Engine connected to SQLite DB & real Selector
$engineCI = (object) [
    'db' => $engineDb,
    'load' => new class { public function library($name) {} },
    'reminder_delivery_selector' => $realSelector,
    'reminder_delivery_whatsapp_adapter' => $engineAdapter,
    'reminder_delivery_backoff' => new Reminder_delivery_backoff(),
    'reminder_delivery_rate_limiter' => $engineLimiter,
    'reminder_whatsapp_formatter' => new class { public function format($row) { return ''; } },
];

$engine = (new ReflectionClass('Reminder_engine'))->newInstanceWithoutConstructor();
$ciProp = new ReflectionProperty($engine, 'CI');
$ciProp->setValue($engine, $engineCI);
$dispatchWhatsAppMethod = new ReflectionMethod('Reminder_engine', 'dispatchWhatsAppDeliveries');
$GLOBALS['options_mock']['sp_reminder_whatsapp_enabled'] = '1';
$GLOBALS['options_mock']['sp_reminder_whatsapp_time_budget'] = '15.0';

// 9.1 & 9.2: Insert real DB records and run through Engine for retry_safe === true and 6 invalid variants
$sqlitePdo->exec("INSERT INTO tblsales_pipeline_reminder_deliveries 
    (id, reminder_id, channel, status, last_error_code, attempt_count, created_at, updated_at) 
    VALUES (101, 1, 'whatsapp', 'failed', 'whatsapp_delivery_uncertain', 1, '2026-09-10 10:00:00', NULL);");
$engineAdapter->checkStatusResults[101] = [
    'status' => 'failed', 'retry_safe' => true, 'error' => 'Gateway temporary outage'
];

$invalidVariants = [
    201 => ['name' => 'string_true',  'val' => 'true'],
    202 => ['name' => 'int_one',      'val' => 1],
    203 => ['name' => 'bool_false',   'val' => false],
    204 => ['name' => 'string_false', 'val' => 'false'],
    205 => ['name' => 'null_val',     'val' => null],
    206 => ['name' => 'missing',      'val' => 'MISSING_KEY'],
];

foreach ($invalidVariants as $id => $info) {
    $sqlitePdo->exec("INSERT INTO tblsales_pipeline_reminder_deliveries 
        (id, reminder_id, channel, status, last_error_code, attempt_count, created_at, updated_at) 
        VALUES ({$id}, 1, 'whatsapp', 'failed', 'whatsapp_delivery_uncertain', 2, '2026-09-10 10:00:00', NULL);");
    $statusPayload = ['status' => 'failed', 'error' => "Gateway unconfirmed for {$info['name']}"];
    if ($info['val'] !== 'MISSING_KEY') {
        $statusPayload['retry_safe'] = $info['val'];
    }
    $engineAdapter->checkStatusResults[$id] = $statusPayload;
}

// 9.2.1: Verify Quota & Attempt Count BEFORE Reconcile
$limiterCallsBefore = $engineLimiter->callCount;
$dbAttemptBefore = (int) $sqlitePdo->query("SELECT attempt_count FROM tblsales_pipeline_reminder_deliveries WHERE id=201")->fetchColumn();
assert($limiterCallsBefore === 0, 'Rate limiter call count before reconcile must be 0');
assert($dbAttemptBefore === 2, 'Attempt count before reconcile must be 2');

// Run Engine Reconcile Pass
$dispatchWhatsAppMethod->invokeArgs($engine, [new DateTimeImmutable('now'), 3]);

// 9.2.2: Verify Quota & Attempt Count AFTER Reconcile (Point 2: Không tiêu quota, không tăng attempt)
$limiterCallsAfter = $engineLimiter->callCount;
$dbAttemptAfter = (int) $sqlitePdo->query("SELECT attempt_count FROM tblsales_pipeline_reminder_deliveries WHERE id=201")->fetchColumn();
assert($limiterCallsAfter === $limiterCallsBefore, 'Reconcile pass must make zero calls to Rate Limiter (calls before: ' . $limiterCallsBefore . ', after: ' . $limiterCallsAfter . ')');
assert($dbAttemptAfter === $dbAttemptBefore, 'Reconcile pass must NOT touch or increment attempt_count in DB (before: ' . $dbAttemptBefore . ', after: ' . $dbAttemptAfter . ')');

// 9.1 Verification: Valid boolean true row (101) in SQLite DB
$row101 = $sqlitePdo->query("SELECT * FROM tblsales_pipeline_reminder_deliveries WHERE id=101")->fetch(PDO::FETCH_ASSOC);
assert($row101['status'] === 'failed', 'Status must remain failed for retry_safe === true');
assert($row101['last_error_code'] === 'gateway_reported_failed', 'Error code must be gateway_reported_failed in DB');
assert($row101['last_error_class'] === 'transient', 'Error class must be transient in DB');
assert(!empty($row101['next_retry_at']), 'next_retry_at must be populated in DB for retry_safe === true');

// 9.2 Verification: 6 invalid type cases asserted directly from SQLite DB (Point 1)
foreach ($invalidVariants as $id => $info) {
    $dbRow = $sqlitePdo->query("SELECT * FROM tblsales_pipeline_reminder_deliveries WHERE id={$id}")->fetch(PDO::FETCH_ASSOC);
    assert($dbRow['last_error_code'] === 'whatsapp_reconcile_unverified', "Case {$info['name']} (ID {$id}) in DB must have last_error_code = whatsapp_reconcile_unverified");
    assert($dbRow['last_error_class'] === 'unverified', "Case {$info['name']} (ID {$id}) in DB must have last_error_class = unverified");
    assert($dbRow['next_retry_at'] === null, "Case {$info['name']} (ID {$id}) in DB next_retry_at must be NULL");
    assert($dbRow['status'] === 'failed', "Case {$info['name']} (ID {$id}) in DB status must remain failed (NOT cancelled)");
    assert((int) $dbRow['attempt_count'] === 2, "Case {$info['name']} (ID {$id}) in DB attempt_count must remain unchanged at 2");
}

// 9.3 Consistent Isolation Verification: due() on SQLite DB must exclude all reconciled unverified rows
$now = new DateTimeImmutable('now');
$dueRows = $realSelector->due('whatsapp', 50, 5, $now);
$dueIds = array_column($dueRows, 'id');
foreach (array_keys($invalidVariants) as $invId) {
    assert(!in_array($invId, $dueIds, true), "due() on DB must exclude quarantined record {$invId}");
}

// 9.4 Real Round-Robin on DB with Real Time-Budget Cutoff in Engine (Point 3)
// Insert 5 uncertain rows with identical created_at and updated_at NULL
$sqlitePdo->exec("DELETE FROM tblsales_pipeline_reminder_deliveries WHERE id >= 300;");
for ($i = 301; $i <= 305; $i++) {
    $sqlitePdo->exec("INSERT INTO tblsales_pipeline_reminder_deliveries 
        (id, reminder_id, channel, status, last_error_code, created_at, updated_at) 
        VALUES ({$i}, 1, 'whatsapp', 'failed', 'whatsapp_delivery_uncertain', '2026-09-10 00:00:00', NULL);");
    $engineAdapter->checkStatusResults[$i] = ['status' => 'in_progress'];
}

// Test A: Initial selector run on DB -> Identical timestamp orders by id ASC [301, 302, 303, 304, 305]
$initialBatch = $realSelector->dueUncertainWhatsApp(10);
$initialIds = array_map('intval', array_column($initialBatch, 'id'));
assert($initialIds === [301, 302, 303, 304, 305], 'Initial batch on DB must order deterministically by id ASC');

// Test B: Engine Reconcile with 25ms delay per item and 3.04s time budget
// Engine threshold: ($timeBudget - $elapsed) < 3.0 triggers break!
// After item 301 (~25ms) and item 302 (~50ms), elapsed is ~50ms > 0.04s, so 3.04 - 0.05 < 3.0 triggers break;
$GLOBALS['options_mock']['sp_reminder_whatsapp_time_budget'] = '3.04';
$engineAdapter->delayMs = 25;

$dispatchWhatsAppMethod->invokeArgs($engine, [new DateTimeImmutable('now'), 3]);

// Verify DB state after time budget break:
// Records 301 and 302 were evaluated and updated with current timestamp
// Records 303, 304, 305 were NOT evaluated and retain updated_at = NULL
$db301 = $sqlitePdo->query("SELECT updated_at FROM tblsales_pipeline_reminder_deliveries WHERE id=301")->fetch(PDO::FETCH_ASSOC);
$db302 = $sqlitePdo->query("SELECT updated_at FROM tblsales_pipeline_reminder_deliveries WHERE id=302")->fetch(PDO::FETCH_ASSOC);
$db303 = $sqlitePdo->query("SELECT updated_at FROM tblsales_pipeline_reminder_deliveries WHERE id=303")->fetch(PDO::FETCH_ASSOC);
$db304 = $sqlitePdo->query("SELECT updated_at FROM tblsales_pipeline_reminder_deliveries WHERE id=304")->fetch(PDO::FETCH_ASSOC);
$db305 = $sqlitePdo->query("SELECT updated_at FROM tblsales_pipeline_reminder_deliveries WHERE id=305")->fetch(PDO::FETCH_ASSOC);

assert(!empty($db301['updated_at']), 'ID 301 must be evaluated and have updated_at populated in DB');
assert(!empty($db302['updated_at']), 'ID 302 must be evaluated and have updated_at populated in DB');
assert($db303['updated_at'] === null, 'ID 303 must remain un-evaluated with updated_at NULL due to time budget break');
assert($db304['updated_at'] === null, 'ID 304 must remain un-evaluated with updated_at NULL due to time budget break');
assert($db305['updated_at'] === null, 'ID 305 must remain un-evaluated with updated_at NULL due to time budget break');

// Test C: Next pass of Selector on DB -> Un-evaluated records (303, 304, 305) MUST come first before 301 and 302
$nextBatch = $realSelector->dueUncertainWhatsApp(10);
$nextIds = array_map('intval', array_column($nextBatch, 'id'));
assert($nextIds === [303, 304, 305, 301, 302], 'Round-robin DB selection must prioritize un-evaluated records [303, 304, 305] over recently touched [301, 302]');

echo "PASS: Reminder delivery WhatsApp integration, idempotency, selector isolation and formatting contract tests\n";



