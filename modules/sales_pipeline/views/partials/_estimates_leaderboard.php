<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$estimate_dashboard = $dashboard['estimates'] ?? [];
$summary = $estimate_dashboard['summary'] ?? [];
$leaderboard = $estimate_dashboard['leaderboard'] ?? [];
$performance_status = $estimate_dashboard['performance_status'] ?? 'not_configured';
$viewer = $dashboard['viewer'] ?? [];
$can_view_all = !empty($viewer['can_view_all']);
$selected_range = $dashboard['selected_period_range'] ?? [];
$missing_revenue_rate_count = (int) ($summary['missing_revenue_rate_count'] ?? 0);
$base_currency = get_base_currency();
?>

<section class="sp-quote-dashboard" aria-labelledby="sp-quote-dashboard-title">
    <?php if ($missing_revenue_rate_count > 0) { ?>
        <div class="sp-quote-data-note" role="status">
            <i class="fa fa-info-circle" aria-hidden="true"></i>
            <span><?php echo _l('sales_pipeline_quote_revenue_missing_rates_notice', [$missing_revenue_rate_count]); ?></span>
        </div>
    <?php } ?>

    <section class="sp-dashboard-leaderboard sp-quote-leaderboard" aria-labelledby="sp-quote-dashboard-title">
        <header class="sp-leaderboard-header">
            <div class="sp-leaderboard-header__left">
                <h2 id="sp-quote-dashboard-title">
                    <i class="fa fa-trophy" aria-hidden="true"></i>
                    <?php echo _l('sales_pipeline_performance_leaderboard'); ?>
                </h2>
            </div>
        </header>

        <?php if ($performance_status === 'not_configured') { ?>
            <div class="sp-performance-state sp-performance-state--warning" role="status">
                <i class="fa fa-info-circle" aria-hidden="true"></i>
                <div>
                    <strong><?php echo _l('sales_pipeline_performance_not_configured_title'); ?></strong>
                    <span><?php echo _l('sales_pipeline_performance_not_configured_description'); ?></span>
                </div>
            </div>
        <?php } ?>

        <?php if ($performance_status === 'ready' && $leaderboard) { ?>
            <div class="sp-dashboard-table-wrap">
                <table class="sp-dashboard-table sp-quote-table sp-performance-table">
                    <thead>
                        <tr>
                            <th scope="col" class="sp-column-rank"><?php echo _l('sales_pipeline_dashboard_rank'); ?></th>
                            <th scope="col" class="sp-column-staff"><?php echo _l('sales_pipeline_dashboard_staff'); ?></th>
                            <th scope="col"><?php echo _l('sales_pipeline_quote_estimate_count'); ?></th>
                            <th scope="col"><?php echo _l('sales_pipeline_quote_revenue'); ?></th>
                            <th scope="col"><?php echo _l('sales_pipeline_quote_acceptance_rate_percent'); ?></th>
                            <th scope="col"><?php echo $can_view_all
                                ? _l('sales_pipeline_performance_score')
                                : _l('sales_pipeline_performance_your_score'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaderboard as $metric) { ?>
                            <?php
                            $rank = isset($metric['rank']) ? (int) $metric['rank'] : null;
                            $staff_rate = $metric['acceptance_rate'];
                            $rate_status = $staff_rate === null
                                ? 'muted'
                                : ($staff_rate >= 50 ? 'success' : ($staff_rate >= 20 ? 'warning' : 'danger'));
                            $revenue = (float) $metric['accepted_revenue'];
                            $revenue_full = app_format_money($revenue, $base_currency);
                            $is_current = !empty($metric['is_current_staff']);
                            $can_open_details = !empty($metric['can_open_details']);
                            ?>
                            <tr class="<?php echo $is_current ? 'sp-leaderboard-row--current' : ''; ?>">
                                <td class="sp-column-rank" data-label="<?php echo html_escape(_l('sales_pipeline_dashboard_rank')); ?>">
                                    <span class="sp-rank sp-rank--<?php echo $rank && $rank <= 3 ? $rank : 'standard'; ?>">
                                        <?php echo $rank === null ? '—' : $rank; ?>
                                    </span>
                                </td>
                                <td class="sp-column-staff" data-label="<?php echo html_escape(_l('sales_pipeline_dashboard_staff')); ?>">
                                    <?php if ($can_open_details) { ?>
                                        <button type="button"
                                                class="sp-staff-trigger js-sp-open-staff"
                                                data-staff-id="<?php echo (int) $metric['staff_id']; ?>"
                                                aria-haspopup="dialog"
                                                aria-controls="sp-dashboard-drawer"
                                                title="<?php echo html_escape(_l('sales_pipeline_performance_view_details')); ?>">
                                            <?php echo staff_profile_image(
                                                $metric['staff_id'],
                                                ['sp-staff-avatar'],
                                                'small',
                                                ['alt' => $metric['staff_name']]
                                            ); ?>
                                            <span class="sp-staff-copy">
                                                <strong>
                                                    <?php if ($is_current) { ?>
                                                        <span class="sp-performance-you"><?php echo _l('sales_pipeline_performance_you'); ?></span>
                                                    <?php } ?>
                                                    <?php echo html_escape($metric['staff_name']); ?>
                                                </strong>
                                                <?php if ($can_view_all && !empty($metric['email'])) { ?>
                                                    <small><?php echo html_escape($metric['email']); ?></small>
                                                <?php } ?>
                                            </span>
                                            <i class="fa fa-angle-right" aria-hidden="true"></i>
                                        </button>
                                    <?php } else { ?>
                                        <div class="sp-staff-trigger sp-staff-trigger--static">
                                            <?php echo staff_profile_image(
                                                $metric['staff_id'],
                                                ['sp-staff-avatar'],
                                                'small',
                                                ['alt' => $metric['staff_name']]
                                            ); ?>
                                            <span class="sp-staff-copy">
                                                <strong><?php echo html_escape($metric['staff_name']); ?></strong>
                                            </span>
                                        </div>
                                    <?php } ?>
                                </td>
                                <td data-label="<?php echo html_escape(_l('sales_pipeline_quote_estimate_count')); ?>">
                                    <strong class="sp-quote-estimate-total">
                                        <?php echo number_format((int) $metric['estimate_count'], 0, ',', '.'); ?>
                                    </strong>
                                </td>
                                <td data-label="<?php echo html_escape(_l('sales_pipeline_quote_revenue')); ?>"
                                    title="<?php echo html_escape($revenue_full); ?>">
                                    <strong class="sp-quote-revenue">
                                        <?php echo html_escape(sales_pipeline_compact_money($revenue)); ?>
                                    </strong>
                                </td>
                                <td data-label="<?php echo html_escape(_l('sales_pipeline_quote_acceptance_rate_percent')); ?>">
                                    <span class="sp-quote-rate sp-quote-rate--<?php echo $rate_status; ?>">
                                        <?php echo $staff_rate === null ? '—' : number_format((float) $staff_rate, 1, ',', '.') . '%'; ?>
                                    </span>
                                    <?php if ($can_view_all) { ?>
                                        <small class="sp-quote-rate-detail">
                                            <?php echo _l('sales_pipeline_quote_rate_fraction', [
                                                (int) $metric['accepted_count'],
                                                (int) $metric['closed_count'],
                                            ]); ?>
                                        </small>
                                    <?php } ?>
                                </td>
                                <td data-label="<?php echo html_escape($can_view_all
                                    ? _l('sales_pipeline_performance_score')
                                    : _l('sales_pipeline_performance_your_score')); ?>">
                                    <?php
                                    $tier = function_exists('sales_pipeline_resolve_performance_tier')
                                        ? sales_pipeline_resolve_performance_tier($metric['performance_score'])
                                        : ['label' => '', 'badge_class' => '', 'score_class' => ''];
                                    ?>
                                    <div class="sp-performance-score">
                                        <strong class="<?php echo html_escape($tier['score_class']); ?>">
                                            <?php echo number_format((float) $metric['performance_score'], 1, ',', '.'); ?>
                                        </strong>
                                        <span><?php echo _l('sales_pipeline_performance_points'); ?></span>
                                    </div>
                                    <div class="sp-tier-badge-container">
                                        <?php if (!empty($tier['label'])) { ?>
                                            <span class="sp-tier-badge <?php echo html_escape($tier['badge_class']); ?>">
                                                <?php echo html_escape($tier['label']); ?>
                                            </span>
                                        <?php } ?>
                                        <?php if (!empty($metric['is_provisional'])) { ?>
                                            <span class="sp-performance-badge sp-performance-badge--provisional" role="status">
                                                <?php echo _l('sales_pipeline_performance_provisional'); ?>
                                            </span>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } elseif ($performance_status === 'ready') { ?>
            <div class="sp-dashboard-empty sp-quote-empty" role="status">
                <i class="fa fa-file-text-o" aria-hidden="true"></i>
                <strong><?php echo $can_view_all
                    ? _l('sales_pipeline_performance_empty_admin')
                    : _l('sales_pipeline_performance_empty_staff'); ?></strong>
                <?php if (has_permission('estimates', '', 'create')) { ?>
                    <a class="sp-quote-empty__action" href="<?php echo html_escape(admin_url('estimates/estimate')); ?>">
                        <i class="fa fa-plus" aria-hidden="true"></i>
                        <?php echo _l('sales_pipeline_quote_create_action'); ?>
                    </a>
                <?php } ?>
            </div>
        <?php } ?>
    </section>
</section>
