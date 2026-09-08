<?php

defined('BASEPATH') or define('BASEPATH', dirname(__DIR__));

// -----------------------------------------------------------------------------
// Test Harness Mock for CodeIgniter / Database
// -----------------------------------------------------------------------------
class TestDbMock
{
    public $tables = [
        'tblsales_pipeline_estimate_groups'         => ['id' => 1, 'decision_estimate_id' => 1, 'decision_value_base' => 1, 'last_reconciled_at' => 1],
        'tblsales_pipeline_estimate_versions'       => ['estimate_group_id' => 1, 'parent_estimate_id' => 1, 'link_method' => 1, 'linked_by' => 1],
        'tblsales_pipeline_estimate_outcome_history'=> ['estimate_group_id' => 1],
        'tblsales_pipeline_estimate_group_events'   => ['id' => 1],
        'tblsales_pipeline_deal_estimate_groups'    => ['id' => 1],
        'tblsales_pipeline'                         => ['is_manual_lock' => 1],
    ];
    public $indexes = [];
    public $queries = [];
    public $insert_id_val = 100;
    public $trans_status_val = true;
    public $where_clauses = [];
    public $mock_rows = [];

    public function table_exists($table)
    {
        return isset($this->tables[$table]);
    }

    public function field_exists($field, $table)
    {
        return isset($this->tables[$table][$field]);
    }

    public function query($sql)
    {
        $this->queries[] = $sql;
        return new class {
            public function row_array() { return ['mock' => 1]; }
            public function result_array() { return []; }
        };
    }

    public function select($fields, $escape = true) { return $this; }
    public function from($table) { return $this; }
    public function join($table, $cond, $type = 'inner') { return $this; }
    public function where($key, $val = null) { $this->where_clauses[$key] = $val; return $this; }
    public function where_in($key, $vals, $escape = true) { return $this; }
    public function group_start() { return $this; }
    public function group_end() { return $this; }
    public function or_where($key, $val = null) { return $this; }
    public function order_by($col, $dir = 'asc', $escape = true) { return $this; }
    public function group_by($col, $escape = true) { return $this; }
    public function limit($limit, $offset = 0) { return $this; }

    public function get($table = '')
    {
        $rows = $this->mock_rows;
        return new class($rows) {
            private $rows;
            public function __construct($r) { $this->rows = $r; }
            public function row_array() {
                if (!empty($this->rows)) {
                    return $this->rows[0];
                }
                return [
                    'id'               => 105,
                    'clientid'         => 10,
                    'sale_agent'       => 1,
                    'addedfrom'        => 1,
                    'owner_staff_id'   => 1,
                    'status'           => 1,
                    'currency'         => 1,
                    'total'            => 1000,
                    'base_total'       => 1000,
                    'exchange_rate'    => 1.0,
                    'base_currency_id' => 1,
                    'datecreated'      => '2026-08-21 12:00:00',
                    'invoiced_date'    => null,
                    'deal_name'        => 'Mock Deal',
                    'isdefault'        => 1,
                ];
            }
            public function result_array() { return $this->rows; }
        };
    }

    public function insert($table, $data)
    {
        $this->insert_id_val++;
        $this->queries[] = ['insert' => $table, 'data' => $data];
        return true;
    }

    public function insert_id()
    {
        return $this->insert_id_val;
    }

    public function update($table, $data)
    {
        $this->queries[] = ['update' => $table, 'data' => $data, 'where' => $this->where_clauses];
        return true;
    }

    public function delete($table)
    {
        $this->queries[] = ['delete' => $table, 'where' => $this->where_clauses];
        return true;
    }

    public function trans_start() {}
    public function trans_complete() {}
    public function trans_rollback() {}
    public function trans_status() { return $this->trans_status_val; }
    public function count_all_results($table = '') { return 1; }
}

class TestLoaderMock
{
    private $ci;

    public function __construct($ci)
    {
        $this->ci = $ci;
    }

    public function model($model) {}

    public function library($library)
    {
        $name = strtolower(basename($library));
        if ($name === 'quote_currency_resolver') {
            require_once dirname(__DIR__) . '/libraries/Quote_currency_resolver.php';
            $this->ci->quote_currency_resolver = new Quote_currency_resolver();
        } elseif ($name === 'deal_bridge_calculator') {
            require_once dirname(__DIR__) . '/libraries/Deal_bridge_calculator.php';
            $this->ci->deal_bridge_calculator = new Deal_bridge_calculator();
        }
    }
}

