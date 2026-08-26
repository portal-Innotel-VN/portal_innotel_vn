<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$staff_name = trim($staff->firstname . ' ' . $staff->lastname);
$staff_profile_url = admin_url('staff/member/' . $staff->staffid);
$can_open_staff_profile = !empty($can_open_staff_profile);
$compact_money = static function ($value) {
    $value = (float) $value;
    $absolute = abs($value);

    if ($absolute >= 1000000000) {
        $formatted = rtrim(rtrim(number_format($value / 1000000000, 1, ',', '.'), '0'), ',');
        return $formatted . ' ' . _l('sales_pipeline_currency_billion');
    }

    if ($absolute >= 1000000) {
        $formatted = rtrim(rtrim(number_format($value / 1000000, 1, ',', '.'), '0'), ',');
        return $formatted . ' ' . _l('sales_pipeline_currency_million');
    }

    return number_format($value, 0, ',', '.') . ' ' . _l('sales_pipeline_vnd');
};

$metric = $metric ?: [
    'count_estimates_today' => 0,
    'count_estimates_month' => 0,
    'sum_revenue_this_week' => 0,
    'open_pipeline_deals'   => 0,
    'period_deals'          => 0,
    'period_revenue'        => 0,
    'period_estimates'      => 0,
];
?>

<div class="sp-drawer-staff-header">
    <?php if ($can_open_staff_profile) { ?>
    <a href="<?php echo html_escape($staff_profile_url); ?>"
       class="sp-drawer-staff-profile-link"
       title="<?php echo html_escape($staff_name); ?>">
    <?php } ?>
    <?php echo staff_profile_image(
        $staff->staffid,
        ['sp-drawer-staff-avatar'],
        'small',
        ['alt' => $staff_name]
    ); ?>
    <?php if ($can_open_staff_profile) { ?>
    </a>
    <?php } ?>
    <div>
        <h2>
            <?php if ($can_open_staff_profile) { ?>
                <a href="<?php echo html_escape($staff_profile_url); ?>"
                   class="sp-drawer-staff-name-link"
                   title="<?php echo html_escape($staff_name); ?>">
                    <?php echo html_escape($staff_name); ?>
                </a>
            <?php } else { ?>
                <?php echo html_escape($staff_name); ?>
            <?php } ?>
        </h2>
        <p><?php echo html_escape($staff->email); ?></p>
    </div>
</div>

<?php
$is_estimate_dashboard = isset($dashboard_tab) && $dashboard_tab === 'estimates';
?>

<div class="sp-drawer-metrics">
    <?php if ($is_estimate_dashboard) { ?>
        <div class="sp-drawer-metric">
            <span><?php echo _l('sales_pipeline_dashboard_total_estimates'); ?></span>
            <strong><?php echo number_format((int) ($metric['period_estimates'] ?? 0), 0, ',', '.'); ?></strong>
        </div>
        <div class="sp-drawer-metric">
            <span><?php echo _l('sales_pipeline_dashboard_total_revenue'); ?></span>
            <strong title="<?php echo html_escape(number_format((float) ($metric['period_revenue'] ?? 0), 0, ',', '.') . ' ' . _l('sales_pipeline_vnd')); ?>">
                <?php echo html_escape($compact_money($metric['period_revenue'] ?? 0)); ?>
            </strong>
        </div>
    <?php } else { ?>
        <div class="sp-drawer-metric">
            <span><?php echo _l('sales_pipeline_dashboard_total_deals'); ?></span>
            <strong><?php echo number_format((int) ($metric['period_deals'] ?? 0), 0, ',', '.'); ?></strong>
        </div>
        <div class="sp-drawer-metric">
            <span><?php echo _l('sales_pipeline_dashboard_total_revenue'); ?></span>
            <strong title="<?php echo html_escape(number_format((float) ($metric['period_revenue'] ?? 0), 0, ',', '.') . ' ' . _l('sales_pipeline_vnd')); ?>">
                <?php echo html_escape($compact_money($metric['period_revenue'] ?? 0)); ?>
            </strong>
        </div>
    <?php } ?>
