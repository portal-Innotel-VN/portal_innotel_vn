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

                                <!-- SECTION 1: CẤU HÌNH CHUNG & THỜI GIAN GỬI -->
                                <div class="sp-reminder-section sp-reminder-section--global">
                                    <h5><i class="fa fa-sliders" aria-hidden="true"></i> <?php echo _l('sp_reminder_global_switch'); ?></h5>
                                    <div class="row">
                                        <div class="col-md-7 col-sm-12">
                                            <div class="checkbox checkbox-primary">
                                                <input id="sp_reminder_global_enabled" name="sp_reminder_global_enabled" type="checkbox" value="1"<?php echo $checked('sp_reminder_global_enabled'); ?>>
                                                <label for="sp_reminder_global_enabled" class="bold"><?php echo _l('sp_reminder_global_switch'); ?></label>
                                            </div>
                                            <div class="checkbox checkbox-primary">
                                                <input id="sp_reminder_skip_weekends" name="sp_reminder_skip_weekends" type="checkbox" value="1"<?php echo $checked('sp_reminder_skip_weekends'); ?>>
                                                <label for="sp_reminder_skip_weekends"><?php echo _l('sp_reminder_skip_weekends_label'); ?></label>
                                            </div>
                                            <div class="form-group sp-compact-field mtop15">
                                                <label for="sp_reminder_sla_hours" class="control-label"><?php echo _l('sp_reminder_sla_hours_label'); ?></label>
                                                <div class="input-group sp-compact-number">
                                                    <input type="number" id="sp_reminder_sla_hours" name="sp_reminder_sla_hours" class="form-control" min="1" max="720" value="<?php echo html_escape($ro['sp_reminder_sla_hours']); ?>">
                                                    <span class="input-group-addon"><?php echo _l('hours'); ?></span>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="sp_reminder_holiday_dates" class="control-label"><?php echo _l('sp_reminder_holiday_dates_label'); ?></label>
                                                <textarea class="form-control sp-holiday-dates-textarea" rows="3" id="sp_reminder_holiday_dates" name="sp_reminder_holiday_dates" placeholder="2026-01-01&#10;2026-04-30&#10;2026-05-01"><?php echo html_escape($ro['sp_reminder_holiday_dates']); ?></textarea>
                                            </div>
                                            <div class="row">
                                                <div class="col-xs-12 col-sm-6">
                                                    <div class="form-group sp-compact-time">
                                                        <label for="sp_reminder_quiet_hours_start" class="control-label"><?php echo _l('sp_reminder_quiet_hours_label') . ' (' . _l('sp_reminder_start') . ')'; ?></label>
                                                        <input type="time" id="sp_reminder_quiet_hours_start" name="sp_reminder_quiet_hours_start" class="form-control" value="<?php echo html_escape($ro['sp_reminder_quiet_hours_start']); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-xs-12 col-sm-6">
                                                    <div class="form-group sp-compact-time">
                                                        <label for="sp_reminder_quiet_hours_end" class="control-label"><?php echo _l('sp_reminder_quiet_hours_label') . ' (' . _l('sp_reminder_end') . ')'; ?></label>
                                                        <input type="time" id="sp_reminder_quiet_hours_end" name="sp_reminder_quiet_hours_end" class="form-control" value="<?php echo html_escape($ro['sp_reminder_quiet_hours_end']); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-5 col-sm-12">
                                            <div class="sp-guide-panel sp-guide-panel--global">
                                                <div class="sp-guide-panel__title">
                                                    <i class="fa fa-info-circle text-success" aria-hidden="true"></i> <?php echo _l('sp_reminder_guide_global_title'); ?>
                                                </div>
                                                <div class="sp-guide-panel__body">
                                                    <p><?php echo _l('sp_reminder_guide_global_schedule'); ?></p>
                                                    <p><?php echo _l('sp_reminder_guide_global_holidays'); ?></p>
                                                    <p><?php echo _l('sp_reminder_guide_global_quiet_hours'); ?></p>
                                                    <p class="sp-guide-panel__note"><?php echo _l('sp_reminder_guide_global_sla'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- SECTION 2: NGƯỜI NHẬN CẢNH BÁO QUẢN LÝ (V2) & EMAIL CC -->
                                <div class="sp-reminder-section sp-reminder-section--cc">
                                    <h5><i class="fa fa-users text-primary" aria-hidden="true"></i> <?php echo _l('sp_settings_reminder_manager_roles_title'); ?></h5>
                                    <div class="row">
                                        <div class="col-md-7 col-sm-12">
                                            <!-- Switch Policy V2 -->
                                            <div class="checkbox checkbox-primary">
                                                <input id="sp_reminder_recipient_policy_v2_enabled" name="sp_reminder_recipient_policy_v2_enabled" type="checkbox" value="1"<?php echo $checked('sp_reminder_recipient_policy_v2_enabled'); ?>>
                                                <label for="sp_reminder_recipient_policy_v2_enabled" class="bold text-primary">
                                                    <?php echo _l('sp_settings_reminder_policy_v2_enabled_label'); ?>
                                                </label>
                                                <p class="text-muted small mtop5"><?php echo _l('sp_settings_reminder_policy_v2_enabled_help'); ?></p>
                                            </div>

                                            <!-- Source Mode Selection -->
                                            <div class="form-group mtop15" id="sp-manager-source-container">
                                                <label class="control-label bold"><?php echo _l('sp_settings_reminder_manager_source_label'); ?></label>
                                                <div class="radio radio-primary">
                                                    <input type="radio" id="sp_source_explicit_view" name="sp_reminder_manager_recipient_source" value="explicit_view"<?php echo ($ro['sp_reminder_manager_recipient_source'] ?? 'explicit_view') === 'explicit_view' ? ' checked' : ''; ?>>
                                                    <label for="sp_source_explicit_view"><?php echo _l('sp_settings_reminder_source_explicit_view'); ?></label>
                                                </div>
                                                <div class="radio radio-primary">
                                                    <input type="radio" id="sp_source_selected_staff" name="sp_reminder_manager_recipient_source" value="selected_staff"<?php echo ($ro['sp_reminder_manager_recipient_source'] ?? '') === 'selected_staff' ? ' checked' : ''; ?>>
                                                    <label for="sp_source_selected_staff"><?php echo _l('sp_settings_reminder_source_selected_staff'); ?></label>
                                                </div>
                                            </div>

                                            <!-- Multiselect for selected_staff -->
                                            <?php
                                            $selectedStaffIds = json_decode((string) ($ro['sp_reminder_manager_recipient_staff_ids'] ?? '[]'), true);
                                            if (!is_array($selectedStaffIds)) { $selectedStaffIds = []; }
                                            ?>
                                            <div class="form-group mtop15" id="sp-selected-staff-wrapper" style="<?php echo ($ro['sp_reminder_manager_recipient_source'] ?? 'explicit_view') === 'selected_staff' ? '' : 'display:none;'; ?>">
                                                <label for="sp_reminder_manager_recipient_staff_ids" class="control-label bold"><?php echo _l('sp_settings_reminder_selected_staff_label'); ?></label>
                                                <select name="sp_reminder_manager_recipient_staff_ids[]" id="sp_reminder_manager_recipient_staff_ids" class="form-control selectpicker" multiple data-live-search="true" data-actions-box="true">
                                                    <?php if (!empty($staff_members)): ?>
                                                        <?php foreach ($staff_members as $member): ?>
                                                            <?php
                                                            $sid = (int) $member['staffid'];
                                                            $isSelected = in_array($sid, $selectedStaffIds, true);
                                                            $fullName = trim($member['firstname'] . ' ' . $member['lastname']);
                                                            $adminBadge = (int) $member['admin'] === 1 ? ' [' . _l('admin') . ']' : '';
                                                            ?>
                                                            <option value="<?php echo $sid; ?>"<?php echo $isSelected ? ' selected' : ''; ?>>
                                                                <?php echo html_escape($fullName . $adminBadge); ?> (<?php echo html_escape($member['email']); ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                                <p class="text-muted small mtop5"><?php echo _l('sp_settings_reminder_selected_staff_help'); ?></p>
                                            </div>

                                            <hr class="hr-10">

                                            <!-- Email CC options -->
                                            <div class="checkbox checkbox-primary">
                                                <input id="sp_reminder_email_cc_manager_enabled" name="sp_reminder_email_cc_manager_enabled" type="checkbox" value="1"<?php echo $checked('sp_reminder_email_cc_manager_enabled'); ?>>
                                                <label for="sp_reminder_email_cc_manager_enabled" class="bold"><?php echo _l('sp_settings_reminder_email_cc_manager_help'); ?></label>
                                            </div>
                                            <div class="form-group sp-compact-field mtop15">
                                                <label for="sp_reminder_email_cc_scope" class="control-label"><?php echo _l('sp_settings_reminder_email_cc_scope'); ?></label>
                                                <select name="sp_reminder_email_cc_scope" id="sp_reminder_email_cc_scope" class="form-control selectpicker">
                                                    <option value="all"<?php echo ($ro['sp_reminder_email_cc_scope'] ?? 'all') === 'all' ? ' selected' : ''; ?>><?php echo _l('sp_settings_reminder_email_cc_scope_all'); ?></option>
                                                    <option value="critical_only"<?php echo ($ro['sp_reminder_email_cc_scope'] ?? '') === 'critical_only' ? ' selected' : ''; ?>><?php echo _l('sp_settings_reminder_email_cc_scope_critical'); ?></option>
                                                </select>
                                            </div>
                                            <div class="form-group" id="sp-fallback-emails-wrapper">
                                                <label for="sp_reminder_manager_fallback_emails" class="control-label"><?php echo _l('sp_settings_reminder_email_cc_fallback'); ?></label>
                                                <input type="text" id="sp_reminder_manager_fallback_emails" name="sp_reminder_manager_fallback_emails" class="form-control" value="<?php echo html_escape($ro['sp_reminder_manager_fallback_emails'] ?? ''); ?>" placeholder="manager1@example.com, manager2@example.com">
                                                <p class="text-muted small mtop5"><?php echo _l('sp_settings_reminder_email_cc_fallback_help'); ?></p>
                                            </div>

                                            <!-- Live Preview Trigger -->
                                            <div class="mtop15">
                                                <button type="button" class="btn btn-info btn-sm" id="sp-btn-preview-recipients">
                                                    <i class="fa fa-eye" aria-hidden="true"></i> <?php echo _l('sp_reminder_preview_recipients_btn'); ?>
                                                </button>
                                                <span id="sp-preview-loading" style="display:none;" class="mleft10 text-muted">
                                                    <i class="fa fa-spinner fa-spin"></i> <?php echo _l('loading'); ?>...
                                                </span>
                                            </div>
                                            <div id="sp-recipients-preview-container" class="mtop15" style="display:none;"></div>
                                        </div>
                                        <div class="col-md-5 col-sm-12">
                                            <div class="sp-guide-panel sp-guide-panel--cc">
                                                <div class="sp-guide-panel__title">
                                                    <i class="fa fa-shield text-primary" aria-hidden="true"></i> <?php echo _l('sp_reminder_guide_roles_title'); ?>
                                                </div>
                                                <div class="sp-guide-panel__body">
                                                    <p><?php echo _l('sp_reminder_guide_roles_p1'); ?></p>
                                                    <p><?php echo _l('sp_reminder_guide_roles_p2'); ?></p>
                                                    <p><?php echo _l('sp_reminder_guide_roles_p3'); ?></p>
                                                    <p class="sp-guide-panel__note text-warning"><?php echo _l('sp_reminder_guide_roles_note'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- SECTION 3: KÊNH WHATSAPP GATEWAY -->
                                <div class="sp-reminder-section sp-reminder-section--whatsapp">
                                    <h5><i class="fa fa-whatsapp text-success" aria-hidden="true"></i> <?php echo _l('sp_reminder_whatsapp_settings_title'); ?></h5>
                                    <div class="row">
                                        <div class="col-md-7 col-sm-12">
                                            <div class="checkbox checkbox-primary">
                                                <input id="sp_reminder_whatsapp_enabled" name="sp_reminder_whatsapp_enabled" type="checkbox" value="1"<?php echo $checked('sp_reminder_whatsapp_enabled'); ?>>
                                                <label for="sp_reminder_whatsapp_enabled" class="bold"><?php echo _l('sp_reminder_whatsapp_enable_label'); ?></label>
                                            </div>
                                            <div class="row mtop15">
                                                <div class="col-xs-12 col-sm-6">
                                                    <div class="form-group">
                                                        <label for="sp_reminder_whatsapp_endpoint" class="control-label"><?php echo _l('sp_reminder_whatsapp_endpoint_label'); ?></label>
                                                        <input type="text" id="sp_reminder_whatsapp_endpoint" name="sp_reminder_whatsapp_endpoint" class="form-control" value="<?php echo html_escape($ro['sp_reminder_whatsapp_endpoint'] ?? 'http://127.0.0.1:3050/api/v1/messages/send'); ?>" placeholder="http://127.0.0.1:3050/api/v1/messages/send">
                                                    </div>
                                                </div>
                                                <div class="col-xs-12 col-sm-6">
                                                    <div class="form-group">
                                                        <label for="sp_reminder_whatsapp_secret_key" class="control-label"><?php echo _l('sp_reminder_whatsapp_secret_label'); ?></label>
                                                        <div class="input-group">
                                                            <input type="password" id="sp_reminder_whatsapp_secret_key" name="sp_reminder_whatsapp_secret_key" class="form-control" value="<?php echo html_escape($ro['sp_reminder_whatsapp_secret_key'] ?? ''); ?>" placeholder="<?php echo html_escape(_l('sp_reminder_whatsapp_secret_placeholder')); ?>" autocomplete="new-password">
                                                            <span class="input-group-btn">
                                                                <button class="btn btn-default" type="button" id="btn-toggle-wa-secret"><i class="fa fa-eye"></i></button>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-xs-12 col-sm-6">
                                                    <div class="form-group">
                                                        <label for="sp_reminder_whatsapp_manager_mode" class="control-label"><?php echo _l('sp_reminder_whatsapp_manager_mode_label'); ?></label>
                                                        <select name="sp_reminder_whatsapp_manager_mode" id="sp_reminder_whatsapp_manager_mode" class="form-control selectpicker">
                                                            <option value="group_only"<?php echo ($ro['sp_reminder_whatsapp_manager_mode'] ?? 'group_only') === 'group_only' ? ' selected' : ''; ?>><?php echo _l('sp_reminder_whatsapp_mode_group_only'); ?></option>
                                                            <option value="direct_only"<?php echo ($ro['sp_reminder_whatsapp_manager_mode'] ?? '') === 'direct_only' ? ' selected' : ''; ?>><?php echo _l('sp_reminder_whatsapp_mode_direct_only'); ?></option>
                                                            <option value="both"<?php echo ($ro['sp_reminder_whatsapp_manager_mode'] ?? '') === 'both' ? ' selected' : ''; ?>><?php echo _l('sp_reminder_whatsapp_mode_both'); ?></option>
                                                        </select>
                                                        <p id="sp-wa-direct-mode-hint" class="text-info mtop5 small" style="display:none; line-height: 1.4;">
                                                            <i class="fa fa-info-circle"></i> <?php echo _l('sp_reminder_whatsapp_mode_direct_hint'); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="col-xs-12 col-sm-6" id="sp-wa-group-jid-wrapper">
                                                    <div class="form-group">
                                                        <label for="sp_reminder_whatsapp_group_jid" class="control-label"><?php echo _l('sp_reminder_whatsapp_group_jid_label'); ?></label>
                                                        <div class="input-group">
                                                            <input type="text" id="sp_reminder_whatsapp_group_jid" name="sp_reminder_whatsapp_group_jid" class="form-control" value="<?php echo html_escape($ro['sp_reminder_whatsapp_group_jid'] ?? ''); ?>" placeholder="120363xxxxxxxxx@g.us">
                                                            <span class="input-group-btn">
                                                                <button class="btn btn-default" type="button" id="btn-fetch-wa-groups" title="<?php echo html_escape(_l('sp_reminder_whatsapp_fetch_groups_btn')); ?>">
                                                                    <i class="fa fa-refresh text-info"></i>
                                                                </button>
                                                            </span>
                                                        </div>
                                                        <div id="wa-groups-dropdown-container" class="mtop5" style="display:none;">
                                                            <select id="wa-groups-select" class="form-control">
                                                                <option value=""><?php echo html_escape(_l('sp_reminder_whatsapp_select_group_placeholder')); ?></option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="sp_reminder_whatsapp_base_url" class="control-label"><?php echo _l('sp_reminder_whatsapp_base_url_label'); ?></label>
                                                <input type="text" id="sp_reminder_whatsapp_base_url" name="sp_reminder_whatsapp_base_url" class="form-control" value="<?php echo html_escape($ro['sp_reminder_whatsapp_base_url'] ?? ''); ?>" placeholder="http://192.168.1.50:8000">
                                            </div>
                                            <div class="row">
                                                <div class="col-xs-12 col-sm-4">
                                                    <div class="form-group sp-compact-number">
                                                        <label for="sp_reminder_whatsapp_timeout_seconds" class="control-label"><?php echo _l('sp_reminder_whatsapp_timeout_label'); ?></label>
                                                        <div class="input-group">
                                                            <input type="number" id="sp_reminder_whatsapp_timeout_seconds" name="sp_reminder_whatsapp_timeout_seconds" class="form-control" min="1" max="30" value="<?php echo html_escape($ro['sp_reminder_whatsapp_timeout_seconds'] ?? '5'); ?>">
                                                            <span class="input-group-addon"><?php echo _l('seconds'); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-xs-12 col-sm-4">
                                                    <div class="form-group sp-compact-number">
                                                        <label for="sp_reminder_delivery_whatsapp_hourly_limit" class="control-label"><?php echo _l('sp_reminder_whatsapp_hourly_limit_label'); ?></label>
                                                        <input type="number" id="sp_reminder_delivery_whatsapp_hourly_limit" name="sp_reminder_delivery_whatsapp_hourly_limit" class="form-control" min="1" max="1000" value="<?php echo html_escape($ro['sp_reminder_delivery_whatsapp_hourly_limit'] ?? '60'); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-xs-12 col-sm-4" style="padding-top: 25px;">
                                                    <button type="button" class="btn btn-default" id="btn-test-wa-conn">
                                                        <i class="fa fa-paper-plane text-success" aria-hidden="true"></i> <?php echo _l('sp_reminder_whatsapp_test_button'); ?>
                                                    </button>
                                                    <div id="wa-test-status" class="mtop5 small" style="word-break: break-word; white-space: normal; max-width: 100%;"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-5 col-sm-12">
                                            <div class="sp-guide-panel sp-guide-panel--whatsapp">
                                                <div class="sp-guide-panel__title">
                                                    <i class="fa fa-whatsapp text-success" aria-hidden="true"></i> <?php echo _l('sp_reminder_guide_whatsapp_title'); ?>
                                                </div>
                                                <div class="sp-guide-panel__body">
                                                    <p><?php echo _l('sp_reminder_guide_whatsapp_gateway'); ?></p>
                                                    <p><?php echo _l('sp_reminder_guide_whatsapp_modes'); ?></p>
                                                    <p><?php echo _l('sp_reminder_guide_whatsapp_deeplink'); ?></p>
                                                    <p class="sp-guide-panel__note"><?php echo _l('sp_reminder_guide_whatsapp_limits'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- SECTION 4, 5, 6: CÁC QUY TẮC NHẮC NHỞ NGHIỆP VỤ -->
                                <?php
                                $groups = [
                                    'sp-reminder-section--deal' => [
                                        'title' => 'sp_reminder_section_deal',
                                        'guide_title' => 'sp_reminder_guide_deal_title',
                                        'guide_desc' => 'sp_reminder_guide_deal_desc',
                                        'guide_class' => 'sp-guide-panel--deal',
                                        'guide_icon' => 'fa-briefcase text-info',
                                        'rules' => [
                                            ['sp_reminder_deal_pipeline', 'sp_reminder_deal_pipeline_label', [
                                                ['sp_reminder_deal_pipeline_min_count','sp_reminder_deal_pipeline_min_count_label','number'],
                                                ['sp_reminder_deal_pipeline_check_time','sp_reminder_check_time_label','time']
                                            ]],
                                            ['sp_reminder_deal_stale', 'sp_reminder_deal_stale_label', [
                                                ['sp_reminder_deal_stale_cutoff_days','sp_reminder_deal_stale_cutoff_label','number'],
                                                ['sp_reminder_deal_stale_max_per_run','sp_reminder_deal_stale_max_label','number']
                                            ]],
                                        ]
                                    ],
                                    'sp-reminder-section--kpi' => [
                                        'title' => 'sp_reminder_section_kpi',
                                        'guide_title' => 'sp_reminder_guide_kpi_title',
                                        'guide_desc' => 'sp_reminder_guide_kpi_desc',
                                        'guide_class' => 'sp-guide-panel--kpi',
                                        'guide_icon' => 'fa-line-chart text-warning',
                                        'rules' => [
                                            ['sp_reminder_est_daily', 'sp_reminder_daily', [
                                                ['sp_reminder_est_daily_threshold','sp_reminder_threshold_label','number'],
                                                ['sp_reminder_est_daily_time','sp_reminder_check_time_label','time']
                                            ]],
                                            ['sp_reminder_est_monthly', 'sp_reminder_monthly', [
                                                ['sp_reminder_est_monthly_d10','sp_reminder_monthly_d10','number'],
                                                ['sp_reminder_est_monthly_d20','sp_reminder_monthly_d20','number'],
                                                ['sp_reminder_est_monthly_final','sp_reminder_monthly_final','number'],
                                                ['sp_reminder_est_monthly_time','sp_reminder_check_time_label','time']
                                            ]],
                                            ['sp_reminder_est_weekly', 'sp_reminder_weekly', [
                                                ['sp_reminder_est_weekly_target','sp_reminder_weekly_target','text'],
                                                ['sp_reminder_est_weekly_midweek_time','sp_reminder_weekly_midweek_time','time'],
                                                ['sp_reminder_est_weekly_final_time','sp_reminder_weekly_final_time','time']
                                            ]],
                                        ]
                                    ],
                                    'sp-reminder-section--lifecycle' => [
                                        'title' => 'sp_reminder_section_lifecycle',
                                        'guide_title' => 'sp_reminder_guide_lifecycle_title',
                                        'guide_desc' => 'sp_reminder_guide_lifecycle_desc',
                                        'guide_class' => 'sp-guide-panel--lifecycle',
                                        'guide_icon' => 'fa-refresh text-danger',
                                        'rules' => [
                                            ['sp_reminder_lc_draft','sp_reminder_lc_draft',[['sp_reminder_lc_draft_days','sp_reminder_days_label','number']]],
                                            ['sp_reminder_lc_sent','sp_reminder_lc_sent',[['sp_reminder_lc_sent_days','sp_reminder_days_label','number'],['sp_reminder_lc_sent_expiry_days','sp_reminder_lc_sent_expiry','number']]],
                                            ['sp_reminder_lc_declined','sp_reminder_lc_declined',[['sp_reminder_lc_declined_days','sp_reminder_days_label','number']]],
                                            ['sp_reminder_lc_expired','sp_reminder_lc_expired',[]],
                                            ['sp_reminder_lc_accepted','sp_reminder_lc_accepted',[]],
                                        ]
                                    ],
                                ];
                                foreach ($groups as $class => $group) { ?>
                                    <div class="sp-reminder-section <?php echo $class; ?> sp-reminder-section--rule">
                                        <h5><?php echo _l($group['title']); ?></h5>
                                        <div class="row">
                                            <div class="col-md-7 col-sm-12">
                                                <?php foreach ($group['rules'] as $rule) {
                                                    $prefix = $rule[0];
                                                    $enabled = $prefix . '_enabled';
                                                    $channel = $prefix . '_channels';
                                                    $saved = explode(',', (string) $ro[$channel]);
                                                ?>
                                                    <div class="sp-reminder-rule-row">
                                                        <div class="checkbox checkbox-primary sp-reminder-rule-toggle">
                                                            <input id="<?php echo $enabled; ?>" name="<?php echo $enabled; ?>" type="checkbox" value="1"<?php echo $checked($enabled); ?>>
                                                            <label for="<?php echo $enabled; ?>" class="bold"><?php echo _l($rule[1]); ?></label>
                                                        </div>
                                                        <div class="sp-reminder-channels">
                                                            <label class="checkbox-inline"><input name="<?php echo $channel; ?>_crm" type="checkbox" value="1"<?php echo in_array('crm', $saved, true) ? ' checked' : ''; ?>> <i class="fa fa-bell-o" aria-hidden="true"></i> CRM</label>
                                                            <label class="checkbox-inline"><input name="<?php echo $channel; ?>_email" type="checkbox" value="1"<?php echo in_array('email', $saved, true) ? ' checked' : ''; ?>> <i class="fa fa-envelope-o" aria-hidden="true"></i> Email</label>
                                                            <label class="checkbox-inline"><input name="<?php echo $channel; ?>_whatsapp" type="checkbox" value="1"<?php echo in_array('whatsapp', $saved, true) ? ' checked' : ''; ?>> <i class="fa fa-whatsapp text-success" aria-hidden="true"></i> WhatsApp</label>
                                                        </div>
                                                        <?php if (!empty($rule[2])) { ?>
                                                            <div class="sp-rule-fields">
                                                                <?php foreach ($rule[2] as $field) {
                                                                    echo render_input($field[0], _l($field[1]), $ro[$field[0]], $field[2]);
                                                                } ?>
                                                            </div>
                                                        <?php } ?>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                            <div class="col-md-5 col-sm-12">
                                                <div class="sp-guide-panel <?php echo $group['guide_class']; ?>">
                                                    <div class="sp-guide-panel__title">
                                                        <i class="fa <?php echo $group['guide_icon']; ?>" aria-hidden="true"></i> <?php echo _l($group['guide_title']); ?>
                                                    </div>
                                                    <div class="sp-guide-panel__body">
                                                        <p><?php echo _l($group['guide_desc']); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>

                                <div class="text-right mtop15">
                                    <button type="submit" class="btn btn-info">
                                        <i class="fa fa-floppy-o" aria-hidden="true"></i> <?php echo _l('sp_reminder_save_changes'); ?>
                                    </button>
                                </div>
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

        function escapeHtml(str) {
            return $('<div>').text(str || '').html();
        }

        function postDeliveryAction(url, $button) {
            var data = {};
            if (typeof csrfData !== 'undefined' && csrfData.token_name) {
                data[csrfData.token_name] = csrfData.hash;
            }
            $button.prop('disabled', true);
            $.post(url, data).done(function (response) {
                alert_float('success', escapeHtml(response.message));
                window.location.reload();
            }).fail(function (xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : <?php echo json_encode(_l('sales_pipeline_reminder_delivery_request_failed'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
                alert_float('danger', escapeHtml(message));
                $button.prop('disabled', false);
            });
        }
        $('#btn-toggle-wa-secret').on('click', function () {
            var $input = $('#sp_reminder_whatsapp_secret_key');
            var $icon = $(this).find('i');
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });

        $('#btn-test-wa-conn').on('click', function () {
            var $btn = $(this);
            var $status = $('#wa-test-status');
            var endpoint = $('#sp_reminder_whatsapp_endpoint').val();
            var secretKey = $('#sp_reminder_whatsapp_secret_key').val();
            var groupJid = $('#sp_reminder_whatsapp_group_jid').val();

            var testConnectingText = <?php echo json_encode(_l('sp_reminder_whatsapp_test_connecting'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            var testConnectingGwText = <?php echo json_encode(_l('sp_reminder_whatsapp_test_connecting_gw'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            var testDefaultBtnText = <?php echo json_encode(_l('sp_reminder_whatsapp_test_button'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            var connFailedText = <?php echo json_encode(_l('sales_pipeline_reminder_delivery_request_failed'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

            var data = {
                endpoint: endpoint,
                secret_key: secretKey,
                manager_mode: $('#sp_reminder_whatsapp_manager_mode').val(),
                group_jid: groupJid,
                timeout_seconds: $('#sp_reminder_whatsapp_timeout_seconds').val()
            };
            if (typeof csrfData !== 'undefined' && csrfData.token_name) {
                data[csrfData.token_name] = csrfData.hash;
            }

            var successBadgeText = <?php echo json_encode(_l('sp_reminder_whatsapp_test_status_success'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            var failedBadgeText = <?php echo json_encode(_l('sp_reminder_whatsapp_test_status_failed'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + testConnectingText);
            $status.empty().append($('<span class="text-muted"><i class="fa fa-spinner fa-spin"></i> </span>').append(document.createTextNode(testConnectingGwText)));

            $.post('<?php echo admin_url('sales_pipeline/test_whatsapp_connection'); ?>', data).done(function (res) {
                if (typeof res === 'string') {
                    try { res = JSON.parse(res); } catch (e) {}
                }
                var isOk = Boolean(res && res.success);
                var msg = res && res.message ? res.message : '';
                alert_float(isOk ? 'success' : 'danger', escapeHtml(msg));

                var badgeClass = isOk ? 'label-success' : 'label-danger';
                var badgeIcon = isOk ? 'fa-check' : 'fa-times';
                var badgeLabel = isOk ? successBadgeText : failedBadgeText;
                var textColor = isOk ? 'text-muted' : 'text-danger';

                var html = '<div class="mtop5" style="white-space: normal; word-break: break-word; line-height: 1.3;">'
                    + '<span class="label ' + badgeClass + '"><i class="fa ' + badgeIcon + '"></i> ' + escapeHtml(badgeLabel) + '</span>'
                    + '<div class="' + textColor + ' small mtop5">' + escapeHtml(msg) + '</div>'
                    + '</div>';
                $status.html(html);
            }).fail(function (xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : connFailedText;
                alert_float('danger', escapeHtml(msg));

                var html = '<div class="mtop5" style="white-space: normal; word-break: break-word; line-height: 1.3;">'
                    + '<span class="label label-danger"><i class="fa fa-times"></i> ' + escapeHtml(failedBadgeText) + '</span>'
                    + '<div class="text-danger small mtop5">' + escapeHtml(msg) + '</div>'
                    + '</div>';
                $status.html(html);
            }).always(function () {
                $btn.prop('disabled', false).html('<i class="fa fa-paper-plane text-success" aria-hidden="true"></i> ' + testDefaultBtnText);
            });
        });

        $('#btn-fetch-wa-groups').on('click', function () {
            var $btn = $(this);
            var $container = $('#wa-groups-dropdown-container');
            var $select = $('#wa-groups-select');
            var endpoint = $('#sp_reminder_whatsapp_endpoint').val();
            var secretKey = $('#sp_reminder_whatsapp_secret_key').val();

            var loadingText = <?php echo json_encode(_l('sp_reminder_whatsapp_fetch_groups_loading'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            var placeholderText = <?php echo json_encode(_l('sp_reminder_whatsapp_select_group_placeholder'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            var emptyText = <?php echo json_encode(_l('sp_reminder_whatsapp_fetch_groups_empty'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

            var data = {
                endpoint: endpoint,
                secret_key: secretKey,
                timeout_seconds: $('#sp_reminder_whatsapp_timeout_seconds').val()
            };
            if (typeof csrfData !== 'undefined' && csrfData.token_name) {
                data[csrfData.token_name] = csrfData.hash;
            }

            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin text-info"></i>');

            $.post('<?php echo admin_url('sales_pipeline/fetch_whatsapp_groups'); ?>', data).done(function (res) {
                if (typeof res === 'string') {
                    try { res = JSON.parse(res); } catch (e) {}
                }
                if (res && res.success && res.data && res.data.groups && res.data.groups.length > 0) {
                    alert_float('success', escapeHtml(res.message));
                    $select.empty().append($('<option>').val('').text(placeholderText));
                    var currentJid = $('#sp_reminder_whatsapp_group_jid').val();
                    $.each(res.data.groups, function (idx, grp) {
                        var countText = grp.participants_count ? ' (' + grp.participants_count + ' thành viên)' : '';
                        var label = (grp.subject || 'Không tên') + countText;
                        var $opt = $('<option>').val(grp.id).text(label);
                        if (grp.id === currentJid) {
                            $opt.prop('selected', true);
                        }
                        $select.append($opt);
                    });
                    $container.slideDown(200);
                } else {
                    alert_float('warning', escapeHtml(res && res.message ? res.message : emptyText));
                }
            }).fail(function (xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : emptyText;
                alert_float('danger', escapeHtml(msg));
            }).always(function () {
                $btn.prop('disabled', false).html('<i class="fa fa-refresh text-info"></i>');
            });
        });

        $('#wa-groups-select').on('change', function () {
            var selectedJid = $(this).val();
            if (selectedJid) {
                $('#sp_reminder_whatsapp_group_jid').val(selectedJid).trigger('change');
            }
        });

        function toggleWhatsAppGroupJidVisibility(isInitial) {
            var mode = $('#sp_reminder_whatsapp_manager_mode').val();
            var $groupWrapper = $('#sp-wa-group-jid-wrapper');
            var $directHint = $('#sp-wa-direct-mode-hint');

            if (mode === 'direct_only') {
                if (isInitial) {
                    $groupWrapper.hide();
                    $directHint.show();
                } else {
                    $groupWrapper.slideUp(200);
                    $directHint.slideDown(200);
                }
            } else {
                if (isInitial) {
                    $groupWrapper.show();
                    $directHint.hide();
                } else {
                    $groupWrapper.slideDown(200);
                    $directHint.slideUp(200);
                }
            }
        }

        $('#sp_reminder_whatsapp_manager_mode').on('change', function () {
            toggleWhatsAppGroupJidVisibility(false);
        });
        toggleWhatsAppGroupJidVisibility(true);

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

        // Policy V2 Manager Source mode toggle
        $('input[name="sp_reminder_manager_recipient_source"]').on('change', function () {
            if ($(this).val() === 'selected_staff') {
                $('#sp-selected-staff-wrapper').slideDown(200);
            } else {
                $('#sp-selected-staff-wrapper').slideUp(200);
            }
        });

        // Live Preview Recipients
        $('#sp-btn-preview-recipients').on('click', function () {
            var $btn = $(this);
            var $loading = $('#sp-preview-loading');
            var $container = $('#sp-recipients-preview-container');

            $btn.prop('disabled', true);
            $loading.show();

            var postData = {
                v2_enabled: $('#sp_reminder_recipient_policy_v2_enabled').is(':checked') ? 1 : 0,
                source: $('input[name="sp_reminder_manager_recipient_source"]:checked').val() || 'explicit_view',
                selected_staff_ids: $('#sp_reminder_manager_recipient_staff_ids').val() || []
            };

            if (typeof csrfData !== 'undefined') {
                postData[csrfData.token_name] = csrfData.hash;
            }

            $.ajax({
                url: admin_url + 'sales_pipeline/preview_manager_recipients',
                type: 'POST',
                data: postData,
                dataType: 'json'
            }).done(function (res) {
                var html = '<div class="panel panel-info" style="border-radius:4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
                html += '<div class="panel-heading" style="font-weight:600; font-size:13px;"><i class="fa fa-users"></i> ' + <?php echo json_encode(_l('sp_reminder_preview_panel_title')); ?> + '</div>';
                html += '<div class="panel-body" style="padding:15px;">';

                if (res.empty_warning) {
                    html += '<div class="alert alert-danger" style="margin-bottom:15px;"><i class="fa fa-exclamation-triangle"></i> <strong>' + <?php echo json_encode(_l('sp_reminder_preview_empty_warning')); ?> + '</strong><br>' + res.no_fallback_note + '</div>';
                }

                html += '<h5 class="bold text-success"><i class="fa fa-check-circle"></i> ' + <?php echo json_encode(_l('sp_reminder_preview_included_managers')); ?> + ' (' + res.included_managers.length + ')</h5>';
                if (res.included_managers.length > 0) {
                    html += '<div class="table-responsive"><table class="table table-bordered table-condensed table-striped" style="margin-bottom:15px;">';
                    html += '<thead><tr class="active"><th>Staff ID</th><th>' + <?php echo json_encode(_l('name')); ?> + '</th><th>Email</th><th>' + <?php echo json_encode(_l('phonenumber')); ?> + '</th><th>' + <?php echo json_encode(_l('reason')); ?> + '</th></tr></thead><tbody>';
                    $.each(res.included_managers, function (idx, m) {
                        html += '<tr><td>' + m.staff_id + '</td><td class="bold">' + m.name + '</td><td>' + m.email_masked + '</td><td>' + m.phone_masked + '</td><td><span class="label label-success">' + m.reason + '</span></td></tr>';
                    });
                    html += '</tbody></table></div>';
                } else {
                    html += '<p class="text-danger"><em>' + <?php echo json_encode(_l('sp_reminder_preview_none_included')); ?> + '</em></p>';
                }

                if (res.excluded_candidates && res.excluded_candidates.length > 0) {
                    html += '<h5 class="bold text-warning mtop15"><i class="fa fa-ban"></i> ' + <?php echo json_encode(_l('sp_reminder_preview_excluded_candidates')); ?> + ' (' + res.excluded_candidates.length + ')</h5>';
                    html += '<div class="table-responsive"><table class="table table-bordered table-condensed" style="margin-bottom:15px;">';
                    html += '<thead><tr class="active"><th>Staff ID</th><th>' + <?php echo json_encode(_l('name')); ?> + '</th><th>' + <?php echo json_encode(_l('reason')); ?> + '</th></tr></thead><tbody>';
                    $.each(res.excluded_candidates, function (idx, e) {
                        html += '<tr><td>' + e.staff_id + '</td><td>' + e.name + '</td><td><span class="label label-default">' + e.reason + '</span></td></tr>';
                    });
                    html += '</tbody></table></div>';
                }

                if (res.technical_admins && res.technical_admins.length > 0) {
                    html += '<h5 class="bold text-info mtop15"><i class="fa fa-wrench"></i> ' + <?php echo json_encode(_l('sp_reminder_preview_technical_admins')); ?> + ' (' + res.technical_admins.length + ')</h5>';
                    html += '<p class="text-muted small">' + <?php echo json_encode(_l('sp_reminder_preview_technical_admins_desc')); ?> + '</p>';
                    html += '<ul class="list-inline">';
                    $.each(res.technical_admins, function (idx, a) {
                        html += '<li class="label label-info mright5">' + a.name + ' (' + a.email_masked + ')</li>';
                    });
                    html += '</ul>';
                }

                html += '</div></div>';
                $container.html(html).slideDown(200);
            }).fail(function () {
                alert_float('danger', 'Failed to generate recipient preview.');
            }).always(function () {
                $btn.prop('disabled', false);
                $loading.hide();
            });
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