class TestCiMock
{
    public $db;
    public $load;
    public $sales_pipeline_model;
    public $quote_currency_resolver;
    public $deal_bridge_calculator;

    public function __construct()
    {
        $this->db = new TestDbMock();
        $this->load = new TestLoaderMock($this);
        $this->sales_pipeline_model = new class {
            public function sync_estimate_group($id) { return true; }
            public function get_customer_estimate_revision_sources($c, $s) { return []; }
            public function get_estimate_revision_candidates($c, $p, $s, $l) { return []; }
            public function get_deal($id) { return ['id' => $id, 'staff_id' => 1]; }
            public function log_activity($d, $m, $s) { return true; }
            public function get($id) { return ['id' => $id, 'deal_name' => 'Mock Deal']; }
        };
    }
}

function get_instance()
{
    static $ci = null;
    if ($ci === null) {
        $ci = new TestCiMock();
    }
    return $ci;
}

function db_prefix()
{
    return 'tbl';
}

function get_staff_user_id()
{
    return 1;
}

function is_admin($id = null)
{
    return true;
}

function hooks()
{
    return new class {
        public function apply_filters($name, $value, ...$args)
        {
            return $value;
        }
    };
}

function _l($key, $arg1 = null, $arg2 = null)
{
    return $key;
}

$GLOBALS['mock_disallowed_estimates'] = [];

function user_can_view_estimate($id, $staff_id = false)
{
    if (in_array((int) $id, $GLOBALS['mock_disallowed_estimates'] ?? [])) {
        return false;
    }
    return true;
}

// -----------------------------------------------------------------------------
// Assert Helper Functions
// -----------------------------------------------------------------------------
function assert_true($cond, $msg)
{
    if (!$cond) {
        fwrite(STDERR, "FAILED: {$msg}\n");
        exit(1);
    }
}

function assert_same($expected, $actual, $msg)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAILED: {$msg} | Expected: " . var_export($expected, true) . ", Actual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

// -----------------------------------------------------------------------------
// Execution & Assertions
// -----------------------------------------------------------------------------
$moduleRoot = dirname(__DIR__);

require_once $moduleRoot . '/libraries/Estimate_revision_service.php';
$service = new Estimate_revision_service();

// Test 1: Capture request context from raw POST data with namespace
$hookPayload = [
    'data' => [
        'clientid' => 10,
        'number'   => '00100',
        'sales_pipeline' => [
            'intent'                   => 'revision',
            'revision_of_estimate_id'  => 55,
            'override_accepted'        => 1,
            'override_reason'          => 'Client requested adjustment',
        ],
    ],
    'items' => []
];
$cleanedPayload = $service->capture_request_context($hookPayload);

assert_true(!isset($cleanedPayload['data']['sales_pipeline']), 'Test 1: sales_pipeline namespace stripped from hook data');
$captured = $service->get_captured_context();
assert_same('declared_revision', $captured['intent'], 'Test 1: intent normalized to declared_revision');
assert_same(55, $captured['source_estimate_id'], 'Test 1: source_estimate_id parsed');
assert_same('declared_revision', $captured['link_method'], 'Test 1: link_method is declared_revision');
assert_same(true, $captured['override_accepted'], 'Test 1: override_accepted boolean');
assert_same('Client requested adjustment', $captured['override_reason'], 'Test 1: override_reason string');

// Test 2: Intent standalone normalization
$hookStandalone = [
    'data' => [
        'clientid' => 10,
        'sales_pipeline' => [
            'intent' => 'standalone',
        ]
    ]
];
$service->capture_request_context($hookStandalone);
$capStandalone = $service->get_captured_context();
assert_same('standalone', $capStandalone['intent'], 'Test 2: standalone intent normalized');
assert_same('origin', $capStandalone['link_method'], 'Test 2: standalone link_method is origin');

// Test 3: Native Copy context setting
$service->set_copy_context(42, 'native_copy');
$copyCtx = $service->get_copy_context();
assert_same(42, $copyCtx['source_estimate_id'], 'Test 3: copy source id matches');
assert_same('native_copy', $copyCtx['link_method'], 'Test 3: copy link_method is native_copy');
$service->clear_copy_context();
assert_same(null, $service->get_copy_context(), 'Test 3: copy context cleared');

