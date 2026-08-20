<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$selected_period = $dashboard['selected_period'] ?? 'this_month';
?>
<?php init_head(); ?>
<link rel="stylesheet" href="<?php echo html_escape(module_dir_url('sales_pipeline', 'assets/css/dashboard.css')); ?>?v=1.1.0">

<div id="wrapper">
    <div class="content">
        <main class="sp-dashboard-shell"
              data-sales-pipeline-dashboard
              data-staff-url="<?php echo html_escape(admin_url('sales_pipeline/dashboard_staff_pipeline')); ?>"
              data-dashboard-url="<?php echo html_escape(admin_url('sales_pipeline/ajax_dashboard_leaderboard')); ?>"
              data-loading-message="<?php echo html_escape(_l('sales_pipeline_dashboard_loading')); ?>"
              data-error-message="<?php echo html_escape(_l('sales_pipeline_dashboard_load_failed')); ?>">
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
                                class="sp-dashboard-secondary-action js-sp-dashboard-refresh"
                                title="<?php echo html_escape(_l('sales_pipeline_dashboard_refresh')); ?>"
                                aria-label="<?php echo html_escape(_l('sales_pipeline_dashboard_refresh')); ?>"
                                data-toggle="tooltip">
                            <i class="fa fa-refresh" aria-hidden="true"></i>
                            <span><?php echo _l('sales_pipeline_dashboard_refresh'); ?></span>
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
<script src="<?php echo html_escape(module_dir_url('sales_pipeline', 'assets/js/dashboard.js')); ?>?v=1.0.9"></script>
</body>
</html>
