<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin font-bold">
                            <i class="fa fa-upload"></i> <?php echo $title; ?>
                        </h4>
                        <hr class="hr-panel-heading" />

                        <div class="alert alert-warning" style="border-left: 4px solid #f39c12;">
                            <div class="flex" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                                <div>
                                    <i class="fa fa-file-excel-o text-warning" style="font-size:18px; margin-right:8px;"></i>
                                    <strong>Sử dụng file Template chuẩn để đảm bảo dữ liệu nhập đúng định dạng.</strong>
                                    <br>
                                    <small class="text-muted" style="margin-left:26px;">File đã có sẵn Data Validation và hướng dẫn điền đầy đủ.</small>
                                </div>
                                <a href="<?php echo admin_url('sales_pipeline/download_template'); ?>"
                                   class="btn btn-success btn-sm"
                                   style="white-space:nowrap; flex-shrink:0;">
                                    <i class="fa fa-download"></i> &nbsp;Tải Template (.xlsx)
                                </a>
                            </div>
                        </div>

                        <?php echo form_open_multipart(admin_url('sales_pipeline/import')); ?>

                        <?php if (!empty($can_assign_others)) { ?>
                        <div class="form-group">
                            <label for="staff_id" class="control-label">
                                <span class="text-danger">*</span> <?php echo _l('sales_pipeline_assigned_staff'); ?>
                            </label>
                            <select name="staff_id" id="staff_id" class="selectpicker" data-width="100%"
                                    data-live-search="true" required>
                                <?php foreach ($staff as $member) { ?>
                                <?php $sid = is_array($member) ? $member['staffid'] : $member->staffid; ?>
                                <?php $fname = is_array($member) ? $member['firstname'] : $member->firstname; ?>
                                <?php $lname = is_array($member) ? $member['lastname'] : $member->lastname; ?>
                                <option value="<?php echo $sid; ?>"
                                    <?php echo ($sid == get_staff_user_id()) ? 'selected' : ''; ?>>
                                    <?php echo $fname . ' ' . $lname; ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php } else { ?>
                        <input type="hidden" name="staff_id" value="<?php echo get_staff_user_id(); ?>">
                        <div class="form-group">
                            <label class="control-label"><?php echo _l('sales_pipeline_assigned_staff'); ?></label>
                            <p class="form-control-static">
                                <strong><?php echo get_staff_full_name(get_staff_user_id()); ?></strong>
                                <!-- <small class="text-muted">(Chỉ được import cho chính mình)</small> -->
                            </p>
                        </div>
                        <?php } ?>

                        <div class="form-group">
                            <label for="import_file" class="control-label">
                                <span class="text-danger">*</span> <?php echo _l('sales_pipeline_select_file'); ?>
                            </label>
                            <div class="file-upload-zone" id="file_upload_zone"
                                 style="border: 2px dashed #ccc; border-radius: 8px; padding: 40px;
                                        text-align: center; cursor: pointer; transition: all 0.3s;">
                                <i class="fa fa-file-excel-o fa-3x text-success"></i>
                                <p class="mtop10 text-muted">
                                    <?php echo _l('sales_pipeline_drag_file'); ?>
                                </p>
                                <p class="text-muted"><small><?php echo _l('sales_pipeline_file_types'); ?>: .xls, .xlsx</small></p>
                                <span id="file_name" class="font-bold text-info"></span>
                            </div>
                            <input type="file" name="import_file" id="import_file"
                                   accept=".xls,.xlsx" style="display: none;">
                        </div>

                        <hr />
                        <div class="text-right">
                            <a href="<?php echo admin_url('sales_pipeline'); ?>" class="btn btn-default mright5">
                                <i class="fa fa-arrow-left"></i> <?php echo _l('sales_pipeline_back'); ?>
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-upload"></i> <?php echo _l('sales_pipeline_upload_import'); ?>
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

<script>
$(function() {
    var zone = $('#file_upload_zone');
    var input = $('#import_file');

    // Click to upload
    zone.on('click', function() {
        input.trigger('click');
    });

    // Display file name
    input.on('change', function() {
        var fileName = this.files[0] ? this.files[0].name : '';
        $('#file_name').text(fileName);
        if (fileName) {
            zone.css('border-color', '#27ae60');
        }
    });

    // Drag & drop
    zone.on('dragover', function(e) {
        e.preventDefault();
        zone.css('border-color', '#3498db').css('background', '#f8f9fa');
    });

    zone.on('dragleave', function(e) {
        e.preventDefault();
        zone.css('border-color', '#ccc').css('background', '');
    });

    zone.on('drop', function(e) {
        e.preventDefault();
        zone.css('border-color', '#27ae60').css('background', '');
        input[0].files = e.originalEvent.dataTransfer.files;
        $('#file_name').text(e.originalEvent.dataTransfer.files[0].name);
    });

    // Validate form on submit
    $('form').on('submit', function(e) {
        if (!input.val()) {
            e.preventDefault();
            alert('Vui lòng kéo thả hoặc click chọn tệp tin Excel (.xls, .xlsx) trước khi nhấn Import.');
            return false;
        }
    });
});
</script>
</body>
</html>