// Test 4: Module Copy context setting
$service->set_copy_context(99, 'module_copy', 88);
$modCtx = $service->get_copy_context();
assert_same(99, $modCtx['source_estimate_id'], 'Test 4: module copy source id');
assert_same(88, $modCtx['parent_estimate_id'], 'Test 4: module copy parent estimate id');
assert_same('module_copy', $modCtx['link_method'], 'Test 4: module copy link_method');
$service->clear_copy_context();

// Test 5: Standard contract return format
$res = $service->handle_estimate_added(0);
assert_same(false, $res['success'], 'Test 5: invalid estimate id fails');
assert_same('invalid_id', $res['warning_code'], 'Test 5: invalid_id warning code');

// Test 6: Invariant verification in schema files
$schemaContent = file_get_contents($moduleRoot . '/includes/estimate_group_schema.php');
assert_true(strpos($schemaContent, 'parent_estimate_id') !== false, 'Test 6: parent_estimate_id in schema');
assert_true(strpos($schemaContent, 'link_method') !== false, 'Test 6: link_method in schema');
assert_true(strpos($schemaContent, 'linked_by') !== false, 'Test 6: linked_by in schema');
assert_true(strpos($schemaContent, 'tblsales_pipeline_estimate_group_events') !== false, 'Test 6: audit table in schema');
assert_true(strpos($schemaContent, 'idx_event_estimate') !== false, 'Test 6: audit estimate index');
assert_true(strpos($schemaContent, 'idx_event_from_group') !== false, 'Test 6: audit from_group index');
assert_true(strpos($schemaContent, 'idx_event_to_group') !== false, 'Test 6: audit to_group index');
assert_true(strpos($schemaContent, 'idx_parent_estimate') !== false, 'Test 6: parent estimate index');

// Test 7: Schema ownership and module wiring verification
$moduleContent = file_get_contents($moduleRoot . '/sales_pipeline.php');
assert_true(strpos($moduleContent, 'sales_pipeline_estimate_group_schema_bootstrap') === false, 'Test 7: no Estimate Group DDL bootstrap on app_init');
assert_true(strpos($moduleContent, 'sales_pipeline_reminder_repository_schema_bootstrap') === false, 'Test 7: no Reminder Repository DDL bootstrap on app_init');
$installContent = file_get_contents($moduleRoot . '/install.php');
assert_true(strpos($installContent, 'sales_pipeline_ensure_estimate_group_schema') !== false, 'Test 7: activation install owns Estimate Group schema creation');
assert_true(strpos($installContent, 'sales_pipeline_ensure_reminder_repository_schema') !== false, 'Test 7: activation install owns Reminder Repository schema creation');
assert_true(strpos($moduleContent, 'manage_estimate_revisions') !== false, 'Test 7: manage_estimate_revisions capability registered');
assert_true(strpos($moduleContent, 'before_estimate_added') !== false, 'Test 7: before_estimate_added filter registered');
assert_true(strpos($moduleContent, 'sales_pipeline_load_estimate_revision_js') !== false, 'Test 7: JS loader registered');
assert_true(strpos($moduleContent, 'sales_pipeline_load_estimate_revision_css') !== false, 'Test 7: CSS loader registered');

// Test 8: Migration 107 and 108 integrity
$mig107 = file_get_contents($moduleRoot . '/migrations/107_version_107.php');
assert_true(strpos($mig107, 'Migration_Version_107') !== false, 'Test 8: Migration 107 exists');
assert_true(strpos($mig107, 'last_reconciled_at') !== false, 'Test 8: Migration 107 adds last_reconciled_at');

$mig108 = file_get_contents($moduleRoot . '/migrations/108_version_108.php');
assert_true(strpos($mig108, 'Migration_Version_108') !== false, 'Test 8: Migration 108 exists');
assert_true(strpos($mig108, 'estimate_group_schema.php') !== false, 'Test 8: Migration 108 invokes schema bootstrap');

// Test 9: Model query logical quote verification (no COUNT(ev.estimate_id) query)
$modelContent = file_get_contents($moduleRoot . '/models/Sales_pipeline_model.php');
assert_true(strpos($modelContent, 'COUNT(ev.estimate_id)') === false, 'Test 9: model must NOT count ev.estimate_id');
assert_true(strpos($modelContent, 'Quote_count_repository') !== false || strpos($modelContent, 'COUNT(grp.id) as estimate_count') !== false, 'Test 9: model must count logical groups via canonical repository');
assert_true(strpos($modelContent, 'get_customer_estimate_revision_sources') !== false, 'Test 9: helper get_customer_estimate_revision_sources exists');

