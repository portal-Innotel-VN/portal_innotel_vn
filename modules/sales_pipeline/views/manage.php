<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">

                <!-- Tổng quan thống kê -->
                <div class="row mbot15">
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
                                <p class="text-muted no-margin">✅ <?php echo _l('sales_pipeline_won'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <div class="panel_s">
                            <div class="panel-body padding-10 text-center">
                                <h3 class="no-margin text-warning font-bold"><?php echo $summary['active_deals']; ?></h3>
                                <p class="text-muted no-margin">⏳ <?php echo _l('sales_pipeline_active'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <div class="panel_s">
                            <div class="panel-body padding-10 text-center">
                                <h3 class="no-margin text-danger font-bold"><?php echo $summary['lost_deals']; ?></h3>
                                <p class="text-muted no-margin">❌ <?php echo _l('sales_pipeline_lost'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel_s">
                    <div class="panel-body">
                        <!-- Tiêu đề & Nút hành động -->
                        <div class="clearfix mbot15">
                            <h4 class="no-margin font-bold pull-left" style="line-height: 34px;">
                                <i class="fa fa-line-chart"></i> <?php echo $title; ?>
                            </h4>
                            <div class="pull-right">
                                <?php if (has_permission('sales_pipeline', '', 'create')) { ?>
                                <a href="<?php echo admin_url('sales_pipeline/import'); ?>"
                                   class="btn btn-default mright5">
                                    <i class="fa fa-upload"></i> <?php echo _l('sales_pipeline_import_excel'); ?>
                                </a>
                                <a href="<?php echo admin_url('sales_pipeline/deal'); ?>"
                                   class="btn btn-info">
                                    <i class="fa fa-plus"></i> <?php echo _l('sales_pipeline_new_deal'); ?>
                                </a>
                                <?php } ?>
                            </div>
                        </div>

                        <!-- Bộ lọc -->
                        <div class="row mbot15">
                            <div class="col-md-3">
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
                            <div class="col-md-3">
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
                        </div>

                        <hr class="hr-panel-heading" />

                        <!-- Bảng danh sách deal -->
                        <div class="table-responsive">
                            <table class="table table-striped table-sales-pipeline">
                                <thead>
                                    <tr>
                                        <th width="3%">#</th>
                                        <th width="10%"><?php echo _l('sales_pipeline_expected_date'); ?></th>
                                        <th width="18%"><?php echo _l('sales_pipeline_customer_name'); ?></th>
                                        <th width="18%"><?php echo _l('sales_pipeline_deal_name'); ?></th>
                                        <th width="10%"><?php echo _l('staff'); ?></th>
                                        <th width="12%" class="text-right"><?php echo _l('sales_pipeline_deal_value'); ?></th>
                                        <th width="6%" class="text-center">% LN</th>
                                        <th width="11%" class="text-right"><?php echo _l('sales_pipeline_profit'); ?></th>
                                        <th width="8%"><?php echo _l('sales_pipeline_status'); ?></th>
                                        <th width="4%" class="text-center"><?php echo _l('options'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($deals)) {
                                        $stt = 0;
                                        foreach ($deals as $deal) {
                                            $stt++; ?>
                                    <tr>
                                        <td><?php echo $stt; ?></td>
                                        <td><?php echo _d($deal['expected_close_date']); ?></td>
                                        <td>
                                            <strong><?php echo html_escape($deal['customer_name']); ?></strong>
                                            <?php if (!empty($deal['contact_name'])) { ?>
                                            <br><small class="text-muted"><?php echo html_escape($deal['contact_name']); ?></small>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo html_escape($deal['deal_name']); ?></td>
                                        <td>
                                            <a href="<?php echo admin_url('staff/profile/' . $deal['staff_id']); ?>">
                                                <?php echo staff_profile_image($deal['staff_id'], ['staff-profile-image-small']); ?>
                                                <?php echo html_escape($deal['staff_name']); ?>
                                            </a>
                                        </td>
                                        <td class="text-right font-bold"><?php echo number_format($deal['deal_value']); ?></td>
                                        <td class="text-center"><?php echo round($deal['profit_margin'] * 100); ?>%</td>
                                        <td class="text-right"><?php echo number_format($deal['expected_profit']); ?></td>
                                        <td>
                                            <span class="label" style="background:<?php echo $deal['status_color']; ?>; color:#fff;">
                                                <?php echo $deal['status_name']; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if (has_permission('sales_pipeline', '', 'edit')) { ?>
                                            <a href="<?php echo admin_url('sales_pipeline/deal/' . $deal['id']); ?>"
                                               class="btn btn-default btn-icon" title="Sửa">
                                                <i class="fa fa-pencil-square-o"></i>
                                            </a>
                                            <?php } ?>
                                            <?php if (has_permission('sales_pipeline', '', 'delete')) { ?>
                                            <a href="<?php echo admin_url('sales_pipeline/delete/' . $deal['id']); ?>"
                                               class="btn btn-danger btn-icon _delete" title="Xóa">
                                                <i class="fa fa-remove"></i>
                                            </a>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php }
                                    } else { ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted">
                                            <i class="fa fa-inbox fa-2x mbot10"></i><br>
                                            <?php echo _l('sales_pipeline_no_deals'); ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
function applyFilters() {
    var quarter = $('#filter_quarter').val();
    var year    = $('#filter_year').val();
    var staff   = $('#filter_staff').val();

    var url = admin_url + 'sales_pipeline?';
    if (quarter) url += 'quarter=' + quarter + '&';
    if (year)    url += 'year=' + year + '&';
    if (staff)   url += 'staff_id=' + staff + '&';

    window.location.href = url;
}
</script>
</body>
</html>
