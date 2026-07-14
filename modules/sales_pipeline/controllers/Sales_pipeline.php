<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Sales_pipeline extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('sales_pipeline/sales_pipeline_model');
        $this->load->library('form_validation');
    }

    /**
     * Trang danh sách Pipeline
     * URL: admin/sales_pipeline
     */
    public function index()
    {
        if (!has_permission('sales_pipeline', '', 'view') && !has_permission('sales_pipeline', '', 'view_own')) {
            access_denied('sales_pipeline');
        }

        $data['switch_kanban'] = 1;

        if ($this->session->userdata('sales_pipeline_kanban_view') == 'true') {
            $data['switch_kanban'] = 0;
            $data['bodyclass']     = 'kan-ban-body';
        }

        $data['statuses'] = $this->sales_pipeline_model->get_statuses();
        
        // Chỉ lấy tất cả staff nếu có quyền xem toàn cục, ngược lại chỉ hiện chính mình
        if (has_permission('sales_pipeline', '', 'view')) {
            $data['staff'] = $this->staff_model->get('', ['active' => 1]);
        } else {
            $data['staff'] = [(array) $this->staff_model->get(get_staff_user_id())];
        }

        // Lọc theo quý và nhân viên
        $quarter  = $this->input->get('quarter') ?: null;
        $year     = $this->input->get('year') ?: date('Y');
        $staff_id = $this->input->get('staff_id') ?: null;
        $search   = $this->input->get('search') ?: '';

        // Lọc theo chứng từ (Hợp đồng / Hóa đơn)
        $contract_signed = $this->input->get('contract_signed');
        $invoice_issued  = $this->input->get('invoice_issued');

        // Chỉ cho xem deal của mình nếu không có quyền view global
        if (!has_permission('sales_pipeline', '', 'view')) {
            $staff_id = get_staff_user_id();
        }

        $where = [];
        if ($quarter) {
            $where['QUARTER(deal_date)'] = $quarter;
        }
        if ($year) {
            $where['YEAR(deal_date)'] = $year;
        }
        if ($staff_id) {
            $where[db_prefix() . 'sales_pipeline.staff_id'] = $staff_id;
        }
        // Filter chứng từ
        if ($contract_signed !== null && $contract_signed !== '') {
            $where[db_prefix() . 'sales_pipeline.contract_signed'] = (int) $contract_signed;
        }
        if ($invoice_issued !== null && $invoice_issued !== '') {
            $where[db_prefix() . 'sales_pipeline.invoice_issued'] = (int) $invoice_issued;
        }

        // Pagination setup
        $per_page = $this->input->get('per_page') ?: 25; // Default 25 records per page
        $page = $this->input->get('page') ?: 1;
        
        // Validate per_page values
        $allowed_per_page = [10, 25, 50, 100];
        if (!in_array($per_page, $allowed_per_page)) {
            $per_page = 25;
        }
        
        // Calculate offset
        $offset = ($page - 1) * $per_page;
        
        // Get total count for pagination
        $total_deals = $this->sales_pipeline_model->count_deals($where, $search);
        
        // Get paginated deals
        $data['deals'] = $this->sales_pipeline_model->get('', $where, $per_page, $offset, $search);
        $data['summary'] = $this->sales_pipeline_model->get_summary($quarter, $year, $staff_id, $search);

        // Pagination data
        $data['total_deals'] = $total_deals;
        $data['current_page'] = $page;
        $data['per_page'] = $per_page;
        $data['total_pages'] = ceil($total_deals / $per_page);

        $data['current_quarter']  = $quarter;
        $data['current_year']     = $year;
        $data['current_staff_id'] = $staff_id;
        $data['current_search']   = $search;
        $data['current_contract_signed'] = $contract_signed;
        $data['current_invoice_issued']  = $invoice_issued;

        // Đếm số lượng deal thiếu giá nhập cho Admin/Manager
        $data['total_missing_cost_prices'] = 0;
        if (is_admin() || has_permission('sales_pipeline', '', 'view')) {
            $data['total_missing_cost_prices'] = count($this->sales_pipeline_model->get_deals_missing_cost_price());
        }

        $data['title'] = _l('sales_pipeline');
        $this->load->view('sales_pipeline/manage', $data);
    }

    /**
     * Form thêm/sửa deal
     * URL: admin/sales_pipeline/deal hoặc admin/sales_pipeline/deal/{id}
     */
    public function deal($id = '')
    {
        if ($id == '') {
            if (!has_permission('sales_pipeline', '', 'create')) {
                access_denied('sales_pipeline');
            }
        } else {
            if (!has_permission('sales_pipeline', '', 'edit') && !has_permission('sales_pipeline', '', 'view_deal_details')) {
                access_denied('sales_pipeline');
            }
        }

        // Validation
        $this->form_validation->set_rules('customer_name', _l('sales_pipeline_customer_name'), 'trim|required');
        $this->form_validation->set_rules('deal_name', _l('sales_pipeline_deal_name'), 'trim|required');
        $this->form_validation->set_rules('deal_value', _l('sales_pipeline_deal_value'), 'trim|required|numeric');
        $this->form_validation->set_rules('deal_date', _l('sales_pipeline_expected_date'), 'trim|required');
        $this->form_validation->set_rules('status', _l('sales_pipeline_status'), 'trim|required|numeric');
        $this->form_validation->set_rules('contact_phone', _l('sales_pipeline_contact_phone'), 'trim|numeric|max_length[10]');
        $this->form_validation->set_rules('contact_email', _l('sales_pipeline_contact_email'), 'trim|valid_email');


        if ($this->input->post()) {
            if ($this->form_validation->run() !== false) {
                // Handle cost_price: convert to float or NULL if empty
                $cost_price_input = $this->input->post('cost_price');
                $cost_price = null;
                if ($cost_price_input !== '' && $cost_price_input !== null) {
                    $cost_price = floatval($cost_price_input);
                    if ($cost_price < 0) {
                        $cost_price = null; // Negative values not allowed
                    }
                }

                $post_data = [
                    'customer_name'       => $this->input->post('customer_name'),
                    'contact_name'        => $this->input->post('contact_name'),
                    'contact_phone'       => $this->input->post('contact_phone'),
                    'contact_email'       => $this->input->post('contact_email'),
                    'source_id'           => $this->input->post('source_id'),
                    'deal_name'           => $this->input->post('deal_name'),
                    'deal_value'          => $this->input->post('deal_value'),
                    'cost_price'          => $cost_price,  // NEW: cost price instead of profit_margin
                    'deal_date'           => to_sql_date($this->input->post('deal_date')),
                    'status'              => $this->input->post('status'),
                    'staff_id'            => $this->input->post('staff_id') ?: get_staff_user_id(),
                    'contract_signed'     => $this->input->post('contract_signed'),
                    'invoice_issued'      => $this->input->post('invoice_issued'),
                    'reminder_enabled'    => $this->input->post('reminder_enabled'),
                    'reminder_frequency'  => $this->input->post('reminder_frequency') ?: 2,
                    'activity_description' => $this->input->post('activity_description'),
                ];

                if ($id == '') {
                    $insert_id = $this->sales_pipeline_model->add($post_data);
                    if ($insert_id) {
                        set_alert('success', _l('sales_pipeline_deal_added'));
                    }
                } else {
                    $success = $this->sales_pipeline_model->update($post_data, $id);
                    if ($success) {
                        set_alert('success', _l('sales_pipeline_deal_updated'));
                    }
                }
                $redirect_url = admin_url('sales_pipeline');
                if (!empty($_SERVER['QUERY_STRING'])) {
                    $redirect_url .= '?' . $_SERVER['QUERY_STRING'];
                }
                redirect($redirect_url);
            }
        }

        if ($id != '') {
            $data['deal'] = $this->sales_pipeline_model->get($id);
            if (!$data['deal']) {
                show_404();
            }
        }

        $data['statuses'] = $this->sales_pipeline_model->get_statuses();
        $data['sources']  = $this->sales_pipeline_model->get_sources();
        $data['staff']    = $this->staff_model->get('', ['active' => 1]);

        $data['title'] = ($id == '') ? _l('sales_pipeline_new_deal') : _l('sales_pipeline_edit_deal');
        $this->load->view('sales_pipeline/deal', $data);
    }

    /**
     * Xóa deal
     * URL: admin/sales_pipeline/delete/{id}
     */
    public function delete($id)
    {
        if (!has_permission('sales_pipeline', '', 'delete')) {
            access_denied('sales_pipeline');
        }

        $success = $this->sales_pipeline_model->delete($id);
        if ($success) {
            set_alert('success', _l('sales_pipeline_deal_deleted'));
        }
        redirect(admin_url('sales_pipeline'));
    }

    /**
     * Cập nhật trạng thái nhanh (AJAX)
     * URL: admin/sales_pipeline/update_status/{id}
     */
    public function update_status($id)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (!has_permission('sales_pipeline', '', 'edit')) {
            ajax_access_denied();
        }

        $status = $this->input->post('status');
        if ($status) {
            $this->sales_pipeline_model->update(['status' => $status], $id);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }

    /**
     * Nhân viên phản hồi nhắc nhở (AJAX)
     * URL: admin/sales_pipeline/respond_reminder/{reminder_id}
     */
    public function respond_reminder($reminder_id)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $response = $this->input->post('response');
        if ($response) {
            $this->db->where('id', $reminder_id);
            $this->db->update(db_prefix() . 'sales_pipeline_reminders_log', [
                'staff_response' => $response,
                'responded_at'   => date('Y-m-d H:i:s'),
            ]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }

    /**
     * Cập nhật giá nhập (cost price) qua AJAX
     * URL: admin/sales_pipeline/update_cost_price
     * POST: pipeline_id, cost_price
     */
    public function update_cost_price()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $pipeline_id = $this->input->post('pipeline_id');
        $cost_price  = $this->input->post('cost_price');

        if (!$pipeline_id) {
            echo json_encode([
                'success' => false,
                'message' => 'ID deal không hợp lệ'
            ]);
            return;
        }

        // Kiểm tra quyền: Admin hoặc staff sở hữu deal hoặc người import
        $deal = $this->sales_pipeline_model->get($pipeline_id);
        if (!$deal) {
            echo json_encode([
                'success' => false,
                'message' => 'Deal không tồn tại'
            ]);
            return;
        }

        $current_user_id = get_staff_user_id();
        $is_owner = ($deal['staff_id'] == $current_user_id);
        $is_importer = (isset($deal['imported_by']) && $deal['imported_by'] == $current_user_id);

        if (!is_admin() && !$is_owner && !$is_importer) {
            echo json_encode([
                'success' => false,
                'message' => 'Bạn không có quyền cập nhật giá nhập cho deal này'
            ]);
            return;
        }

        // Validate cost_price: phải là số dương hoặc NULL
        if ($cost_price !== '' && $cost_price !== null) {
            $cost_price = floatval(str_replace([',', ' '], '', $cost_price));
            if ($cost_price < 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Giá nhập phải là số dương'
                ]);
                return;
            }
        } else {
            $cost_price = null;
        }

        // Gọi model để update
        $success = $this->sales_pipeline_model->update_cost_price($pipeline_id, $cost_price);

        if ($success) {
            // Tính lại profit sau khi update
            $updated_deal = $this->sales_pipeline_model->get($pipeline_id);
            
            echo json_encode([
                'success' => true,
                'message' => 'Cập nhật giá nhập thành công',
                'data' => [
                    'cost_price' => $cost_price,
                    'actual_profit' => $updated_deal['actual_profit'],
                    'profit_percentage' => $updated_deal['profit_percentage'],
                    'missing_cost_price' => $updated_deal['missing_cost_price']
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Cập nhật thất bại. Vui lòng thử lại.'
            ]);
        }
    }

    /**
     * Danh sách deal thiếu giá nhập (cho Manager/Admin)
     * URL: admin/sales_pipeline/missing_cost_prices
     */
    public function missing_cost_prices()
    {
        if (!is_admin() && !has_permission('sales_pipeline', '', 'view')) {
            access_denied('sales_pipeline');
        }

        $page = $this->input->get('page') ?: 1;
        if (!is_numeric($page) || $page < 1) {
            $page = 1;
        }
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        $filters = [];
        
        // Lọc theo staff nếu có
        $staff_id = $this->input->get('staff_id');
        if ($staff_id) {
            $filters['staff_id'] = $staff_id;
        }

        // Lọc theo quý/năm
        $quarter = $this->input->get('quarter');
        $year = $this->input->get('year') ?: date('Y');
        if ($quarter) {
            $filters['quarter'] = $quarter;
        }
        if ($year) {
            $filters['year'] = $year;
        }

        // Đếm tổng số lượng deal thiếu giá nhập theo bộ lọc
        $total_deals = $this->sales_pipeline_model->count_deals_missing_cost_price($filters);

        // Truyền limit, offset để lấy dữ liệu trang hiện tại
        $filters['limit'] = $per_page;
        $filters['offset'] = $offset;

        $data['deals'] = $this->sales_pipeline_model->get_deals_missing_cost_price($filters);
        $data['staff'] = $this->staff_model->get('', ['active' => 1]);
        $data['statuses'] = $this->sales_pipeline_model->get_statuses();
        
        $data['current_staff_id'] = $staff_id;
        $data['current_quarter'] = $quarter;
        $data['current_year'] = $year;

        // Dữ liệu phân trang chuyển sang view
        $data['total_deals'] = $total_deals;
        $data['current_page'] = $page;
        $data['per_page'] = $per_page;
        $data['total_pages'] = ceil($total_deals / $per_page);

        $data['title'] = _l('sales_pipeline_missing_cost_prices');
        $this->load->view('sales_pipeline/missing_cost_prices', $data);
    }

    /**
     * Tải file Excel Template mẫu về máy
     * URL: admin/sales_pipeline/download_template
     */
    public function download_template()
    {
        if (!has_permission('sales_pipeline', '', 'create')) {
            access_denied('sales_pipeline');
        }

        $file_path = module_dir_path('sales_pipeline', 'assets/') . 'INNOTEL_BaoCaoKinhDoanh_Template.xlsx';
        $file_name = 'INNOTEL_BaoCaoKinhDoanh_Template.xlsx';

        if (!file_exists($file_path)) {
            set_alert('danger', 'File template không tồn tại. Vui lòng liên hệ bộ phận IT.');
            redirect(admin_url('sales_pipeline/import'));
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: no-cache, must-revalidate');
        readfile($file_path);
        exit;
    }

    /**
     * Kanban view (AJAX)
     * URL: admin/sales_pipeline/kanban
     */
    public function kanban()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (!has_permission('sales_pipeline', '', 'view') && !has_permission('sales_pipeline', '', 'view_own')) {
            ajax_access_denied();
        }

        $data['statuses'] = $this->sales_pipeline_model->get_statuses();
        $data['search'] = $this->input->post('search') ?: '';
        $data['sort_by'] = $this->input->post('sort') ?: 'deal_date';
        $data['sort_type'] = $this->input->post('sort_type') ?: 'desc';
        $data['quarter'] = $this->input->post('quarter') ?: '';
        $data['year'] = $this->input->post('year') ?: '';
        $data['staff_id'] = $this->input->post('staff_id') ?: '';
        $data['contract_signed'] = $this->input->post('contract_signed');
        $data['invoice_issued'] = $this->input->post('invoice_issued');
        
        // Build query string to preserve filter states when clicking kanban cards
        $query_params = [];
        if ($data['quarter']) $query_params['quarter'] = $data['quarter'];
        if ($data['year'])    $query_params['year'] = $data['year'];
        if ($data['staff_id']) $query_params['staff_id'] = $data['staff_id'];
        if ($data['search'])  $query_params['search'] = $data['search'];
        if ($data['contract_signed'] !== null && $data['contract_signed'] !== '') $query_params['contract_signed'] = $data['contract_signed'];
        if ($data['invoice_issued'] !== null && $data['invoice_issued'] !== '') $query_params['invoice_issued'] = $data['invoice_issued'];
        $data['query_string'] = !empty($query_params) ? '?' . http_build_query($query_params) : '';

        $html = $this->load->view('sales_pipeline/kan-ban', $data, true);
        
        header('Content-Type: application/json');
        echo json_encode(['kanban' => $html]);
    }

    /**
     * Load more deals for kanban (AJAX)
     * URL: admin/sales_pipeline/kanban_load_more
     */
    public function kanban_load_more()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (!has_permission('sales_pipeline', '', 'view') && !has_permission('sales_pipeline', '', 'view_own')) {
            ajax_access_denied();
        }

        $status = $this->input->post('status_id');
        $page = $this->input->post('page');
        $search = $this->input->post('search');

        $deals = $this->sales_pipeline_model->do_kanban_query($status, $search, $page, [
            'sort_by' => $this->input->post('sort'),
            'sort' => $this->input->post('sort_type'),
            'quarter' => $this->input->post('quarter'),
            'year' => $this->input->post('year'),
            'staff_id' => $this->input->post('staff_id'),
            'contract_signed' => $this->input->post('contract_signed'),
            'invoice_issued' => $this->input->post('invoice_issued'),
        ]);

        foreach ($deals as $deal) {
            $this->load->view('sales_pipeline/_kanban_card', ['deal' => $deal, 'status' => $status]);
        }
    }

    /**
     * Switch between kanban and list view
     * URL: admin/sales_pipeline/switch_kanban/{0|1}
     */
    public function switch_kanban($set = 0)
    {
        $this->session->set_userdata([
            'sales_pipeline_kanban_view' => $set == 1 ? 'true' : 'false',
        ]);
        
        $query_string = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
        redirect(admin_url('sales_pipeline' . $query_string));
    }

    /**
     * Update deal status (drag-and-drop in Kanban)
     * URL: admin/sales_pipeline/update_deal_status (POST via AJAX)
     */
    public function update_deal_status()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $deal_id = $this->input->post('deal_id');
        $status_id = $this->input->post('status_id');

        if (!$deal_id || !$status_id) {
            echo json_encode(['success' => false, 'message' => 'Thiếu thông tin deal hoặc trạng thái']);
            return;
        }

        // Get current deal to check permissions
        $deal = $this->sales_pipeline_model->get($deal_id);
        
        if (!$deal) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy deal']);
            return;
        }

        // Check permissions
        $can_edit = is_admin() || 
                    has_permission('sales_pipeline', '', 'edit') ||
                    ($deal->staff_id == get_staff_user_id() && has_permission('sales_pipeline', '', 'edit_own'));

        if (!$can_edit) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền cập nhật deal này']);
            return;
        }

        // Update status
        $this->db->where('id', $deal_id);
        $this->db->update(db_prefix() . 'sales_pipeline', [
            'status_id' => $status_id,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if ($this->db->affected_rows() > 0) {
            // Get new status info
            $status = $this->sales_pipeline_model->get_status($status_id);
            
            // Log activity
            $this->sales_pipeline_model->log_activity($deal_id, 
                'Cập nhật trạng thái', 
                'Chuyển sang trạng thái: ' . $status->name
            );

            echo json_encode([
                'success' => true,
                'message' => 'Đã cập nhật trạng thái deal',
                'status_color' => $status->color,
                'status_name' => $status->name
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể cập nhật trạng thái']);
        }
    }

    /**
     * Trang import Excel
     * URL: admin/sales_pipeline/import
     */
    public function import()
    {

        // Import = tạo deal mới → yêu cầu quyền create
        if (!has_permission('sales_pipeline', '', 'create')) {
            access_denied('sales_pipeline');
        }

        if ($this->input->post()) {
            // Xử lý upload file
            if (isset($_FILES['import_file']) && $_FILES['import_file']['size'] > 0) {
                $this->load->library('sales_pipeline/Import_sales_pipeline', [], 'import');

                $staff_id = $this->input->post('staff_id') ?: get_staff_user_id();

                // Chỉ admin mới được import và gán cho nhân viên khác
                if (!is_admin()) {
                    $staff_id = get_staff_user_id();
                }

                // Validate staff_id tồn tại và active
                $target_staff = $this->staff_model->get($staff_id);
                if (!$target_staff || (is_array($target_staff) ? $target_staff['active'] : $target_staff->active) != 1) {
                    set_alert('danger', 'Nhân viên được chọn không hợp lệ hoặc đã ngừng hoạt động.');
                    redirect(admin_url('sales_pipeline/import'));
                }

                $result = $this->import->process($_FILES['import_file'], $staff_id);

                if ($result['success']) {
                    set_alert('success', $result['message']);
                } else {
                    set_alert('danger', $result['message']);
                }
                redirect(admin_url('sales_pipeline'));
            }
        }

        // Admin hoặc có quyền view global → thấy tất cả staff
        // Ngược lại → chỉ thấy chính mình
        if (is_admin()) {
            $data['staff'] = $this->staff_model->get('', ['active' => 1]);
        } else {
            $data['staff'] = [$this->staff_model->get(get_staff_user_id())];
        }
        $data['can_assign_others'] = is_admin();
        $data['title'] = _l('sales_pipeline_import');
        $this->load->view('sales_pipeline/import', $data);
    }
    // =========================================================================
    // CÀI ĐẶT (SETTINGS)
    // =========================================================================

    public function settings()
    {
        if (!is_admin()) {
            access_denied('sales_pipeline_settings');
        }

        if ($this->input->post()) {
            $data = $this->input->post();
            $type = $this->input->post('setting_type'); // 'status' or 'source'
            unset($data['setting_type']);
            
            if ($type == 'status') {
                $id = $data['id'];
                unset($data['id']);
                // Default checkboxes
                if (!isset($data['is_won'])) $data['is_won'] = 0;
                if (!isset($data['is_lost'])) $data['is_lost'] = 0;

                if ($id == '') {
                    $success = $this->sales_pipeline_model->add_status($data);
                    if ($success) {
                        set_alert('success', _l('added_successfully', _l('sales_pipeline_status')));
                    }
                } else {
                    $success = $this->sales_pipeline_model->update_status($data, $id);
                    if ($success) {
                        set_alert('success', _l('updated_successfully', _l('sales_pipeline_status')));
                    }
                }
            } elseif ($type == 'source') {
                $id = $data['id'];
                unset($data['id']);
                
                if ($id == '') {
                    $success = $this->sales_pipeline_model->add_source($data);
                    if ($success) {
                        set_alert('success', _l('added_successfully', _l('sales_pipeline_source')));
                    }
                } else {
                    $success = $this->sales_pipeline_model->update_source($data, $id);
                    if ($success) {
                        set_alert('success', _l('updated_successfully', _l('sales_pipeline_source')));
                    }
                }
            }
            redirect(admin_url('sales_pipeline/settings'));
        }

        $data['statuses'] = $this->sales_pipeline_model->get_statuses();
        $data['sources'] = $this->sales_pipeline_model->get_sources();
        $data['title'] = _l('sales_pipeline_settings');

        $this->load->view('sales_pipeline/settings', $data);
    }

    public function delete_setting($type, $id)
    {
        if (!is_admin()) {
            access_denied('sales_pipeline_settings');
        }

        if (!$id) {
            redirect(admin_url('sales_pipeline/settings'));
        }

        if ($type == 'status') {
            $response = $this->sales_pipeline_model->delete_status($id);
            if (isset($response['referenced'])) {
                set_alert('warning', 'Không thể xóa Trạng thái này vì đang có Deal sử dụng.');
            } elseif ($response['success']) {
                set_alert('success', _l('deleted', _l('sales_pipeline_status')));
            } else {
                set_alert('warning', _l('problem_deleting', _l('sales_pipeline_status')));
            }
        } elseif ($type == 'source') {
            $response = $this->sales_pipeline_model->delete_source($id);
            if (isset($response['referenced'])) {
                set_alert('warning', 'Không thể xóa Nguồn này vì đang có Deal sử dụng.');
            } elseif ($response['success']) {
                set_alert('success', _l('deleted', _l('sales_pipeline_source')));
            } else {
                set_alert('warning', _l('problem_deleting', _l('sales_pipeline_source')));
            }
        }

        redirect(admin_url('sales_pipeline/settings'));
    }
}