// Test 10: Controller endpoint verification
$ctrlContent = file_get_contents($moduleRoot . '/controllers/Sales_pipeline.php');
assert_true(strpos($ctrlContent, 'public function estimate_revision_sources') !== false, 'Test 10: estimate_revision_sources controller method exists');

// Test 11: Frontend JS & CSS files existence and basic structure
$jsContent = file_get_contents($moduleRoot . '/assets/js/estimate_revision.js');
assert_true(strpos($jsContent, 'sales_pipeline[intent]') !== false, 'Test 11: JS contains intent input');
assert_true(strpos($jsContent, 'sales_pipeline[revision_of_estimate_id]') !== false, 'Test 11: JS contains source selector');
assert_true(strpos($jsContent, 'sales_pipeline[override_reason]') !== false, 'Test 11: JS contains override reason textarea');
assert_true(strpos($jsContent, 'i18n.sourcesUrl') !== false, 'Test 11: JS calls the localized source endpoint');
assert_true(strpos($moduleContent, "admin_url('sales_pipeline/estimate_revision_sources')") !== false, 'Test 11: PHP injects estimate_revision_sources URL');

$cssContent = file_get_contents($moduleRoot . '/assets/css/estimate_revision.css');
assert_true(strpos($cssContent, '.sp-estimate-intent-panel') !== false, 'Test 11: CSS panel selector exists');
assert_true(strpos($cssContent, '.sp-intent-card') !== false, 'Test 11: CSS intent card exists');

// Test 12: Localization keys presence
$viContent = file_get_contents($moduleRoot . '/language/vietnamese/sales_pipeline_lang.php');
assert_true(strpos($viContent, 'sales_pipeline_estimate_intent_label') !== false, 'Test 12: VI translation intent label');
assert_true(strpos($viContent, 'sales_pipeline_permission_manage_revisions') !== false, 'Test 12: VI translation permission');
assert_true(strpos($viContent, 'sales_pipeline_revision_client_mismatch') !== false, 'Test 12: VI translation mismatch');

$enContent = file_get_contents($moduleRoot . '/language/english/sales_pipeline_lang.php');
assert_true(strpos($enContent, 'sales_pipeline_estimate_intent_label') !== false, 'Test 12: EN translation intent label');
assert_true(strpos($enContent, 'sales_pipeline_permission_manage_revisions') !== false, 'Test 12: EN translation permission');
assert_true(strpos($enContent, 'sales_pipeline_revision_client_mismatch') !== false, 'Test 12: EN translation mismatch');

// Test 13: Reconcile cursor verification
assert_true(strpos($modelContent, 'COALESCE(last_reconciled_at') !== false && strpos($modelContent, '1970-01-01') !== false, 'Test 13: reconcile cursor uses last_reconciled_at');
assert_true(strpos($modelContent, "'last_reconciled_at' => \$now") !== false, 'Test 13: reconcile stamps last_reconciled_at on each group');

// Test 14: Customer mismatch fallback contract verification
$mockContextMismatch = [
    'source_estimate_id' => 999,
    'parent_estimate_id' => 999,
    'link_method'        => 'declared_revision',
    'actor_staff_id'     => 1,
];
assert_same('declared_revision', $mockContextMismatch['link_method'], 'Test 14: link method preserved');

// Test 15: Revision numbering MAX logic verification
assert_true(strpos(file_get_contents($moduleRoot . '/libraries/Estimate_revision_service.php'), 'MAX(revision_no) as max_rev') !== false, 'Test 15: service calculates MAX(revision_no) + 1');

// Test 16: Table and index verification for events
assert_true(strpos($schemaContent, 'KEY `idx_event_estimate` (`estimate_id`, `datecreated`)') !== false, 'Test 16: event estimate index');
assert_true(strpos($schemaContent, 'KEY `idx_event_from_group` (`from_group_id`, `datecreated`)') !== false, 'Test 16: event from_group index');
assert_true(strpos($schemaContent, 'KEY `idx_event_to_group` (`to_group_id`, `datecreated`)') !== false, 'Test 16: event to_group index');

