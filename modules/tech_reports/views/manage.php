<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="_buttons">
                            <?php if (has_permission('tech_reports', '', 'create')) { ?>
                                <a href="<?php echo admin_url('tech_reports/report'); ?>" class="btn btn-primary pull-left display-block">
                                    <i class="fa-regular fa-plus tw-mr-1"></i>
                                    <?php echo _l('tech_report_add'); ?>
                                </a>
                            <?php } ?>
                            <a href="<?php echo admin_url('tech_reports/export?' . http_build_query($_GET)); ?>" class="btn btn-default pull-left display-block mleft5">
                                <i class="fa fa-download tw-mr-1"></i>
                                <?php echo _l('tech_report_export'); ?>
                            </a>
                            <a href="<?php echo admin_url('tech_reports/import'); ?>" class="btn btn-info pull-left display-block mleft5">
                                <i class="fa fa-upload tw-mr-1"></i>
                                <?php echo _l('tech_reports_import'); ?>
                            </a>
                            <div class="clearfix"></div>
                        </div>
                        <hr class="hr-panel-heading" />
                        
                        <!-- Filters -->
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="filter_staff"><?php echo _l('tech_report_staff'); ?></label>
                                    <select name="filter_staff" id="filter_staff" class="selectpicker" data-width="100%" data-live-search="true">
                                        <option value=""><?php echo _l('all'); ?></option>
                                        <?php foreach ($staff_members as $staff) { ?>
                                            <option value="<?php echo $staff['staffid']; ?>">
                                                <?php echo $staff['firstname'] . ' ' . $staff['lastname']; ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="filter_category"><?php echo _l('tech_report_category'); ?></label>
                                    <select name="filter_category" id="filter_category" class="selectpicker" data-width="100%">
                                        <option value=""><?php echo _l('all'); ?></option>
                                        <?php foreach ($task_categories as $key => $category) { ?>
                                            <option value="<?php echo $key; ?>"><?php echo $category; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="filter_date_from"><?php echo _l('date_from'); ?></label>
                                    <input type="date" id="filter_date_from" class="form-control" value="<?php echo date('Y-m-01'); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="filter_date_to"><?php echo _l('date_to'); ?></label>
                                    <input type="date" id="filter_date_to" class="form-control" value="<?php echo date('Y-m-t'); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="button" id="btn_filter" class="btn btn-info btn-block">
                                        <?php echo _l('tech_report_filter'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Table -->
                        <?php render_datatable([
                            _l('tech_report_id'),
                            _l('tech_report_staff'),
                            _l('tech_report_date'),
                            _l('tech_report_task'),
                            _l('tech_report_category'),
                            _l('tech_report_hours'),
                            _l('tech_report_status'),
                            _l('tech_table_actions'),
                        ], 'tech_reports'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
    $(function() {
        initDataTable('.table-tech_reports', window.location.href, undefined, undefined, undefined, [7, 'desc']);

        $('#btn_filter').on('click', function() {
            var table = $('.table-tech_reports').DataTable();
            table.ajax.reload();
        });

        // Apply filters to datatable
        $.each(['filter_staff', 'filter_category', 'filter_date_from', 'filter_date_to'], function(i, filter) {
            $('#' + filter).on('change', function() {
                var table = $('.table-tech_reports').DataTable();
                table.ajax.reload();
            });
        });
    });
</script>
