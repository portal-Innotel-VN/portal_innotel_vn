<?php

defined('BASEPATH') or exit('No direct script access allowed');

class My_customers extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        // Load model để lấy dữ liệu từ bảng my_customers
        $this->load->model('my_customers_model');
        // Load thư viện form_validation để xử lý xác thực dữ liệu
        $this->load->library('form_validation');
    }

    // Hàm mặc định: hiển thị danh sách khách hàng
    public function index()
    {
        if (!has_permission('my_customers', '', 'view')) {
            access_denied('my_customers');
        }

        // Lấy toàn bộ danh sách khách hàng từ Model
        $data['customers'] = $this->my_customers_model->get();

        // Tiêu đề trang
        $data['title'] = 'Khách Hàng Của Tôi';

        // Load giao diện (View)
        $this->load->view('admin/my_customers/manage', $data);
    }

    // API Endpoint trả dữ liệu Server-side cho DataTables
    public function table()
    {
        if (!has_permission('my_customers', '', 'view')) {
            ajax_access_denied();
        }

        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        // GIẢI PHÁP 1: Đóng quyền ghi Session sớm cho AJAX để khắc phục lỗi "Dính Flashdata"
        session_write_close();

        // Gọi Table Helper xử lý file views/admin/tables/my_customers.php
        $this->app->get_table_data('my_customers');
    }

    public function customer($id = '')
    {
        if ($id == '') {
            if (!has_permission('my_customers', '', 'create')) {
                access_denied('my_customers');
            }
        } else {
            if (!has_permission('my_customers', '', 'edit')) {
                access_denied('my_customers');
            }
        }

        $data = [];

        // 1. Rule cho Tên công ty
        $this->form_validation->set_rules(
            'company',
            'Tên công ty',
            'trim|required',
        );

        // 1.1 Rule cho Số điện thoại
        $this->form_validation->set_rules(
            'phonenumber',
            'Số điện thoại',
            'trim|required|numeric|min_length[9]|max_length[11]',
            array(
                'required'   => 'Vui lòng nhập %s.',
                'numeric'    => '%s chỉ được phép chứa các chữ số.',
                'min_length' => '%s phải có ít nhất 9 chữ số.',
                'max_length' => '%s không được vượt quá 11 chữ số.'
            )
        );

        // Load model của staff để lấy danh sách nhân viên
        $this->load->model('staff_model');

        // 2. Chỉ chạy validation khi người dùng thực sự bấm nút Submit gửi dữ liệu (POST)
        if ($this->input->post()) {

            // Nếu dữ liệu hợp lệ (run trả về TRUE)
            if ($this->form_validation->run() !== false) {
                $data = [
                    'company'        => $this->input->post('company'),
                    'phonenumber'    => $this->input->post('phonenumber'),
                    'address'        => $this->input->post('address'),
                    'active'         => $this->input->post('active'),
                    'assigned_staff' => $this->input->post('assigned_staff') ? $this->input->post('assigned_staff') : null,
                ];

                if ($id == '') {
                    // Tạo mới
                    $insert_id = $this->my_customers_model->add($data);
                    if ($insert_id) {
                        set_alert('success', 'Thêm khách hàng thành công!');
                    }
                } else {
                    // Cập nhật
                    $success = $this->my_customers_model->update($data, $id);
                    if ($success) {
                        set_alert('success', 'Cập nhật khách hàng thành công!');
                    }
                }
                redirect(admin_url('my_customers'));
            }
            // Nếu validation thất bại, chương trình tự động đi tiếp xuống dưới để load lại Form cùng thông báo lỗi.
        }

        // 3. Chuẩn bị dữ liệu hiển thị cho Form (dùng chung cho cả GET và khi POST lỗi)
        if ($id != '') {
            $data['customer'] = $this->my_customers_model->get($id);
        }

        // Lấy danh sách nhân viên đang hoạt động để hiển thị ở dropdown select
        $data['staffs'] = $this->staff_model->get('', ['active' => 1]);
        $data['title'] = ($id == '') ? 'Thêm Khách Hàng' : 'Sửa Khách Hàng';

        // Luôn load đúng view Form nhập liệu
        $this->load->view('admin/my_customers/customers', $data);
    }


    // Xóa khách hàng
    public function delete($id)
    {
        if (!has_permission('my_customers', '', 'delete')) {
            access_denied('my_customers');
        }

        $success = $this->my_customers_model->delete($id);
        if ($success) {
            set_alert('success', 'Xóa Khách Hàng Thành Công!');
        }
        redirect(admin_url('my_customers'));
    }

    // Hàm sinh dữ liệu giả lập (Fake data seeder)
    // URL chạy: http://localhost:8000/admin/my_customers/seed_data/1000
    public function seed_data($count = 500)
    {

        // Admin mới có quyền sử dụng
        if (!is_admin()) {
            access_denied('mycustomers');
        }
        // Giới hạn tối đa để tránh tràn bộ nhớ
        $count = min(intval($count), 2000);
        if ($count <= 0) {
            $count = 500;
        }

        $companies_prefix = ['Công ty TNHH', 'Tập đoàn', 'Tổng công ty', 'Công ty Cổ phần', 'Doanh nghiệp', 'Cửa hàng'];
        $companies_name = ['An Phát', 'Bình Minh', 'Cường Thịnh', 'Đại Nam', 'Trường Giang', 'Hải Tiến', 'Hòa Bình', 'Hưng Thịnh', 'Sao Mai', 'Việt Á', 'Đông Á', 'Nam Á', 'Tân Tiến', 'Phương Nam', 'Đại Việt'];
        $companies_suffix = ['Solutions', 'Logistics', 'Media', 'Group', 'Invest', 'Software', 'Trading', 'Services', 'Technology', 'Global'];

        $streets = ['Lê Lợi', 'Nguyễn Huệ', 'Trần Hưng Đạo', 'Cách Mạng Tháng Tám', 'Điện Biên Phủ', 'Hai Bà Trưng', 'Lý Tự Trọng', 'Nam Kỳ Khởi Nghĩa', 'Võ Văn Kiệt', 'Trường Chinh', 'Hoàng Hoa Thám', 'Lê Duẩn', 'Phạm Hồng Thái'];
        $districts = ['Q.1, TP.HCM', 'Q.3, TP.HCM', 'Q. Bình Thạnh, TP.HCM', 'Q. Tân Bình, TP.HCM', 'Q.7, TP.HCM', 'Cầu Giấy, Hà Nội', 'Ba Đình, Hà Nội', 'Hoàn Kiếm, Hà Nội', 'Hải Châu, Đà Nẵng', 'Ninh Kiều, Cần Thơ'];

        $phone_prefixes = ['090', '091', '098', '096', '093', '097', '086', '032', '070', '079'];

        $batch_data = [];
        $now = date('Y-m-d H:i:s');

        for ($i = 0; $i < $count; $i++) {
            $prefix = $companies_prefix[array_rand($companies_prefix)];
            $name = $companies_name[array_rand($companies_name)];
            $suffix = $companies_suffix[array_rand($companies_suffix)];

            $company = $prefix . ' ' . $name . (rand(0, 1) ? ' ' . $suffix : '');
            $phone = $phone_prefixes[array_rand($phone_prefixes)] . rand(1000000, 9999999);
            $address = rand(1, 450) . ' ' . $streets[array_rand($streets)] . ', ' . $districts[array_rand($districts)];
            $active = (rand(1, 10) <= 8) ? 1 : 0;

            $batch_data[] = [
                'company'     => $company,
                'phonenumber' => $phone,
                'address'     => $address,
                'active'      => $active,
                'datecreated' => $now
            ];
        }

        if (!empty($batch_data)) {
            $this->db->insert_batch(db_prefix() . 'my_customers', $batch_data);
            set_alert('success', 'Đã nạp thành công ' . $count . ' khách hàng giả lập!');
        }

        redirect(admin_url('my_customers'));
    }

    // Endpoint tạm thời để xem danh sách tài khoản test phân quyền
    public function get_staff_test_info()
    {
        if (!is_admin()) {
            access_denied('my_customers');
        }
        $staff = $this->db->select('staffid, email, firstname, lastname, admin, active')->get(db_prefix() . 'staff')->result_array();
        echo "<h3>Danh sach tai khoan nhan vien de test phan quyen:</h3>";
        echo "<pre>";
        print_r($staff);
        echo "</pre>";
        exit;
    }
}