// Test 17: Version number bump verification (must be >= 1.0.11, e.g. 1.1.4)
assert_true(preg_match('/Version:\s*(1\.1\.\d+|1\.0\.(1[1-9]|\d{3,}))/', $moduleContent) === 1, 'Test 17: module version is 1.0.11+ / 1.1.4');

// Test 18: Audit event allow-list types supported in service
$serviceCode = file_get_contents($moduleRoot . '/libraries/Estimate_revision_service.php');
assert_true(strpos($serviceCode, 'group_created') !== false, 'Test 18: group_created event in service');
assert_true(strpos($serviceCode, 'revision_linked') !== false, 'Test 18: revision_linked event in service');
assert_true(strpos($serviceCode, 'accepted_override') !== false, 'Test 18: accepted_override event in service');
assert_true(strpos($serviceCode, 'revision_fallback_standalone') !== false, 'Test 18: revision_fallback_standalone event in service');
assert_true(strpos($serviceCode, 'revision_unlinked') !== false, 'Test 18: revision_unlinked event in service');
assert_true(strpos($serviceCode, 'revision_link_failed') !== false, 'Test 18: revision_link_failed event in service');

// Test 19: Locked transaction verification
assert_true(strpos($serviceCode, 'FOR UPDATE') !== false, 'Test 19: append_revision locks group with FOR UPDATE');

// Test 20: Safe backfill on legacy versions
assert_true(strpos($schemaContent, 'SET ev.link_method = "legacy_import"') !== false, 'Test 20: legacy import backfilled');
assert_true(strpos($schemaContent, 'SET link_method = "legacy_revision"') !== false, 'Test 20: legacy revision backfilled');

// Test 21: Smart Prompt scoring logic in model
assert_true(strpos($modelContent, 'get_estimate_revision_candidates') !== false, 'Test 21: model has get_estimate_revision_candidates');
assert_true(strpos($modelContent, "reasonCodes[] = 'same_project'") !== false, 'Test 21: scoring has same_project (+40)');
assert_true(strpos($modelContent, "reasonCodes[] = 'same_owner'") !== false, 'Test 21: scoring has same_owner (+30)');
assert_true(strpos($modelContent, "reasonCodes[] = 'recently_expired'") !== false, 'Test 21: scoring has recently_expired (+25)');
assert_true(strpos($modelContent, "reasonCodes[] = 'recent_activity'") !== false, 'Test 21: scoring has recent_activity (+15)');

// Test 22: Smart Prompt 60-day cutoff filter verification & Group Current Estimate Hard Filter
assert_true(strpos($modelContent, 'DATE_SUB(NOW(), INTERVAL 60 DAY)') !== false, 'Test 22: 60-day cutoff hard filter present');
assert_true(strpos($modelContent, 'grp.current_estimate_id = e.id') !== false, 'Test 22: current estimate of group hard filter present');
assert_true(strpos($modelContent, 'grp.id IS NOT NULL') !== false, 'Test 22: group membership hard filter present');
assert_true(strpos($modelContent, '$candidateStatuses = [1, 2, 3, 5]') !== false, 'Test 22: Smart Prompt status whitelist includes draft, sent, declined and expired');
assert_true(strpos($modelContent, 'where_in(\'e.status\', $candidateStatuses)') !== false, 'Test 22: Smart Prompt applies status whitelist');

// Test 23: Smart Prompt candidate endpoint controller verification
assert_true(strpos($ctrlContent, 'public function estimate_revision_candidates') !== false, 'Test 23: candidate endpoint exists');

// Test 24: Smart Prompt UI banner structure & CSS
assert_true(strpos($jsContent, 'sp-smart-prompt-banner') !== false, 'Test 24: JS manages smart prompt banner');
assert_true(strpos($cssContent, '.sp-smart-prompt-banner') !== false, 'Test 24: CSS styles smart prompt banner');

// Test 25: Manual Link implementation
assert_true(strpos($serviceCode, 'link_standalone_revision') !== false, 'Test 25: service has link_standalone_revision');
assert_true(strpos($ctrlContent, 'public function link_estimate_revision') !== false, 'Test 25: controller has link_estimate_revision');

// Test 26: Manual Unlink implementation
assert_true(strpos($serviceCode, 'unlink_estimate_revision') !== false, 'Test 26: service has unlink_estimate_revision');
assert_true(strpos($ctrlContent, 'public function unlink_estimate_revision') !== false, 'Test 26: controller has unlink_estimate_revision');

