<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$active_tab = $dashboard['selected_dashboard_tab'] ?? 'deals';
$selected_period = $dashboard['selected_period'] ?? 'this_month';
$period_query = $selected_period !== 'this_month' ? 'period=' . urlencode($selected_period) : '';

$deals_url = admin_url('sales_pipeline/dashboard') . ($period_query ? '?' . $period_query : '');
$estimates_url = admin_url('sales_pipeline/dashboard?dashboard_tab=estimates') . ($period_query ? '&' . $period_query : '');
?>

<div class="sp-dashboard-segments"
     role="tablist"
     aria-label="<?php echo html_escape(_l('sales_pipeline_dashboard_data_view')); ?>">
    <a href="<?php echo html_escape($deals_url); ?>"
       id="sp-dashboard-tab-deals"
       class="sp-dashboard-segment<?php echo $active_tab === 'deals' ? ' is-active' : ''; ?>"
       role="tab"
       aria-selected="<?php echo $active_tab === 'deals' ? 'true' : 'false'; ?>"
       aria-controls="sp-dashboard-panel-deals"
       tabindex="<?php echo $active_tab === 'deals' ? '0' : '-1'; ?>"
       data-dashboard-tab="deals">
        <i class="fa fa-briefcase" aria-hidden="true"></i>
        <span><?php echo _l('sales_pipeline_dashboard_tab_deals'); ?></span>
    </a>
    <a href="<?php echo html_escape($estimates_url); ?>"
       id="sp-dashboard-tab-estimates"
       class="sp-dashboard-segment<?php echo $active_tab === 'estimates' ? ' is-active' : ''; ?>"
       role="tab"
       aria-selected="<?php echo $active_tab === 'estimates' ? 'true' : 'false'; ?>"
       aria-controls="sp-dashboard-panel-estimates"
       tabindex="<?php echo $active_tab === 'estimates' ? '0' : '-1'; ?>"
       data-dashboard-tab="estimates">
        <i class="fa fa-file-text-o" aria-hidden="true"></i>
        <span><?php echo _l('sales_pipeline_dashboard_tab_estimates'); ?></span>
    </a>
</div>

