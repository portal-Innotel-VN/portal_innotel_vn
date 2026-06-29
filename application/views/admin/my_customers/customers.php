<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <!-- Tiêu đề -->
                        <h4 class="no-margin font-bold">
                            <i class="fa fa-address-book"></i> <?php echo $title; ?>
                        </h4>
                        <hr class="hr-panel-heading" />

                        <!-- Thêm đoạn này để hiển thị danh sách các lỗi nếu validation thất bại -->
                        <?php if (validation_errors()) { ?>
                            <div class="alert alert-danger">
                                <?php echo validation_errors(); ?>
                            </div>
                        <?php } ?>
                        
                        <!-- Form nhập liệu -->
                        <?php echo form_open($this->uri->uri_string()); ?>
                        
                        <div class="form-group">
                            <label for="company" class="control-label"><span class="text-danger">*</span> Tên Công Ty</label>
                            <input type="text" class="form-control" name="company" id="company" value="<?php echo isset($customer) ? html_escape($customer['company']) : ''; ?>" required autocomplete="off">
                        </div>

                        <div class="form-group">
                            <label for="phonenumber" class="control-label">Số Điện Thoại</label>
                            <input type="text" class="form-control" name="phonenumber" id="phonenumber" value="<?php echo isset($customer) ? html_escape($customer['phonenumber']) : ''; ?>" autocomplete="off">
                        </div>

                        <div class="form-group">
                            <label for="address" class="control-label">Địa Chỉ</label>
                            <input type="text" class="form-control" name="address" id="address" value="<?php echo isset($customer) ? html_escape($customer['address']) : ''; ?>" autocomplete="off">
                        </div>

                        <div class="form-group">
                            <label for="active" class="control-label">Trạng Thái</label>
                            <select name="active" id="active" class="form-control">
                                <option value="1" <?php echo (isset($customer) && $customer['active'] == 0) ? '' : 'selected'; ?>>Hoạt động</option>
                                <option value="0" <?php echo (isset($customer) && $customer['active'] == 0) ? 'selected' : ''; ?>>Ngừng hoạt động</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <?php echo render_select('assigned_staff', $staffs, array('staffid', array('firstname', 'lastname')), 'Nhân Viên Phụ Trách', isset($customer) ? $customer['assigned_staff'] : ''); ?>
                        </div>

                        <hr />
                        <div class="text-right">
                            <a href="<?php echo admin_url('my_customers'); ?>" class="btn btn-default mright5">Quay Lại</a>
                            <button type="submit" class="btn btn-info">Lưu Lại</button>
                        </div>

                        <?php echo form_close(); ?>

                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
</body>
</html>
