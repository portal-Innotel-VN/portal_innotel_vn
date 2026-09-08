<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

function mvc_v2_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

require_once dirname(__DIR__) . '/libraries/Performance_score_service.php';

$service = new Performance_score_service();
$targets = [
    'quote_target'      => 30,
    'revenue_target'    => 100000000,
    'acceptance_target' => 50,
    'response_target'   => 90,
    'min_closed_quotes' => 3,
];
$cohort = [
    [
        'staff_id'           => 10,
        'staff_name'         => 'Beta Staff',
        'estimate_count'     => 30,
        'accepted_revenue'   => 100000000,
        'accepted_count'     => 1,
        'declined_count'     => 0,
        'eligible_reminders' => 1,
        'on_time_reminders'  => 1,
    ],
    [
        'staff_id'           => 11,
        'staff_name'         => 'Alpha Staff',
        'estimate_count'     => 0,
        'accepted_revenue'   => 0,
        'accepted_count'     => 0,
        'declined_count'     => 0,
        'eligible_reminders' => 0,
        'on_time_reminders'  => 0,
    ],
];

$v2 = $service->calculate_runtime(
    ['type' => 'this_month', 'start' => '2026-09-01', 'end' => '2026-09-30'],
    $cohort,
    $targets,
    '2026-09-07 12:00:00'
);
mvc_v2_assert($v2['formula_version'] === 'performance_score_v2', 'September this_month must execute V2 through Dispatcher');
mvc_v2_assert(array_keys($v2['cohort']) === [10, 11], 'V2 ranking must preserve staff IDs from indexed Model metrics');
$v2Top = $v2['cohort'][10];
mvc_v2_assert($v2Top['component_status']['reminder_response'] === 'active', 'V2 reminder component must be active when eligible');
mvc_v2_assert(in_array('insufficient_closed_quotes', $v2Top['data_quality_flags'], true), 'V2 must apply closed-count confidence flag');
mvc_v2_assert(in_array('insufficient_reminder_sample', $v2Top['data_quality_flags'], true), 'V2 must apply reminder confidence flag');
mvc_v2_assert(isset($v2Top['quote_score'], $v2Top['accepted_revenue_score'], $v2Top['acceptance_score'], $v2Top['response_score']), 'V2 must adapt to the Dashboard component contract');
mvc_v2_assert($v2Top['rank'] === 1 && $v2Top['total_ranked_staff'] === 2, 'V2 must rank the full cohort');

$v1 = $service->calculate_runtime(
    ['type' => 'this_month', 'start' => '2026-08-01', 'end' => '2026-08-31'],
    [$cohort[0]],
    $targets,
    '2026-08-31 12:00:00'
);
mvc_v2_assert($v1['formula_version'] === 'performance_score_v1', 'August this_month must execute immutable V1');
mvc_v2_assert($v1['cohort'][10]['component_status']['reminder_response'] === 'inactive', 'V1 must ignore Reminder response');
mvc_v2_assert($v1['cohort'][10]['response_score'] === null, 'V1 Dashboard adapter must not expose a response score');

$moduleRoot = dirname(__DIR__);
$controller = file_get_contents($moduleRoot . '/controllers/Sales_pipeline.php');
$model = file_get_contents($moduleRoot . '/models/Sales_pipeline_model.php');
$standaloneDoc = file_get_contents(dirname($moduleRoot, 2) . '/docs/ai/SALES_PIPELINE_STANDALONE_COMPONENTS.md');

mvc_v2_assert(strpos($controller, '$this->db') === false, 'Controller must not perform direct DB mutations');
mvc_v2_assert(strpos($controller, 'set_deal_manual_lock($deal_id') !== false, 'Controller must delegate manual lock to Model');
mvc_v2_assert(strpos($controller, 'save_pipeline_settings($normalized)') !== false, 'Controller must delegate option transaction to Model');
mvc_v2_assert(strpos($controller, 'set_finance_lock(') !== false, 'Controller must delegate Finance lock to Model');
mvc_v2_assert(strpos($model, "'deal'           => db_prefix() . 'sales_pipeline'") !== false, 'Finance lock must target the runtime Deal table');
mvc_v2_assert(strpos($model, "'finance_lock_reason'") !== false, 'Finance lock must persist its reason');
mvc_v2_assert(strpos($model, 'new Performance_score_service($this)') !== false, 'Model leaderboard must execute the versioned score service');
mvc_v2_assert(strpos($model, '$this->performance_score_calculator->calculate_leaderboard($cohort') === false, 'Model must not bypass Dispatcher with the legacy calculator');

foreach (['get_reminder_context', 'get_estimate_copy_source_id', 'create_estimate_group', 'append_estimate_revision', 'insert_estimate_version'] as $method) {
    mvc_v2_assert(
        preg_match('/\/\*\*(?:(?!\*\/).)*@deprecated(?:(?!\*\/).)*\*\/\s*(?:public|private) function ' . preg_quote($method, '/') . '\b/s', $model) === 1,
        "Legacy method {$method} must be marked @deprecated"
    );
}
mvc_v2_assert(strpos($standaloneDoc, 'Cost Price Alert subsystem') !== false, 'Cost Price Alert standalone ownership must be documented');
mvc_v2_assert(strpos($standaloneDoc, 'Currency Data Sanitizer') !== false, 'Currency sanitizer standalone ownership must be documented');

echo "PASS: MVC delegation, Performance V2 runtime, and standalone ownership contracts\n";
