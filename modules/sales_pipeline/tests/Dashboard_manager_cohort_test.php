<?php

// Execute the model's actual cohort-building/filtering blocks with deterministic
// metrics. DB aggregation is outside this focused regression test.
$source = file_get_contents(dirname(__DIR__) . '/models/Sales_pipeline_model.php');
function has_permission($feature, $staffId, $capability)
{
    return $feature === 'sales_pipeline' && $capability === 'view' && (int) $staffId === 2;
}
function cohort_check($ok, $message)
{
    if (!$ok) { throw new RuntimeException($message); }
}
function cohort_slice($source, $start, $end)
{
    $a = strpos($source, $start);
    $b = strpos($source, $end, $a);
    if ($a === false || $b === false) { throw new RuntimeException('Model test seam changed'); }
    return substr($source, $a, $b - $a);
}
$estimateSource = cohort_slice($source, '    public function get_estimate_performance_ranking(', '    public function project_estimate_performance_ranking(');
$dealSource = cohort_slice($source, '    public function get_staff_kpi_metrics(', '    private function get_dashboard_staff_members(');
$estimateInit = cohort_slice($estimateSource, '        foreach ($staff_members as $staff)', '        if (!$staff_ids)');
$dealInit = cohort_slice($dealSource, '        foreach ($staff_members as $staff)', '        $estimate_table =');
$estimateFilter = cohort_slice($estimateSource, '        $cohort = [];', '        $score_service =');
$dealGuard = cohort_slice($dealSource, '            $has_sales_activity =', "            \$metric['estimates_today_progress']");
$staff_members = [];
foreach ([1 => 1, 2 => 0, 3 => 0] as $id => $admin) {
    $staff_members[] = ['staffid' => $id, 'admin' => $admin, 'firstname' => 'Staff', 'lastname' => (string) $id, 'email' => 'staff' . $id . '@example.test'];
}
$reminder_sla_available = true;
$metrics = $staff_ids = [];
eval($estimateInit);
$baseEstimate = $metrics;
eval($estimateFilter);
cohort_check(array_column($cohort, 'staff_id') === [3], 'Estimate cohort must exclude idle global manager and admin, retain idle salesperson');

// 1. Sales activities in period retain manager (including declined_count alone)
foreach (['estimate_count', 'accepted_count', 'declined_count'] as $activity) {
    $metrics = $baseEstimate;
    $metrics[2][$activity] = 1;
    eval($estimateFilter);
    cohort_check(array_column($cohort, 'staff_id') === [2, 3], 'Active manager must remain for ' . $activity);
}

// 2. Pure reminder SLA must NOT retain manager in estimate cohort
$metrics = $baseEstimate;
$metrics[2]['eligible_reminders'] = 1;
eval($estimateFilter);
cohort_check(array_column($cohort, 'staff_id') === [3], 'Pure reminder SLA must NOT retain manager in estimate cohort');

$metrics = array_intersect_key($baseEstimate, [1 => true, 2 => true]);
eval($estimateFilter);
cohort_check($cohort === [], 'An all-idle-manager cohort must stay empty; no fallback reinsertion');

$metrics = $staff_ids = [];
eval($dealInit);
$baseDeal = $metrics;
$filterDeals = function ($metrics, $staff_id = null) use ($dealGuard) {
    $result = [];
    eval('foreach ($metrics as $metric) {' . $dealGuard . '$result[] = $metric; }');
    return $result;
};
$rows = $filterDeals($metrics);
cohort_check(array_column($rows, 'staff_id') === [3], 'Deal cohort must exclude idle global manager and admin');
cohort_check(count($rows) * 1000000000 === 1000000000, 'Company target must count only retained staff');

// 3. Sales activities in selected period retain manager (including period_estimates alone)
foreach (['period_deals', 'period_estimates', 'period_revenue'] as $activity) {
    $metrics = $baseDeal;
    $metrics[2][$activity] = 1;
    cohort_check(array_column($filterDeals($metrics), 'staff_id') === [2, 3], 'Active deal manager must remain for ' . $activity);
}

// 4. Current month quotes or historical pipeline deals outside selected period must NOT retain manager
foreach (['count_estimates_month', 'total_pipeline_deals'] as $activity) {
    $metrics = $baseDeal;
    $metrics[2][$activity] = 1;
    cohort_check(array_column($filterDeals($metrics), 'staff_id') === [3], 'Out-of-period quotes or historical deals must NOT retain manager');
}

cohort_check(count($filterDeals([2 => $baseDeal[2]], 2)) === 1, 'Explicit staff drilldown remains available');
cohort_check($filterDeals(array_intersect_key($baseDeal, [1 => true, 2 => true])) === [], 'All idle managers must yield zero company staff');
echo "PASS: Dashboard manager cohorts, activity retention, empty cohort, company target and explicit staff scope\n";

