<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$staff_metrics = $dashboard['staff'] ?? [];
$selected_range = $dashboard['selected_period_range'] ?? [];
$period_label = !empty($selected_range['start']) && !empty($selected_range['end'])
    ? date('d/m/Y', strtotime($selected_range['start'])) . ' - ' . date('d/m/Y', strtotime($selected_range['end']))
    : '';
?>
<section class="sp-dashboard-leaderboard" aria-labelledby="sp-leaderboard-title">
    <header class="sp-leaderboard-header">
        <div class="sp-leaderboard-header__left">
            <h2 id="sp-leaderboard-title">
                <i class="fa fa-trophy" aria-hidden="true"></i>
                <?php echo _l('sales_pipeline_dashboard_leaderboard'); ?>
            </h2>
        </div>
    </header>

    <?php if (!empty($staff_metrics)) { ?>
        <div class="sp-dashboard-table-wrap">
            <table class="sp-dashboard-table">
                <thead>
                    <tr>
                        <th scope="col" class="sp-column-rank"><?php echo _l('sales_pipeline_dashboard_rank'); ?></th>
                        <th scope="col" class="sp-column-staff"><?php echo _l('sales_pipeline_dashboard_staff'); ?></th>

                        <th scope="col"><?php echo _l('sales_pipeline_dashboard_period_deals'); ?></th>
                        <th scope="col"><?php echo _l('sales_pipeline_dashboard_period_revenue'); ?></th>
                        <th scope="col"><?php echo _l('sales_pipeline_dashboard_win_rate'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff_metrics as $index => $metric) { ?>
                        <?php
                        $rank = $index + 1;
                        $period_revenue_formatted = sales_pipeline_compact_money($metric['period_revenue'] ?? 0);
                        $period_revenue_full = number_format((float) ($metric['period_revenue'] ?? 0), 0, ',', '.') . ' ' . _l('sales_pipeline_vnd');
                        $win_rate = (float) ($metric['period_win_rate'] ?? 0);
                        $win_rate_status = $win_rate >= 50 ? 'success' : ($win_rate >= 20 ? 'warning' : 'danger');
                        ?>
                        <tr>
                            <td class="sp-column-rank" data-label="<?php echo html_escape(_l('sales_pipeline_dashboard_rank')); ?>">
                                <span class="sp-rank sp-rank--<?php echo $rank <= 3 ? $rank : 'standard'; ?>">
                                    <?php echo $rank; ?>
                                </span>
                            </td>
                            <td class="sp-column-staff" data-label="<?php echo html_escape(_l('sales_pipeline_dashboard_staff')); ?>">
                                <button type="button"
                                        class="sp-staff-trigger js-sp-open-staff"
                                        data-staff-id="<?php echo (int) $metric['staff_id']; ?>"
                                        aria-haspopup="dialog"
                                        aria-controls="sp-dashboard-drawer"
                                        title="<?php echo html_escape(_l('sales_pipeline_dashboard_view_staff')); ?>">
                                    <?php echo staff_profile_image(
                                        $metric['staff_id'],
                                        ['sp-staff-avatar'],
                                        'small',
                                        ['alt' => $metric['staff_name']]
                                    ); ?>
                                    <span class="sp-staff-copy">
                                        <strong><?php echo html_escape($metric['staff_name']); ?></strong>
                                        <small><?php echo html_escape($metric['email']); ?></small>
                                    </span>
                                    <i class="fa fa-angle-right" aria-hidden="true"></i>
                                </button>
                            </td>

                            <td data-label="<?php echo html_escape(_l('sales_pipeline_dashboard_period_deals')); ?>">
                                <strong class="sp-quote-estimate-total">
                                    <?php echo number_format((int) ($metric['period_deals'] ?? 0), 0, ',', '.'); ?>
                                </strong>
                            </td>
                            <td data-label="<?php echo html_escape(_l('sales_pipeline_dashboard_period_revenue')); ?>"
                                title="<?php echo html_escape($period_revenue_full); ?>">
                                <strong class="sp-quote-revenue">
                                    <?php echo html_escape($period_revenue_formatted); ?>
                                </strong>
                            </td>
                            <td data-label="<?php echo html_escape(_l('sales_pipeline_dashboard_win_rate')); ?>">
                                <span class="sp-quote-rate sp-quote-rate--<?php echo $win_rate_status; ?>">
                                    <?php echo number_format($win_rate, 1, ',', ''); ?>%
                                </span>
                                <small class="sp-quote-rate-detail">
                                    <?php echo _l('sales_pipeline_quote_rate_fraction', [
                                        (int) ($metric['period_won_deals'] ?? 0),
                                        (int) ($metric['period_deals'] ?? 0),
                                    ]); ?>
                                </small>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } else { ?>
        <div class="sp-dashboard-empty">
            <i class="fa fa-bar-chart" aria-hidden="true"></i>
            <strong><?php echo _l('sales_pipeline_dashboard_no_staff'); ?></strong>
        </div>
    <?php } ?>
</section>