</div>

<?php if ($is_estimate_dashboard && !empty($performance_metric)) { ?>
    <section class="sp-score-breakdown" aria-labelledby="sp-score-breakdown-title">
        <header class="sp-score-breakdown__header">
            <div class="sp-score-breakdown__header-left">
                <h3 id="sp-score-breakdown-title">
                    <i class="fa fa-calculator" aria-hidden="true"></i>
                    <?php echo _l('sales_pipeline_performance_breakdown'); ?>
                </h3>
            </div>
            <div class="sp-score-breakdown__header-right">
                <div class="sp-score-breakdown__total" title="<?php echo html_escape(number_format((float) $performance_metric['performance_score'], 1, ',', '.') . ' ' . _l('sales_pipeline_performance_points')); ?>">
                    <span class="sp-score-breakdown__total-label"><?php echo _l('sales_pipeline_performance_score'); ?>:</span>
                    <strong><?php echo number_format((float) $performance_metric['performance_score'], 1, ',', '.'); ?></strong>
                    <small><?php echo _l('sales_pipeline_performance_points'); ?></small>
                </div>
                <?php if (!empty($performance_metric['is_provisional'])) { ?>
                    <span class="sp-performance-badge sp-performance-badge--provisional" role="status">
                        <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                        <?php echo _l('sales_pipeline_performance_provisional'); ?>
                    </span>
                <?php } ?>
            </div>
        </header>
        <div class="sp-score-breakdown__grid">
            <?php
            $score_components = [
                [
                    'label' => 'sales_pipeline_performance_quote_component',
                    'score' => $performance_metric['quote_score'],
                    'weight' => $performance_metric['effective_weights']['quote_score'],
                    'icon'  => 'fa-file-text-o',
                ],
                [
                    'label' => 'sales_pipeline_performance_revenue_component',
                    'score' => $performance_metric['accepted_revenue_score'],
                    'weight' => $performance_metric['effective_weights']['accepted_revenue_score'],
                    'icon'  => 'fa-line-chart',
                ],
                [
                    'label' => 'sales_pipeline_performance_acceptance_component',
                    'score' => $performance_metric['acceptance_score'],
                    'weight' => $performance_metric['effective_weights']['acceptance_score'],
                    'icon'  => 'fa-check-circle-o',
                ],
            ];
            foreach ($score_components as $component) { ?>
                <div class="sp-score-breakdown__item">
                    <div class="sp-score-breakdown__item-top">
                        <i class="fa <?php echo html_escape($component['icon']); ?> sp-score-breakdown__item-icon" aria-hidden="true"></i>
                        <span class="sp-score-breakdown__item-label"><?php echo _l($component['label']); ?></span>
                    </div>
                    <div class="sp-score-breakdown__item-value">
                        <strong><?php echo number_format((float) $component['score'], 1, ',', '.'); ?></strong>
                        <span class="sp-score-breakdown__item-unit"><?php echo _l('sales_pipeline_performance_points'); ?></span>
                    </div>
                    <div class="sp-score-breakdown__item-bottom">
                        <span class="sp-score-breakdown__weight-tag">
                            <?php echo _l('sales_pipeline_performance_effective_weight', [number_format((float) $component['weight'], 2, ',', '.')]); ?>
                        </span>
                    </div>
                </div>
            <?php } ?>
            <div class="sp-score-breakdown__item sp-score-breakdown__item--inactive">
                <div class="sp-score-breakdown__item-top">
                    <i class="fa fa-bell-o sp-score-breakdown__item-icon" aria-hidden="true"></i>
                    <span class="sp-score-breakdown__item-label"><?php echo _l('sales_pipeline_performance_reminder_component'); ?></span>
                </div>
                <div class="sp-score-breakdown__item-value">
                    <strong>—</strong>
                </div>
                <div class="sp-score-breakdown__item-bottom">
                    <span class="sp-score-breakdown__weight-tag sp-score-breakdown__weight-tag--inactive">
                        <?php echo _l('sales_pipeline_performance_inactive'); ?>
                    </span>
                </div>
            </div>
        </div>
    </section>
<?php } ?>

