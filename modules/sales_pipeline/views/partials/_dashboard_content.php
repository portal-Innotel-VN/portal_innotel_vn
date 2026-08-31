<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$active_tab = $dashboard['selected_dashboard_tab'] ?? 'deals';
$sp_range   = $dashboard['selected_period_range'] ?? [];
$sp_period_label = (!empty($sp_range['start']) && !empty($sp_range['end']))
    ? date('d/m/Y', strtotime($sp_range['start'])) . ' – ' . date('d/m/Y', strtotime($sp_range['end']))
    : '';
?>
<span data-sp-period-label="<?php echo html_escape($sp_period_label); ?>"
      data-sp-period-anchor="<?php echo html_escape($sp_range['anchor'] ?? date('Y-m-d')); ?>"
      data-sp-period-anchor-display="<?php echo html_escape(_d($sp_range['anchor'] ?? date('Y-m-d'))); ?>"
      data-sp-period-key="<?php echo html_escape($dashboard['selected_period'] ?? 'this_month'); ?>"
      hidden aria-hidden="true"></span>

<div id="sp-dashboard-panel-deals"
     class="sp-dashboard-tab-panel<?php echo $active_tab === 'deals' ? ' is-active' : ''; ?>"
     role="tabpanel"
     aria-labelledby="sp-dashboard-tab-deals"
     <?php echo $active_tab === 'deals' ? '' : 'hidden'; ?>
     data-dashboard-panel="deals">
    <?php $this->load->view('sales_pipeline/partials/_revenue_kpi_card', ['dashboard' => $dashboard]); ?>

    <div class="sp-dashboard-main-grid">
        <?php $this->load->view('sales_pipeline/partials/_leaderboard', ['dashboard' => $dashboard]); ?>
    </div>
</div>

<div id="sp-dashboard-panel-estimates"
     class="sp-dashboard-tab-panel<?php echo $active_tab === 'estimates' ? ' is-active' : ''; ?>"
     role="tabpanel"
     aria-labelledby="sp-dashboard-tab-estimates"
     <?php echo $active_tab === 'estimates' ? '' : 'hidden'; ?>
     data-dashboard-panel="estimates">
    <?php $this->load->view('sales_pipeline/partials/_estimate_revenue_kpi_card', ['dashboard' => $dashboard]); ?>
    <?php $this->load->view('sales_pipeline/partials/_estimates_leaderboard', ['dashboard' => $dashboard]); ?>
</div>