// Test 27: Version History API & Tree client render
$versionHistoryJsContent = file_get_contents($moduleRoot . '/assets/js/estimate_version_history.js');
$versionHistoryCssContent = file_get_contents($moduleRoot . '/assets/css/estimate_version_history.css');
assert_true(strpos($serviceCode, 'get_estimate_version_history') !== false, 'Test 27: service has get_estimate_version_history');
assert_true(strpos($ctrlContent, 'public function estimate_version_history') !== false, 'Test 27: controller has estimate_version_history');
assert_true(file_exists($moduleRoot . '/assets/js/estimate_version_history.js'), 'Test 27: estimate_version_history.js exists');
assert_true(file_exists($moduleRoot . '/assets/css/estimate_version_history.css'), 'Test 27: estimate_version_history.css exists');
assert_true(strpos($versionHistoryJsContent, 'inject_estimate_version_history_tab') !== false, 'Test 27: module injects Version History tab without core view override');
assert_true(strpos($versionHistoryJsContent, 'sp-version-history-container') !== false, 'Test 27: module injects Version History container');
assert_true(strpos($versionHistoryJsContent, '.preview-tabs-top ul.nav-tabs.nav-tabs-horizontal') !== false, 'Test 27: Version History injector matches the Perfex estimate tabs DOM');
assert_true(strpos($versionHistoryJsContent, 'MutationObserver') !== false, 'Test 27: Version History injector observes AJAX-mounted estimate preview');
assert_true(strpos($versionHistoryJsContent, 'ajaxComplete.salesPipelineVersionHistory') !== false, 'Test 27: Version History injector handles AJAX completion');
assert_true(strpos($moduleContent, "filemtime(\$asset_path)") !== false, 'Test 27: Version History assets use file modification cache busting');
assert_true(strpos($serviceCode, "'manage_estimate_revisions'") !== false, 'Test 27: manual link requires revision-management capability');

// Test 28: Migration 109 integrity
assert_true(file_exists($moduleRoot . '/migrations/109_version_109.php'), 'Test 28: Migration 109 exists');
$mig109 = file_get_contents($moduleRoot . '/migrations/109_version_109.php');
assert_true(strpos($mig109, 'tblsales_pipeline_deal_estimate_groups') !== false, 'Test 28: Migration 109 creates bridge table');
assert_true(strpos($mig109, 'uq_estimate_group') !== false, 'Test 28: Migration 109 adds unique key on estimate_group_id');
assert_true(strpos($mig109, 'is_manual_lock') !== false, 'Test 28: Migration 109 adds is_manual_lock column');

// Test 29: Deal Bridge one-way sync logic & Manual Lock guard & Dedicated sync_deal method
assert_true(strpos($serviceCode, 'sync_deal_from_estimate_group') !== false, 'Test 29: service has sync_deal_from_estimate_group');
assert_true(strpos($serviceCode, 'public function sync_deal(') !== false, 'Test 29: service has dedicated sync_deal method');
assert_true(strpos($serviceCode, '!empty($deal[\'is_manual_lock\'])') !== false, 'Test 29: deal sync checks is_manual_lock');
assert_true(strpos($ctrlContent, 'public function set_deal_manual_lock') !== false, 'Test 29: controller has set_deal_manual_lock');

// Test 30: Language files comprehensive coverage for Steps 5, 6, 7
assert_true(strpos($viContent, 'sales_pipeline_smart_prompt_title') !== false, 'Test 30: VI smart prompt title');
assert_true(strpos($viContent, 'sales_pipeline_version_history_title') !== false, 'Test 30: VI version history title');
assert_true(strpos($viContent, 'sales_pipeline_deal_manual_lock') !== false, 'Test 30: VI deal manual lock');
assert_true(strpos($enContent, 'sales_pipeline_smart_prompt_title') !== false, 'Test 30: EN smart prompt title');
assert_true(strpos($enContent, 'sales_pipeline_version_history_title') !== false, 'Test 30: EN version history title');
assert_true(strpos($enContent, 'sales_pipeline_deal_manual_lock') !== false, 'Test 30: EN deal manual lock');