<?php
$open_deals_list  = !empty($open_deals['deals']) && is_array($open_deals['deals']) ? $open_deals['deals'] : [];
$open_deals_total = !empty($open_deals['total']) ? (int) $open_deals['total'] : 0;
$actionable_feed_list  = !empty($actionable_feed) && is_array($actionable_feed) ? $actionable_feed : [];
$actionable_feed_total = count($actionable_feed_list);
$estimate_follow_up_list = !empty($estimate_follow_ups) && is_array($estimate_follow_ups) ? $estimate_follow_ups : [];
$estimate_follow_up_total = count($estimate_follow_up_list);
$estimate_reminder_title_keys = [
    'DAILY_ESTIMATE'                => 'sales_pipeline_estimate_reminder_daily_title',
    'ESTIMATE_DAILY'                => 'sales_pipeline_estimate_reminder_daily_title',
    'ESTIMATE_DAILY_MIN_COUNT'      => 'sales_pipeline_estimate_reminder_daily_title',
    'MONTHLY_ESTIMATE'              => 'sales_pipeline_estimate_reminder_monthly_title',
    'ESTIMATE_MONTHLY'              => 'sales_pipeline_estimate_reminder_monthly_title',
    'ESTIMATE_MONTHLY_MIN_COUNT'    => 'sales_pipeline_estimate_reminder_monthly_title',
    'WEEKLY_ESTIMATE'               => 'sales_pipeline_estimate_reminder_weekly_title',
    'ESTIMATE_WEEKLY'               => 'sales_pipeline_estimate_reminder_weekly_title',
    'ESTIMATE_WEEKLY_MIN_REVENUE'   => 'sales_pipeline_estimate_reminder_weekly_title',
];
?>

