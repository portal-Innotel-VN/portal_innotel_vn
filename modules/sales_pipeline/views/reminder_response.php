<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$responseRequired = (int) ($reminder['response_required'] ?? 1) === 1;
$responded = $reminder['staff_response'] !== null;
$canRespond = (int) $reminder['staff_id'] === (int) get_staff_user_id();
?>
<?php init_head(); ?>
<link rel="stylesheet" href="<?php echo html_escape(module_dir_url('sales_pipeline', 'assets/css/reminder_response.css')); ?>?v=1.0.7">

<div id="wrapper">
    <div class="content sp-reminder-response">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body sp-reminder-response__panel">
                        <header class="sp-reminder-response__header">
                            <div>
                                <span class="sp-reminder-response__eyebrow"><?php echo html_escape($reminder['entity_label']); ?></span>
                                <h1><?php echo html_escape($reminder['quick_response_title']); ?></h1>
                            </div>
                            <span class="sp-reminder-state sp-reminder-state--<?php echo !$responseRequired ? 'info' : ($responded ? 'responded' : 'pending'); ?>">
                                <i class="fa <?php echo !$responseRequired ? 'fa-info-circle' : ($responded ? 'fa-check-circle' : 'fa-clock-o'); ?>" aria-hidden="true"></i>
                                <?php echo _l(!$responseRequired
                                    ? 'sales_pipeline_reminder_informational'
                                    : ($responded ? 'sales_pipeline_dashboard_reminder_responded' : 'sales_pipeline_dashboard_reminder_pending')); ?>
                            </span>
                        </header>

                        <div class="sp-reminder-context" aria-label="<?php echo html_escape(_l('sales_pipeline_reminder_context')); ?>">
                            <?php foreach ($reminder['context_cards'] as $card) { ?>
                                <div><span><?php echo html_escape($card['label']); ?></span><strong><?php echo html_escape($card['value']); ?></strong></div>
                            <?php } ?>
                        </div>

                        <section class="sp-reminder-message" role="note">
                            <i class="fa fa-bell-o sp-reminder-message__icon" aria-hidden="true"></i>
                            <div class="sp-reminder-message__content">
                                <p class="sp-reminder-message__greeting"><?php echo nl2br(html_escape($reminder['display_message'])); ?></p>
                                <p class="sp-reminder-message__target">
                                    <strong><?php echo html_escape($reminder['target_label']); ?></strong>
                                    <?php echo html_escape($reminder['target_summary']); ?>
                                    <?php echo html_escape($reminder['target_question']); ?>
                                </p>
                                <p class="sp-reminder-message__intro"><?php echo html_escape($reminder['snapshot_intro']); ?></p>
                                <ul class="sp-reminder-message__questions">
                                    <?php foreach ($reminder['snapshot_questions'] as $question) { ?>
                                        <li><strong><?php echo html_escape($question['label']); ?></strong><span><?php echo html_escape($question['body']); ?></span></li>
                                    <?php } ?>
                                </ul>
                                <div class="alert alert-warning sp-reminder-message__warning" role="alert">
                                    <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                                    <strong><?php echo html_escape($reminder['snapshot_warning_label']); ?></strong>
                                    <?php echo html_escape($reminder['snapshot_warning_body']); ?>
                                </div>
                            </div>
                        </section>

                        <?php if (!$responseRequired) { ?>
                            <div class="alert alert-info sp-reminder-info-note">
                                <?php echo _l('sales_pipeline_reminder_informational_help'); ?>
                            </div>
                        <?php } elseif ($responded) { ?>
                            <section class="sp-reminder-response__answer form-group">
                                <label class="control-label"><?php echo _l('sales_pipeline_your_response'); ?></label>
                                <div class="form-control sp-reminder-response__answer-content" role="textbox" aria-readonly="true"><?php echo nl2br(html_escape($reminder['staff_response'])); ?></div>
                                <?php if (!empty($reminder['responded_at'])) { ?>
                                    <time datetime="<?php echo html_escape($reminder['responded_at']); ?>"><?php echo html_escape(_dt($reminder['responded_at'])); ?></time>
                                <?php } ?>
                                <div class="alert alert-success sp-reminder-response__success" role="status"><i class="fa fa-check" aria-hidden="true"></i> <?php echo _l('sales_pipeline_response_sent'); ?></div>
                                <p class="help-block mbot0"><?php echo _l('sales_pipeline_response_locked_help'); ?></p>
                            </section>
                        <?php } elseif ($canRespond) { ?>
                            <?php echo form_open(admin_url('sales_pipeline/respond_reminder/' . $reminder['id']), ['id' => 'reminder-response-form', 'class' => 'disable-on-submit']); ?>
                                <div class="form-group">
                                    <label for="response" class="control-label"><?php echo _l('sales_pipeline_your_response'); ?> <span class="text-danger">*</span></label>
                                    <textarea id="response" name="response" class="form-control" rows="6" maxlength="2000" required autofocus placeholder="<?php echo html_escape(_l('sales_pipeline_response_placeholder')); ?>"></textarea>
                                    <p class="text-muted mtop5 mbot0"><?php echo _l('sales_pipeline_response_max_length'); ?></p>
                                </div>
                                <button type="submit" class="btn btn-primary sp-reminder-action"><i class="fa fa-paper-plane" aria-hidden="true"></i> <?php echo _l('sales_pipeline_send_response'); ?></button>
                            <?php echo form_close(); ?>
                        <?php } else { ?>
                            <div class="alert alert-info sp-reminder-supervisor-banner" role="alert">
                                <i class="fa fa-eye" aria-hidden="true" style="margin-right: 6px;"></i>
                                <?php echo _l('sp_reminder_supervisor_mode_notice', [html_escape(trim((string) ($reminder['staff_name'] ?? '')) ?: ('#' . (int) $reminder['staff_id']))]); ?>
                            </div>
                            <div class="alert alert-warning"><?php echo _l('sales_pipeline_reminder_manager_read_only'); ?></div>
                        <?php } ?>

                        <?php if (!empty($reminder['entity_url'])) { ?>
                            <a href="<?php echo html_escape($reminder['entity_url']); ?>" class="btn btn-default sp-reminder-action mtop15">
                                <i class="fa fa-external-link" aria-hidden="true"></i>
                                <?php echo html_escape($reminder['detail_action_label']); ?>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
