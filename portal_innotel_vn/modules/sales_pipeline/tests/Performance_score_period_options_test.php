<?php

define('BASEPATH', __DIR__);

$moduleRoot = dirname(__DIR__);
require_once $moduleRoot . '/includes/performance_score_defaults.php';
require_once $moduleRoot . '/libraries/Performance_score_calculator.php';

$defaults = sales_pipeline_performance_score_default_options();
$periods = ['this_week', 'this_month', 'this_quarter', 'this_year'];
$targetTypes = ['quote', 'revenue'];

foreach ($periods as $period) {
    foreach ($targetTypes as $targetType) {
        $optionName = 'performance_' . $targetType . '_target_' . $period;
        if (!isset($defaults[$optionName]) || !is_numeric($defaults[$optionName]) || (float) $defaults[$optionName] <= 0) {
            fwrite(STDERR, "FAIL: Missing or invalid {$optionName}\n");
            exit(1);
        }
    }

    $calculator = new Performance_score_calculator();
    $result = $calculator->calculate_leaderboard([], [
        'formula_version'   => 'performance_score_v1',
        'quote_target'      => $defaults['performance_quote_target_' . $period],
        'revenue_target'    => $defaults['performance_revenue_target_' . $period],
        'acceptance_target' => $defaults['performance_acceptance_target_percent'],
        'response_target'   => $defaults['performance_response_target_percent'],
        'component_cap'     => $defaults['performance_component_cap'],
        'min_closed_quotes' => $defaults['performance_min_closed_quotes'],
    ]);
    if ($result['status'] !== 'ready') {
        fwrite(STDERR, "FAIL: {$period} still resolves to {$result['status']}\n");
        exit(1);
    }
}

foreach (['sales_pipeline.php', 'install.php'] as $entryPoint) {
    $source = file_get_contents($moduleRoot . '/' . $entryPoint);
    if (strpos($source, 'sales_pipeline_seed_performance_score_options();') === false) {
        fwrite(STDERR, "FAIL: {$entryPoint} does not seed the shared period targets\n");
        exit(1);
    }
}

fwrite(STDOUT, "PASS: Performance Score period targets\n");
