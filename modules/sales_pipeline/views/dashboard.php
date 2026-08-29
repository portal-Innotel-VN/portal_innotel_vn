<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$selected_period = $dashboard['selected_period'] ?? 'this_month';
$active_tab = $dashboard['selected_dashboard_tab'] ?? 'deals';
$period_query = $selected_period !== 'this_month' ? 'period=' . urlencode($selected_period) : '';
$deals_url = admin_url('sales_pipeline/dashboard') . ($period_query ? '?' . $period_query : '');
$estimates_url = admin_url('sales_pipeline/dashboard?dashboard_tab=estimates') . ($period_query ? '&' . $period_query : '');
$sp_range = $dashboard['selected_period_range'] ?? [];
$period_label_short = (!empty($sp_range['start']) && !empty($sp_range['end']))
    ? date('d/m', strtotime($sp_range['start'])) . ' – ' . date('d/m', strtotime($sp_range['end']))
    : '';
?>
<?php init_head(); ?>
<link rel="stylesheet" href="<?php echo html_escape(module_dir_url('sales_pipeline', 'assets/css/dashboard.css')); ?>?v=1.8.3">

<div id="wrapper">
    <div class="content">
        <main class="sp-dashboard-shell"
              data-sales-pipeline-dashboard
              data-staff-url="<?php echo html_escape(admin_url('sales_pipeline/dashboard_staff_pipeline')); ?>"
              data-dashboard-url="<?php echo html_escape(admin_url('sales_pipeline/ajax_dashboard_leaderboard')); ?>"
              data-loading-message="<?php echo html_escape(_l('sales_pipeline_dashboard_loading')); ?>"
              data-error-message="<?php echo html_escape(_l('sales_pipeline_dashboard_load_failed')); ?>"
              data-on-date-text="<?php echo html_escape(_l('sales_pipeline_dashboard_on_date')); ?>">
            <section class="sp-dashboard-overview" aria-labelledby="sp-dashboard-title">
                <div class="sp-dashboard-commandbar">
                    <div class="sp-dashboard-heading">
                        <div>
                            <div class="sp-dashboard-title-line">
                                <h1 id="sp-dashboard-title"><?php echo _l('sales_pipeline_dashboard_title'); ?></h1>
                            </div>
                        </div>
                    </div>

                    <div class="sp-dashboard-actions">
                        <button type="button"
                                class="sp-dashboard-secondary-action sp-dashboard-refresh-action js-sp-dashboard-refresh"
                                title="<?php echo html_escape(_l('sales_pipeline_dashboard_refresh')); ?>"
                                aria-label="<?php echo html_escape(_l('sales_pipeline_dashboard_refresh')); ?>"
                                data-toggle="tooltip">
                            <span class="sp-dashboard-refresh-action__text">
                                <?php echo _l('sales_pipeline_dashboard_data_updated_at'); ?> <strong data-last-updated-time><?php echo date('H:i') . ' ' . _l('sales_pipeline_dashboard_on_date') . ' ' . date('d/m'); ?></strong> - <i class="fa fa-refresh sp-dashboard-refresh-action__spin" aria-hidden="true"></i> <?php echo _l('sales_pipeline_dashboard_refresh_action'); ?>
                            </span>
                        </button>
                        <a href="<?php echo html_escape(admin_url('sales_pipeline')); ?>"
                           class="sp-dashboard-primary-action"
                           title="<?php echo html_escape(_l('sales_pipeline_view_pipeline')); ?>">
                            <i class="fa fa-th-list" aria-hidden="true"></i>
                            <span><?php echo _l('sales_pipeline_view_pipeline'); ?></span>
                        </a>
                    </div>
                </div>

                <div class="sp-dashboard-filterbar">
                    <div role="tablist"
                         aria-label="<?php echo html_escape(_l('sales_pipeline_dashboard_data_view')); ?>"
                         class="sp-dashboard-segments sp-dashboard-segments--inline">
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
                    <div class="sp-dashboard-filter-control">
                        <select id="sp-dashboard-time-filter" class="selectpicker" data-dropup-auto="false" data-width="fit">
                            <option value="this_week" <?php echo $selected_period === 'this_week' ? 'selected' : ''; ?>>
                                <?php echo _l('sales_pipeline_dashboard_filter_this_week'); ?>
                            </option>
                            <option value="this_month" <?php echo $selected_period === 'this_month' ? 'selected' : ''; ?>>
                                <?php echo _l('sales_pipeline_dashboard_filter_this_month'); ?>
                            </option>
                            <option value="this_quarter" <?php echo $selected_period === 'this_quarter' ? 'selected' : ''; ?>>
                                <?php echo _l('sales_pipeline_dashboard_filter_this_quarter'); ?>
                            </option>
                            <option value="this_year" <?php echo $selected_period === 'this_year' ? 'selected' : ''; ?>>
                                <?php echo _l('sales_pipeline_dashboard_filter_this_year'); ?>
                            </option>
                        </select>
                        <?php if ($period_label_short) { ?>
                        <span class="sp-filter-period-badge" data-period-badge aria-live="polite">
                            <i class="fa fa-calendar-o" aria-hidden="true"></i>
                            <span class="sp-filter-period-badge__text"><?php echo html_escape($period_label_short); ?></span>
                        </span>
                        <?php } ?>
                    </div>
                </div>
            </section>

            <div class="sp-dashboard-content-wrapper" data-dashboard-content-wrapper aria-live="polite">
                <?php $this->load->view('sales_pipeline/partials/_dashboard_content', ['dashboard' => $dashboard]); ?>
            </div>

            <div id="sp-dashboard-drawer"
                 class="sp-dashboard-drawer"
                 data-dashboard-drawer
                 aria-hidden="true">
                <button type="button"
                        class="sp-dashboard-drawer__backdrop js-sp-close-drawer"
                        aria-label="<?php echo html_escape(_l('sales_pipeline_dashboard_close')); ?>"></button>
                <aside class="sp-dashboard-drawer__panel"
                       role="dialog"
                       aria-modal="true"
                       aria-labelledby="sp-dashboard-drawer-title"
                       tabindex="-1">
                    <div class="sp-dashboard-drawer__toolbar">
                        <div>
                            <strong id="sp-dashboard-drawer-title"><?php echo _l('sales_pipeline_dashboard_staff_pipeline'); ?></strong>
                        </div>
                        <button type="button"
                                class="sp-dashboard-drawer__close js-sp-close-drawer"
                                title="<?php echo html_escape(_l('sales_pipeline_dashboard_close')); ?>"
                                aria-label="<?php echo html_escape(_l('sales_pipeline_dashboard_close')); ?>">
                            <i class="fa fa-times" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="sp-dashboard-drawer__content" data-dashboard-drawer-content aria-live="polite"></div>
                </aside>
            </div>
        </main>
    </div>
</div>

<?php init_tail(); ?>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="<?php echo html_escape(module_dir_url('sales_pipeline', 'assets/js/dashboard.js')); ?>?v=1.8.3"></script>
</body>
</html>
