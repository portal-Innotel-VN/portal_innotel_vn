<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$counts = $health['counts'];
$summary = $health['summary'];
$circuitOpen = $health['circuit_state'] !== 'closed';
$count = function ($channel, $status) use ($counts) {
    return (int) ($counts[$channel][$status] ?? 0);
};
?>
<section class="sp-delivery-health" aria-labelledby="sp-delivery-health-title">
    <div class="sp-delivery-health__circuit sp-delivery-health__circuit--<?php echo $circuitOpen ? 'open' : 'closed'; ?>">
        <div>
            <h5 id="sp-delivery-health-title"><i class="fa fa-heartbeat" aria-hidden="true"></i> <?php echo _l('sales_pipeline_reminder_delivery_health'); ?></h5>
            <span class="sp-delivery-health__state">
                <i class="fa <?php echo $circuitOpen ? 'fa-exclamation-triangle' : 'fa-check-circle'; ?>" aria-hidden="true"></i>
                <?php echo _l($circuitOpen ? 'sales_pipeline_reminder_delivery_circuit_open' : 'sales_pipeline_reminder_delivery_circuit_closed'); ?>
            </span>
            <?php if ($circuitOpen && $health['circuit_opened_at']) { ?>
                <span class="sp-delivery-health__meta"><?php echo html_escape(_dt($health['circuit_opened_at'])); ?></span>
            <?php } ?>
        </div>
        <?php if ($circuitOpen) { ?>
            <button type="button" class="btn btn-warning sp-delivery-resume" data-url="<?php echo admin_url('sales_pipeline/reminder_delivery_resume_circuit'); ?>">
                <i class="fa fa-play" aria-hidden="true"></i> <?php echo _l('sales_pipeline_reminder_delivery_resume'); ?>
            </button>
        <?php } ?>
    </div>

    <div class="sp-delivery-health__metrics">
        <div><span><?php echo _l('sales_pipeline_reminder_delivery_email_pending'); ?></span><strong><?php echo $count('email', 'pending'); ?></strong></div>
        <div><span><?php echo _l('sales_pipeline_reminder_delivery_email_failed'); ?></span><strong><?php echo $count('email', 'failed'); ?></strong></div>
        <div><span><?php echo _l('sales_pipeline_reminder_delivery_email_sent'); ?></span><strong><?php echo $count('email', 'sent'); ?></strong></div>
        <div><span><?php echo _l('sales_pipeline_reminder_delivery_expired'); ?></span><strong><?php echo $count('email', 'expired'); ?></strong></div>
        <div><span><?php echo _l('sales_pipeline_reminder_delivery_crm_pending'); ?></span><strong><?php echo $count('crm', 'pending'); ?></strong></div>
        <div><span><?php echo _l('sales_pipeline_reminder_delivery_rate_limited_24h'); ?></span><strong><?php echo (int) ($summary['rate_limited_24h'] ?? 0); ?></strong></div>
    </div>

    <dl class="sp-delivery-health__timeline">
        <dt><?php echo _l('sales_pipeline_reminder_delivery_oldest_pending'); ?></dt>
        <dd><?php echo !empty($summary['oldest_email_pending_at']) ? html_escape(_dt($summary['oldest_email_pending_at'])) : _l('sales_pipeline_reminder_delivery_none'); ?></dd>
        <dt><?php echo _l('sales_pipeline_reminder_delivery_last_sent'); ?></dt>
        <dd><?php echo !empty($summary['last_email_sent_at']) ? html_escape(_dt($summary['last_email_sent_at'])) : _l('sales_pipeline_reminder_delivery_none'); ?></dd>
        <dt><?php echo _l('sales_pipeline_reminder_delivery_cooldown_until'); ?></dt>
        <dd><?php echo !empty($summary['cooldown_until']) ? html_escape(_dt($summary['cooldown_until'])) : _l('sales_pipeline_reminder_delivery_none'); ?></dd>
    </dl>

    <h6 class="sp-delivery-health__table-title"><?php echo _l('sales_pipeline_reminder_delivery_recent_errors'); ?></h6>
    <?php if (!$health['recent_errors']) { ?>
        <div class="sp-delivery-health__empty"><i class="fa fa-check-circle" aria-hidden="true"></i> <?php echo _l('sales_pipeline_reminder_delivery_no_errors'); ?></div>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="table sp-delivery-health__table">
                <thead><tr>
                    <th><?php echo _l('sales_pipeline_reminder_delivery_id'); ?></th>
                    <th><?php echo _l('sales_pipeline_reminder_delivery_rule_entity'); ?></th>
                    <th><?php echo _l('sales_pipeline_reminder_delivery_recipient'); ?></th>
                    <th><?php echo _l('sales_pipeline_reminder_delivery_error_class'); ?></th>
                    <th><?php echo _l('sales_pipeline_reminder_delivery_attempts'); ?></th>
                    <th><?php echo _l('sales_pipeline_reminder_delivery_next_retry'); ?></th>
                    <th class="text-right"><?php echo _l('sales_pipeline_reminder_delivery_action'); ?></th>
                </tr></thead>
                <tbody><?php foreach ($health['recent_errors'] as $error) { ?><tr>
                    <td>#<?php echo (int) $error['id']; ?></td>
                    <td><strong><?php echo html_escape($error['rule_code']); ?></strong><br><span><?php echo html_escape($error['entity_type']); ?> #<?php echo (int) $error['entity_id']; ?></span></td>
                    <td><?php echo html_escape($error['recipient_masked']); ?></td>
                    <td><span class="label label-default"><?php echo html_escape($error['last_error_class']); ?></span></td>
                    <td><?php echo (int) $error['attempt_count']; ?></td>
                    <td><?php echo $error['next_retry_at'] ? html_escape(_dt($error['next_retry_at'])) : _l('sales_pipeline_reminder_delivery_manual'); ?></td>
                    <td class="text-right">
                        <?php if (in_array($error['status'], ['failed', 'cancelled'], true)) { ?>
                            <button type="button" class="btn btn-default btn-icon sp-delivery-retry" title="<?php echo html_escape(_l('sales_pipeline_reminder_delivery_retry')); ?>" aria-label="<?php echo html_escape(_l('sales_pipeline_reminder_delivery_retry')); ?>" data-id="<?php echo (int) $error['id']; ?>" data-url="<?php echo admin_url('sales_pipeline/reminder_delivery_retry/' . (int) $error['id']); ?>"><i class="fa fa-refresh" aria-hidden="true"></i></button>
                        <?php } ?>
                    </td>
                </tr><?php } ?></tbody>
            </table>
        </div>
    <?php } ?>
</section>
