<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$selected_period = $dashboard['selected_period'] ?? 'this_month';
$active_tab = $dashboard['selected_dashboard_tab'] ?? 'deals';
$sp_range = $dashboard['selected_period_range'] ?? [];
$period_anchor = $sp_range['anchor'] ?? date('Y-m-d');
$period_params = [];
if ($selected_period !== 'this_month') {
    $period_params['period'] = $selected_period;
}
if ($period_anchor !== date('Y-m-d')) {
    $period_params['period_anchor'] = $period_anchor;
}
$deals_url = admin_url('sales_pipeline/dashboard') . ($period_params ? '?' . http_build_query($period_params) : '');
$estimate_params = array_merge(['dashboard_tab' => 'estimates'], $period_params);
$estimates_url = admin_url('sales_pipeline/dashboard?' . http_build_query($estimate_params));
$period_label_short = (!empty($sp_range['start']) && !empty($sp_range['end']))
    ? date('d/m/Y', strtotime($sp_range['start'])) . ' – ' . date('d/m/Y', strtotime($sp_range['end']))
    : '';
?>
<?php init_head(); ?>
<link rel="stylesheet" href="<?php echo html_escape(module_dir_url('sales_pipeline', 'assets/css/dashboard.css')); ?>?v=1.8.5">

<div id="wrapper">
    <div class="content">
        <main class="sp-dashboard-shell"
              data-sales-pipeline-dashboard
              data-staff-url="<?php echo html_escape(admin_url('sales_pipeline/dashboard_staff_pipeline')); ?>"
              data-dashboard-url="<?php echo html_escape(admin_url('sales_pipeline/ajax_dashboard_leaderboard')); ?>"
              data-loading-message="<?php echo html_escape(_l('sales_pipeline_dashboard_loading')); ?>"
              data-loading-estimates-message="<?php echo html_escape(_l('sales_pipeline_dashboard_estimates_loading')); ?>"
              data-error-message="<?php echo html_escape(_l('sales_pipeline_dashboard_load_failed')); ?>"
              data-invalid-date-message="<?php echo html_escape(_l('sales_pipeline_dashboard_history_invalid_date')); ?>"
              data-on-date-text="<?php echo html_escape(_l('sales_pipeline_dashboard_on_date')); ?>"
              data-period-anchor="<?php echo html_escape($period_anchor); ?>">
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
                        <button type="button"
                                class="sp-filter-period-badge js-sp-history-picker-toggle"
                                data-period-badge
                                aria-expanded="false"
                                aria-controls="sp-dashboard-history-picker"
                                title="<?php echo html_escape(_l('sales_pipeline_dashboard_history_picker_open')); ?>">
                            <i class="fa fa-calendar-o" aria-hidden="true"></i>
                            <span class="sp-filter-period-badge__text" aria-live="polite"><?php echo html_escape($period_label_short); ?></span>
                        </button>
                        <div id="sp-dashboard-history-picker"
                             class="sp-history-picker"
                             data-history-picker
                             hidden>
                            <div class="sp-history-picker__header">
                                <div>
                                    <strong><?php echo _l('sales_pipeline_dashboard_history_picker_title'); ?></strong>
                                    <span><?php echo _l('sales_pipeline_dashboard_history_picker_help'); ?></span>
                                </div>
                                <button type="button"
                                        class="sp-history-picker__close js-sp-history-picker-close"
                                        aria-label="<?php echo html_escape(_l('sales_pipeline_dashboard_close')); ?>">
                                    <i class="fa fa-times" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="sp-history-picker__periods" role="group" aria-label="<?php echo html_escape(_l('sales_pipeline_dashboard_period_filter')); ?>">
                                <?php foreach (['this_week', 'this_month', 'this_quarter', 'this_year'] as $history_period) { ?>
                                <button type="button"
                                        class="sp-history-picker__period<?php echo $selected_period === $history_period ? ' is-active' : ''; ?>"
                                        data-history-period="<?php echo html_escape($history_period); ?>"
                                        aria-pressed="<?php echo $selected_period === $history_period ? 'true' : 'false'; ?>">
                                    <?php echo _l('sales_pipeline_dashboard_filter_' . $history_period); ?>
                                </button>
                                <?php } ?>
                            </div>
                            <label class="sp-history-picker__date-label" for="sp-dashboard-period-anchor-display">
                                <?php echo _l('sales_pipeline_dashboard_history_anchor'); ?>
                            </label>
                            <div class="form-group sp-history-picker__date-field">
                                <div class="input-group date">
                                    <input type="text"
                                           id="sp-dashboard-period-anchor-display"
                                           class="form-control datepicker sp-history-picker__date"
                                           value="<?php echo html_escape(_d($period_anchor)); ?>"
                                           data-date-end-date="<?php echo html_escape(_d(date('Y-m-d'))); ?>"
                                           autocomplete="off"
                                           required>
                                    <div class="input-group-addon sp-history-picker__calendar-addon">
                                        <i class="fa fa-calendar calendar-icon" aria-hidden="true"></i>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden"
                                   id="sp-dashboard-period-anchor"
                                   value="<?php echo html_escape($period_anchor); ?>">
                            <div class="sp-history-picker__actions">
                                <button type="button" class="sp-history-picker__current js-sp-history-picker-current">
                                    <?php echo _l('sales_pipeline_dashboard_history_current'); ?>
                                </button>
                                <button type="button" class="sp-history-picker__apply js-sp-history-picker-apply">
                                    <?php echo _l('sales_pipeline_dashboard_history_apply'); ?>
                                </button>
                            </div>
                        </div>
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
<script src="<?php echo html_escape(module_dir_url('sales_pipeline', 'assets/js/apexcharts.min.js')); ?>?v=7.1.0"></script>
<script src="<?php echo html_escape(module_dir_url('sales_pipeline', 'assets/js/dashboard.js')); ?>?v=1.8.7"></script>
</body>
</html>
