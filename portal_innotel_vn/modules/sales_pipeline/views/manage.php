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
                                       class="btn btn-default mright5" data-toggle="tooltip" title="<?php echo html_escape(_l('sales_pipeline_settings_sources_statuses')); ?>">
                                        <i class="fa fa-cogs"></i> <?php echo _l('sales_pipeline_settings'); ?>
                                    </a>
                                    <?php } ?>
                                    <?php if (!empty($can_access_dashboard)) { ?>
                                    <a href="<?php echo admin_url('sales_pipeline/dashboard'); ?>"
                                       class="btn btn-default mright5"
                                       data-toggle="tooltip"
                                       title="<?php echo html_escape(_l('sales_pipeline_dashboard_title')); ?>">
                                        <?php echo _l('sales_pipeline_dashboard_title'); ?>
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
                                <?php echo _l('sales_pipeline_missing_cost_count_alert', ['<strong>' . (int) $total_missing_cost_prices . '</strong>']); ?>
                                <a href="<?php echo admin_url('sales_pipeline/missing_cost_prices'); ?>" class="alert-link" style="text-decoration: underline; margin-left: 10px;">
                                    <?php echo _l('sales_pipeline_view_and_update_now'); ?>
                                </a>
                            </div>
                            <?php } ?>

                            <!-- Toggle buttons row -->
                            <div class="row">
                                <div class="col-md-9">
                                    <a href="#" class="btn btn-default btn-with-tooltip" data-toggle="tooltip" 
                                       data-title="<?php echo html_escape(_l('sales_pipeline_business_overview')); ?>" data-placement="bottom"
                                       onclick="slideToggle('.sales-pipeline-overview'); return false;">
                                        <i class="fa fa-bar-chart"></i>
                                    </a>
                                    <a href="<?php echo admin_url('sales_pipeline/switch_kanban/' . $switch_kanban . $query_string); ?>" 
                                       class="btn btn-default mleft10 hidden-xs js-sales-pipeline-switch-view"
                                       data-switch-kanban="<?php echo (int) $switch_kanban; ?>">
                                        <?php if($switch_kanban == 1) { 
                                            echo '<i class="fa fa-th"></i> ' . _l('sales_pipeline_switch_to_kanban');
                                        } else { 
                                            echo '<i class="fa fa-list"></i> ' . _l('sales_pipeline_switch_to_list');
                                        } ?>
                                    </a>
                                </div>
                                <div class="col-md-3 pull-right pipeline-search">
                                    <div class="form-group no-margin">
                                        <div class="input-group" style="width: 100%;">
                                            <input type="search" name="search" id="pipeline_search" class="form-control"
                                                   value="<?php echo isset($current_search) ? html_escape($current_search) : ''; ?>"
                                                   placeholder="<?php echo html_escape(_l('sales_pipeline_search_placeholder')); ?>"
                                                   autocomplete="off">
                                            <span class="input-group-btn">
                                                <button type="button" id="btn_clear_search" class="btn btn-default"
                                                        data-toggle="tooltip" data-placement="bottom"
                                                        title="<?php echo html_escape(_l('sales_pipeline_clear_search')); ?>"
                                                        style="<?php echo !empty($current_search) ? '' : 'display:none;'; ?>">
                                                    <i class="fa fa-times"></i>
                                                </button>
                                            </span>
                                        </div>
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
                                        onchange="if(window.triggerApplyFilters){window.triggerApplyFilters();}else{applyFilters();}">
                                    <option value=""><?php echo _l('sales_pipeline_all_quarters'); ?></option>
                                    <option value="1" <?php echo ($current_quarter == 1) ? 'selected' : ''; ?>><?php echo _l('sales_pipeline_q1'); ?></option>
                                    <option value="2" <?php echo ($current_quarter == 2) ? 'selected' : ''; ?>><?php echo _l('sales_pipeline_q2'); ?></option>
                                    <option value="3" <?php echo ($current_quarter == 3) ? 'selected' : ''; ?>><?php echo _l('sales_pipeline_q3'); ?></option>
                                    <option value="4" <?php echo ($current_quarter == 4) ? 'selected' : ''; ?>><?php echo _l('sales_pipeline_q4'); ?></option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="filter_year" id="filter_year" class="selectpicker" data-width="100%"
                                        onchange="if(window.triggerApplyFilters){window.triggerApplyFilters();}else{applyFilters();}">
                                    <option value="" <?php echo ($current_year === null || $current_year === '') ? 'selected' : ''; ?>><?php echo _l('sales_pipeline_all_years'); ?></option>
                                    <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--) { ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($current_year == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="filter_staff" id="filter_staff" class="selectpicker" data-width="100%"
                                        data-live-search="true"
                                        data-none-selected-text="<?php echo _l('sales_pipeline_filter_staff'); ?>"
                                        onchange="if(window.triggerApplyFilters){window.triggerApplyFilters();}else{applyFilters();}">
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
                                        data-none-selected-text="<?php echo html_escape(_l('sales_pipeline_filter_document')); ?>"
                                        onchange="if(window.triggerApplyFilters){window.triggerApplyFilters();}else{applyFilters();}">
                                    <option value=""><?php echo _l('sales_pipeline_all_documents'); ?></option>
                                    <option value="contract_yes" <?php echo (isset($current_contract_signed) && $current_contract_signed === '1') ? 'selected' : ''; ?>><?php echo _l('sales_pipeline_contract_signed_yes'); ?></option>
                                    <option value="contract_no" <?php echo (isset($current_contract_signed) && $current_contract_signed === '0') ? 'selected' : ''; ?>><?php echo _l('sales_pipeline_contract_signed_no'); ?></option>
                                    <option value="invoice_yes" <?php echo (isset($current_invoice_issued) && $current_invoice_issued === '1') ? 'selected' : ''; ?>><?php echo _l('sales_pipeline_invoice_issued_yes'); ?></option>
                                    <option value="invoice_no" <?php echo (isset($current_invoice_issued) && $current_invoice_issued === '0') ? 'selected' : ''; ?>><?php echo _l('sales_pipeline_invoice_issued_no'); ?></option>
                                </select>
                            </div>
                            <?php if ($switch_kanban == 1) { // Only show pagination dropdown in List view ?>
                            <div class="col-md-2">
                                <select name="per_page" id="per_page" class="selectpicker" data-width="100%"
                                        onchange="if(window.triggerApplyFilters){window.triggerApplyFilters();}else{applyFilters();}">
                                    <?php foreach ([10, 25, 50, 100] as $page_size) { ?>
                                    <option value="<?php echo $page_size; ?>" <?php echo ($per_page == $page_size) ? 'selected' : ''; ?>><?php echo _l('sales_pipeline_per_page', [$page_size]); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <?php } ?>
                        </div>


<!-- Tổng quan thống kê (Collapsible) -->
                <div id="sales_pipeline_summary" class="row mbot15 sales-pipeline-overview">
                    <div class="col-md-3 col-sm-6">
                        <div class="panel_s">
                            <div class="panel-body padding-10 text-center">
                                <h3 class="no-margin text-info font-bold" id="summary_total_deals"><?php echo number_format($summary['total_deals']); ?></h3>
                                <p class="text-muted no-margin"><?php echo _l('sales_pipeline_total_deals'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="panel_s">
                            <div class="panel-body padding-10 text-center">
                                <h3 class="no-margin text-primary font-bold" id="summary_total_value"><?php echo number_format($summary['total_value']); ?></h3>
                                <p class="text-muted no-margin"><?php echo _l('sales_pipeline_total_value'); ?> (<?php echo _l('sales_pipeline_vnd'); ?>)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <div class="panel_s">
                            <div class="panel-body padding-10 text-center">
                                <h3 class="no-margin text-success font-bold" id="summary_won_deals"><?php echo $summary['won_deals']; ?></h3>
                                <p class="text-muted no-margin"><?php echo _l('sales_pipeline_won'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <div class="panel_s">
                            <div class="panel-body padding-10 text-center">
                                <h3 class="no-margin text-warning font-bold" id="summary_active_deals"><?php echo $summary['active_deals']; ?></h3>
                                <p class="text-muted no-margin"><?php echo _l('sales_pipeline_active'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <div class="panel_s">
                            <div class="panel-body padding-10 text-center">
                                <h3 class="no-margin text-danger font-bold" id="summary_lost_deals"><?php echo $summary['lost_deals']; ?></h3>
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
                                    <span class="bold"><?php echo _l('sales_pipeline_sort_by'); ?> </span>
                                    <a href="#" onclick="pipeline_kanban_sort('deal_date'); return false" class="deal_date">
                                        <i class="kanban-sort-icon fa fa-sort-amount-desc"></i> <?php echo _l('sales_pipeline_expected_date'); ?>
                                    </a>
                                    |
                                    <a href="#" onclick="pipeline_kanban_sort('deal_value'); return false;" class="deal_value">
                                        <?php echo _l('sales_pipeline_sort_deal_value'); ?>
                                    </a>
                                    |
                                    <a href="#" onclick="pipeline_kanban_sort('actual_profit'); return false;" class="actual_profit">
                                        <?php echo _l('sales_pipeline_profit'); ?>
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
                                        <th width="3%"><?php echo _l('sales_pipeline_sequence_number'); ?></th>
                                        <th width="8%"><?php echo _l('sales_pipeline_expected_date'); ?></th>
                                        <th width="14%"><?php echo _l('sales_pipeline_customer_name'); ?></th>
                                        <th width="13%"><?php echo _l('sales_pipeline_deal_name'); ?></th>
                                        <th width="9%" class="text-right"><?php echo _l('sales_pipeline_cost_price'); ?> (<?php echo _l('sales_pipeline_vnd'); ?>)</th>
                                        <th width="9%" class="text-right"><?php echo _l('sales_pipeline_deal_value'); ?> (<?php echo _l('sales_pipeline_vnd'); ?>)</th>
                                        <th width="9%" class="text-right"><?php echo _l('sales_pipeline_profit'); ?> (<?php echo _l('sales_pipeline_vnd'); ?>)</th>
                                        <th width="8%"><?php echo _l('sales_pipeline_assigned_staff'); ?></th>
                                        <th width="9%"><?php echo _l('sales_pipeline_status'); ?></th>

                                        <th width="10%"><?php echo _l('sales_pipeline_notes'); ?></th>
                                        <?php if (is_admin() || has_permission('sales_pipeline', '', 'view_deal_details') || has_permission('sales_pipeline', '', 'delete')) { ?>
                                        <th width="8%" class="text-center"><?php echo _l('sales_pipeline_actions'); ?></th>
                                        <?php } ?>
                                    </tr>
                                </thead>
                                <tbody id="sales_pipeline_table_body">
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
                                        
                                        <!-- 12. Hành động (Chi tiết / Xóa) -->
                                        <?php if (is_admin() || has_permission('sales_pipeline', '', 'view_deal_details') || has_permission('sales_pipeline', '', 'delete')) { ?>
                                        <td class="text-center">
                                            <?php if (is_admin() || has_permission('sales_pipeline', '', 'view_deal_details')) { ?>
                                            <a href="<?php echo admin_url('sales_pipeline/deal/' . $deal['id'] . $query_string); ?>"
                                               class="btn btn-xs btn-info" 
                                               title="<?php echo html_escape(_l('sales_pipeline_view_full_details')); ?>"
                                               data-toggle="tooltip">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            <?php } ?>
                                            <?php if (is_admin() || has_permission('sales_pipeline', '', 'delete')) { ?>
                                            <a href="<?php echo admin_url('sales_pipeline/delete/' . $deal['id'] . $query_string); ?>"
                                               class="btn btn-xs btn-danger _delete" 
                                               title="<?php echo html_escape(_l('sales_pipeline_delete_deal')); ?>"
                                               data-toggle="tooltip">
                                                <i class="fa fa-trash-o" aria-hidden="true"></i>
                                            </a>
                                            <?php } ?>
                                        </td>
                                        <?php } ?>
                                    </tr>
                                    <?php }
                                    } else { ?>
                                    <tr>
                                        <td colspan="<?php echo (is_admin() || has_permission('sales_pipeline', '', 'view_deal_details') || has_permission('sales_pipeline', '', 'delete')) ? '11' : '10'; ?>" class="text-center text-muted">
                                            <i class="fa fa-inbox fa-2x mbot10"></i><br>
                                            <?php echo _l('sales_pipeline_no_deals'); ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Controls Container -->
                        <div id="sales_pipeline_pagination">
                        <?php if ($total_pages > 1) { ?>
                        <div class="row mtop15">
                            <div class="col-md-6">
                                <p class="text-muted"><?php echo _l('sales_pipeline_pagination_summary', [
                                    (($current_page - 1) * $per_page) + 1,
                                    min($current_page * $per_page, $total_deals),
                                    number_format($total_deals),
                                ]); ?></p>
                            </div>
                            <div class="col-md-6 text-right">
                                <nav>
                                    <ul class="pagination pagination-sm" style="margin: 0;">
                                        <!-- Previous Button -->
                                        <?php if ($current_page > 1) { ?>
                                        <li>
                                            <a href="#" onclick="goToPage(<?php echo $current_page - 1; ?>); return false;">
                                                <i class="fa fa-chevron-left"></i> <?php echo _l('sales_pipeline_pagination_prev'); ?>
                                            </a>
                                        </li>
                                        <?php } else { ?>
                                        <li class="disabled"><span><i class="fa fa-chevron-left"></i> <?php echo _l('sales_pipeline_pagination_prev'); ?></span></li>
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
/* Sticky Footer Flexbox Layout */
html, body {
    height: 100%;
}
#wrapper {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}
#wrapper > .content {
    flex-grow: 1;
    flex: 1 0 auto;
}
#wrapper > footer,
#wrapper > .footer {
    flex-shrink: 0;
}

.pipeline-search .input-group {
    width: 100% !important;
}
.pipeline-search #pipeline_search {
    width: 100% !important;
}
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

.pipeline-kan-ban-col-placeholder {
    min-width: 300px;
    max-width: 300px;
    background: #e3f2fd;
    border: 2px dashed #2196f3;
    border-radius: 4px;
    min-height: 200px;
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
var salesPipelineLang = <?php echo json_encode([
    'loadingData' => _l('sales_pipeline_loading_data'),
    'paginatedLoadFailed' => _l('sales_pipeline_paginated_load_failed'),
    'serverConnectionFailed' => _l('sales_pipeline_server_connection_failed', ['%s']),
    'pageLoadFailed' => _l('sales_pipeline_page_load_failed', ['%s']),
    'noMatchingDeals' => _l('sales_pipeline_no_matching_deals'),
    'missingCostInformation' => _l('sales_pipeline_missing_cost_information'),
    'notEntered' => _l('sales_pipeline_not_entered'),
    'enterCost' => _l('sales_pipeline_enter_cost'),
    'viewDetails' => _l('sales_pipeline_view_details'),
    'deleteDeal' => _l('sales_pipeline_delete_deal'),
    'paginationSummary' => _l('sales_pipeline_pagination_summary', ['%s', '%s', '%s']),
    'previous' => _l('sales_pipeline_pagination_prev'),
    'next' => _l('sales_pipeline_pagination_next'),
    'locale' => _l('sales_pipeline_js_locale'),
    'responseProcessingError' => _l('sales_pipeline_response_processing_error'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

function salesPipelineTranslate(key, replacements) {
    var translated = salesPipelineLang[key] || key;
    $.each(replacements || [], function(index, replacement) {
        translated = translated.replace('%s', replacement);
    });
    return translated;
}

if (typeof window.sp_alert !== 'function') {
    window.sp_alert = function(type, message, timeout) {
        if (typeof alert_float === 'function') {
            alert_float(type, message, timeout);
        } else if (typeof console !== 'undefined' && console.log) {
            console.log('[' + type + '] ' + message);
        }
    };
}

function applyDocumentFilterParams(params) {
    var doc = $('#filter_document').val() || '';

    delete params.contract_signed;
    delete params.invoice_issued;

    if (doc === 'contract_yes') {
        params.contract_signed = 1;
    } else if (doc === 'contract_no') {
        params.contract_signed = 0;
    } else if (doc === 'invoice_yes') {
        params.invoice_issued = 1;
    } else if (doc === 'invoice_no') {
        params.invoice_issued = 0;
    }

    return params;
}

function getSalesPipelineFilterParams(includeListPagination) {
    var params = {
        search: $('#pipeline_search').val() || '',
        quarter: $('#filter_quarter').val() || '',
        year: $('#filter_year').val() || '',
        staff_id: $('#filter_staff').val() || '',
        sort: $('input[name="sort"]').val() || '',
        sort_type: $('input[name="sort_type"]').val() || ''
    };

    if (includeListPagination) {
        params.per_page = $('#per_page').val() || '';
    }

    return applyDocumentFilterParams(params);
}

function cleanSalesPipelineParams(params) {
    var cleaned = {};
    $.each(params || {}, function(k, v) {
        if (v !== null && v !== '' && v !== undefined) {
            cleaned[k] = v;
        }
    });
    return cleaned;
}

function preserveSalesPipelinePageSize(params) {
    var stateParams = $.extend({}, params || {});
    if (!stateParams.per_page) {
        var currentPerPage = new URLSearchParams(window.location.search).get('per_page');
        if (currentPerPage) {
            stateParams.per_page = currentPerPage;
        }
    }

    return stateParams;
}

function syncSalesPipelineUrl(params) {
    var stateParams = preserveSalesPipelinePageSize(params);
    var urlParams = new URLSearchParams();
    $.each(cleanSalesPipelineParams(stateParams), function(k, v) {
        urlParams.set(k, v);
    });

    var newQuery = urlParams.toString();
    var newUrl = window.location.pathname + (newQuery ? '?' + newQuery : '');
    window.history.replaceState({}, '', newUrl);
    syncSalesPipelineSwitchUrl(stateParams);
}

function syncSalesPipelineSwitchUrl(params) {
    var $switch = $('.js-sales-pipeline-switch-view');
    if (!$switch.length) return;

    var switchKanban = $switch.data('switch-kanban');
    var queryParams = cleanSalesPipelineParams(preserveSalesPipelinePageSize(params));
    delete queryParams.page;

    var query = new URLSearchParams();
    $.each(queryParams, function(k, v) {
        query.set(k, v);
    });

    $switch.attr('href', admin_url + 'sales_pipeline/switch_kanban/' + switchKanban + (query.toString() ? '?' + query.toString() : ''));
}

function fetchDealsAjax(page) {
    page = page || 1;
    var params = getSalesPipelineFilterParams(true);
    params.page = page;
    syncSalesPipelineUrl(params);

    // Hiển thị hiệu ứng loading
    $('#sales_pipeline_table_body').html('<tr><td colspan="11" class="text-center p20 text-muted"><i class="fa fa-spinner fa-spin fa-2x mbot10"></i><br>' + salesPipelineLang.loadingData + '</td></tr>');

    $.ajax({
        url: admin_url + 'sales_pipeline/ajax_search',
        type: 'GET',
        data: params,
        dataType: 'json',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(response) {
            var isSuccess = response && (response.status === true || response.success === true);
            var resData = response ? (response.data || response) : null;

            if (isSuccess && resData && resData.pagination) {
                var offset = (resData.pagination.current_page - 1) * resData.pagination.per_page;
                renderDealTable(resData.deals, {
                    can_view_deal_details: !!resData.can_view_deal_details,
                    can_delete_deal: !!resData.can_delete_deal,
                    can_edit_cost_price: !!resData.can_edit_cost_price
                }, resData.current_user_id, offset);
                renderPagination(resData.pagination);
                updateSummary(resData.summary);
            } else {
                $('#sales_pipeline_table_body').html('<tr><td colspan="11" class="text-center p20 text-danger"><i class="fa fa-exclamation-triangle fa-2x mbot10"></i><br>' + (response && response.message ? response.message : salesPipelineLang.paginatedLoadFailed) + '</td></tr>');
            }
        },
        error: function(xhr, status, error) {
            $('#sales_pipeline_table_body').html('<tr><td colspan="11" class="text-center p20 text-danger"><i class="fa fa-exclamation-triangle fa-2x mbot10"></i><br>' + salesPipelineTranslate('serverConnectionFailed', [xhr.status || '500']) + '</td></tr>');
            if (typeof sp_alert === 'function') {
                sp_alert('danger', salesPipelineTranslate('pageLoadFailed', [page]));
            }
        }
    });
}

function applyFilters() {
    if ($('#kan-ban').length) {
        var search = $('input[name="search"]').val();
        pipeline_kanban(search);
    } else {
        fetchDealsAjax(1);
    }
}

function goToPage(page) {
    if ($('#kan-ban').length) {
        return;
    }
    fetchDealsAjax(page);
}

function updateSummary(summary) {
    if (!summary) return;
    if ($('#summary_total_deals').length) $('#summary_total_deals').text(number_format(summary.total_deals));
    if ($('#summary_total_value').length) $('#summary_total_value').text(number_format(summary.total_value));
    if ($('#summary_won_deals').length) $('#summary_won_deals').text(summary.won_deals);
    if ($('#summary_active_deals').length) $('#summary_active_deals').text(summary.active_deals);
    if ($('#summary_lost_deals').length) $('#summary_lost_deals').text(summary.lost_deals);
}

function renderDealTable(deals, permissions, currentUserId, offset) {
    var $tbody = $('#sales_pipeline_table_body');
    $tbody.empty();

    permissions = permissions || {};
    var canViewDealDetails = !!permissions.can_view_deal_details;
    var canDeleteDeal = !!permissions.can_delete_deal;
    var canEditCostPrice = !!permissions.can_edit_cost_price;
    var canRenderActions = canViewDealDetails || canDeleteDeal;

    if (!deals || deals.length === 0) {
        var colSpan = canRenderActions ? 11 : 10;
        $tbody.html('<tr><td colspan="' + colSpan + '" class="text-center text-muted p20"><i class="fa fa-inbox fa-2x mbot10"></i><br>' + salesPipelineLang.noMatchingDeals + '</td></tr>');
        return;
    }

    var html = '';
    $.each(deals, function(index, deal) {
        var stt = offset + index + 1;
        var missingCost = (deal.missing_cost_price == 1 || deal.cost_price === null || deal.cost_price === '');
        var rowClass = missingCost ? 'warning-row' : '';
        var canEditCost = canEditCostPrice || deal.staff_id == currentUserId || (deal.imported_by && deal.imported_by == currentUserId);

        html += '<tr class="' + rowClass + '" data-deal-id="' + deal.id + '">';
        // 1. STT
        html += '<td>' + stt + '</td>';
        // 2. Ngày tạo
        html += '<td>' + (deal.deal_date ? formatSalesPipelineDate(deal.deal_date) : '') + '</td>';
        // 3. Tên công ty
        html += '<td><strong>' + escapeHtml(deal.customer_name) + '</strong>';
        if (deal.contact_name) {
            html += '<br><small class="text-muted">' + escapeHtml(deal.contact_name) + '</small>';
        }
        html += '</td>';
        // 4. Mô tả SP/DV
        html += '<td>' + escapeHtml(deal.deal_name) + '</td>';
        // 5. Giá nhập (VNĐ)
        html += '<td class="text-right cost-price-cell">';
        if (missingCost) {
            html += '<span class="missing-cost-badge" title="' + salesPipelineLang.missingCostInformation + '"><i class="fa fa-exclamation-triangle text-warning"></i> <span class="text-muted">' + salesPipelineLang.notEntered + '</span></span>';
            if (canEditCost) {
                html += '<br><a href="#" class="btn btn-xs btn-info edit-cost-price" data-deal-id="' + deal.id + '" data-deal-value="' + deal.deal_value + '"><i class="fa fa-edit"></i> ' + salesPipelineLang.enterCost + '</a>';
            }
        } else {
            html += '<span class="cost-price-value">' + number_format(deal.cost_price) + '</span>';
            if (canEditCost) {
                html += '<br><a href="#" class="btn btn-xs btn-default edit-cost-price" data-deal-id="' + deal.id + '" data-deal-value="' + deal.deal_value + '" data-current-cost="' + deal.cost_price + '"><i class="fa fa-edit"></i></a>';
            }
        }
        html += '</td>';
        // 6. Giá bán (VNĐ)
        html += '<td class="text-right font-bold">' + number_format(deal.deal_value) + '</td>';
        // 7. Lợi nhuận (VNĐ)
        html += '<td class="text-right profit-cell">';
        if (missingCost) {
            html += '<span class="text-muted">--</span>';
        } else {
            var profit = deal.actual_profit !== null ? deal.actual_profit : (deal.deal_value - deal.cost_price);
            var profitPct = deal.profit_percentage !== null ? parseFloat(deal.profit_percentage).toFixed(1) : ((profit / deal.deal_value) * 100).toFixed(1);
            html += '<span class="profit-value">' + number_format(profit) + '</span><br><small class="text-muted profit-percent">(' + profitPct + '%)</small>';
        }
        html += '</td>';
        // 8. Nhân viên
        html += '<td><a href="' + admin_url + 'staff/profile/' + deal.staff_id + '">' + escapeHtml(deal.staff_name) + '</a></td>';
        // 9. Trạng thái
        html += '<td><span class="label" style="background:' + deal.status_color + '; color:#fff;">' + escapeHtml(deal.status_name) + '</span></td>';
        // 10. Ghi chú
        var notesDisplay = deal.notes ? escapeHtml(deal.notes) : '';
        if (notesDisplay.length > 100) {
            notesDisplay = notesDisplay.substring(0, 100) + '...';
        }
        html += '<td>' + (notesDisplay ? '<span class="notes-content">' + notesDisplay + '</span>' : '<span class="text-muted">--</span>') + '</td>';
        // 11. Hành động
        if (canRenderActions) {
            // Lấy query string hiện tại (đã được cập nhật bởi fetchDealsAjax qua history.replaceState)
            var currentQs = window.location.search;
            html += '<td class="text-center">';
            if (canViewDealDetails) {
                html += '<a href="' + admin_url + 'sales_pipeline/deal/' + deal.id + currentQs + '" class="btn btn-xs btn-info mright5" title="' + salesPipelineLang.viewDetails + '" data-toggle="tooltip"><i class="fa fa-eye"></i></a>';
            }
            if (canDeleteDeal) {
                html += '<a href="' + admin_url + 'sales_pipeline/delete/' + deal.id + currentQs + '" class="btn btn-xs btn-danger _delete" title="' + salesPipelineLang.deleteDeal + '" data-toggle="tooltip"><i class="fa fa-trash-o"></i></a>';
            }
            html += '</td>';
        }
        html += '</tr>';
    });

    $tbody.html(html);
    $('[data-toggle="tooltip"]').tooltip();
}

function renderPagination(p) {
    var $container = $('#sales_pipeline_pagination');
    if (!p || p.total_pages <= 1) {
        $container.html('');
        return;
    }

    var startRecord = ((p.current_page - 1) * p.per_page) + 1;
    var endRecord = Math.min(p.current_page * p.per_page, p.total_records);

    var html = '<div class="row mtop15">';
    html += '<div class="col-md-6">';
    html += '<p class="text-muted">' + salesPipelineTranslate('paginationSummary', [startRecord, endRecord, number_format(p.total_records)]) + '</p>';
    html += '</div>';
    html += '<div class="col-md-6 text-right">';
    html += '<nav><ul class="pagination pagination-sm" style="margin:0;">';

    if (p.current_page > 1) {
        html += '<li><a href="#" onclick="goToPage(' + (p.current_page - 1) + '); return false;"><i class="fa fa-chevron-left"></i> ' + salesPipelineLang.previous + '</a></li>';
    } else {
        html += '<li class="disabled"><span><i class="fa fa-chevron-left"></i> ' + salesPipelineLang.previous + '</span></li>';
    }

    var startPage = Math.max(1, p.current_page - 2);
    var endPage = Math.min(p.total_pages, p.current_page + 2);

    if (startPage > 1) {
        html += '<li><a href="#" onclick="goToPage(1); return false;">1</a></li>';
        if (startPage > 2) {
            html += '<li class="disabled"><span>...</span></li>';
        }
    }

    for (var i = startPage; i <= endPage; i++) {
        if (i === p.current_page) {
            html += '<li class="active"><span>' + i + '</span></li>';
        } else {
            html += '<li><a href="#" onclick="goToPage(' + i + '); return false;">' + i + '</a></li>';
        }
    }

    if (endPage < p.total_pages) {
        if (endPage < p.total_pages - 1) {
            html += '<li class="disabled"><span>...</span></li>';
        }
        html += '<li><a href="#" onclick="goToPage(' + p.total_pages + '); return false;">' + p.total_pages + '</a></li>';
    }

    if (p.current_page < p.total_pages) {
        html += '<li><a href="#" onclick="goToPage(' + (p.current_page + 1) + '); return false;">' + salesPipelineLang.next + ' <i class="fa fa-chevron-right"></i></a></li>';
    } else {
        html += '<li class="disabled"><span>' + salesPipelineLang.next + ' <i class="fa fa-chevron-right"></i></span></li>';
    }

    html += '</ul></nav>';
    html += '</div></div>';

    $container.html(html);
}

function number_format(val) {
    if (val === null || val === undefined || isNaN(val)) return '0';
    return parseInt(val).toLocaleString(salesPipelineLang.locale);
}

function formatSalesPipelineDate(dateStr) {
    if (!dateStr) return '';
    var parts = dateStr.split('-');
    if (parts.length === 3) {
        return new Date(parts[0], parts[1] - 1, parts[2]).toLocaleDateString(salesPipelineLang.locale);
    }
    return dateStr;
}

function escapeHtml(text) {
    if (!text) return '';
    return $('<div>').text(text).html();
}

// Kanban functionality
var pipeline_kanban = (function() {
    var kanbanServerParams = {};
    
    return function(search) {
        kanbanServerParams = getSalesPipelineFilterParams(false);
        if (typeof(search) != 'undefined') {
            kanbanServerParams.search = search;
        }

        // Keep transport-only data (CSRF) out of the browser URL and switch-view link.
        syncSalesPipelineUrl(kanbanServerParams);

        if (typeof(csrfData) !== 'undefined') {
            kanbanServerParams[csrfData['token_name']] = csrfData['hash'];
        }
        
        var loadArea = $('#kan-ban');
        if (loadArea.length === 0) return;
        
        $.ajax({
            url: admin_url + 'sales_pipeline/kanban',
            data: kanbanServerParams,
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                var resData = response ? (response.data || response) : {};
                loadArea.html(resData.kanban || '');
                if (resData.summary) {
                    updateSummary(resData.summary);
                }
                
                init_pipeline_status_sortable();
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

function init_pipeline_status_sortable() {
    $('#kan-ban').sortable({
        helper: 'clone',
        items: '.pipeline-kan-ban-col',
        handle: '.panel-heading',
        tolerance: 'pointer',
        placeholder: 'pipeline-kan-ban-col-placeholder',
        forcePlaceholderSize: true,
        update: function(event, ui) {
            var order = [];
            var cols = $('.pipeline-kan-ban-col');
            var i = 0;
            $.each(cols, function() {
                order.push([$(this).data('status-id'), i]);
                i++;
            });
            var postParams = { order: order };
            if (typeof(csrfData) !== 'undefined') {
                postParams[csrfData['token_name']] = csrfData['hash'];
            }
            $.post(admin_url + 'sales_pipeline/update_status_order', postParams);
        }
    });
}

// Load more deals for a specific status column
function pipeline_load_more(status_id, page, button) {
    var btn = $(button);
    var params = getSalesPipelineFilterParams(false);
    params.status_id = status_id;
    params.page = page;

    if (typeof(csrfData) !== 'undefined') {
        params[csrfData['token_name']] = csrfData['hash'];
    }

    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <?php echo _l('please_wait'); ?>');
    
    $.ajax({
        url: admin_url + 'sales_pipeline/kanban_load_more',
        method: 'POST',
        dataType: 'json',
        data: params,
        success: function(response) {
            var resData = (response && response.data) ? response.data : response;
            if (resData && resData.html) {
                var col = $('.pipeline-kan-ban-col[data-status-id="' + status_id + '"]');
                var list = col.find('ul');
                var loadMoreContainer = btn.parent();
                
                // Append new deals
                list.append(resData.html);
                
                // Update count
                var count = list.find('li[data-deal-id]').length;
                col.find('.kanban-status-count').text(count);
                
                // Check if there are still more pages
                var currentPage = resData.page || page;
                var totalPages  = resData.total_pages || 0;

                if (currentPage < totalPages && resData.html.trim() !== '') {
                    // Còn dữ liệu -> Cập nhật nút cho trang tiếp theo (currentPage + 1)
                    btn.prop('disabled', false)
                       .html('<i class="fa fa-angle-down"></i> <?php echo _l('load_more'); ?>')
                       .attr('onclick', 'pipeline_load_more(' + status_id + ', ' + (currentPage + 1) + ', this); return false;');
                } else {
                    // Đã hết dữ liệu -> Xóa hoàn toàn nút Tải thêm khỏi DOM
                    loadMoreContainer.remove();
                }
                
                // Re-initialize sortable
                init_pipeline_status_sortable();
            } else {
                btn.parent().remove();
            }
        },
        error: function() {
            sp_alert('danger', '<?php echo _l('sales_pipeline_load_more_failed'); ?>');
            btn.prop('disabled', false).html('<i class="fa fa-angle-down"></i> <?php echo _l('load_more'); ?>');
        }
    });
}

$(function() {
    syncSalesPipelineSwitchUrl(getSalesPipelineFilterParams(!!$('#per_page').length));

    var applyFilterTimer;
    window.triggerApplyFilters = function() {
        clearTimeout(applyFilterTimer);
        applyFilterTimer = setTimeout(function() {
            applyFilters();
        }, 50);
    };

    // Lắng nghe sự kiện change & changed.bs.select cho Bootstrap-Select
    $(document).on('change changed.bs.select change.bs.select', '#filter_quarter, #filter_year, #filter_staff, #filter_document, #per_page', function() {
        window.triggerApplyFilters();
    });

    // Dự phòng click trên menu dropdown của Bootstrap-Select
    $(document).on('click', '.bootstrap-select .dropdown-menu li a', function() {
        window.triggerApplyFilters();
    });

    // Load kanban if in kanban view
    if ($('#kan-ban').length) {
        pipeline_kanban();
    }
    
    // Search logic (debounced for Kanban, Enter for List View)
    var searchTimer;
    var $searchInput = $('input[name="search"]');
    var $clearBtn = $('#btn_clear_search');

    // Toggle hiển thị nút Clear (×) khi ô tìm kiếm có nội dung
    function toggleClearBtn() {
        if ($searchInput.val().trim().length > 0) {
            $clearBtn.show();
        } else {
            $clearBtn.hide();
        }
    }

    $searchInput.on('input', function(e) {
        var val = $(this).val();
        toggleClearBtn();
        
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            if ($('#kan-ban').length) {
                pipeline_kanban(val);
            } else {
                fetchDealsAjax(1);
            }
        }, 300);
    });

    $searchInput.on('keypress', function(e) {
        if (e.which == 13) { // Enter key
            e.preventDefault();
            clearTimeout(searchTimer);
            if ($('#kan-ban').length) {
                pipeline_kanban($(this).val());
            } else {
                fetchDealsAjax(1);
            }
        }
    });

    // Nút Clear (×): Xóa nội dung tìm kiếm và nạp lại danh sách qua AJAX
    $clearBtn.on('click', function() {
        clearTimeout(searchTimer);
        $searchInput.val('').focus();
        $(this).hide();

        if ($('#kan-ban').length) {
            pipeline_kanban('');
        } else {
            fetchDealsAjax(1);
        }
    });
    
    // Open cost price edit modal
    $('body').on('click', '.edit-cost-price', function(e) {
        e.preventDefault();
        var dealId = $(this).data('deal-id');
        var dealValue = $(this).data('deal-value');
        var currentCost = $(this).data('current-cost') || '';

        $('#modal_deal_id').val(dealId);
        $('#modal_deal_value').val(parseInt(dealValue).toLocaleString(salesPipelineLang.locale));
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

            $('#preview_profit').text(profit.toLocaleString(salesPipelineLang.locale));
            $('#preview_percent').text(percent.toFixed(2));
            $('#profit_preview').show();
        } else {
            $('#profit_preview').hide();
        }
    });

    // Save cost price via AJAX
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
            // Điều này đảm bảo người dùng không thể click lại trước khi throttle hết hạn
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

                        // Update table row
                        var $row = $('tr[data-deal-id="' + dealId + '"]');
                        $row.removeClass('warning-row');

                        // Update cost price cell (column 5: index 4)
                        var $costCell = $row.find('.cost-price-cell');
                        if ($costCell.length > 0 && response.data) {
                            var dealValueStr = $row.find('td:eq(5)').text() || '';
                            var dealValue = dealValueStr.replace(/[^0-9]/g, ''); // Giá bán ở cột 6 (index 5)
                            var costValFormatted = response.data.cost_price ? parseInt(response.data.cost_price).toLocaleString(salesPipelineLang.locale) : '0';
                            $costCell.html(
                                '<span class="cost-price-value">' + costValFormatted + '</span>' +
                                '<br><a href="#" class="btn btn-xs btn-default edit-cost-price" data-deal-id="' + dealId + '" data-deal-value="' + dealValue + '" data-current-cost="' + response.data.cost_price + '"><i class="fa fa-edit"></i></a>'
                            );
                        }

                        // Update profit cell
                        if (response.data && response.data.actual_profit !== null && typeof response.data.actual_profit !== 'undefined') {
                            var $profitCell = $row.find('.profit-cell');
                            if ($profitCell.length > 0) {
                                var pct = response.data.profit_percentage !== null ? parseFloat(response.data.profit_percentage).toFixed(1) : 0;
                                $profitCell.html(
                                    '<span class="profit-value">' + parseInt(response.data.actual_profit).toLocaleString(salesPipelineLang.locale) + '</span>' +
                                    '<br><small class="text-muted profit-percent">(' + pct + '%)</small>'
                                );
                            }
                        }

                        // Trigger reload if table isn't present (e.g. Kanban)
                        if ($('tr[data-deal-id="' + dealId + '"]').length === 0) {
                            if (typeof(pipeline_kanban) === 'function') {
                                pipeline_kanban();
                            }
                        }
                    } else {
                        sp_alert('danger', (response && response.message) ? response.message : '<?php echo _l('something_went_wrong'); ?>');
                    }
                } catch (e) {
                    console.error(salesPipelineLang.responseProcessingError, e);
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
</script>
</body>
</html>
