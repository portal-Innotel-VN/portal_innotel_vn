<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo $title; ?></h4>
                        <hr class="hr-panel-heading" />
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle"></i>
                                    <?php echo _l('tech_reports_import_instructions'); ?>
                                </div>

                                <div class="text-center mbot20">
                                    <a href="<?php echo admin_url('tech_reports/download_template'); ?>" class="btn btn-success btn-lg">
                                        <i class="fa fa-download"></i>
                                        <?php echo _l('tech_reports_download_template'); ?>
                                    </a>
                                </div>

                                <?php echo form_open_multipart(admin_url('tech_reports/import'), ['id' => 'import_form']); ?>
                                
                                <div class="form-group">
                                    <label for="file_excel"><?php echo _l('tech_reports_import_file'); ?></label>
                                    <input type="file" name="file_excel" id="file_excel" class="form-control" accept=".xlsx,.xls" required>
                                    <p class="help-block"><?php echo _l('allowed_file_types') . ': .xlsx, .xls'; ?></p>
                                </div>

                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-upload"></i>
                                        <?php echo _l('import'); ?>
                                    </button>
                                    <a href="<?php echo admin_url('tech_reports'); ?>" class="btn btn-default">
                                        <?php echo _l('cancel'); ?>
                                    </a>
                                </div>

                                <?php echo form_close(); ?>
                            </div>
                            <div class="col-md-4">
                                <div class="panel_s">
                                    <div class="panel-body">
                                        <h4><?php echo _l('instructions'); ?></h4>
                                        <ul class="list-unstyled">
                                            <li><i class="fa fa-check text-success"></i> <?php echo _l('tech_report_staff'); ?>: Staff ID (required)</li>
                                            <li><i class="fa fa-check text-success"></i> <?php echo _l('tech_report_date'); ?>: YYYY-MM-DD (required)</li>
                                            <li><i class="fa fa-check text-success"></i> <?php echo _l('tech_report_task'); ?>: Description (required)</li>
                                            <li><i class="fa fa-info-circle text-info"></i> <?php echo _l('tech_report_category'); ?>: Optional</li>
                                            <li><i class="fa fa-info-circle text-info"></i> <?php echo _l('tech_report_hours'); ?>: Optional</li>
                                            <li><i class="fa fa-info-circle text-info"></i> <?php echo _l('tech_report_status'); ?>: Optional</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
    $(function() {
        appValidateForm($('#import_form'), {
            file_excel: 'required'
        });
    });
</script>
