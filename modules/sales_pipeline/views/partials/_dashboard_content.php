<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $active_tab = $dashboard['selected_dashboard_tab'] ?? 'deals'; ?>

<?php $this->load->view('sales_pipeline/partials/_segmented_control', ['dashboard' => $dashboard]); ?>

<div id="sp-dashboard-panel-deals"
     class="sp-dashboard-tab-panel<?php echo $active_tab === 'deals' ? ' is-active' : ''; ?>"
     role="tabpanel"
     aria-labelledby="sp-dashboard-tab-deals"
     <?php echo $active_tab === 'deals' ? '' : 'hidden'; ?>
     data-dashboard-panel="deals">
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
    <?php $this->load->view('sales_pipeline/partials/_estimates_leaderboard', ['dashboard' => $dashboard]); ?>
</div>
