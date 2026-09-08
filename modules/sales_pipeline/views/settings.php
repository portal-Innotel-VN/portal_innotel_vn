<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <i class="fa fa-cogs"></i> <?php echo $title; ?>
                            <a href="<?php echo admin_url('sales_pipeline'); ?>" class="btn btn-default pull-right">
                                <i class="fa fa-arrow-left"></i> <?php echo _l('sales_pipeline_back'); ?>
                            </a>
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <div class="horizontal-scrollable-tabs">
                            <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
                            <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
                            <div class="horizontal-tabs">
                                <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
                                    <li role="presentation" class="active">
                                        <a href="#statuses" aria-controls="statuses" role="tab" data-toggle="tab">
                                            <?php echo _l('sales_pipeline_settings_statuses'); ?>
                                        </a>
                                    </li>
                                    <li role="presentation">
                                        <a href="#sources" aria-controls="sources" role="tab" data-toggle="tab">
                                            <?php echo _l('sales_pipeline_settings_sources'); ?>
                                        </a>
                                    </li>
                                    <li role="presentation">
                                        <a href="#reminders" aria-controls="reminders" role="tab" data-toggle="tab">
                                            <?php echo _l('sales_pipeline_settings_reminders'); ?>
                                        </a>
                                    </li>
                                    <li role="presentation">
                                        <a href="#performance" aria-controls="performance" role="tab" data-toggle="tab">
                                            <?php echo _l('sales_pipeline_settings_performance'); ?>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="tab-content mtop15">
                            <!-- TAB TRẠNG THÁI -->
                            <div role="tabpanel" class="tab-pane active" id="statuses">
                                <a href="#" class="btn btn-info mbot15" data-toggle="modal" data-target="#status_modal" onclick="reset_status_modal(); return false;">
                                    <i class="fa fa-plus"></i> <?php echo _l('sales_pipeline_add_status'); ?>
                                </a>
                                <table class="table table-bordered dt-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th><?php echo _l('sales_pipeline_status_name'); ?></th>
                                            <th><?php echo _l('sales_pipeline_status_color'); ?></th>
                                            <th><?php echo _l('sales_pipeline_status_order'); ?></th>
                                            <th><?php echo _l('sales_pipeline_status_is_won'); ?></th>
                                            <th><?php echo _l('sales_pipeline_status_is_lost'); ?></th>
                                            <th><?php echo _l('sales_pipeline_options'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($statuses as $status) { ?>
                                        <tr>
                                            <td><?php echo $status['id']; ?></td>
                                            <td><span class="label" style="background:<?php echo $status['color']; ?>"><?php echo html_escape($status['name']); ?></span></td>
                                            <td><?php echo $status['color']; ?></td>
                                            <td><?php echo $status['order']; ?></td>
                                            <td><?php echo $status['is_won'] ? '<i class="fa fa-check text-success"></i>' : ''; ?></td>
                                            <td><?php echo $status['is_lost'] ? '<i class="fa fa-check text-success"></i>' : ''; ?></td>
                                            <td>
                                                <a href="#" class="btn btn-default btn-icon" onclick="edit_status(this, <?php echo $status['id']; ?>); return false;" data-name="<?php echo html_escape($status['name']); ?>" data-color="<?php echo $status['color']; ?>" data-order="<?php echo $status['order']; ?>" data-is-won="<?php echo $status['is_won']; ?>" data-is-lost="<?php echo $status['is_lost']; ?>"><i class="fa fa-pencil"></i></a>
                                                <a href="<?php echo admin_url('sales_pipeline/delete_setting/status/' . $status['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-trash-o"></i></a>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- TAB NGUỒN KHÁCH HÀNG -->
                            <div role="tabpanel" class="tab-pane" id="sources">
                                <a href="#" class="btn btn-info mbot15" data-toggle="modal" data-target="#source_modal" onclick="reset_source_modal(); return false;">
                                    <i class="fa fa-plus"></i> <?php echo _l('sales_pipeline_add_source'); ?>
                                </a>
                                <table class="table table-bordered dt-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th><?php echo _l('sales_pipeline_source_name'); ?></th>
                                            <th><?php echo _l('sales_pipeline_options'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sources as $source) { ?>
                                        <tr>
                                            <td><?php echo $source['id']; ?></td>
                                            <td><?php echo html_escape($source['name']); ?></td>
                                            <td>
                                                <a href="#" class="btn btn-default btn-icon" onclick="edit_source(this, <?php echo $source['id']; ?>); return false;" data-name="<?php echo html_escape($source['name']); ?>"><i class="fa fa-pencil"></i></a>
                                                <a href="<?php echo admin_url('sales_pipeline/delete_setting/source/' . $source['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-trash-o"></i></a>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>

                            <div role="tabpanel" class="tab-pane" id="reminders">
                                <?php $ro = $reminder_options; $checked = function ($key) use ($ro) { return (string) $ro[$key] === '1' ? ' checked' : ''; }; ?>
                                <?php $this->load->view('sales_pipeline/partials/reminder_delivery_health', ['health' => $reminder_delivery_health]); ?>
                                <?php echo form_open(admin_url('sales_pipeline/settings'), ['id' => 'sp-reminder-settings-form', 'class' => 'sp-reminder-settings']); ?>
                                <input type="hidden" name="setting_type" value="reminder">
                                <div class="sp-reminder-section sp-reminder-section--global">
                                    <h5><i class="fa fa-power-off" aria-hidden="true"></i> <?php echo _l('sp_reminder_global_switch'); ?></h5>
                                    <div class="checkbox checkbox-primary"><input id="sp_reminder_global_enabled" name="sp_reminder_global_enabled" type="checkbox" value="1"<?php echo $checked('sp_reminder_global_enabled'); ?>><label for="sp_reminder_global_enabled"><?php echo _l('sp_reminder_global_switch'); ?></label></div>
                                    <div class="checkbox checkbox-primary"><input id="sp_reminder_skip_weekends" name="sp_reminder_skip_weekends" type="checkbox" value="1"<?php echo $checked('sp_reminder_skip_weekends'); ?>><label for="sp_reminder_skip_weekends"><?php echo _l('sp_reminder_skip_weekends_label'); ?></label></div>
                                    <div class="row mtop10">
                                        <div class="col-md-6">
                                            <?php echo render_input('sp_reminder_sla_hours', _l('sp_reminder_sla_hours_label'), $ro['sp_reminder_sla_hours'], 'number', ['min' => 1, 'max' => 720]); ?>
                                        </div>
                                    </div>
                                    <div class="row"><div class="col-md-6"><label for="sp_reminder_holiday_dates"><?php echo _l('sp_reminder_holiday_dates_label'); ?></label><textarea class="form-control" rows="3" id="sp_reminder_holiday_dates" name="sp_reminder_holiday_dates" placeholder="2026-01-01"><?php echo html_escape($ro['sp_reminder_holiday_dates']); ?></textarea></div><div class="col-md-3"><?php echo render_input('sp_reminder_quiet_hours_start', _l('sp_reminder_quiet_hours_label') . ' (' . _l('sp_reminder_start') . ')', $ro['sp_reminder_quiet_hours_start'], 'time'); ?></div><div class="col-md-3"><?php echo render_input('sp_reminder_quiet_hours_end', _l('sp_reminder_quiet_hours_label') . ' (' . _l('sp_reminder_end') . ')', $ro['sp_reminder_quiet_hours_end'], 'time'); ?></div></div>
                                </div>
                                <div class="sp-reminder-section sp-reminder-section--cc">
                                    <h5><i class="fa fa-envelope" aria-hidden="true"></i> <?php echo _l('sp_settings_reminder_email_cc_manager'); ?></h5>
                                    <div class="checkbox checkbox-primary">
                                        <input id="sp_reminder_email_cc_manager_enabled" name="sp_reminder_email_cc_manager_enabled" type="checkbox" value="1"<?php echo $checked('sp_reminder_email_cc_manager_enabled'); ?>>
                                        <label for="sp_reminder_email_cc_manager_enabled"><?php echo _l('sp_settings_reminder_email_cc_manager_help'); ?></label>
                                    </div>
                                    <div class="row mtop10">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="sp_reminder_email_cc_scope"><?php echo _l('sp_settings_reminder_email_cc_scope'); ?></label>
                                                <select name="sp_reminder_email_cc_scope" id="sp_reminder_email_cc_scope" class="form-control selectpicker">
                                                    <option value="all"<?php echo ($ro['sp_reminder_email_cc_scope'] ?? 'all') === 'all' ? ' selected' : ''; ?>><?php echo _l('sp_settings_reminder_email_cc_scope_all'); ?></option>
                                                    <option value="critical_only"<?php echo ($ro['sp_reminder_email_cc_scope'] ?? '') === 'critical_only' ? ' selected' : ''; ?>><?php echo _l('sp_settings_reminder_email_cc_scope_critical'); ?></option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-8">
                                            <?php echo render_input('sp_reminder_manager_fallback_emails', _l('sp_settings_reminder_email_cc_fallback'), $ro['sp_reminder_manager_fallback_emails'] ?? '', 'text', ['placeholder' => 'manager1@example.com, manager2@example.com']); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php
                                $groups = [
                                    'sp-reminder-section--deal' => ['title' => 'sp_reminder_section_deal', 'rules' => [
                                        ['sp_reminder_deal_pipeline', 'sp_reminder_deal_pipeline_label', [['sp_reminder_deal_pipeline_min_count','sp_reminder_deal_pipeline_min_count_label','number'],['sp_reminder_deal_pipeline_check_time','sp_reminder_check_time_label','time']]],
                                        ['sp_reminder_deal_stale', 'sp_reminder_deal_stale_label', [['sp_reminder_deal_stale_cutoff_days','sp_reminder_deal_stale_cutoff_label','number'],['sp_reminder_deal_stale_max_per_run','sp_reminder_deal_stale_max_label','number']]],
                                    ]],
                                    'sp-reminder-section--kpi' => ['title' => 'sp_reminder_section_kpi', 'rules' => [
                                        ['sp_reminder_est_daily', 'sp_reminder_daily', [['sp_reminder_est_daily_threshold','sp_reminder_threshold_label','number'],['sp_reminder_est_daily_time','sp_reminder_check_time_label','time']]],
                                        ['sp_reminder_est_monthly', 'sp_reminder_monthly', [['sp_reminder_est_monthly_d10','sp_reminder_monthly_d10','number'],['sp_reminder_est_monthly_d20','sp_reminder_monthly_d20','number'],['sp_reminder_est_monthly_final','sp_reminder_monthly_final','number'],['sp_reminder_est_monthly_time','sp_reminder_check_time_label','time']]],
                                        ['sp_reminder_est_weekly', 'sp_reminder_weekly', [['sp_reminder_est_weekly_target','sp_reminder_weekly_target','text'],['sp_reminder_est_weekly_midweek_time','sp_reminder_weekly_midweek_time','time'],['sp_reminder_est_weekly_final_time','sp_reminder_weekly_final_time','time']]],
                                    ]],
                                    'sp-reminder-section--lifecycle' => ['title' => 'sp_reminder_section_lifecycle', 'rules' => [
                                        ['sp_reminder_lc_draft','sp_reminder_lc_draft',[['sp_reminder_lc_draft_days','sp_reminder_days_label','number']]], ['sp_reminder_lc_sent','sp_reminder_lc_sent',[['sp_reminder_lc_sent_days','sp_reminder_days_label','number'],['sp_reminder_lc_sent_expiry_days','sp_reminder_lc_sent_expiry','number']]], ['sp_reminder_lc_declined','sp_reminder_lc_declined',[['sp_reminder_lc_declined_days','sp_reminder_days_label','number']]], ['sp_reminder_lc_expired','sp_reminder_lc_expired',[]], ['sp_reminder_lc_accepted','sp_reminder_lc_accepted',[]],
                                    ]],
                                ];
                                foreach ($groups as $class => $group) { ?>
                                    <div class="sp-reminder-section <?php echo $class; ?> sp-reminder-section--rule"><h5><?php echo _l($group['title']); ?></h5>
                                    <?php foreach ($group['rules'] as $rule) { $prefix = $rule[0]; $enabled = $prefix . '_enabled'; $channel = $prefix . '_channels'; $saved = explode(',', (string) $ro[$channel]); ?>
                                        <div class="sp-reminder-rule-row"><div class="checkbox checkbox-primary sp-reminder-rule-toggle"><input id="<?php echo $enabled; ?>" name="<?php echo $enabled; ?>" type="checkbox" value="1"<?php echo $checked($enabled); ?>><label for="<?php echo $enabled; ?>"><?php echo _l($rule[1]); ?></label></div><div class="sp-reminder-channels"><label class="checkbox-inline"><input name="<?php echo $channel; ?>_crm" type="checkbox" value="1"<?php echo in_array('crm', $saved, true) ? ' checked' : ''; ?>> <i class="fa fa-bell-o" aria-hidden="true"></i> CRM</label><label class="checkbox-inline"><input name="<?php echo $channel; ?>_email" type="checkbox" value="1"<?php echo in_array('email', $saved, true) ? ' checked' : ''; ?>> <i class="fa fa-envelope-o" aria-hidden="true"></i> Email</label></div><div class="sp-rule-fields"><?php foreach ($rule[2] as $field) { echo render_input($field[0], _l($field[1]), $ro[$field[0]], $field[2]); } ?></div></div>
                                    <?php } ?></div>
                                <?php } ?>
                                <div class="text-right"><button type="submit" class="btn btn-info"><i class="fa fa-save" aria-hidden="true"></i> <?php echo _l('sp_reminder_save_changes'); ?></button></div>
                                <?php echo form_close(); ?>
                            </div>

                            <!-- TAB ĐIỂM HIỆU SUẤT (PERFORMANCE SCORE) -->
                            <div role="tabpanel" class="tab-pane" id="performance">
                                <?php echo form_open(admin_url('sales_pipeline/settings'), ['id' => 'sp-performance-settings-form']); ?>
                                <input type="hidden" name="setting_type" value="performance">
                                <div class="panel_s">
                                    <div class="panel-body">
                                        <h5 class="bold"><?php echo _l('sales_pipeline_settings_performance_heading'); ?></h5>
                                        <p class="text-muted"><?php echo _l('sales_pipeline_settings_performance_help'); ?></p>
                                        <hr />
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="performance_response_target_percent" class="control-label">
                                                        <?php echo _l('performance_response_target_percent_label'); ?>
                                                    </label>
                                                    <div class="input-group">
                                                        <input type="number"
                                                               id="performance_response_target_percent"
                                                               name="performance_response_target_percent"
                                                               class="form-control"
                                                               min="1"
                                                               max="100"
                                                               step="any"
                                                               required
                                                               value="<?php echo html_escape($performance_options['performance_response_target_percent'] ?? '90'); ?>">
                                                        <span class="input-group-addon">%</span>
                                                    </div>
                                                    <p class="text-muted small mtop5">
                                                        <?php echo _l('performance_response_target_percent_help'); ?>
                                                    </p>
                                                </div>

                                                <div class="mtop25">
                                                    <button type="submit" class="btn btn-info">
                                                        <i class="fa fa-floppy-o" aria-hidden="true"></i> <?php echo _l('sales_pipeline_save_settings'); ?>
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="panel panel-default" style="border-left: 4px solid #28b8da; background-color: #f8fafc; border-radius: 4px; box-shadow: none;">
                                                    <div class="panel-body" style="padding: 16px 20px;">
                                                        <h5 class="bold" style="margin-top: 0; margin-bottom: 12px; color: #1e879e; font-size: 14px;">
                                                            <?php echo _l('sales_pipeline_perf_how_it_works_title'); ?>
                                                        </h5>
                                                        <div style="font-size: 13px; line-height: 1.6; color: #475569;">
                                                            <p style="margin-bottom: 10px;">
                                                                <?php echo _l('sales_pipeline_perf_weight_desc'); ?>
                                                            </p>
                                                            <p style="margin-bottom: 10px;">
                                                                <?php echo _l('sales_pipeline_perf_formula_desc'); ?>
                                                            </p>
                                                            <p style="margin-bottom: 10px; color: #64748b;">
                                                                <?php echo _l('sales_pipeline_perf_example_desc'); ?>
                                                            </p>
                                                            <p style="margin-bottom: 0; padding-top: 8px; border-top: 1px dashed #cbd5e1; font-size: 12px; color: #64748b;">
                                                                <?php echo _l('sales_pipeline_perf_sla_link_note'); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php echo form_close(); ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Trạng Thái -->
<div class="modal fade" id="status_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?php echo form_open(admin_url('sales_pipeline/settings')); ?>
        <input type="hidden" name="setting_type" value="status">
        <input type="hidden" name="id" id="status_id" value="">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo html_escape(_l('sales_pipeline_close')); ?>"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="status_modal_title"><?php echo _l('sales_pipeline_add_status'); ?></h4>
            </div>
            <div class="modal-body">
                <?php echo render_input('name', _l('sales_pipeline_status_name'), '', 'text', ['required' => 'true']); ?>
                <?php echo render_color_picker('color', _l('sales_pipeline_status_color')); ?>
                <?php echo render_input('order', _l('sales_pipeline_display_order'), '0', 'number'); ?>
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" name="is_won" id="is_won" value="1">
                    <label for="is_won"><?php echo _l('sales_pipeline_is_won_status'); ?></label>
                </div>
                <div class="checkbox checkbox-danger">
                    <input type="checkbox" name="is_lost" id="is_lost" value="1">
                    <label for="is_lost"><?php echo _l('sales_pipeline_is_lost_status'); ?></label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="submit" class="btn btn-info"><?php echo _l('submit'); ?></button>
            </div>
        </div>
        <?php echo form_close(); ?>
    </div>
</div>

<!-- Modal Nguồn Khách Hàng -->
<div class="modal fade" id="source_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?php echo form_open(admin_url('sales_pipeline/settings')); ?>
        <input type="hidden" name="setting_type" value="source">
        <input type="hidden" name="id" id="source_id_input" value="">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo html_escape(_l('sales_pipeline_close')); ?>"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="source_modal_title"><?php echo _l('sales_pipeline_add_source_title'); ?></h4>
            </div>
            <div class="modal-body">
                <?php echo render_input('name', _l('sales_pipeline_source_name'), '', 'text', ['required' => 'true']); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="submit" class="btn btn-info"><?php echo _l('submit'); ?></button>
            </div>
        </div>
        <?php echo form_close(); ?>
    </div>
</div>

<?php init_tail(); ?>
<script>
    $(function(){
        initDataTable('.dt-table');
        if (window.location.hash && $('.nav-tabs a[href="' + window.location.hash + '"]').length) {
            $('.nav-tabs a[href="' + window.location.hash + '"]').tab('show');
        }
        $('.nav-tabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            if (history.replaceState) {
                history.replaceState(null, null, e.target.hash);
            } else {
                window.location.hash = e.target.hash;
            }
        });
        $('#sp_reminder_global_enabled').on('change', function () { $('.sp-reminder-section--rule').toggleClass('sp-reminder-section--dimmed', !this.checked); }).trigger('change');
        $('.sp-reminder-rule-toggle input').on('change', function () { $(this).closest('.sp-reminder-rule-row').find('.sp-reminder-channels,.sp-rule-fields').toggleClass('sp-reminder-section--dimmed', !this.checked); }).trigger('change');
        $('#sp-reminder-settings-form').on('submit', function () { $('.sp-reminder-section--dimmed').removeClass('sp-reminder-section--dimmed'); });

        function postDeliveryAction(url, $button) {
            var data = {};
            if (typeof csrfData !== 'undefined' && csrfData.token_name) {
                data[csrfData.token_name] = csrfData.hash;
            }
            $button.prop('disabled', true);
            $.post(url, data).done(function (response) {
                alert_float('success', response.message);
                window.location.reload();
            }).fail(function (xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : <?php echo json_encode(_l('sales_pipeline_reminder_delivery_request_failed'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
                alert_float('danger', message);
                $button.prop('disabled', false);
            });
        }
        $('.sp-delivery-retry').on('click', function () {
            if (window.confirm(<?php echo json_encode(_l('sales_pipeline_reminder_delivery_retry_confirm'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)) {
                postDeliveryAction($(this).data('url'), $(this));
            }
        });
        $('.sp-delivery-resume').on('click', function () {
            if (window.confirm(<?php echo json_encode(_l('sales_pipeline_reminder_delivery_resume_confirm'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)) {
                postDeliveryAction($(this).data('url'), $(this));
            }
        });
    });

    function reset_status_modal() {
        $('#status_modal_title').text('<?php echo _l('sales_pipeline_add_status'); ?>');
        $('#status_modal input[name="id"]').val('');
        $('#status_modal input[name="name"]').val('');
        $('#status_modal input[name="color"]').val('#333333');
        $('#status_modal input[name="color"]').next('.input-group-addon').find('i').css('background-color', '#333333');
        $('#status_modal input[name="order"]').val('0');
        $('#status_modal input[name="is_won"]').prop('checked', false);
        $('#status_modal input[name="is_lost"]').prop('checked', false);
    }

    function edit_status(invoker, id) {
        var name = $(invoker).data('name');
        var color = $(invoker).data('color');
        var order = $(invoker).data('order');
        var is_won = $(invoker).data('is-won');
        var is_lost = $(invoker).data('is-lost');

        $('#status_modal_title').text('<?php echo _l('sales_pipeline_edit_status'); ?>');
        $('#status_modal input[name="id"]').val(id);
        $('#status_modal input[name="name"]').val(name);
        $('#status_modal input[name="color"]').val(color);
        $('#status_modal input[name="color"]').next('.input-group-addon').find('i').css('background-color', color);
        $('#status_modal input[name="order"]').val(order);
        $('#status_modal input[name="is_won"]').prop('checked', is_won == 1);
        $('#status_modal input[name="is_lost"]').prop('checked', is_lost == 1);
        $('#status_modal').modal('show');
    }

    function reset_source_modal() {
        $('#source_modal_title').text('<?php echo _l('sales_pipeline_add_source_title'); ?>');
        $('#source_modal input[name="id"]').val('');
        $('#source_modal input[name="name"]').val('');
    }

    function edit_source(invoker, id) {
        var name = $(invoker).data('name');
        $('#source_modal_title').text('<?php echo _l('sales_pipeline_edit_source_title'); ?>');
        $('#source_modal input[name="id"]').val(id);
        $('#source_modal input[name="name"]').val(name);
        $('#source_modal').modal('show');
    }
</script>
</body>
</html>