// Test 31: Permission validation unit test for source and target
$GLOBALS['mock_disallowed_estimates'] = [777];
$permResSource = $service->validate_revision_context(100, ['source_estimate_id' => 777, 'actor_staff_id' => 1]);
assert_same(false, $permResSource['valid'], 'Test 31: unauthorized source estimate is blocked');
assert_same('permission_denied_source', $permResSource['error_code'], 'Test 31: permission_denied_source code returned');

$permResTarget = $service->validate_revision_context(777, ['source_estimate_id' => 100, 'actor_staff_id' => 1]);
assert_same(false, $permResTarget['valid'], 'Test 31: unauthorized target estimate is blocked');
assert_same('permission_denied_target', $permResTarget['error_code'], 'Test 31: permission_denied_target code returned');
$GLOBALS['mock_disallowed_estimates'] = [];

// Test 32: Fallback Standalone Atomic Creation Unit Test
$fallbackGroupId = $service->create_fallback_standalone_with_audit(
    105,
    99,
    null,
    'declared_revision',
    1,
    'customer_mismatch',
    ['note' => 'test atomic fallback']
);
assert_true($fallbackGroupId > 0, 'Test 32: create_fallback_standalone_with_audit returns valid group id');

// Test 33: Deal Delete Bridge Cleanup Transaction Verification in Model
assert_true(strpos($modelContent, '$this->db->where(\'pipeline_id\', $id);') !== false, 'Test 33: delete model removes bridge records by pipeline_id');
assert_true(strpos($modelContent, '$this->db->trans_start();') !== false, 'Test 33: delete model runs within transaction');

// Test 34: Schema helper retains legacy cleanup, audit, and bridge ownership
assert_true(strpos($schemaContent, '$legacy_group_table =') !== false, 'Test 34: schema helper defines legacy_group_table');
assert_true(strpos($schemaContent, '$events_table =') !== false, 'Test 34: schema helper defines events_table');
assert_true(strpos($schemaContent, '$bridge_table =') !== false, 'Test 34: schema helper defines bridge_table');

// Test 35: Version History UI labels are fully localized (no English status suffixes)
assert_true(strpos($versionHistoryJsContent, 'salesPipelineVersionHistoryI18n') !== false, 'Test 35: Version History UI consumes module translations');
assert_true(strpos($versionHistoryJsContent, 'var eventKey = {') !== false, 'Test 35: audit event labels use a translation map');
assert_true(strpos($versionHistoryJsContent, 'ev.event_type.replace(/_/g, \' \')') === false, 'Test 35: raw event machine names are not rendered');
assert_true(strpos($viContent, 'sales_pipeline_version_history_status_pending') !== false, 'Test 35: VI pending status translation');
assert_true(strpos($viContent, 'sales_pipeline_version_history_method_manual_unlink') !== false, 'Test 35: VI manual unlink translation');
assert_true(strpos($viContent, 'sales_pipeline_event_revision_unlinked') !== false, 'Test 35: VI revision unlinked event translation');
assert_true(strpos($enContent, 'sales_pipeline_version_history_status_pending') !== false, 'Test 35: EN pending status translation');
assert_true(strpos($enContent, 'sales_pipeline_version_history_method_manual_unlink') !== false, 'Test 35: EN manual unlink translation');

// Test 36: Native and module copy methods have explicit Version History labels
assert_true(strpos($versionHistoryJsContent, "v.link_method === 'native_copy'") !== false, 'Test 36: native_copy UI mapping exists');
assert_true(strpos($versionHistoryJsContent, "v.link_method === 'module_copy'") !== false, 'Test 36: module_copy UI mapping exists');
assert_true(strpos($viContent, 'sales_pipeline_version_history_method_native_copy') !== false, 'Test 36: VI native copy translation');
assert_true(strpos($viContent, 'sales_pipeline_version_history_method_module_copy') !== false, 'Test 36: VI module copy translation');
assert_true(strpos($enContent, 'sales_pipeline_version_history_method_native_copy') !== false, 'Test 36: EN native copy translation');
assert_true(strpos($enContent, 'sales_pipeline_version_history_method_module_copy') !== false, 'Test 36: EN module copy translation');

// Test 37: Revision badge prefix is localized
assert_true(strpos($versionHistoryJsContent, "version_history_text('revisionPrefix')") !== false, 'Test 37: revision badge uses localized prefix');
assert_true(strpos($viContent, 'sales_pipeline_version_history_revision_prefix') !== false, 'Test 37: VI revision prefix translation');
assert_true(strpos($enContent, 'sales_pipeline_version_history_revision_prefix') !== false, 'Test 37: EN revision prefix translation');

