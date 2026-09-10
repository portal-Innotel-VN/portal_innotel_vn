<?php

define('BASEPATH', __DIR__);
require_once dirname(__DIR__) . '/libraries/Reminder_engine.php';
require_once dirname(__DIR__) . '/libraries/Reminder_delivery_policy.php';
require_once dirname(__DIR__) . '/libraries/Reminder_whatsapp_formatter.php';

function db_prefix() { return 'tbl'; }
function get_option($key) { return $GLOBALS['manager_options'][$key] ?? ''; }
function update_option($key, $value) { $GLOBALS['manager_options'][$key] = $value; }
function &get_instance() { return $GLOBALS['manager_ci']; }
function has_permission($feature, $staffId, $capability)
{
    return $feature === 'sales_pipeline' && $capability === 'view'
        && in_array((int) $staffId, [2, 4], true);
}
function add_notification($data) { $GLOBALS['manager_notifications'][] = $data; return true; }
function manager_check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function manager_call($engine, $method, array $args = [])
{
    $reflection = new ReflectionMethod($engine, $method);
    return $reflection->invokeArgs($engine, $args);
}

class ManagerRecipientsDb
{
    public $staff = [];
    public $deliveries = [];
    private $filters = [];
    public function where($key, $value) { $this->filters[$key] = $value; return $this; }
    public function select($fields) { return $this; }
    public function get($table)
    {
        $filters = $this->filters;
        $this->filters = [];
        $rows = array_values(array_filter($this->staff, function ($staff) use ($filters) {
            foreach ($filters as $key => $value) {
                if ($staff->$key != $value) { return false; }
            }
            return true;
        }));
        return new class($rows) {
            private $rows;
            public function __construct($rows) { $this->rows = $rows; }
            public function result() { return $this->rows; }
            public function result_array() { return array_map(function ($row) { return (array) $row; }, $this->rows); }
        };
    }
    public function query($sql, $params)
    {
        manager_check(strpos($sql, 'INSERT IGNORE INTO `tblsales_pipeline_reminder_deliveries`') === 0, 'Unexpected SQL');
        $this->deliveries = array_merge($this->deliveries, array_chunk($params, 8));
    }
}

$GLOBALS['manager_options'] = [
    'sp_reminder_whatsapp_enabled' => '1',
    'sp_reminder_whatsapp_manager_mode' => 'both',
    'sp_reminder_whatsapp_group_jid' => '120363123456789@g.us',
    'sp_reminder_delivery_default_max_valid_age_hours' => '24',
    'sp_reminder_email_cc_manager_enabled' => '0',
];
$GLOBALS['manager_notifications'] = [];
$db = new ManagerRecipientsDb();
// Active admin, active global-view manager, view-own staff, inactive manager, inactive admin.
foreach ([[1, 1, 1], [2, 0, 1], [3, 0, 1], [4, 0, 0], [5, 1, 0]] as $fixture) {
    list($id, $admin, $active) = $fixture;
    $db->staff[$id] = (object) [
        'staffid' => $id, 'admin' => $admin, 'active' => $active,
        'email' => 'staff' . $id . '@example.test', 'phonenumber' => '090123456' . $id,
    ];
}
$GLOBALS['manager_ci'] = (object) [
    'db' => $db,
    'staff_model' => new class($db) {
        private $db;
        public function __construct($db) { $this->db = $db; }
        public function get($id) { return $this->db->staff[$id] ?? null; }
    },
    'load' => new class { public function library($name) {} },
    'reminder_delivery_policy' => new Reminder_delivery_policy(),
];
$GLOBALS['manager_ci']->reminder_whatsapp_formatter = new Reminder_whatsapp_formatter();
$engine = (new ReflectionClass('Reminder_engine'))->newInstanceWithoutConstructor();
$ciProperty = new ReflectionProperty($engine, 'CI');
$ciProperty->setValue($engine, $GLOBALS['manager_ci']);
$event = ['staff_id' => 3, 'recipients' => ['staff', 'manager'], 'channels' => ['crm', 'email', 'whatsapp']];
manager_call($engine, 'materializeDeliveries', [101, $event]);
foreach (['crm', 'email', 'whatsapp'] as $channel) {
    $ids = [];
    foreach ($db->deliveries as $row) {
        if ($row[1] === $channel && $row[2] === 'manager' && $row[3] !== null) { $ids[] = $row[3]; }
    }
    sort($ids);
    manager_check($ids === [1, 2], $channel . ': expected active admin + global-view manager, got ' . json_encode($ids));
}
$groups = array_filter($db->deliveries, function ($row) { return $row[1] === 'whatsapp' && $row[3] === null; });
manager_check(count($groups) === 1, 'both mode must keep one group delivery');
foreach ($db->deliveries as $row) {
    if ($row[2] === 'staff') { manager_check($row[3] === 3, 'Original staff delivery ownership must remain unchanged'); }
}
$GLOBALS['manager_options']['sp_reminder_email_cc_manager_enabled'] = '1';
$cc = manager_call($engine, 'resolveManagerCCEmails', [3]);
sort($cc);
manager_check($cc === ['staff1@example.test', 'staff2@example.test'], 'Email CC must include global-view manager');
manager_check(manager_call($engine, 'resolveManagerCCEmails', [2]) === ['staff1@example.test'], 'Do not CC manager their own reminder');
manager_call($engine, 'openAuthenticationCircuit', ['test_auth']);
manager_call($engine, 'alertSystemBccIncident');
manager_check(count($GLOBALS['manager_notifications']) === 2, 'Expected both infrastructure notifications');
foreach ($GLOBALS['manager_notifications'] as $notification) {
    manager_check($notification['touserid'] === 1, 'Infrastructure alerts must remain active-admin-only');
}
echo "PASS: Manager routing across CRM/email/WhatsApp, CC, active/global permissions and infrastructure isolation\n";
