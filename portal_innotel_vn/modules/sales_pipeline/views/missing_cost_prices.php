<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                
                <div class="panel_s">
                    <div class="panel-body">
                        <!-- Tiêu đề & Nút hành động -->
                        <div class="clearfix mbot15">
                            <h4 class="no-margin font-bold pull-left" style="line-height: 34px;">
                                <i class="fa fa-exclamation-triangle text-danger"></i> <?php echo $title; ?>
                            </h4>
                            <div class="pull-right">
                                <a href="<?php echo admin_url('sales_pipeline'); ?>" class="btn btn-default">
                                    <i class="fa fa-arrow-left"></i> <?php echo _l('sales_pipeline_back'); ?>
                                </a>
                            </div>
                        </div>

                        <hr class="hr-panel-heading" />

                        <!-- Bộ lọc rút gọn -->
                        <div class="row mbot15">
                            <div class="col-md-3">
                                <select name="quarter" id="filter_quarter" class="selectpicker" data-width="100%"
                                        data-none-selected-text="<?php echo _l('sales_pipeline_filter_quarter'); ?>"
                                        onchange="applyFilters()">
                                    <option value=""><?php echo _l('sales_pipeline_all_quarters'); ?></option>
                                    <option value="1" <?php echo ($current_quarter == 1) ? 'selected' : ''; ?>><?php echo _l('sales_pipeline_q1'); ?></option>
                                    <option value="2" <?php echo ($current_quarter == 2) ? 'selected' : ''; ?>><?php echo _l('sales_pipeline_q2'); ?></option>
                                    <option value="3" <?php echo ($current_quarter == 3) ? 'selected' : ''; ?>><?php echo _l('sales_pipeline_q3'); ?></option>
                                    <option value="4" <?php echo ($current_quarter == 4) ? 'selected' : ''; ?>><?php echo _l('sales_pipeline_q4'); ?></option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="year" id="filter_year" class="selectpicker" data-width="100%"
                                        onchange="applyFilters()">
                                    <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--) { ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($current_year == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="staff_id" id="filter_staff" class="selectpicker" data-width="100%"
                                        data-live-search="true"
                                        data-none-selected-text="<?php echo _l('sales_pipeline_filter_staff'); ?>"
                                        onchange="applyFilters()">
                                    <option value=""><?php echo _l('sales_pipeline_all_staff'); ?></option>
                                    <?php foreach ($staff as $member) { ?>
                                    <option value="<?php echo $member['staffid']; ?>"
                                        <?php echo ($current_staff_id == $member['staffid']) ? 'selected' : ''; ?>>
                                        <?php echo html_escape($member['firstname'] . ' ' . $member['lastname']); ?>
                                    </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <!-- Bảng danh sách deal thiếu giá nhập -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 5%;" class="text-center">#</th>
                                        <th style="width: 12%;"><?php echo _l('sales_pipeline_expected_date'); ?></th>
                                        <th style="width: 25%;"><?php echo _l('sales_pipeline_customer_name'); ?></th>
                                        <th style="width: 25%;"><?php echo _l('sales_pipeline_deal_name'); ?></th>
                                        <th style="width: 15%;" class="text-right"><?php echo _l('sales_pipeline_deal_value'); ?> (<?php echo _l('sales_pipeline_vnd'); ?>)</th>
                                        <th style="width: 13%;"><?php echo _l('sales_pipeline_assigned_staff'); ?></th>
                                        <th style="width: 12%;"><?php echo _l('sales_pipeline_status'); ?></th>
                                        <th style="width: 8%;" class="text-center"><?php echo _l('sales_pipeline_actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($deals)) { ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">
                                            <?php echo _l('sales_pipeline_no_missing_cost_prices'); ?>
                                        </td>
                                    </tr>
                                    <?php } else { 
                                        $stt = 0;
                                        foreach ($deals as $deal) {
                                            $stt++;
                                            ?>
                                    <tr data-deal-id="<?php echo $deal['id']; ?>" class="warning-row">
                                        <td class="text-center"><?php echo $stt; ?></td>
                                        <td><?php echo _d($deal['deal_date']); ?></td>
                                        <td>
                                            <strong><?php echo html_escape($deal['customer_name']); ?></strong>
                                            <?php if (!empty($deal['contact_name'])) { ?>
                                            <br><small class="text-muted"><?php echo html_escape($deal['contact_name']); ?></small>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo html_escape($deal['deal_name']); ?></td>
                                        <td class="text-right font-bold"><?php echo number_format($deal['deal_value']); ?></td>
                                        <td>
                                            <a href="<?php echo admin_url('staff/profile/' . $deal['staff_id']); ?>">
                                                <?php echo html_escape($deal['staff_name']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="label" style="background:<?php echo $deal['status_color']; ?>; color:#fff;">
                                                <?php echo html_escape($deal['status_name']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="#" class="btn btn-xs btn-info edit-cost-price" 
                                               data-deal-id="<?php echo $deal['id']; ?>" 
                                               data-deal-value="<?php echo $deal['deal_value']; ?>">
                                                <i class="fa fa-edit"></i> <?php echo _l('sales_pipeline_enter_price'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php } } ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Phân trang -->
                        <?php if (isset($total_pages) && $total_pages > 1) { ?>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="text-muted" style="margin-top: 5px;">
                                    <?php echo _l('sales_pipeline_pagination_showing'); ?> <?php echo (($current_page - 1) * $per_page) + 1; ?> 
                                    <?php echo _l('sales_pipeline_pagination_to'); ?> <?php echo min($current_page * $per_page, $total_deals); ?> 
                                    <?php echo _l('sales_pipeline_pagination_of_total'); ?> <?php echo number_format($total_deals); ?> <?php echo _l('sales_pipeline_pagination_deals'); ?>
                                </p>
                            </div>
                            <div class="col-md-6 text-right">
                                <nav>
                                    <ul class="pagination pagination-sm" style="margin: 0;">
                                        <!-- Nút Trước -->
                                        <?php if ($current_page > 1) { ?>
                                        <li>
                                            <a href="#" onclick="goToPage(<?php echo $current_page - 1; ?>); return false;">
                                                <i class="fa fa-chevron-left"></i> <?php echo _l('sales_pipeline_pagination_prev'); ?>
                                            </a>
                                        </li>
                                        <?php } else { ?>
                                        <li class="disabled"><span><i class="fa fa-chevron-left"></i> <?php echo _l('sales_pipeline_pagination_prev'); ?></span></li>
                                        <?php } ?>

                                        <!-- Số trang -->
                                        <?php
                                        $start_page = max(1, $current_page - 2);
                                        $end_page = min($total_pages, $current_page + 2);
                                        
                                        if ($start_page > 1) {
                                            echo '<li><a href="#" onclick="goToPage(1); return false;">1</a></li>';
                                            if ($start_page > 2) {
                                                echo '<li class="disabled"><span>...</span></li>';
                                            }
                                        }
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++) {
                                            if ($i == $current_page) {
                                                echo '<li class="active"><span>' . $i . '</span></li>';
                                            } else {
                                                echo '<li><a href="#" onclick="goToPage(' . $i . '); return false;">' . $i . '</a></li>';
                                            }
                                        }
                                        
                                        if ($end_page < $total_pages) {
                                            if ($end_page < $total_pages - 1) {
                                                echo '<li class="disabled"><span>...</span></li>';
                                            }
                                            echo '<li><a href="#" onclick="goToPage(' . $total_pages . '); return false;">' . $total_pages . '</a></li>';
                                        }
                                        ?>

                                        <!-- Nút Sau -->
                                        <?php if ($current_page < $total_pages) { ?>
                                        <li>
                                            <a href="#" onclick="goToPage(<?php echo $current_page + 1; ?>); return false;">
                                                <?php echo _l('sales_pipeline_pagination_next'); ?> <i class="fa fa-chevron-right"></i>
                                            </a>
                                        </li>
                                        <?php } else { ?>
                                        <li class="disabled"><span><?php echo _l('sales_pipeline_pagination_next'); ?> <i class="fa fa-chevron-right"></i></span></li>
                                        <?php } ?>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                        <?php } ?>

                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Modal cập nhật giá nhập nhanh (AJAX) -->
<div class="modal fade" id="costPriceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-money"></i> <?php echo _l('sales_pipeline_update_cost_price'); ?></h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="modal_deal_value"><?php echo _l('sales_pipeline_deal_value'); ?> (<?php echo _l('sales_pipeline_vnd'); ?>)</label>
                    <input type="text" class="form-control" id="modal_deal_value" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label for="modal_cost_price"><span class="text-danger">*</span> <?php echo _l('sales_pipeline_cost_price'); ?> (<?php echo _l('sales_pipeline_vnd'); ?>)</label>
                    <input type="number" class="form-control" id="modal_cost_price" min="0" step="any" placeholder="<?php echo _l('sales_pipeline_enter_cost_price'); ?>">
                    <small class="text-muted"><?php echo _l('sales_pipeline_cost_price_hint'); ?></small>
                </div>
                <div class="form-group" id="profit_preview" style="display:none;">
                    <label><?php echo _l('sales_pipeline_profit_preview'); ?></label>
                    <div class="well well-sm">
                        <strong><?php echo _l('sales_pipeline_profit'); ?>:</strong> <span id="preview_profit" class="text-success">0</span> <?php echo _l('sales_pipeline_vnd'); ?>
                        <br>
                        <strong><?php echo _l('sales_pipeline_profit_margin'); ?>:</strong> <span id="preview_percent" class="text-info">0</span>%
                    </div>
                </div>
                <input type="hidden" id="modal_deal_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="button" class="btn btn-info" id="saveCostPrice">
                    <i class="fa fa-save"></i> <?php echo _l('save'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<style>
.warning-row {
    background-color: #fff3cd !important;
}
.warning-row:hover {
    background-color: #ffe8a1 !important;
}
</style>

<script>
var salesPipelineLocale = <?php echo json_encode(_l('sales_pipeline_js_locale')); ?>;
var salesPipelineResponseProcessingError = <?php echo json_encode(_l('sales_pipeline_response_processing_error'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
// Fallback: đảm bảo sp_alert luôn tồn tại kể cả khi sales_pipeline.js chưa tải xong
if (typeof window.sp_alert !== 'function') {
    window.sp_alert = function(type, message, timeout) {
        if (typeof alert_float === 'function') {
            alert_float(type, message, timeout || 3500);
        } else if (typeof console !== 'undefined' && console.log) {
            console.log('[' + type + '] ' + message);
        }
    };
}
$(function() {
    // Mở modal nhập giá nhanh
    $('body').on('click', '.edit-cost-price', function(e) {
        e.preventDefault();
        var dealId = $(this).data('deal-id');
        var dealValue = $(this).data('deal-value');
        var currentCost = $(this).data('current-cost') || '';

        $('#modal_deal_id').val(dealId);
        $('#modal_deal_value').val(parseInt(dealValue).toLocaleString(salesPipelineLocale));
        $('#modal_cost_price').val(currentCost);
        $('#profit_preview').hide();

        $('#costPriceModal').modal('show');
    });

    // Xem trước lợi nhuận khi nhập giá nhập
    $('#modal_cost_price').on('input', function() {
        var dealValue = parseFloat($('#modal_deal_value').val().replace(/[^0-9]/g, ''));
        var costPrice = parseFloat($(this).val()) || 0;

        if (costPrice > 0 && dealValue > 0) {
            var profit = dealValue - costPrice;
            var percent = (profit / dealValue) * 100;

            $('#preview_profit').text(profit.toLocaleString(salesPipelineLocale));
            $('#preview_percent').text(percent.toFixed(2));
            $('#profit_preview').show();
        } else {
            $('#profit_preview').hide();
        }
    });

    // Lưu giá nhập qua AJAX
    $(document).off('click', '#saveCostPrice').on('click', '#saveCostPrice', function(e) {
        e.preventDefault();

        // Khóa nút NGAY LẬP TỨC để chống spam click, kể cả khi validation thất bại
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <?php echo _l('please_wait'); ?>');

        var dealId = $('#modal_deal_id').val();
        var costPrice = $('#modal_cost_price').val();

        if (!dealId) {
            sp_alert('danger', '<?php echo _l('sales_pipeline_invalid_deal_id'); ?>');
            // Giữ nút bị khóa đúng 1500ms (= throttle window của sp_alert) rồi mới mở lại
            setTimeout(function() {
                btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo _l('save'); ?>');
            }, 1500);
            return;
        }

        if (costPrice === '' || isNaN(costPrice) || parseFloat(costPrice) < 0) {
            sp_alert('danger', '<?php echo _l('sales_pipeline_invalid_cost_price'); ?>');
            // Giữ nút bị khóa đúng 1500ms (= throttle window của sp_alert) rồi mới mở lại
            setTimeout(function() {
                btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo _l('save'); ?>');
            }, 1500);
            return;
        }

        var postData = {
            pipeline_id: dealId,
            cost_price: costPrice
        };
        if (typeof(csrfData) !== 'undefined') {
            postData[csrfData['token_name']] = csrfData['hash'];
        }

        $.ajax({
            url: admin_url + 'sales_pipeline/update_cost_price',
            type: 'POST',
            dataType: 'json',
            data: postData,
            success: function(response) {
                try {
                    var isSuccess = response && (response.status === true || response.success === true);
                    if (isSuccess) {
                        sp_alert('success', response.message);
                        $('#costPriceModal').modal('hide');

                        // Reload page to update list
                        location.reload();
                    } else {
                        sp_alert('danger', (response && response.message) ? response.message : '<?php echo _l('something_went_wrong'); ?>');
                    }
                } catch (e) {
                    console.error(salesPipelineResponseProcessingError, e);
                    sp_alert('danger', '<?php echo _l('something_went_wrong'); ?>');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", status, error, xhr.responseText);
                sp_alert('danger', '<?php echo _l('something_went_wrong'); ?>');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo _l('save'); ?>');
            }
        });
    });
});

// Điều hướng bộ lọc không dùng thẻ form để tránh cảnh báo dirty form của thư viện areYouSure
function applyFilters() {
    var quarter = $('#filter_quarter').val();
    var year    = $('#filter_year').val();
    var staff   = $('#filter_staff').val();

    var url = admin_url + 'sales_pipeline/missing_cost_prices?page=1';
    if (quarter) url += '&quarter=' + quarter;
    if (year)    url += '&year=' + year;
    if (staff)   url += '&staff_id=' + staff;

    window.location.href = url;
}

// Chuyển trang
function goToPage(page) {
    var quarter = $('#filter_quarter').val();
    var year    = $('#filter_year').val();
    var staff   = $('#filter_staff').val();

    var url = admin_url + 'sales_pipeline/missing_cost_prices?page=' + page;
    if (quarter) url += '&quarter=' + quarter;
    if (year)    url += '&year=' + year;
    if (staff)   url += '&staff_id=' + staff;

    window.location.href = url;
}
</script>
</body>
</html>
