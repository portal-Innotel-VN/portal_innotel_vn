<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$summary = $dashboard['summary'] ?? [];
$staff_metrics = $dashboard['staff'] ?? [];
$periods = $dashboard['periods'] ?? [];

$resolve_status = static function ($progress) {
    if ($progress >= 100) {
        return 'success';
    }
    if ($progress >= 60) {
        return 'warning';
    }
    return 'danger';
};

$summary_cards = [
    [
        'label'    => _l('sales_pipeline_dashboard_estimates_today'),
        'value'    => number_format((int) ($summary['estimates_today'] ?? 0), 0, ',', '.'),
        'progress' => (float) ($summary['estimates_today_progress'] ?? 0),
        'period'   => !empty($periods['today']) ? date('d/m/Y', strtotime($periods['today'])) : date('d/m/Y'),
    ],
    [
        'label'    => _l('sales_pipeline_dashboard_revenue_week'),
        'value'    => sales_pipeline_compact_money($summary['revenue_week'] ?? 0),
        'progress' => (float) ($summary['revenue_week_progress'] ?? 0),
        'period'   => !empty($periods['week_start']) && !empty($periods['week_end'])
            ? date('d/m', strtotime($periods['week_start'])) . ' - ' . date('d/m', strtotime($periods['week_end']))
            : '',
    ],
    [
        'label'    => _l('sales_pipeline_dashboard_estimates_month'),
        'value'    => number_format((int) ($summary['estimates_month'] ?? 0), 0, ',', '.'),
        'progress' => (float) ($summary['estimates_month_progress'] ?? 0),
        'period'   => !empty($periods['month_start']) ? date('m/Y', strtotime($periods['month_start'])) : date('m/Y'),
    ],
];
?>
<?php init_head(); ?>
<link rel="stylesheet" href="<?php echo html_escape(module_dir_url('sales_pipeline', 'assets/css/dashboard.css')); ?>?v=1.0.3">

<div id="wrapper">
    <div class="content">
        <main class="sp-dashboard-shell"
              data-sales-pipeline-dashboard
              data-staff-url="<?php echo html_escape(admin_url('sales_pipeline/dashboard_staff_pipeline')); ?>"
              data-leaderboard-url="<?php echo html_escape(admin_url('sales_pipeline/ajax_dashboard_leaderboard')); ?>"
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

                <div class="sp-dashboard-summary-grid">
                    <?php foreach ($summary_cards as $card) { ?>
                        <?php $card_status = $resolve_status($card['progress']); ?>
                        <article class="sp-summary-card sp-summary-card--<?php echo $card_status; ?>">
                            <div class="sp-summary-card__topline">
                                <h2><?php echo $card['label']; ?></h2>
                                <span class="sp-summary-card__period"><?php echo html_escape($card['period']); ?></span>
                            </div>
                            <div class="sp-summary-card__value"><?php echo html_escape($card['value']); ?></div>
                            <div class="sp-progress" role="progressbar"
                                 aria-valuemin="0"
                                 aria-valuemax="100"
                                 aria-valuenow="<?php echo html_escape((string) $card['progress']); ?>">
                                <span class="sp-progress__bar sp-progress__bar--<?php echo $card_status; ?>"
                                      style="width: <?php echo number_format($card['progress'], 1, '.', ''); ?>%"></span>
                            </div>
                        </article>
                    <?php } ?>
                </div>
            </section>

            <div class="sp-dashboard-main-grid">
                <div class="sp-dashboard-leaderboard-wrapper" data-dashboard-leaderboard-wrapper>
                    <?php $this->load->view('sales_pipeline/partials/_leaderboard', ['dashboard' => $dashboard]); ?>
                </div>
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
<script src="<?php echo html_escape(module_dir_url('sales_pipeline', 'assets/js/dashboard.js')); ?>?v=1.0.3"></script>
</body>
</html>
