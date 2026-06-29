<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">

                        <!-- Tiêu đề trang & Nút Thêm mới -->
                        <div class="clearfix mbot15">
                            <h4 class="no-margin font-bold pull-left" style="line-height: 34px;">
                                <i class="fa fa-address-book"></i> Khách Hàng Của Tôi
                            </h4>
                            <?php if (has_permission('my_customers', '', 'create')) { ?>
                            <a href="<?php echo admin_url('my_customers/customer'); ?>" class="btn btn-info pull-right">
                                <i class="fa fa-plus"></i> Thêm Khách Hàng
                            </a>
                            <?php } ?>
                        </div>
                        <hr class="hr-panel-heading" />

                        <!-- Vùng Bộ Lọc Dữ Liệu (Filters) -->
                        <div class="row mbot15">
                            <div class="col-md-3">
                                <label for="filter_region">Vùng Miền (Tỉnh/Thành)</label>
                                <select name="filter_region" id="filter_region" class="selectpicker" data-width="100%" data-none-selected-text="Tất cả vùng miền">
                                    <option value=""></option>
                                    <option value="Hà Nội">Hà Nội</option>
                                    <option value="TP.HCM">TP. Hồ Chí Minh</option>
                                    <option value="Đà Nẵng">Đà Nẵng</option>
                                    <option value="Cần Thơ">Cần Thơ</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filter_status">Trạng Thái</label>
                                <select name="filter_status" id="filter_status" class="selectpicker" data-width="100%" data-none-selected-text="Tất cả trạng thái">
                                    <option value=""></option>
                                    <option value="1">Hoạt động</option>
                                    <option value="0">Ngừng hoạt động</option>
                                </select>
                            </div>
                        </div>
                        <hr class="hr-panel-heading" />

                        <!-- Bảng danh sách khách hàng (Server-side) -->
                        <table class="table table-my-customers table-striped">
                            <thead>
                                <tr>
                                    <th># ID</th>
                                    <th>Tên Công Ty</th>
                                    <th>Số Điện Thoại</th>
                                    <th>Địa Chỉ</th>
                                    <th>Trạng Thái</th>
                                    <th>Nhân Viên Phụ Trách</th>
                                    <th>Ngày Tạo</th>
                                    <th class="text-center">Thao Tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dữ liệu sẽ được nạp động qua Ajax -->
                            </tbody>
                        </table>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
    $(function() {
        // Cấu hình các tham số lọc bổ sung gửi lên server
        var MyCustomersServerParams = {
            'region': '[name="filter_region"]',
            'active': '[name="filter_status"]'
        };

        // Khởi tạo Datatable Server-side
        // Hàm initDataTable nhận các đối số: (selector, url, colsNotSearchable, colsNotSortable, fnServerParams, defaultOrder)
        var table_my_customers = initDataTable(
            '.table-my-customers', 
            admin_url + 'my_customers/table', 
            [7], // Cột "Thao tác" không cho phép tìm kiếm
            [7], // Cột "Thao tác" không cho phép sắp xếp
            MyCustomersServerParams, 
            [0, 'desc'] // Mặc định sắp xếp theo ID giảm dần
        );

        // Lắng nghe sự kiện thay đổi của các bộ lọc để tải lại dữ liệu bảng
        $('select[name="filter_region"], select[name="filter_status"]').on('change', function() {
            $('.table-my-customers').DataTable().ajax.reload();
        });
    });
</script>