<div class="sp-drawer-tabs-wrapper">
    <ul class="nav nav-tabs sp-drawer-nav-tabs" role="tablist">
        <?php if (!$is_estimate_dashboard) { ?>
        <li role="presentation" class="active">
            <a href="#sp_drawer_tab_open_deals" aria-controls="sp_drawer_tab_open_deals" role="tab" data-toggle="tab">
                <i class="fa fa-folder-open-o" aria-hidden="true"></i>
                <span><?php echo _l('sales_pipeline_staff_open_deals'); ?></span>
                <span class="sp-drawer-panel__badge"><?php echo number_format($open_deals_total, 0, ',', '.'); ?></span>
            </a>
        </li>
        <?php } ?>
        <?php if ($is_estimate_dashboard) { ?>
        <li role="presentation" class="active">
            <a href="#sp_drawer_tab_follow_up" aria-controls="sp_drawer_tab_follow_up" role="tab" data-toggle="tab">
                <i class="fa fa-bell-o" aria-hidden="true"></i>
                <span><?php echo _l('sales_pipeline_staff_follow_up_customers'); ?></span>
                <?php if ($estimate_follow_up_total > 0) { ?>
                    <span class="sp-drawer-panel__badge sp-drawer-panel__badge--warning"><?php echo number_format($estimate_follow_up_total, 0, ',', '.'); ?></span>
                <?php } ?>
            </a>
        </li>
        <?php } ?>
        <li role="presentation">
            <a href="#sp_drawer_tab_activity" aria-controls="sp_drawer_tab_activity" role="tab" data-toggle="tab">
                <i class="fa fa-comments-o" aria-hidden="true"></i>
                <span><?php echo _l('sales_pipeline_dashboard_actionable_feed'); ?></span>
                <?php if ($actionable_feed_total > 0) { ?>
                    <span class="sp-drawer-panel__badge sp-drawer-panel__badge--activity"><?php echo number_format($actionable_feed_total, 0, ',', '.'); ?></span>
                <?php } ?>
            </a>
        </li>
    </ul>

    <div class="tab-content sp-drawer-tab-content">
        <?php if (!$is_estimate_dashboard) { ?>
        <div role="tabpanel" class="tab-pane active" id="sp_drawer_tab_open_deals">
            <section class="sp-drawer-panel sp-drawer-panel--open-deals" aria-labelledby="sp-drawer-open-deals-title">
                <header class="sp-drawer-panel__header sr-only">
                    <h3 id="sp-drawer-open-deals-title">
                        <i class="fa fa-folder-open-o" aria-hidden="true"></i>
                        <?php echo _l('sales_pipeline_staff_open_deals'); ?>
                        <span class="sp-drawer-panel__badge"><?php echo number_format($open_deals_total, 0, ',', '.'); ?></span>
                    </h3>
                </header>

                <?php if (!empty($open_deals_list)) { ?>
                    <div class="sp-drawer-table-wrap">
                        <table class="table sp-drawer-table">
                            <thead>
                                <tr>
                                    <th><?php echo _l('sales_pipeline_staff_deal_name'); ?></th>
                                    <th><?php echo _l('sales_pipeline_status'); ?></th>
                                    <th><?php echo _l('sales_pipeline_customer'); ?></th>
                                    <th><?php echo _l('sales_pipeline_value'); ?></th>
                                    <th><?php echo _l('sales_pipeline_date'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($open_deals_list as $deal) { ?>
                                    <?php
                                    $raw_color = $deal['status_color'] ?? '#64748b';
                                    $status_color = preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $raw_color)
                                        ? $raw_color
                                        : '#64748b';
                                    $deal_name = !empty($deal['deal_name']) ? trim($deal['deal_name']) : _l('sales_pipeline_status_unknown');
                                    $can_open_deal = !empty($can_open_pipeline_deal);
                                    ?>
                                    <tr>
                                        <td class="sp-drawer-table__deal">
                                            <?php if ($can_open_deal && !empty($deal['id'])) { ?>
                                                <a href="<?php echo html_escape(admin_url('sales_pipeline/deal/' . (int) $deal['id'])); ?>"
                                                   title="<?php echo html_escape($deal_name); ?>">
                                                    <?php echo html_escape($deal_name); ?>
                                                    <i class="fa fa-external-link" aria-hidden="true"></i>
                                                </a>
                                            <?php } else { ?>
                                                <span><?php echo html_escape($deal_name); ?></span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <span class="sp-drawer-status-badge" style="--sp-status-color: <?php echo html_escape($status_color); ?>">
                                                <span class="sp-drawer-status-badge__dot" aria-hidden="true"></span>
                                                <?php echo html_escape($deal['status_name'] ?? ''); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="sp-drawer-table__customer" title="<?php echo html_escape($deal['customer_name'] ?? ''); ?>">
                                                <?php echo html_escape($deal['customer_name'] ?? '-'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong title="<?php echo html_escape(number_format((float) ($deal['deal_value'] ?? 0), 0, ',', '.') . ' ' . _l('sales_pipeline_vnd')); ?>">
                                                <?php echo html_escape($compact_money($deal['deal_value'] ?? 0)); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <span>
                                                <?php echo !empty($deal['deal_date']) ? html_escape(_d($deal['deal_date'])) : '-'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } else { ?>
                    <div class="sp-drawer-panel__empty">
                        <i class="fa fa-folder-open-o" aria-hidden="true"></i>
                        <strong><?php echo _l('sales_pipeline_no_open_deals'); ?></strong>
                    </div>
                <?php } ?>
            </section>
        </div>
        <?php } ?>        <?php if ($is_estimate_dashboard) { ?>
        <div role="tabpanel" class="tab-pane active" id="sp_drawer_tab_follow_up">
            <section class="sp-drawer-panel sp-drawer-panel--follow-up" aria-label="<?php echo html_escape(_l('sales_pipeline_staff_follow_up_customers')); ?>">
                <?php if (!empty($estimate_follow_up_list)) { ?>
                    <div class="sp-drawer-table-wrap">
                        <table class="table sp-drawer-table">
                            <thead>
                                <tr>
                                    <th><?php echo _l('estimate_dt_table_heading_number'); ?></th>
                                    <th><?php echo _l('estimate_dt_table_heading_status'); ?></th>
                                    <th><?php echo _l('estimate_dt_table_heading_client'); ?></th>
                                    <th><?php echo _l('estimate_dt_table_heading_amount'); ?></th>
                                    <th><?php echo _l('estimate_dt_table_heading_expirydate'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estimate_follow_up_list as $item) { ?>
                                    <?php
                                    $estimate_id = (int) $item['id'];
                                    $estimate_number = format_estimate_number($estimate_id);
                                    $can_open = !empty($can_open_estimate);
                                    ?>
                                    <tr>
                                        <td class="sp-drawer-table__deal">
                                            <?php if ($can_open && !empty($estimate_id)) { ?>
                                                <a href="<?php echo html_escape(admin_url('estimates/list_estimates/' . $estimate_id . '#' . $estimate_id)); ?>"
                                                   title="<?php echo html_escape($estimate_number); ?>">
                                                    <?php echo html_escape($estimate_number); ?>
                                                    <i class="fa fa-external-link" aria-hidden="true"></i>
                                                </a>
                                            <?php } else { ?>
                                                <span><?php echo html_escape($estimate_number); ?></span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php echo format_estimate_status($item['status'], '', false); ?>
                                        </td>
                                        <td>
                                            <span class="sp-drawer-table__customer" title="<?php echo html_escape($item['customer_name'] ?? ''); ?>">
                                                <?php echo html_escape($item['customer_name'] ?? '-'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong title="<?php echo html_escape(number_format((float) ($item['total'] ?? 0), 0, ',', '.') . ' ' . _l('sales_pipeline_vnd')); ?>">
                                                <?php echo html_escape($compact_money($item['total'] ?? 0)); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <span>
                                                <?php echo !empty($item['expirydate']) ? html_escape(_d($item['expirydate'])) : '-'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } else { ?>
                    <div class="sp-drawer-panel__empty">
                        <i class="fa fa-check-circle-o" aria-hidden="true"></i>
                        <strong><?php echo _l('sales_pipeline_no_follow_up_customers'); ?></strong>
                    </div>
                <?php } ?>
            </section>
        </div>
        <?php } ?>

        <div role="tabpanel" class="tab-pane" id="sp_drawer_tab_activity">
            <section class="sp-drawer-panel sp-drawer-panel--activity" aria-labelledby="sp-drawer-activity-title">
                <header class="sp-drawer-panel__header sr-only">
                    <h3 id="sp-drawer-activity-title">
                        <i class="fa fa-comments-o" aria-hidden="true"></i>
                        <?php echo _l('sales_pipeline_dashboard_actionable_feed'); ?>
                    </h3>
                </header>

                <?php if (!empty($actionable_feed)) { ?>
                    <div class="sp-drawer-feed__list">
                        <?php foreach ($actionable_feed as $feed_item) { ?>
                            <?php
                            $is_informational = isset($feed_item['response_required']) && (int) $feed_item['response_required'] !== 1;
                            $is_responded = !$is_informational && (!empty($feed_item['staff_response']) || (array_key_exists('staff_response', $feed_item) && $feed_item['staff_response'] !== null));
                            $entity_type = trim((string) ($feed_item['entity_type'] ?? 'deal'));
                            $feed_title = trim((string) ($feed_item['deal_name'] ?? ''));

                            if ($entity_type === 'staff_estimate_period' || $entity_type === 'estimate') {
                                $snapshot = json_decode((string) ($feed_item['snapshot_json'] ?? ''), true);
                                $snapshot = is_array($snapshot) ? $snapshot : [];
                                $feed_title = '';

                                $feed_title = trim((string) ($feed_item['title'] ?? ''));
                                foreach (['title', 'reminder_title', 'report_title', 'estimate_number'] as $snapshot_title_key) {
                                    if (isset($snapshot[$snapshot_title_key]) && is_string($snapshot[$snapshot_title_key])) {
                                        $feed_title = trim($snapshot[$snapshot_title_key]);
                                    }
                                    if ($feed_title !== '') {
                                        break;
                                    }
                                }

                                if ($feed_title === '') {
                                    $rule_code = strtoupper(trim((string) ($feed_item['rule_code'] ?? '')));
                                    $title_key = $estimate_reminder_title_keys[$rule_code]
                                        ?? 'sales_pipeline_estimate_reminder_default_title';
                                    $feed_title = _l($title_key);
                                }
                            }

                            $feed_title = $feed_title !== '' ? $feed_title : _l('sales_pipeline_status_unknown');
                            $can_open_deal = !empty($can_open_pipeline_deal);
                            ?>
                            <article class="sp-drawer-feed__item sp-drawer-feed__item--<?php echo $is_informational ? 'info' : ($is_responded ? 'responded' : 'pending'); ?>">
                                <div class="sp-drawer-feed__top-row">
                                    <div class="sp-drawer-feed__deal">
                                        <?php if ($can_open_deal && !empty($feed_item['pipeline_id']) && !empty($feed_item['deal_name'])) { ?>
                                            <a href="<?php echo html_escape(admin_url('sales_pipeline/deal/' . (int) $feed_item['pipeline_id'])); ?>">
                                                <?php echo html_escape($feed_title); ?>
                                                <i class="fa fa-external-link" aria-hidden="true"></i>
                                            </a>
                                        <?php } else { ?>
                                            <strong><?php echo html_escape($feed_title); ?></strong>
                                        <?php } ?>
                                        <?php if (!empty($feed_item['customer_name'])) { ?>
                                            <span class="sp-drawer-feed__customer"><?php echo html_escape($feed_item['customer_name']); ?></span>
                                        <?php } ?>
                                    </div>
                                    <span class="sp-drawer-feed__status">
                                        <i class="fa <?php echo $is_informational ? 'fa-info-circle' : ($is_responded ? 'fa-check-circle' : 'fa-clock-o'); ?>" aria-hidden="true"></i>
                                        <?php echo _l($is_informational ? 'sales_pipeline_reminder_informational' : ($is_responded
                                            ? 'sales_pipeline_dashboard_reminder_responded'
                                            : 'sales_pipeline_dashboard_reminder_pending')); ?>
                                    </span>
                                </div>

                                <div class="sp-drawer-feed__message">
                                    <?php echo nl2br(html_escape($feed_item['message'])); ?>
                                </div>

                                <?php if ($is_responded && !empty($feed_item['staff_response'])) { ?>
                                    <div class="sp-drawer-feed__response">
                                        <span><?php echo _l('sales_pipeline_dashboard_staff_response'); ?></span>
                                        <p><?php echo nl2br(html_escape($feed_item['staff_response'])); ?></p>
                                    </div>
                                <?php } ?>

                                <footer class="sp-drawer-feed__time">
                                    <span>
                                        <?php echo _l('sales_pipeline_dashboard_reminder_sent_at'); ?>:
                                        <time datetime="<?php echo html_escape($feed_item['sent_at']); ?>">
                                            <?php echo html_escape(_dt($feed_item['sent_at'])); ?>
                                        </time>
                                    </span>
                                    <?php if ($is_responded && !empty($feed_item['responded_at'])) { ?>
                                        <span>
                                            <?php echo _l('sales_pipeline_dashboard_response_time'); ?>:
                                            <time datetime="<?php echo html_escape($feed_item['responded_at']); ?>">
                                                <?php echo html_escape(_dt($feed_item['responded_at'])); ?>
                                            </time>
                                        </span>
                                    <?php } ?>
                                </footer>
                            </article>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="sp-drawer-panel__empty">
                        <i class="fa fa-comments-o" aria-hidden="true"></i>
                        <strong><?php echo _l('sales_pipeline_dashboard_no_reminder_responses_in_period'); ?></strong>
                    </div>
                <?php } ?>
            </section>
        </div>
    </div>
</div>
