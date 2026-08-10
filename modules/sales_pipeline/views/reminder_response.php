<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$status_color = !empty($reminder['status_color']) && preg_match('/^#[0-9a-f]{6}$/i', $reminder['status_color'])
    ? $reminder['status_color']
    : '#777777';
?>
<?php init_head(); ?>
<link rel="stylesheet" href="<?php echo html_escape(module_dir_url('sales_pipeline', 'assets/css/reminder_response.css')); ?>?v=1.0.0">

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="clearfix">
                            <h4 class="no-margin pull-left font-bold">
                                <i class="fa fa-reply"></i>
                                <?php echo _l('sales_pipeline_quick_response_title'); ?>
                            </h4>
                            <?php if ($reminder['staff_response'] !== null) { ?>
                                <span class="label label-success pull-right">
                                    <?php echo _l('sales_pipeline_dashboard_reminder_responded'); ?>
                                </span>
                            <?php } else { ?>
                                <span class="label label-warning pull-right">
                                    <?php echo _l('sales_pipeline_dashboard_reminder_pending'); ?>
                                </span>
                            <?php } ?>
                        </div>

                        <hr class="hr-panel-heading" />

                        <div class="row">
                            <div class="col-md-6">
                                <p class="text-muted mbot5"><?php echo html_escape($reminder['entity_label']); ?></p>
                                <p class="font-medium"><?php echo html_escape($reminder['entity_name']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted mbot5"><?php echo _l('sales_pipeline_customer_name'); ?></p>
                                <p class="font-medium"><?php echo html_escape($reminder['customer_name']); ?></p>
                            </div>
                        </div>

                        <div class="row mtop10">
                            <div class="col-md-6">
                                <p class="text-muted mbot5"><?php echo _l('sales_pipeline_deal_value'); ?></p>
                                <p><?php echo number_format((float) $reminder['deal_value']); ?> <?php echo _l('sales_pipeline_vnd'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted mbot5"><?php echo _l('sales_pipeline_expected_date'); ?></p>
                                <p><?php echo html_escape(_d($reminder['deal_date'])); ?></p>
                            </div>
                        </div>

                        <div class="row mtop10">
                            <div class="col-md-12">
                                <p class="text-muted mbot5"><?php echo _l('sales_pipeline_status'); ?></p>
                                <p>
                                    <span class="label" style="background-color:<?php echo html_escape($status_color); ?>;color:#fff">
                                        <?php echo html_escape($reminder['status_name']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div class="sp-reminder-message mtop15 mbot20">
                            <p class="sp-reminder-message__greeting">
                                <?php echo _l('sales_pipeline_reminder_snapshot_greeting', [
                                    '<strong>' . html_escape($reminder['staff_name']) . '</strong>',
                                    html_escape($reminder['entity_label']),
                                ]); ?>
                            </p>

                            <p class="sp-reminder-message__intro">
                                <?php echo _l('sales_pipeline_reminder_snapshot_intro'); ?>
                            </p>

                            <ul class="sp-reminder-message__questions">
                                <li>
                                    <strong><?php echo _l('sales_pipeline_reminder_snapshot_yesterday_label'); ?></strong>
                                    <span><?php echo _l('sales_pipeline_reminder_snapshot_yesterday_body'); ?></span>
                                </li>
                                <li>
                                    <strong><?php echo _l('sales_pipeline_reminder_snapshot_today_label'); ?></strong>
                                    <span><?php echo _l('sales_pipeline_reminder_snapshot_today_body'); ?></span>
                                </li>
                                <li>
                                    <strong><?php echo _l('sales_pipeline_reminder_snapshot_support_label'); ?></strong>
                                    <span><?php echo _l('sales_pipeline_reminder_snapshot_support_body'); ?></span>
                                </li>
                                <li class="sp-reminder-message__warning">
                                    <strong><?php echo _l('sales_pipeline_reminder_snapshot_pipeline_warning_label'); ?></strong>
                                    <span><?php echo _l(
                                        'sales_pipeline_reminder_snapshot_pipeline_warning_body',
                                        [html_escape($reminder['entity_label'])]
                                    ); ?></span>
                                </li>
                            </ul>
                        </div>

                        <?php if ($reminder['staff_response'] !== null) { ?>
                            <div class="form-group">
                                <label class="control-label"><?php echo _l('sales_pipeline_your_response'); ?></label>
                                <div class="form-control" style="height:auto;min-height:100px;background-color:#f5f5f5">
                                    <?php echo nl2br(html_escape($reminder['staff_response'])); ?>
                                </div>
                            </div>

                            <div class="alert alert-success">
                                <strong><?php echo _l('sales_pipeline_response_sent'); ?></strong>
                                <?php if (!empty($reminder['responded_at'])) { ?>
                                    <span class="display-block mtop5">
                                        <?php echo _l('sales_pipeline_dashboard_response_time'); ?>:
                                        <?php echo html_escape(_dt($reminder['responded_at'])); ?>
                                    </span>
                                <?php } ?>
                            </div>

                            <p class="text-muted">
                                <?php echo _l('sales_pipeline_response_locked_help'); ?>
                            </p>
                        <?php } else { ?>
                            <?php echo form_open(
                                admin_url('sales_pipeline/respond_reminder/' . $reminder['id']),
                                ['id' => 'reminder-response-form', 'class' => 'disable-on-submit']
                            ); ?>
                                <div class="form-group">
                                    <label for="response" class="control-label">
                                        <?php echo _l('sales_pipeline_your_response'); ?>
                                    </label>
                                    <textarea
                                        id="response"
                                        name="response"
                                        class="form-control"
                                        rows="6"
                                        maxlength="2000"
                                        required
                                        autofocus
                                        placeholder="<?php echo html_escape(_l('sales_pipeline_response_placeholder')); ?>"
                                    ></textarea>
                                    <p class="text-muted mtop5 mbot0">
                                        <?php echo _l('sales_pipeline_response_max_length'); ?>
                                    </p>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-paper-plane"></i>
                                    <?php echo _l('sales_pipeline_send_response'); ?>
                                </button>
                            <?php echo form_close(); ?>
                        <?php } ?>

                        <a href="<?php echo admin_url('sales_pipeline/deal/' . $reminder['pipeline_id']); ?>"
                           class="btn btn-default mtop15">
                            <i class="fa fa-external-link"></i>
                            <?php echo _l('sales_pipeline_view_deal'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
</body>
</html>
