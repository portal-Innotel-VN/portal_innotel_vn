<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$query_string = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">

                
                <div class="panel_s">
                    <div class="panel-body">
                        <!-- Tiêu đề & Nút hành động -->
                        <div class="_buttons">
                            <div class="clearfix mbot15">
                                <h4 class="no-margin font-bold pull-left" style="line-height: 34px;">
                                    <i class="fa fa-line-chart"></i> <?php echo $title; ?>
                                </h4>
                                <div class="pull-right">
                                    <?php if (is_admin()) { ?>
                                    <a href="<?php echo admin_url('sales_pipeline/settings'); ?>"
                                       class="btn btn-default mright5" data-toggle="tooltip" title="Cài đặt Nguồn & Trạng Thái">
                                        <i class="fa fa-cogs"></i> Cài đặt
                                    </a>
                                    <?php } ?>
                                    <?php if (has_permission('sales_pipeline', '', 'create')) { ?>
                                    <a href="<?php echo admin_url('sales_pipeline/import'); ?>"
                                       class="btn btn-default mright5">
                                        <i class="fa fa-upload"></i> <?php echo _l('sales_pipeline_import_excel'); ?>
                                    </a>
                                    <a href="<?php echo admin_url('sales_pipeline/deal' . $query_string); ?>"
                                       class="btn btn-info">
                                        <i class="fa fa-plus"></i> <?php echo _l('sales_pipeline_new_deal'); ?>
                                    </a>
                                    <?php } ?>
                                </div>
                            </div>

                            <!-- Alert Banner deal thiếu giá nhập -->
                            <?php if (isset($total_missing_cost_prices) && $total_missing_cost_prices > 0) { ?>
                            <div class="alert alert-danger" style="margin-bottom: 15px; border-left: 4px solid #fc2d42;">
                                <i class="fa fa-exclamation-triangle" style="font-size: 16px; margin-right: 8px;"></i>
                                Có <strong><?php echo $total_missing_cost_prices; ?></strong> cơ hội bán hàng (deal) đang thiếu thông tin giá nhập.
                                <a href="<?php echo admin_url('sales_pipeline/missing_cost_prices'); ?>" class="alert-link" style="text-decoration: underline; margin-left: 10px;">
                                    [Xem và cập nhật ngay]
                                </a>
                            </div>
                            <?php } ?>

                            <!-- Toggle buttons row -->
                            <div class="row">
                                <div class="col-md-5">
                                    <a href="#" class="btn btn-default btn-with-tooltip" data-toggle="tooltip" 
                                       data-title="Tổng quan Kinh Doanh" data-placement="bottom" 
                                       onclick="slideToggle('.sales-pipeline-overview'); return false;">
                                        <i class="fa fa-bar-chart"></i>
                                    </a>
                                    <a href="<?php echo admin_url('sales_pipeline/switch_kanban/' . $switch_kanban . $query_string); ?>" 
                                       class="btn btn-default mleft10 hidden-xs">
                                        <?php if($switch_kanban == 1) { 
                                            echo '<i class="fa fa-th"></i> Chuyển sang Kanban';
                                        } else { 
                                            echo '<i class="fa fa-list"></i> Chuyển sang Danh sách';
                                        } ?>
                                    </a>
                                </div>
                                <div class="col-md-4 col-xs-12 pull-right pipeline-search">
                                    <div data-toggle="tooltip" data-placement="bottom" data-title="Tìm kiếm theo tên công ty hoặc sản phẩm (nhấn Enter)">
                                        <?php echo render_input('search', '', isset($current_search) ? $current_search : '', 'search', array(
                                            'data-name' => 'search',
                                            'placeholder' => 'Tìm kiếm deal... (Nhấn Enter)'
                                        ), array(), 'no-margin'); ?>
                                    </div>
                                    <?php echo form_hidden('sort_type'); ?>
                                    <?php echo form_hidden('sort', ''); ?>
                                </div>
                            </div>
                            <div class="clearfix"></div>
                        </div>

                        <hr class="hr-panel-heading" />

                        <!-- Bộ lọc -->
                        <div class="row mbot15">
                            <div class="col-md-2">
                                <select name="filter_quarter" id="filter_quarter" class="selectpicker" data-width="100%"
                                        data-none-selected-text="<?php echo _l('sales_pipeline_filter_quarter'); ?>"
                                        onchange="applyFilters()">
                                    <option value=""><?php echo _l('sales_pipeline_all_quarters'); ?></option>
                                    <option value="1" <?php echo ($current_quarter == 1) ? 'selected' : ''; ?>>Quý 1 (T1-3)</option>
                                    <option value="2" <?php echo ($current_quarter == 2) ? 'selected' : ''; ?>>Quý 2 (T4-6)</option>
                                    <option value="3" <?php echo ($current_quarter == 3) ? 'selected' : ''; ?>>Quý 3 (T7-9)</option>
                                    <option value="4" <?php echo ($current_quarter == 4) ? 'selected' : ''; ?>>Quý 4 (T10-12)</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="filter_year" id="filter_year" class="selectpicker" data-width="100%"
                                        onchange="applyFilters()">
                                    <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--) { ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($current_year == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="filter_staff" id="filter_staff" class="selectpicker" data-width="100%"
                                        data-live-search="true"
                                        data-none-selected-text="<?php echo _l('sales_pipeline_filter_staff'); ?>"
                                        onchange="applyFilters()">
                                    <option value=""><?php echo _l('sales_pipeline_all_staff'); ?></option>
                                    <?php foreach ($staff as $member) { ?>
                                    <option value="<?php echo $member['staffid']; ?>"
                                        <?php echo ($current_staff_id == $member['staffid']) ? 'selected' : ''; ?>>
                                        <?php echo $member['firstname'] . ' ' . $member['lastname']; ?>
                                    </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="filter_document" id="filter_document" class="selectpicker" data-width="100%"
                                        data-none-selected-text="Lọc chứng từ"
                                        onchange="applyFilters()">
                                    <option value="">Tất cả chứng từ</option>
                                    <option value="contract_yes" <?php echo (isset($current_contract_signed) && $current_contract_signed === '1') ? 'selected' : ''; ?>>Đã ký Hợp Đồng</option>
                                    <option value="contract_no" <?php echo (isset($current_contract_signed) && $current_contract_signed === '0') ? 'selected' : ''; ?>>Chưa ký HĐ</option>
                                    <option value="invoice_yes" <?php echo (isset($current_invoice_issued) && $current_invoice_issued === '1') ? 'selected' : ''; ?>>Đã xuất HĐN</option>
                                    <option value="invoice_no" <?php echo (isset($current_invoice_issued) && $current_invoice_issued === '0') ? 'selected' : ''; ?>>Chưa xuất HĐN</option>
                                </select>
                            </div>
                            <?php if ($switch_kanban == 1) { // Only show pagination dropdown in List view ?>
                            <div class="col-md-2">
                                <select name="per_page" id="per_page" class="selectpicker" data-width="100%"
                                        onchange="applyFilters()">
                                    <option value="10" <?php echo ($per_page == 10) ? 'selected' : ''; ?>>10 / trang</option>
                                    <option value="25" <?php echo ($per_page == 25) ? 'selected' : ''; ?>>25 / trang</option>
                                    <option value="50" <?php echo ($per_page == 50) ? 'selected' : ''; ?>>50 / trang</option>
                                    <option value="100" <?php echo ($per_page == 100) ? 'selected' : ''; ?>>100 / trang</option>
                                </select>
                            </div>
                            <?php } ?>
                        </div>


<!-- Tổng quan thống kê (Collapsible) -->
                <div id="sales_pipeline_summary" class="row mbot15 sales-pipeline-overview">
                    <div class="col-md-3 col-sm-6">
                        <div class="panel_s">
                            <div class="panel-body padding-10 text-center">
                                <h3 class="no-margin text-info font-bold"><?php echo number_format($summary['total_deals']); ?></h3>
                                <p class="text-muted no-margin"><?php echo _l('sales_pipeline_total_deals'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="panel_s">
                            <div class="panel-body padding-10 text-center">
                                <h3 class="no-margin text-primary font-bold"><?php echo number_format($summary['total_value']); ?></h3>
                                <p class="text-muted no-margin"><?php echo _l('sales_pipeline_total_value'); ?> (VNĐ)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <div class="panel_s">
                            <div class="panel-body padding-10 text-center">
                                <h3 class="no-margin text-success font-bold"><?php echo $summary['won_deals']; ?></h3>
                                <p class="text-muted no-margin"><?php echo _l('sales_pipeline_won'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <div class="panel_s">
                            <div class="panel-body padding-10 text-center">
                                <h3 class="no-margin text-warning font-bold"><?php echo $summary['active_deals']; ?></h3>
                                <p class="text-muted no-margin"><?php echo _l('sales_pipeline_active'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <div class="panel_s">
                            <div class="panel-body padding-10 text-center">
                                <h3 class="no-margin text-danger font-bold"><?php echo $summary['lost_deals']; ?></h3>
                                <p class="text-muted no-margin"><?php echo _l('sales_pipeline_lost'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                        <!-- Tab content for Kanban and List views -->
                        <div class="tab-content">
                            <?php if($this->session->has_userdata('sales_pipeline_kanban_view') && $this->session->userdata('sales_pipeline_kanban_view') == 'true') { ?>
                            <!-- Kanban View -->
                            <div class="active kan-ban-tab" id="kan-ban-tab" style="overflow:auto;">
                                <div class="kanban-deals-sort">
                                    <span class="bold">Sắp xếp theo: </span>
                                    <a href="#" onclick="pipeline_kanban_sort('deal_date'); return false" class="deal_date">
                                        <i class="kanban-sort-icon fa fa-sort-amount-desc"></i> Ngày tạo
                                    </a>
                                    |
                                    <a href="#" onclick="pipeline_kanban_sort('deal_value'); return false;" class="deal_value">
                                        Giá trị deal
                                    </a>
                                    |
                                    <a href="#" onclick="pipeline_kanban_sort('actual_profit'); return false;" class="actual_profit">
                                        Lợi nhuận
                                    </a>
                                </div>
                                <div class="row">
                                    <div class="container-fluid">
                                        <div id="kan-ban" class="pipeline-kan-ban"></div>
                                    </div>
                                </div>
                            </div>
                            <?php } else { ?>
                            <!-- List View (existing table) -->
                            <div class="row" id="deals-table">

                        <hr class="hr-panel-heading" />

                        <!-- Bảng danh sách deal -->
                        <div class="table-responsive">
                            <table class="table table-striped table-sales-pipeline">
                                <thead>
                                    <tr>
                                        <th width="3%">Số TT</th>
                                        <th width="8%">Ngày tạo</th>
                                        <th width="14%">Tên công ty</th>
                                        <th width="13%">Mô tả sản phẩm/dịch vụ</th>
                                        <th width="9%" class="text-right">Giá nhập (VNĐ)</th>
                                        <th width="9%" class="text-right">Giá bán (VNĐ)</th>
                                        <th width="9%" class="text-right">Lợi nhuận (VNĐ)</th>
                                        <th width="8%">Nhân viên</th>
                                        <th width="9%">Trạng thái</th>

                                        <th width="10%">Ghi chú</th>
                                        <?php if (is_admin() || has_permission('sales_pipeline', '', 'view_deal_details')) { ?>
                                        <th width="5%" class="text-center">Chi tiết</th>
                                        <?php } ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($deals)) {
                                        $stt = 0;
                                        foreach ($deals as $deal) {
                                            $stt++;
                                            $missing_cost = isset($deal['missing_cost_price']) && $deal['missing_cost_price'] == 1;
                                            $row_class = $missing_cost ? 'warning-row' : '';
                                            ?>
                                    <tr class="<?php echo $row_class; ?>" data-deal-id="<?php echo $deal['id']; ?>">
                                        <!-- 1. Số TT -->
                                        <td><?php echo $stt; ?></td>
                                        
                                        <!-- 2. Ngày tạo -->
                                        <td><?php echo _d($deal['deal_date']); ?></td>
                                        
                                        <!-- 3. Tên công ty -->
                                        <td>
                                            <strong><?php echo html_escape($deal['customer_name']); ?></strong>
                                            <?php if (!empty($deal['contact_name'])) { ?>
                                            <br><small class="text-muted"><?php echo html_escape($deal['contact_name']); ?></small>
                                            <?php } ?>
                                        </td>
                                        
                                        <!-- 4. Mô tả sản phẩm/dịch vụ -->
                                        <td><?php echo html_escape($deal['deal_name']); ?></td>
                                        
                                        <!-- 5. Giá nhập (VNĐ) -->
                                        <td class="text-right cost-price-cell">
                                            <?php if ($missing_cost) { ?>
                                                <span class="missing-cost-badge" title="<?php echo _l('sales_pipeline_missing_cost_price_hint'); ?>">
                                                    <i class="fa fa-exclamation-triangle text-warning"></i>
                                                    <span class="text-muted"><?php echo _l('sales_pipeline_not_filled'); ?></span>
                                                </span>
                                                <?php if (is_admin() || $deal['staff_id'] == get_staff_user_id() || (isset($deal['imported_by']) && $deal['imported_by'] == get_staff_user_id())) { ?>
                                                <br>
                                                <a href="#" class="btn btn-xs btn-info edit-cost-price" data-deal-id="<?php echo $deal['id']; ?>" data-deal-value="<?php echo $deal['deal_value']; ?>">
                                                    <i class="fa fa-edit"></i> <?php echo _l('sales_pipeline_fill_cost_price'); ?>
                                                </a>
                                                <?php } ?>
                                            <?php } else { ?>
                                                <span class="cost-price-value"><?php echo number_format($deal['cost_price']); ?></span>
                                                <?php if (is_admin() || $deal['staff_id'] == get_staff_user_id() || (isset($deal['imported_by']) && $deal['imported_by'] == get_staff_user_id())) { ?>
                                                <br>
                                                <a href="#" class="btn btn-xs btn-default edit-cost-price" data-deal-id="<?php echo $deal['id']; ?>" data-deal-value="<?php echo $deal['deal_value']; ?>" data-current-cost="<?php echo $deal['cost_price']; ?>">
                                                    <i class="fa fa-edit"></i>
                                                </a>
                                                <?php } ?>
                                            <?php } ?>
                                        </td>
                                        
                                        <!-- 6. Giá bán (VNĐ) -->
                                        <td class="text-right font-bold"><?php echo number_format($deal['deal_value']); ?></td>
                                        
                                        <!-- 7. Lợi nhuận (VNĐ) -->
                                        <td class="text-right profit-cell">
                                            <?php if ($missing_cost) { ?>
                                                <span class="text-muted">--</span>
                                            <?php } else { ?>
                                                <span class="profit-value"><?php echo number_format($deal['actual_profit']); ?></span>
                                                <br>
                                                <small class="text-muted profit-percent">(<?php echo round($deal['profit_percentage'], 1); ?>%)</small>
                                            <?php } ?>
                                        </td>
                                        
                                        <!-- 8. Nhân viên -->
                                        <td>
                                            <a href="<?php echo admin_url('staff/profile/' . $deal['staff_id']); ?>">
                                                <?php echo staff_profile_image($deal['staff_id'], ['staff-profile-image-small']); ?>
                                                <?php echo html_escape($deal['staff_name']); ?>
                                            </a>
                                        </td>
                                        
                                        <!-- 9. Trạng thái -->
                                        <td>
                                            <span class="label" style="background:<?php echo $deal['status_color']; ?>; color:#fff;">
                                                <?php echo $deal['status_name']; ?>
                                            </span>
                                        </td>
                                        

                                        <!-- 10. Ghi chú -->
                                        <td>
                                            <?php 
                                            // Display notes from Excel col H + latest activity updates
                                            $notes_display = '';
                                            
                                            // Get base notes from Excel import (col H)
                                            if (!empty($deal['notes'])) {
                                                $notes_display = html_escape($deal['notes']);
                                            }
                                            
                                            // Truncate if too long (show first 100 chars)
                                            if (strlen($notes_display) > 100) {
                                                $notes_display = mb_substr($notes_display, 0, 100, 'UTF-8') . '...';
                                            }
                                            
                                            if (!empty($notes_display)) {
                                                echo '<span class="notes-content">' . nl2br($notes_display) . '</span>';
                                            } else {
                                                echo '<span class="text-muted">--</span>';
                                            }
                                            ?>
                                        </td>
                                        
                                        <!-- 12. Chi tiết (Admin hoặc có quyền view_deal_details) -->
                                        <?php if (is_admin() || has_permission('sales_pipeline', '', 'view_deal_details')) { ?>
                                        <td class="text-center">
                                            <a href="<?php echo admin_url('sales_pipeline/deal/' . $deal['id'] . $query_string); ?>"
                                               class="btn btn-xs btn-info" 
                                               title="Xem chi tiết đầy đủ và lịch sử cập nhật"
                                               data-toggle="tooltip">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        </td>
                                        <?php } ?>
                                    </tr>
                                    <?php }
                                    } else { ?>
                                    <tr>
                                        <td colspan="<?php echo (is_admin() || has_permission('sales_pipeline', '', 'view_deal_details')) ? '11' : '10'; ?>" class="text-center text-muted">
                                            <i class="fa fa-inbox fa-2x mbot10"></i><br>
                                            <?php echo _l('sales_pipeline_no_deals'); ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Controls -->
                        <?php if ($total_pages > 1) { ?>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="text-muted">
                                    Hiển thị <?php echo (($current_page - 1) * $per_page) + 1; ?> 
                                    đến <?php echo min($current_page * $per_page, $total_deals); ?> 
                                    trong tổng số <?php echo number_format($total_deals); ?> deal
                                </p>
                            </div>
                            <div class="col-md-6 text-right">
                                <nav>
                                    <ul class="pagination pagination-sm" style="margin: 0;">
                                        <!-- Previous Button -->
                                        <?php if ($current_page > 1) { ?>
                                        <li>
                                            <a href="#" onclick="goToPage(<?php echo $current_page - 1; ?>); return false;">
                                                <i class="fa fa-chevron-left"></i> Trước
                                            </a>
                                        </li>
                                        <?php } else { ?>
                                        <li class="disabled"><span><i class="fa fa-chevron-left"></i> Trước</span></li>
                                        <?php } ?>

                                        <!-- Page Numbers -->
                                        <?php
                                        $start_page = max(1, $current_page - 2);
                                        $end_page = min($total_pages, $current_page + 2);
                                        
                                        // Always show first page
                                        if ($start_page > 1) {
                                            echo '<li><a href="#" onclick="goToPage(1); return false;">1</a></li>';
                                            if ($start_page > 2) {
                                                echo '<li class="disabled"><span>...</span></li>';
                                            }
                                        }
                                        
                                        // Show page numbers
                                        for ($i = $start_page; $i <= $end_page; $i++) {
                                            if ($i == $current_page) {
                                                echo '<li class="active"><span>' . $i . '</span></li>';
                                            } else {
                                                echo '<li><a href="#" onclick="goToPage(' . $i . '); return false;">' . $i . '</a></li>';
                                            }
                                        }
                                        
                                        // Always show last page
                                        if ($end_page < $total_pages) {
                                            if ($end_page < $total_pages - 1) {
                                                echo '<li class="disabled"><span>...</span></li>';
                                            }
                                            echo '<li><a href="#" onclick="goToPage(' . $total_pages . '); return false;">' . $total_pages . '</a></li>';
                                        }
                                        ?>

                                        <!-- Next Button -->
                                        <?php if ($current_page < $total_pages) { ?>
                                        <li>
                                            <a href="#" onclick="goToPage(<?php echo $current_page + 1; ?>); return false;">
                                                Sau <i class="fa fa-chevron-right"></i>
                                            </a>
                                        </li>
                                        <?php } else { ?>
                                        <li class="disabled"><span>Sau <i class="fa fa-chevron-right"></i></span></li>
                                        <?php } ?>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                        <?php } ?>

                            </div>
                            <!-- End List View -->
                            <?php } ?>
                        </div>
                        <!-- End tab-content -->

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for editing cost price -->
<div class="modal fade" id="costPriceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-money"></i> <?php echo _l('sales_pipeline_update_cost_price'); ?></h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="modal_deal_value"><?php echo _l('sales_pipeline_deal_value'); ?> (VNĐ)</label>
                    <input type="text" class="form-control" id="modal_deal_value" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label for="modal_cost_price"><span class="text-danger">*</span> <?php echo _l('sales_pipeline_cost_price'); ?> (VNĐ)</label>
                    <input type="number" class="form-control" id="modal_cost_price" min="0" step="1000" placeholder="<?php echo _l('sales_pipeline_enter_cost_price'); ?>">
                    <small class="text-muted"><?php echo _l('sales_pipeline_cost_price_hint'); ?></small>
                </div>
                <div class="form-group" id="profit_preview" style="display:none;">
                    <label><?php echo _l('sales_pipeline_profit_preview'); ?></label>
                    <div class="well well-sm">
                        <strong><?php echo _l('sales_pipeline_profit'); ?>:</strong> <span id="preview_profit" class="text-success">0</span> VNĐ
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
.missing-cost-badge {
    display: inline-block;
}
.notes-content {
    font-size: 13px;
    color: #555;
    line-height: 1.5;
}

/* Kanban Styles */
.pipeline-kan-ban {
    display: flex;
    gap: 15px;
    overflow-x: auto;
    padding-bottom: 20px;
}

.pipeline-kan-ban-col {
    min-width: 300px;
    max-width: 300px;
    background: #f9f9f9;
    border-radius: 4px;
    padding: 10px;
}

.pipeline-kan-ban-col .panel_s {
    margin-bottom: 10px;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.pipeline-kan-ban-col .panel_s:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.pipeline-kan-ban-col .panel-heading {
    cursor: move;
    padding: 10px;
    background: #fff;
    border-bottom: 1px solid #e5e5e5;
}

.pipeline-kan-ban-col .panel-body {
    padding: 10px;
    background: #fff;
}

.pipeline-kan-ban-col ul {
    list-style: none;
    padding: 0;
    margin: 0;
    min-height: 50px;
}

.pipeline-kan-ban-col li {
    margin-bottom: 10px;
}

.kanban-status-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px;
    background: #fff;
    border-radius: 4px;
    margin-bottom: 10px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.kanban-status-name {
    font-weight: bold;
    font-size: 14px;
}

.kanban-status-count {
    background: #84c529;
    color: #fff;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.kanban-placeholder {
    background: #e3f2fd;
    border: 2px dashed #2196f3;
    border-radius: 4px;
    margin-bottom: 10px;
}

.dragging {
    opacity: 0.7;
}

.kanban-deals-sort {
    margin-bottom: 15px;
    padding: 10px;
    background: #f5f5f5;
    border-radius: 4px;
}

.kanban-deals-sort a {
    color: #333;
    text-decoration: none;
    margin: 0 5px;
}

.kanban-deals-sort a:hover {
    color: #84c529;
}

.kanban-load-more {
    text-align: center;
    margin-top: 10px;
}

.kanban-load-more .btn {
    width: 100%;
}

/* Deal card specific styles */
.deal-card-header {
    font-weight: 600;
    margin-bottom: 8px;
    line-height: 1.3;
}

.deal-card-company {
    color: #333;
    font-size: 13px;
}

.deal-card-name {
    color: #666;
    font-size: 12px;
    margin-top: 3px;
}

.deal-card-value {
    font-size: 16px;
    font-weight: bold;
    color: #2196f3;
    margin: 8px 0;
}

.deal-card-profit {
    font-size: 14px;
    color: #4caf50;
}

.deal-card-profit-percent {
    font-size: 12px;
    color: #666;
    margin-left: 5px;
}

.deal-card-meta {
    font-size: 11px;
    color: #999;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #eee;
}

.deal-card-warning {
    background: #fff3cd;
    border-left: 3px solid #ff9800;
    padding: 8px;
    margin: 8px 0;
    font-size: 12px;
    color: #856404;
}

.label-deal-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    color: #fff;
}

/* Responsive Kanban */
@media (max-width: 768px) {
    .pipeline-kan-ban {
        flex-direction: column;
    }
    
    .pipeline-kan-ban-col {
        min-width: 100%;
        max-width: 100%;
    }
}
</style>

<script>
function applyFilters() {
    var quarter  = $('#filter_quarter').val();
    var year     = $('#filter_year').val();
    var staff    = $('#filter_staff').val();
    var perPage  = $('#per_page').val();
    var search   = $('input[name="search"]').val();
    var document = $('#filter_document').val();

    var url = admin_url + 'sales_pipeline?';
    if (quarter) url += 'quarter=' + quarter + '&';
    if (year)    url += 'year=' + year + '&';
    if (staff)   url += 'staff_id=' + staff + '&';
    if (perPage) url += 'per_page=' + perPage + '&';
    if (search)  url += 'search=' + encodeURIComponent(search) + '&';

    // Lọc chứng từ
    if (document === 'contract_yes') url += 'contract_signed=1&';
    if (document === 'contract_no')  url += 'contract_signed=0&';
    if (document === 'invoice_yes')  url += 'invoice_issued=1&';
    if (document === 'invoice_no')   url += 'invoice_issued=0&';

    window.location.href = url;
}

function goToPage(page) {
    var quarter  = $('#filter_quarter').val();
    var year     = $('#filter_year').val();
    var staff    = $('#filter_staff').val();
    var perPage  = $('#per_page').val();
    var document = $('#filter_document').val();

    var url = admin_url + 'sales_pipeline?page=' + page;
    if (quarter) url += '&quarter=' + quarter;
    if (year)    url += '&year=' + year;
    if (staff)   url += '&staff_id=' + staff;
    if (perPage) url += '&per_page=' + perPage;

    // Lọc chứng từ
    if (document === 'contract_yes') url += '&contract_signed=1';
    if (document === 'contract_no')  url += '&contract_signed=0';
    if (document === 'invoice_yes')  url += '&invoice_issued=1';
    if (document === 'invoice_no')   url += '&invoice_issued=0';

    window.location.href = url;
}

// Kanban functionality
var pipeline_kanban = (function() {
    var kanbanServerParams = {};
    
    return function(search) {
        if (typeof(search) != 'undefined') {
            kanbanServerParams['search'] = search;
        } else {
            kanbanServerParams['search'] = $('input[name="search"]').val() || '';
        }
        
        kanbanServerParams['sort'] = $('input[name="sort"]').val() || '';
        kanbanServerParams['sort_type'] = $('input[name="sort_type"]').val() || '';
        kanbanServerParams['quarter'] = $('#filter_quarter').val() || '';
        kanbanServerParams['year'] = $('#filter_year').val() || '';
        kanbanServerParams['staff_id'] = $('#filter_staff').val() || '';

        var document = $('#filter_document').val() || '';
        if (document === 'contract_yes') {
            kanbanServerParams['contract_signed'] = '1';
            kanbanServerParams['invoice_issued'] = '';
        } else if (document === 'contract_no') {
            kanbanServerParams['contract_signed'] = '0';
            kanbanServerParams['invoice_issued'] = '';
        } else if (document === 'invoice_yes') {
            kanbanServerParams['contract_signed'] = '';
            kanbanServerParams['invoice_issued'] = '1';
        } else if (document === 'invoice_no') {
            kanbanServerParams['contract_signed'] = '';
            kanbanServerParams['invoice_issued'] = '0';
        } else {
            kanbanServerParams['contract_signed'] = '';
            kanbanServerParams['invoice_issued'] = '';
        }
        
        var loadArea = $('#kan-ban');
        if (loadArea.length === 0) return;
        
        $.ajax({
            url: admin_url + 'sales_pipeline/kanban',
            data: kanbanServerParams,
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                loadArea.html(response.kanban);
                
                // Initialize sortable for each status column
                $('.pipeline-kan-ban-col').each(function() {
                    init_kanban_sortable($(this));
                });
            }
        });
    }
})();

// Sort Kanban by field
function pipeline_kanban_sort(by) {
    var sort = $('input[name="sort"]');
    var sort_type = $('input[name="sort_type"]');
    
    // Toggle sort direction
    if (sort.val() == by) {
        if (sort_type.val() == 'asc') {
            sort_type.val('desc');
        } else {
            sort_type.val('asc');
        }
    } else {
        sort_type.val('asc');
    }
    
    sort.val(by);
    
    // Update sort icon
    $('.kanban-deals-sort a').find('.kanban-sort-icon').remove();
    $('.kanban-deals-sort a.' + by).prepend('<i class="kanban-sort-icon fa fa-sort-amount-' + sort_type.val() + '"></i> ');
    
    // Reload kanban with new sort
    pipeline_kanban();
}

// Initialize sortable for kanban columns
function init_kanban_sortable(col) {
    var placeholder = $('<div class="kanban-placeholder"></div>');
    
    col.find('ul').sortable({
        placeholder: placeholder,
        connectWith: '.pipeline-kan-ban-col ul',
        handle: '.panel-heading',
        tolerance: 'pointer',
        helper: 'clone',
        forcePlaceholderSize: true,
        opacity: 0.95,
        scroll: true,
        scrollSensitivity: 100,
        scrollSpeed: 15,
        
        start: function(event, ui) {
            placeholder.height(ui.helper.outerHeight());
            ui.helper.addClass('dragging');
        },
        
        stop: function(event, ui) {
            ui.item.removeClass('dragging');
        },
        
        update: function(event, ui) {
            if (this === ui.item.parent()[0]) {
                var deal_id = ui.item.attr('data-deal-id');
                var new_status_id = ui.item.closest('.pipeline-kan-ban-col').attr('data-status-id');
                
                // Update deal status via AJAX
                $.post(admin_url + 'sales_pipeline/update_deal_status', {
                    deal_id: deal_id,
                    status_id: new_status_id
                }).done(function(response) {
                    if (response.success) {
                        alert_float('success', 'Đã cập nhật trạng thái deal thành công');
                        
                        // Update status badge color
                        ui.item.find('.label-deal-status').css('background', response.status_color);
                        
                        // Update count badges
                        $('.pipeline-kan-ban-col').each(function() {
                            var status_id = $(this).attr('data-status-id');
                            var count = $(this).find('li[data-deal-id]').length;
                            $(this).find('.kanban-status-count').text(count);
                        });
                    } else {
                        alert_float('danger', response.message || 'Có lỗi xảy ra');
                        pipeline_kanban(); // Reload to revert
                    }
                }).fail(function() {
                    alert_float('danger', 'Có lỗi xảy ra khi cập nhật trạng thái');
                    pipeline_kanban(); // Reload to revert
                });
            }
        }
    });
}

// Load more deals for a specific status column
function pipeline_load_more(status_id, page, button) {
    var btn = $(button);
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Đang tải...');
    
    $.ajax({
        url: admin_url + 'sales_pipeline/kanban_load_more',
        method: 'POST',
        dataType: 'json',
        data: {
            status_id: status_id,
            page: page,
            search: $('input[name="search"]').val() || '',
            sort: $('input[name="sort"]').val() || '',
            sort_type: $('input[name="sort_type"]').val() || '',
            quarter: $('#filter_quarter').val() || '',
            year: $('#filter_year').val() || '',
            staff_id: $('#filter_staff').val() || ''
        },
        success: function(response) {
            if (response.html) {
                var col = $('.pipeline-kan-ban-col[data-status-id="' + status_id + '"]');
                var list = col.find('ul');
                
                // Remove load more button
                btn.parent().remove();
                
                // Append new deals
                list.append(response.html);
                
                // Update count
                var count = list.find('li[data-deal-id]').length;
                col.find('.kanban-status-count').text(count);
                
                // Re-initialize sortable
                init_kanban_sortable(col);
            }
        },
        error: function() {
            alert_float('danger', 'Có lỗi xảy ra khi tải thêm deal');
            btn.prop('disabled', false).html('<i class="fa fa-angle-down"></i> Tải thêm');
        }
    });
}

$(function() {
    // Load kanban if in kanban view
    if ($('#kan-ban').length) {
        pipeline_kanban();
    }
    
    // Search logic (debounced for Kanban, Enter for List View)
    var searchTimer;
    $('input[name="search"]').on('input', function(e) {
        var val = $(this).val();
        
        if ($('#kan-ban').length) {
            // Sync URL parameters in real-time in Kanban view to preserve search state when switching views
            var urlParams = new URLSearchParams(window.location.search);
            if (val) {
                urlParams.set('search', val);
            } else {
                urlParams.delete('search');
            }
            var newQuery = urlParams.toString();
            var newUrl = window.location.pathname + (newQuery ? '?' + newQuery : '');
            window.history.replaceState({}, '', newUrl);

            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() {
                pipeline_kanban(val);
            }, 300);
        }
    });

    $('input[name="search"]').on('keypress', function(e) {
        if (e.which == 13) { // Enter key
            e.preventDefault();
            if ($('#kan-ban').length) {
                pipeline_kanban($(this).val());
            } else {
                applyFilters();
            }
        }
    });
    
    // Open cost price edit modal
    $('body').on('click', '.edit-cost-price', function(e) {
        e.preventDefault();
        var dealId = $(this).data('deal-id');
        var dealValue = $(this).data('deal-value');
        var currentCost = $(this).data('current-cost') || '';

        $('#modal_deal_id').val(dealId);
        $('#modal_deal_value').val(parseInt(dealValue).toLocaleString('vi-VN'));
        $('#modal_cost_price').val(currentCost);
        $('#profit_preview').hide();

        $('#costPriceModal').modal('show');
    });

    // Calculate profit preview
    $('#modal_cost_price').on('input', function() {
        var dealValue = parseFloat($('#modal_deal_value').val().replace(/[^0-9]/g, ''));
        var costPrice = parseFloat($(this).val()) || 0;

        if (costPrice > 0 && dealValue > 0) {
            var profit = dealValue - costPrice;
            var percent = (profit / dealValue) * 100;

            $('#preview_profit').text(profit.toLocaleString('vi-VN'));
            $('#preview_percent').text(percent.toFixed(2));
            $('#profit_preview').show();
        } else {
            $('#profit_preview').hide();
        }
    });

    // Save cost price via AJAX
    $('#saveCostPrice').on('click', function() {
        var dealId = $('#modal_deal_id').val();
        var costPrice = $('#modal_cost_price').val();

        if (!costPrice || parseFloat(costPrice) < 0) {
            alert('<?php echo _l('sales_pipeline_invalid_cost_price'); ?>');
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <?php echo _l('please_wait'); ?>');

        $.ajax({
            url: admin_url + 'sales_pipeline/update_cost_price',
            type: 'POST',
            dataType: 'json',
            data: {
                pipeline_id: dealId,
                cost_price: costPrice
            },
            success: function(response) {
                if (response.success) {
                    alert_float('success', response.message);
                    $('#costPriceModal').modal('hide');
                    
                    // Update table row
                    var $row = $('tr[data-deal-id="' + dealId + '"]');
                    $row.removeClass('warning-row');
                    
                    // Update cost price cell (column 5: index 4)
                    var $costCell = $row.find('.cost-price-cell');
                    var dealValue = $row.find('td:eq(5)').text().replace(/[^0-9]/g, ''); // Giá bán ở cột 6 (index 5)
                    $costCell.html(
                        '<span class="cost-price-value">' + parseInt(response.data.cost_price).toLocaleString('vi-VN') + '</span>' +
                        '<br><a href="#" class="btn btn-xs btn-default edit-cost-price" data-deal-id="' + dealId + '" data-deal-value="' + dealValue + '" data-current-cost="' + response.data.cost_price + '"><i class="fa fa-edit"></i></a>'
                    );
                    
                    // Update profit cell
                    if (response.data.actual_profit !== null) {
                        var $profitCell = $row.find('.profit-cell');
                        $profitCell.html(
                            '<span class="profit-value">' + parseInt(response.data.actual_profit).toLocaleString('vi-VN') + '</span>' +
                            '<br><small class="text-muted profit-percent">(' + parseFloat(response.data.profit_percentage).toFixed(1) + '%)</small>'
                        );
                    }
                } else {
                    alert_float('danger', response.message);
                }
            },
            error: function() {
                alert_float('danger', '<?php echo _l('something_went_wrong'); ?>');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo _l('save'); ?>');
            }
        });
    });
});
</script>
</body>
</html>
