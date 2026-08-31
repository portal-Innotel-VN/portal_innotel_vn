<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Default targets for every Dashboard period supported by performance_score_v1.
 *
 * Keep this list as the single source used by both fresh installation and the
 * runtime compatibility bootstrap for already-active installations.
 *
 * @return array<string,string>
 */
function sales_pipeline_performance_score_default_options()
{
    return [
        'performance_quote_target_this_week'       => '5',
        'performance_revenue_target_this_week'     => '250000000',
        'performance_quote_target_this_month'      => '20',
        'performance_revenue_target_this_month'    => '1000000000',
        'performance_quote_target_this_quarter'    => '60',
        'performance_revenue_target_this_quarter'  => '3000000000',
        'performance_quote_target_this_year'       => '240',
        'performance_revenue_target_this_year'     => '12000000000',
        'performance_acceptance_target_percent'    => '50',
        'performance_response_target_percent'      => '90',
        'performance_component_cap'                => '120',
        'performance_min_closed_quotes'            => '3',
    ];
}

/**
 * Add only missing options; existing administrator values remain untouched.
 *
 * @return void
 */
function sales_pipeline_seed_performance_score_options()
{
    foreach (sales_pipeline_performance_score_default_options() as $name => $value) {
        add_option($name, $value);
    }
}
