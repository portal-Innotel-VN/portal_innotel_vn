<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin font-bold">
                            <i class="fa fa-line-chart"></i> <?php echo $title; ?>
                        </h4>
                        <hr class="hr-panel-heading" />

                        <?php if (validation_errors()) { ?>
                            <div class="alert alert-danger">
                                <?php echo validation_errors(); ?>
                            </div>
                        <?php } ?>

                        <?php
                        $action_url = isset($deal) ? admin_url('sales_pipeline/deal/' . $deal['id']) : admin_url('sales_pipeline/deal');
                        if (!empty($_SERVER['QUERY_STRING'])) {
                            $action_url .= '?' . $_SERVER['QUERY_STRING'];
                        }
                        echo form_open($action_url, ['id' => 'deal-form', 'class' => 'disable-on-submit', 'novalidate' => 'novalidate']);
                        ?>

                        <!-- === THÔNG TIN KHÁCH HÀNG === -->
                        <div class="panel-group" id="accordion">
                            <div class="panel panel-default">
                                <div class="panel-heading" role="tab">
                                    <h4 class="panel-title">
                                        <a data-toggle="collapse" href="#section_customer">
                                            <i class="fa fa-building"></i> <?php echo _l('sales_pipeline_customer_info'); ?>
                                        </a>
                                    </h4>
                                </div>
                                <div id="section_customer" class="panel-collapse collapse in">
                                    <div class="panel-body">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="customer_name" class="control-label">
                                                        <?php echo _l('sales_pipeline_customer_name'); ?>
                                                    </label>
                                                    <input type="text" class="form-control" name="customer_name" id="customer_name"
                                                           value="<?php echo isset($deal) ? html_escape($deal['customer_name']) : ''; ?>"
                                                           required maxlength="255" autocomplete="off"
                                                           placeholder="<?php echo html_escape(_l('sales_pipeline_customer_name_placeholder')); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="contact_name" class="control-label"><?php echo _l('sales_pipeline_contact_name'); ?></label>
                                                    <input type="text" class="form-control" name="contact_name" id="contact_name"
                                                           value="<?php echo isset($deal) ? html_escape($deal['contact_name']) : ''; ?>"
                                                           maxlength="100" autocomplete="off" placeholder="<?php echo html_escape(_l('sales_pipeline_contact_name_placeholder')); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="contact_phone" class="control-label"><?php echo _l('sales_pipeline_contact_phone'); ?></label>
                                                    <input type="text" class="form-control" name="contact_phone" id="contact_phone" pattern="[0-9]{1,10}" oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                                           value="<?php echo isset($deal) ? html_escape($deal['contact_phone']) : ''; ?>"
                                                           autocomplete="off" placeholder="<?php echo html_escape(_l('sales_pipeline_contact_phone_placeholder')); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="contact_email" class="control-label"><?php echo _l('sales_pipeline_contact_email'); ?></label>
                                                    <input type="email" class="form-control" name="contact_email" id="contact_email"
                                                           value="<?php echo isset($deal) ? html_escape($deal['contact_email']) : ''; ?>"
                                                           maxlength="100" autocomplete="off" placeholder="<?php echo html_escape(_l('sales_pipeline_contact_email_placeholder')); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="source_id" class="control-label"><?php echo _l('sales_pipeline_source'); ?></label>
                                            <select name="source_id" id="source_id" class="selectpicker" data-width="100%"
                                                    data-none-selected-text="<?php echo _l('sales_pipeline_select_source'); ?>">
                                                <option value="">-- <?php echo _l('sales_pipeline_select_source'); ?> --</option>
                                                <?php
                                                foreach ($sources as $src) { ?>
                                                <option value="<?php echo $src['id']; ?>"
                                                    <?php echo (isset($deal) && $deal['source_id'] == $src['id']) ? 'selected' : ''; ?>>
                                                    <?php echo $src['name']; ?>
                                                </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- === THÔNG TIN DEAL === -->
                            <div class="panel panel-default mtop10">
                                <div class="panel-heading" role="tab">
                                    <h4 class="panel-title">
                                        <a data-toggle="collapse" href="#section_deal">
                                            <i class="fa fa-briefcase"></i> <?php echo _l('sales_pipeline_deal_info'); ?>
                                        </a>
                                    </h4>
                                </div>
                                <div id="section_deal" class="panel-collapse collapse in">
                                    <div class="panel-body">
                                        <div class="form-group">
                                            <label for="deal_name" class="control-label">
                                                <?php echo _l('sales_pipeline_deal_name'); ?>
                                            </label>
                                            <input type="text" class="form-control" name="deal_name" id="deal_name"
                                                   value="<?php echo isset($deal) ? html_escape($deal['deal_name']) : ''; ?>"
                                                   required maxlength="500" autocomplete="off"
                                                   placeholder="<?php echo html_escape(_l('sales_pipeline_deal_name_placeholder')); ?>">
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="deal_value" class="control-label">
                                                        <?php echo _l('sales_pipeline_deal_value'); ?> (<?php echo _l('sales_pipeline_vnd'); ?>)
                                                    </label>
                                                    <input type="number" class="form-control positive-number-only" name="deal_value" id="deal_value"
                                                           value="<?php echo isset($deal) && is_numeric($deal['deal_value']) ? (float)$deal['deal_value'] : ''; ?>"
                                                           required min="0" step="any"
                                                           onchange="calculateProfit()" onkeyup="calculateProfit()">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="cost_price" class="control-label">
                                                        <?php echo _l('sales_pipeline_cost_price'); ?> (<?php echo _l('sales_pipeline_vnd'); ?>)
                                                        <i class="fa fa-question-circle" data-toggle="tooltip" 
                                                           title="<?php echo _l('sales_pipeline_cost_price_hint'); ?>"></i>
                                                    </label>
                                                    <input type="number" class="form-control positive-number-only" name="cost_price" id="cost_price"
                                                           value="<?php echo isset($deal) && isset($deal['cost_price']) && is_numeric($deal['cost_price']) ? (float)$deal['cost_price'] : ''; ?>"
                                                           min="0" step="any" placeholder="<?php echo _l('sales_pipeline_enter_cost_price'); ?>"
                                                           onchange="calculateProfit()" onkeyup="calculateProfit()">
                                                    <small class="text-muted">
                                                        <?php echo _l('sales_pipeline_cost_price_hint'); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="control-label"><?php echo _l('sales_pipeline_profit'); ?> (<?php echo _l('sales_pipeline_vnd'); ?>)</label>
                                                    <input type="text" class="form-control" id="expected_profit_display"
                                                           value="<?php echo isset($deal) && isset($deal['actual_profit']) && $deal['actual_profit'] !== null ? number_format($deal['actual_profit']) : '--'; ?>"
                                                           readonly style="background: #f0f0f0; font-weight: bold; color: #27ae60;">
                                                    <small class="text-muted" id="profit_percent_display">
                                                        <?php if (isset($deal) && isset($deal['profit_percentage']) && $deal['profit_percentage'] !== null) {
                                                            echo '(' . round($deal['profit_percentage'], 1) . '%)';
                                                        } ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="deal_date" class="control-label">
                                                <?php echo _l('sales_pipeline_expected_date'); ?>
                                            </label>
                                            <div class="input-group date">
                                                <input type="text" class="form-control datepicker" name="deal_date"
                                                       id="deal_date"
                                                       value="<?php echo isset($deal) ? _d($deal['deal_date']) : ''; ?>"
                                                       required autocomplete="off">
                                                <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- === TRẠNG THÁI & TIẾN ĐỘ === -->
                            <div class="panel panel-default mtop10">
                                <div class="panel-heading" role="tab">
                                    <h4 class="panel-title">
                                        <a data-toggle="collapse" href="#section_status">
                                            <i class="fa fa-tasks"></i> <?php echo _l('sales_pipeline_status_progress'); ?>
                                        </a>
                                    </h4>
                                </div>
                                <div id="section_status" class="panel-collapse collapse in">
                                    <div class="panel-body">
                                        <div class="form-group">
                                            <label for="status" class="control-label">
                                                <?php echo _l('sales_pipeline_status'); ?>
                                            </label>
                                            <select name="status" id="status" class="selectpicker" data-width="100%" required>
                                                <?php foreach ($statuses as $s) { ?>
                                                <option value="<?php echo $s['id']; ?>"
                                                    data-content="<span class='label' style='background:<?php echo $s['color']; ?>'><?php echo $s['name']; ?></span>"
                                                    <?php echo (isset($deal) && $deal['status'] == $s['id']) ? 'selected' : ''; ?>>
                                                    <?php echo $s['name']; ?>
                                                </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="checkbox checkbox-success">
                                                    <input type="checkbox" name="contract_signed" id="contract_signed" value="1"
                                                        <?php echo (isset($deal) && $deal['contract_signed']) ? 'checked' : ''; ?>>
                                                    <label for="contract_signed"><?php echo _l('sales_pipeline_contract_signed'); ?></label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="checkbox checkbox-info">
                                                    <input type="checkbox" name="invoice_issued" id="invoice_issued" value="1"
                                                        <?php echo (isset($deal) && $deal['invoice_issued']) ? 'checked' : ''; ?>>
                                                    <label for="invoice_issued"><?php echo _l('sales_pipeline_invoice_issued'); ?></label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group mtop10">
                                            <label for="activity_description" class="control-label">
                                                <i class="fa fa-comment"></i> <?php echo _l('sales_pipeline_progress_note'); ?>
                                            </label>
                                            <textarea name="activity_description" id="activity_description" class="form-control" rows="3" 
                                                maxlength="2000" placeholder="<?php echo html_escape(_l('sales_pipeline_progress_note_placeholder')); ?>"><?php echo isset($deal['activity_description']) ? html_escape($deal['activity_description']) : ''; ?></textarea>
                                        </div>

                                        <!-- Timeline activity (khi sửa) -->
                                        <?php if (isset($deal) && !empty($deal['activity'])) { ?>
                                        <hr />
                                        <h5 class="font-bold"><i class="fa fa-history"></i> <?php echo _l('sales_pipeline_activity_timeline'); ?></h5>
                                        <div class="activity-feed">
                                            <?php foreach ($deal['activity'] as $act) { ?>
                                            <div class="feed-item">
                                                <div class="date"><?php echo _dt($act['datecreated']); ?></div>
                                                <div class="text">
                                                    <strong><?php echo html_escape($act['staff_name']); ?>:</strong>
                                                    <?php echo html_escape($act['description']); ?>
                                                </div>
                                            </div>
                                            <?php } ?>
                                        </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>

                            <!-- === NHẮC NHỞ & PHÂN CÔNG === -->
                            <div class="panel panel-default mtop10">
                                <div class="panel-heading" role="tab">
                                    <h4 class="panel-title">
                                        <a data-toggle="collapse" href="#section_reminder">
                                            <i class="fa fa-bell"></i> <?php echo _l('sales_pipeline_reminder_settings'); ?>
                                        </a>
                                    </h4>
                                </div>
                                <div id="section_reminder" class="panel-collapse collapse in">
                                    <div class="panel-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="checkbox checkbox-primary">
                                                    <input type="checkbox" name="reminder_enabled" id="reminder_enabled" value="1"
                                                        <?php echo (!isset($deal) || $deal['reminder_enabled']) ? 'checked' : ''; ?>>
                                                    <label for="reminder_enabled"><?php echo _l('sales_pipeline_reminder_enabled'); ?></label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="reminder_frequency" class="control-label"><?php echo _l('sales_pipeline_reminder_frequency'); ?></label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" name="reminder_frequency" id="reminder_frequency"
                                                               value="<?php echo isset($deal) ? $deal['reminder_frequency'] : '2'; ?>"
                                                               min="1" max="30">
                                                        <span class="input-group-addon"><?php echo _l('sales_pipeline_reminder_days'); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="staff_id" class="control-label">
                                                <?php echo _l('sales_pipeline_assigned_staff'); ?>
                                            </label>
                                            <select name="staff_id" id="staff_id" class="selectpicker" data-width="100%"
                                                    data-live-search="true" required>
                                                <?php foreach ($staff as $member) { ?>
                                                <option value="<?php echo $member['staffid']; ?>"
                                                    <?php
                                                    if (isset($deal)) {
                                                        echo ($deal['staff_id'] == $member['staffid']) ? 'selected' : '';
                                                    } else {
                                                        echo ($member['staffid'] == get_staff_user_id()) ? 'selected' : '';
                                                    }
                                                    ?>>
                                                    <?php echo $member['firstname'] . ' ' . $member['lastname']; ?>
                                                </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr />
                        <div class="text-right">
                            <a href="<?php echo admin_url('sales_pipeline') . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''); ?>" class="btn btn-default mright5">
                                <i class="fa fa-arrow-left"></i> <?php echo _l('sales_pipeline_back'); ?>
                            </a>
                            <button type="submit" class="btn btn-info">
                                <i class="fa fa-save"></i> <?php echo _l('sales_pipeline_save_deal'); ?>
                            </button>
                        </div>

                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<style>
/* Activity timeline */
.activity-feed { padding: 15px 0 0 15px; overflow: hidden; }
.activity-feed .feed-item {
    position: relative;
    padding-bottom: 15px;
    padding-left: 25px;
    border-left: 2px solid #e4e8eb;
}
.activity-feed .feed-item:before {
    content: "";
    display: block;
    position: absolute;
    left: -6px;
    top: 0;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #3498db;
}
.activity-feed .feed-item .date {
    color: #999;
    font-size: 11px;
}
.activity-feed .feed-item .text { font-size: 13px; }
</style>

<script>
var salesPipelineLocale = <?php echo json_encode(_l('sales_pipeline_js_locale')); ?>;
function calculateProfit() {
    var dealValue = parseFloat($('#deal_value').val()) || 0;
    var costPrice = parseFloat($('#cost_price').val()) || 0;

    if (costPrice > 0 && dealValue > 0) {
        var profit = dealValue - costPrice;
        var profitPercent = (profit / dealValue) * 100;

        $('#expected_profit_display').val(profit.toLocaleString(salesPipelineLocale));
        $('#profit_percent_display').text('(' + profitPercent.toFixed(1) + '%)');
    } else {
        $('#expected_profit_display').val('--');
        $('#profit_percent_display').text('');
    }
}

$(function() {
    // Tính lợi nhuận ban đầu
    calculateProfit();
    
    // Enable tooltip
    $('[data-toggle="tooltip"]').tooltip();

    // Đồng bộ Trạng thái & Checkbox HĐ/Hóa đơn
    var $status = $('#status');
    var $contract = $('#contract_signed');
    var $invoice = $('#invoice_issued');

    // Hành động A: Từ Checkbox tác động lên Trạng thái (Nguyên tắc "Chỉ tiến, Không lùi")
    $contract.on('change', function() {
        if ($(this).is(':checked')) {
            var currentStatus = parseInt($status.val()) || 0;
            if (currentStatus < 5) {
                $status.val('5').selectpicker('refresh');
            }
        }
    });

    $invoice.on('change', function() {
        if ($(this).is(':checked')) {
            // Tự động tick luôn ô Đã ký hợp đồng
            if (!$contract.is(':checked')) {
                $contract.prop('checked', true);
            }
            var currentStatus = parseInt($status.val()) || 0;
            if (currentStatus < 6) {
                $status.val('6').selectpicker('refresh');
            }
        }
    });

    // Hành động B: Từ Trạng thái tác động lên Checkbox (Nguyên tắc "Hỏi xác nhận, Không ép buộc")
    $status.on('change', function() {
        var val = parseInt($(this).val()) || 0;
        
        if (val >= 5) {
            if (!$contract.is(':checked')) {
                if (confirm("<?php echo _l('sales_pipeline_confirm_contract_received'); ?>")) {
                    $contract.prop('checked', true);
                }
            }
        }
        
        if (val >= 6) {
            if (!$invoice.is(':checked')) {
                if (confirm("<?php echo _l('sales_pipeline_confirm_invoice_issued'); ?>")) {
                    $invoice.prop('checked', true);
                    // Đảm bảo hợp đồng cũng được tick
                    $contract.prop('checked', true);
                }
            }
        }
    });

});

appValidateForm($('#deal-form'), {
    customer_name: {
        required: true,
        maxlength: 255
    },
    contact_name: {
        maxlength: 100
    },
    contact_phone: {
        digits: true,
        maxlength: 10
    },
    contact_email: {
        email: true,
        maxlength: 100
    },
    deal_name: {
        required: true,
        maxlength: 500
    },
    deal_value: {
        required: true,
        number: true
    },
    deal_date: {
        required: true
    },
    status: {
        required: true
    },
    activity_description: {
        maxlength: 2000
    }
}, false, {
    customer_name: {
        required: "<?php echo _l('sales_pipeline_validation_customer_name_required'); ?>",
        maxlength: "<?php echo _l('sales_pipeline_validation_customer_name_maxlength'); ?>"
    },
    contact_name: {
        maxlength: "<?php echo _l('sales_pipeline_validation_contact_name_maxlength'); ?>"
    },
    contact_phone: {
        digits: "<?php echo _l('sales_pipeline_validation_contact_phone_digits'); ?>",
        maxlength: "<?php echo _l('sales_pipeline_validation_contact_phone_maxlength'); ?>"
    },
    contact_email: {
        email: "<?php echo _l('sales_pipeline_validation_contact_email_email'); ?>",
        maxlength: "<?php echo _l('sales_pipeline_validation_contact_email_maxlength'); ?>"
    },
    deal_name: {
        required: "<?php echo _l('sales_pipeline_validation_deal_name_required'); ?>",
        maxlength: "<?php echo _l('sales_pipeline_validation_deal_name_maxlength'); ?>"
    },
    deal_value: {
        required: "<?php echo _l('sales_pipeline_validation_deal_value_required'); ?>",
        number: "<?php echo _l('sales_pipeline_validation_deal_value_numeric'); ?>"
    },
    deal_date: {
        required: "<?php echo _l('sales_pipeline_validation_deal_date_required'); ?>"
    },
    status: {
        required: "<?php echo _l('sales_pipeline_validation_status_required'); ?>"
    },
    activity_description: {
        maxlength: "<?php echo _l('sales_pipeline_validation_activity_description_maxlength'); ?>"
    }
});

// Chạy lắng nghe sự kiện blur/change và chặn phím -, +, e, E cho ô positive-number-only
$(function() {
    // 1. Lắng nghe sự kiện rời khỏi ô nhập liệu (blur) và thay đổi (change) để báo lỗi ngay lập tức
    $('#deal-form input[required], #deal-form textarea[required]').on('blur', function() {
        var validator = $('#deal-form').validate();
        if (validator) {
            validator.element(this);
        }
    });

    $('#deal-form select[required]').on('change', function() {
        var validator = $('#deal-form').validate();
        if (validator) {
            validator.element(this);
        }
    });

    // 2. Chặn phím -, +, e, E và lọc ký tự không phải số cho mọi ô có class positive-number-only
    $(document).on('keydown', '.positive-number-only', function(e) {
        if (['-', '+', 'e', 'E'].includes(e.key)) {
            e.preventDefault();
        }
    });

    $(document).on('input', '.positive-number-only', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (typeof calculateProfit === 'function') {
            calculateProfit();
        }
    });
});

</script>
</body>
</html>