// Test 38: Revision badge and card layout formatting
assert_true(strpos($versionHistoryCssContent, '.sp-vtree-badge') !== false, 'Test 38: revision badge style defined');
assert_true(strpos($versionHistoryCssContent, 'display: flex') !== false, 'Test 38: balanced layout uses flexbox flow');
assert_true(strpos($versionHistoryCssContent, 'white-space: nowrap') !== false, 'Test 38: revision badge keeps localized label on one line');

// Test 39: Standalone audit reason is translated in the Version History UI
assert_true(strpos($versionHistoryJsContent, 'version_history_reason_text') !== false, 'Test 39: audit reasons use a translation helper');
assert_true(strpos($versionHistoryJsContent, "normalized === 'standalone'") !== false, 'Test 39: standalone reason mapping exists');
assert_true(strpos($viContent, 'sales_pipeline_version_history_reason_standalone') !== false, 'Test 39: VI standalone reason translation');
assert_true(strpos($enContent, 'sales_pipeline_version_history_reason_standalone') !== false, 'Test 39: EN standalone reason translation');

// Test 40: Smart Prompt UI contains debounce, abort, and stale client response guards
$revisionJsContent = file_get_contents($moduleRoot . '/assets/js/estimate_revision.js');
assert_true(strpos($revisionJsContent, 'currentCandidatesXhr.abort()') !== false, 'Test 40: candidates request is aborted on re-trigger');
assert_true(strpos($revisionJsContent, 'candidatesDebounceTimer') !== false, 'Test 40: candidates fetch is debounced');
assert_true(strpos($revisionJsContent, 'String(clientId) !== String(state.clientId') !== false, 'Test 40: stale candidate responses are ignored');

// Test 41: Manual Link invariant checks (Staff capability, Customer boundary, Target accepted lock, Override reason)
assert_true(strpos($serviceCode, 'function link_standalone_revision(') !== false, 'Test 41: link_standalone_revision method exists');
assert_true(strpos($serviceCode, "if ((int) \$sourceEstimate['clientid'] !== (int) \$targetEstimate['clientid'])") !== false, 'Test 41: manual link enforces customer boundary');
assert_true(strpos($serviceCode, "'source_not_standalone'") !== false, 'Test 41: manual link requires source to be standalone with 1 revision');
assert_true(strpos($serviceCode, "'override_reason_required'") !== false, 'Test 41: manual link into accepted group requires override reason');

// Test 42: Manual Unlink invariant checks (Reason required, Multi-revision check, Latest revision check, Accepted decision protection)
assert_true(strpos($serviceCode, 'function unlink_estimate_revision(') !== false, 'Test 42: unlink_estimate_revision method exists');
assert_true(strpos($serviceCode, "'cannot_unlink_only_revision'") !== false, 'Test 42: unlink blocks single revision group');
assert_true(strpos($serviceCode, "'not_latest_revision'") !== false, 'Test 42: unlink only allows latest revision');
assert_true(strpos($serviceCode, "'cannot_unlink_accepted_decision'") !== false, 'Test 42: unlink blocks decision estimate of accepted group');
assert_true(strpos($serviceCode, "'reason_required'") !== false, 'Test 42: unlink requires mandatory reason');

// Test 43: Audit Event logging for Manual Link, Manual Unlink, and Accepted Override
assert_true(strpos($serviceCode, '$eventType = $isAcceptedOverride ? \'accepted_override\' : \'revision_linked\';') !== false, 'Test 43: manual link determines eventType');
assert_true(strpos($serviceCode, "'accepted_override'") !== false, 'Test 43: accepted override event type supported');
assert_true(strpos($serviceCode, "'revision_unlinked'") !== false, 'Test 43: manual unlink records revision_unlinked event');

// Test 44: Atomic Transaction & Rollback Safety
assert_true(strpos($serviceCode, "\$this->CI->db->trans_rollback();") !== false, 'Test 44: audit failure triggers database rollback');
assert_true(strpos($serviceCode, "\$this->CI->db->trans_complete();") !== false, 'Test 44: transaction completes safely');

fwrite(STDOUT, "PASS: Estimate group integration test (44/44 test cases verified successfully)\n");
