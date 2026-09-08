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
                                    <strong><?php echo _l('sales_pipeline_import_template_instruction_1'); ?></strong>
                                    <br>
                                    <small class="text-muted" style="margin-left:26px;"><?php echo _l('sales_pipeline_import_template_instruction_2'); ?></small>
                                </div>
                                <a href="<?php echo admin_url('sales_pipeline/download_template'); ?>"
                                   class="btn btn-success btn-sm"
                                   style="white-space:nowrap; flex-shrink:0;">
                                    <i class="fa fa-download"></i> &nbsp;<?php echo _l('sales_pipeline_download_template'); ?>
                                </a>
                            </div>
                        </div>

                        <?php echo form_open_multipart(admin_url('sales_pipeline/import'), ['id' => 'import_form', 'class' => 'disable-on-submit']); ?>

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

    /**
     * [BUG FIX #1] selectedFile phải khai báo ở scope ngoài cùng của closure
     * để cả 2 hành vi (click chọn file & kéo-thả) đều ghi nhận được giá trị.
     * Trước đây biến này chỉ được gán trong sự kiện 'drop' → undefined khi click.
     */
    var selectedFile = null;

    // Click to upload
    zone.on('click', function() {
        input.trigger('click');
    });

    // [BUG FIX #1] Gán selectedFile khi người dùng CLICK chọn file
    input.on('change', function() {
        selectedFile = this.files[0] || null;
        var fileName = selectedFile ? selectedFile.name : '';
        $('#file_name').text(fileName);
        if (fileName) {
            zone.css('border-color', '#27ae60');
        } else {
            zone.css('border-color', '#ccc');
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
        if(e.originalEvent.dataTransfer.files.length > 0){
            // [BUG FIX #1] Gán selectedFile khi người dùng KÉO-THẢ file
            selectedFile = e.originalEvent.dataTransfer.files[0];
            input[0].files = e.originalEvent.dataTransfer.files;
            $('#file_name').text(selectedFile.name);
        }
    });

    // Validate form on submit
    $('#import_form').on('submit', function(e) {
        if (!selectedFile) {
            e.preventDefault();
            alert_float('warning', '<?php echo _l('sales_pipeline_please_select_excel_file'); ?>');
            return false;
        }
        var $btn = $(this).find('button[type="submit"]');
        if (typeof SalesPipeline !== 'undefined' && SalesPipeline.btnLoading) {
            SalesPipeline.btnLoading($btn, '<?php echo _l('please_wait'); ?>');
        }
    });

    /**
     * [BUG FIX #6] Helper lấy CSRF token cho AJAX requests tương lai.
     * Khi chuyển form sang AJAX ($.ajax / fetch), gọi getCsrfData() để lấy
     * object { token_name: token_value } rồi merge vào FormData hoặc POST body.
     *
     * Ví dụ sử dụng:
     *   var formData = new FormData($('#import_form')[0]);
     *   var csrf = getCsrfData();
     *   formData.append(csrf.name, csrf.value);
     *   $.ajax({ data: formData, headers: { 'X-CSRF-TOKEN': csrf.value } });
     */
    window.getCsrfData = function() {
        var tokenName  = '<?php echo $this->security->get_csrf_token_name(); ?>';
        var tokenValue = '<?php echo $this->security->get_csrf_hash(); ?>';
        return { name: tokenName, value: tokenValue };
    };
});
</script>
</body>
</html>
