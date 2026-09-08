<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$revenue_kpi = $dashboard['revenue_kpi'] ?? [];
$current_total = $revenue_kpi['formatted']['current_total'] ?? sales_pipeline_compact_money(0);
$previous_total = $revenue_kpi['formatted']['previous_total'] ?? sales_pipeline_compact_money(0);
$change_pct = $revenue_kpi['change_pct'] ?? 0;
$change_direction = $revenue_kpi['change_direction'] ?? 'flat';

$badge_class = 'sp-kpi-badge--flat';
$badge_icon = 'fa-minus';
$sign = '';

if ($change_direction === 'up') {
    $badge_class = 'sp-kpi-badge--up';
    $badge_icon = 'fa-arrow-up';
    $sign = '+';
} elseif ($change_direction === 'down') {
    $badge_class = 'sp-kpi-badge--down';
    $badge_icon = 'fa-arrow-down';
    $sign = '-';
}
?>
<section class="sp-revenue-kpi-card" aria-labelledby="sp-kpi-revenue-title">
    <div class="sp-revenue-kpi-card__header">
        <div class="sp-revenue-kpi-card__title-row">
            <span class="sp-revenue-kpi-card__tag">
                <i class="fa fa-line-chart" aria-hidden="true"></i>
                <span id="sp-kpi-revenue-title"><?php echo _l('sales_pipeline_kpi_revenue_title'); ?></span>
            </span>
            <div class="sp-revenue-kpi-card__legend">
                <span class="sp-kpi-legend-item sp-kpi-legend-item--current">
                    <span class="sp-kpi-legend-bullet"></span>
                    <span><?php echo _l('sales_pipeline_kpi_current_period'); ?></span>
                </span>
                <span class="sp-kpi-legend-item sp-kpi-legend-item--previous">
                    <span class="sp-kpi-legend-bullet"></span>
                    <span><?php echo _l('sales_pipeline_kpi_previous_period'); ?></span>
                </span>
            </div>
        </div>

        <div class="sp-revenue-kpi-card__value-row">
            <div class="sp-revenue-kpi-card__metric">
                <strong class="sp-revenue-kpi-card__number"><?php echo html_escape($current_total); ?></strong>
            </div>
            <div class="sp-revenue-kpi-card__comparison">
                <span class="sp-kpi-badge <?php echo $badge_class; ?>">
                    <i class="fa <?php echo $badge_icon; ?>" aria-hidden="true"></i>
                    <span><?php echo $sign . number_format((float) $change_pct, 1, ',', ''); ?>%</span>
                </span>
                <span class="sp-revenue-kpi-card__vs-label">
                    <?php echo _l('sales_pipeline_kpi_vs_previous'); ?> (<strong><?php echo html_escape($previous_total); ?></strong>)
                </span>
            </div>
        </div>
    </div>

    <div class="sp-revenue-kpi-card__chart-wrap">
        <div id="sp-revenue-sparkline-chart"
             class="sp-revenue-sparkline-chart"
             data-revenue-kpi="<?php echo html_escape(json_encode($revenue_kpi)); ?>"></div>
    </div>
</section>
