<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Sales_pipeline extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('sales_pipeline/sales_pipeline_model');
        $this->load->library('form_validation');
        $this->load->helper('sales_pipeline/sales_pipeline');
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
        $quarter  = $this->input->get('quarter') ? trim($this->input->get('quarter')) : null;
        $year     = $this->input->get('year') !== null ? trim($this->input->get('year')) : null;
        $staff_id = $this->input->get('staff_id') ? trim($this->input->get('staff_id')) : null;
        $search   = $this->input->get('search') ? trim($this->input->get('search')) : '';

        // Lọc theo chứng từ (Hợp đồng / Hóa đơn)
        $contract_signed = $this->input->get('contract_signed') !== null ? trim($this->input->get('contract_signed')) : null;
        $invoice_issued  = $this->input->get('invoice_issued') !== null ? trim($this->input->get('invoice_issued')) : null;

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
        $per_page = (int) ($this->input->get('per_page') ?: 25); // Default 25 records per page
        $page = (int) ($this->input->get('page') ?: 1);
        
        // Validate per_page values
        $allowed_per_page = [10, 25, 50, 100];
        if (!in_array($per_page, $allowed_per_page)) {
            $per_page = 25;
        }
        if ($page < 1) {
            $page = 1;
        }
        
        // Get total count for pagination
        $total_deals = $this->sales_pipeline_model->count_deals($where, $search);
        $total_pages = max(1, (int) ceil($total_deals / $per_page));
        if ($page > $total_pages) {
            $page = $total_pages;
        }

        // Calculate offset
        $offset = ($page - 1) * $per_page;
        
        // Get paginated deals
        $data['deals'] = $this->sales_pipeline_model->get('', $where, $per_page, $offset, $search);
        $data['summary'] = $this->sales_pipeline_model->get_summary($quarter, $year, $staff_id, $search, $contract_signed, $invoice_issued);

        // Pagination data
        $data['total_deals'] = $total_deals;
        $data['current_page'] = $page;
        $data['per_page'] = $per_page;
        $data['total_pages'] = $total_deals > 0 ? $total_pages : 0;

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

        $data['can_access_dashboard'] = $this->can_access_dashboard();

        $data['title'] = _l('sales_pipeline');
        $this->load->view('sales_pipeline/manage', $data);
    }

    /**
     * Trang Dashboard Phân Tích Sales Pipeline
     * URL: admin/sales_pipeline/dashboard
     */
    public function dashboard()
    {
        if (!$this->can_access_dashboard()) {
            access_denied('sales_pipeline');
        }

        $can_view_all = $this->can_view_dashboard_all();
        $staff_id = $can_view_all ? null : get_staff_user_id();
        $period = $this->input->get('period') ?: 'this_month';
        $period_anchor = $this->input->get('period_anchor');
        $active_tab = $this->resolve_dashboard_tab($this->input->get('dashboard_tab'));

        $data['dashboard'] = $this->sales_pipeline_model->get_executive_dashboard($staff_id, $period, $period_anchor);
        $resolved_period = array_merge(
            ['key' => $data['dashboard']['selected_period']],
            $data['dashboard']['selected_period_range']
        );
        $this->attach_estimate_performance_ranking($data['dashboard'], $resolved_period, $can_view_all);
        $data['dashboard']['selected_dashboard_tab'] = $active_tab;
        $data['can_view_all'] = $can_view_all;
        $data['title'] = _l('sales_pipeline_dashboard_title');
        $this->load->view('sales_pipeline/dashboard', $data);
    }

    /**
     * AJAX nội dung Dashboard theo kỳ thời gian.
     * URL: admin/sales_pipeline/ajax_dashboard_leaderboard
     */
    public function ajax_dashboard_leaderboard()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (!$this->can_access_dashboard()) {
            return $this->json_response(false, _l('access_denied'), [], 403);
        }

        $can_view_all = $this->can_view_dashboard_all();
        $staff_id = $can_view_all ? null : get_staff_user_id();
        $period = $this->input->get('period') ?: 'this_month';
        $period_anchor = $this->input->get('period_anchor');
        $active_tab = $this->resolve_dashboard_tab($this->input->get('dashboard_tab'));
        $dashboard = $this->sales_pipeline_model->get_executive_dashboard($staff_id, $period, $period_anchor);
        $resolved_period = array_merge(
            ['key' => $dashboard['selected_period']],
            $dashboard['selected_period_range']
        );
        $this->attach_estimate_performance_ranking($dashboard, $resolved_period, $can_view_all);
        $dashboard['selected_dashboard_tab'] = $active_tab;

        $html = $this->load->view('sales_pipeline/partials/_dashboard_content', [
            'dashboard' => $dashboard,
        ], true);

        return $this->json_response(true, '', ['html' => $html]);
    }

    /**
     * Duplicate an estimate while retaining its Estimate-only revision group.
     * This endpoint intentionally accepts POST only.
     *
     * URL: admin/sales_pipeline/duplicate_estimate/{source_id}
     */
    public function duplicate_estimate($source_id = null)
    {
        $is_ajax = $this->input->is_ajax_request();
        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
        }

        if (!$source_id || !is_numeric($source_id)) {
            if ($is_ajax) {
                return $this->json_response(false, _l('sales_pipeline_quote_invalid_estimate'), [], 400);
            }
            show_404();
        }

        $source_id = (int) $source_id;
        if (!has_permission('estimates', '', 'create') || !user_can_view_estimate($source_id)) {
            if ($is_ajax) {
                return $this->json_response(false, _l('access_denied'), [], 403);
            }
            access_denied('estimates');
        }

        $this->load->model('estimates_model');
        $source = $this->estimates_model->get($source_id);
        if (!$source) {
            if ($is_ajax) {
                return $this->json_response(false, _l('sales_pipeline_quote_invalid_estimate'), [], 404);
            }
            show_404();
        }

        $new_id = $this->sales_pipeline_model->duplicate_estimate_revision($source_id);
        if (!$new_id) {
            if ($is_ajax) {
                return $this->json_response(false, _l('sales_pipeline_quote_duplicate_failed'), [], 500);
            }
            set_alert('danger', _l('sales_pipeline_quote_duplicate_failed'));
            redirect(admin_url('estimates/list_estimates/' . $source_id));
        }

        if ($is_ajax) {
            return $this->json_response(true, _l('sales_pipeline_quote_duplicate_success'), [
                'estimate_id' => (int) $new_id,
                'url'         => admin_url('estimates/estimate/' . (int) $new_id),
            ]);
        }

        set_alert('success', _l('sales_pipeline_quote_duplicate_success'));
        redirect(admin_url('estimates/estimate/' . (int) $new_id));
    }

    /**
     * Get candidate estimate revision sources for a customer (for UI revision dropdown).
     * URL: admin/sales_pipeline/estimate_revision_sources?client_id={client_id}
     */
    public function estimate_revision_sources()
    {
        if (!is_staff_logged_in()) {
            return $this->json_response(false, _l('access_denied'), [], 401);
        }

        $staff_id = (int) get_staff_user_id();
        $can_view = is_admin($staff_id)
            || (function_exists('staff_can') && (staff_can('view', 'estimates', $staff_id) || staff_can('view_own', 'estimates', $staff_id)));

        if (!$can_view) {
            return $this->json_response(false, _l('access_denied'), [], 403);
        }

        $client_id = (int) $this->input->get('client_id');
        if ($client_id <= 0) {
            return $this->json_response(true, '', ['sources' => []]);
        }

        $sources = $this->sales_pipeline_model->get_customer_estimate_revision_sources($client_id, $staff_id);

        return $this->json_response(true, '', [
            'sources'    => $sources,
            'is_manager' => is_admin($staff_id) || (function_exists('has_permission') && has_permission('sales_pipeline', (string) $staff_id, 'manage_estimate_revisions')),
        ]);
    }

    /**
     * Smart Prompt: Endpoint returning ranked candidate estimates for suggestion banner.
     * GET /admin/sales_pipeline/estimate_revision_candidates
     */
    public function estimate_revision_candidates()
    {
        if (!is_staff_logged_in()) {
            return $this->json_response(false, _l('access_denied'), [], 401);
        }

        $staff_id = (int) get_staff_user_id();
        $can_view = is_admin($staff_id)
            || (function_exists('staff_can') && (staff_can('view', 'estimates', $staff_id) || staff_can('view_own', 'estimates', $staff_id)));

        if (!$can_view) {
            return $this->json_response(false, _l('access_denied'), [], 403);
        }

        $client_id = (int) $this->input->get('client_id');
        if ($client_id <= 0) {
            return $this->json_response(true, '', ['candidates' => []]);
        }

        $project_id = $this->input->get('project_id') ? (int) $this->input->get('project_id') : null;
        $limit = $this->input->get('limit') ? (int) $this->input->get('limit') : 10;

        $candidates = $this->sales_pipeline_model->get_estimate_revision_candidates($client_id, $project_id, $staff_id, $limit);

        return $this->json_response(true, '', [
            'candidates' => $candidates,
            'is_manager' => is_admin($staff_id) || (function_exists('has_permission') && has_permission('sales_pipeline', (string) $staff_id, 'manage_estimate_revisions')),
        ]);
    }

    /**
     * Manual Link: Gộp báo giá standalone vào nhóm báo giá có sẵn.
     * POST /admin/sales_pipeline/link_estimate_revision
     */
    public function link_estimate_revision()
    {
        if (!is_staff_logged_in()) {
            return $this->json_response(false, _l('access_denied'), [], 401);
        }

        $staff_id = (int) get_staff_user_id();
        $source_id = (int) $this->input->post('source_estimate_id');
        $target_id = (int) $this->input->post('target_estimate_id');
        $reason = $this->input->post('reason');

        $this->load->library('sales_pipeline/estimate_revision_service');
        $result = $this->estimate_revision_service->link_standalone_revision($source_id, $target_id, $staff_id, $reason);

        $message = _l($result['message_key'] ?? ($result['success'] ? 'sales_pipeline_revision_linked' : 'sales_pipeline_error_occurred'));
        return $this->json_response($result['success'], $message, $result);
    }

    /**
     * Manual Unlink: Tách phiên bản mới nhất ra thành standalone group.
     * POST /admin/sales_pipeline/unlink_estimate_revision
     */
    public function unlink_estimate_revision()
    {
        if (!is_staff_logged_in()) {
            return $this->json_response(false, _l('access_denied'), [], 401);
        }

        $staff_id = (int) get_staff_user_id();
        $estimate_id = (int) $this->input->post('estimate_id');
        $reason = $this->input->post('reason');

        $this->load->library('sales_pipeline/estimate_revision_service');
        $result = $this->estimate_revision_service->unlink_estimate_revision($estimate_id, $staff_id, $reason);

        $message = _l($result['message_key'] ?? ($result['success'] ? 'sales_pipeline_revision_unlinked' : 'sales_pipeline_error_occurred'));
        return $this->json_response($result['success'], $message, $result);
    }

    /**
     * Version History & Audit Timeline API.
     * GET /admin/sales_pipeline/estimate_version_history
     */
    public function estimate_version_history()
    {
        if (!is_staff_logged_in()) {
            return $this->json_response(false, _l('access_denied'), [], 401);
        }

        $staff_id = (int) get_staff_user_id();
        $estimate_id = (int) $this->input->get('estimate_id');
        if ($estimate_id <= 0) {
            return $this->json_response(false, _l('sales_pipeline_quote_invalid_estimate'), [], 400);
        }

        if (function_exists('user_can_view_estimate') && !user_can_view_estimate($estimate_id, $staff_id)) {
            return $this->json_response(false, _l('access_denied'), [], 403);
        }

        $this->load->library('sales_pipeline/estimate_revision_service');
        $history = $this->estimate_revision_service->get_estimate_version_history($estimate_id);

        return $this->json_response(true, '', $history);
    }

    /**
     * Set Deal Manual Lock (Khóa/Mở khóa cập nhật tự động từ Estimate Group).
     * POST /admin/sales_pipeline/set_deal_manual_lock
     */
    public function set_deal_manual_lock()
    {
        if (!is_staff_logged_in()) {
            return $this->json_response(false, _l('access_denied'), [], 401);
        }

        $staff_id = (int) get_staff_user_id();
        $deal_id = (int) $this->input->post('deal_id');
        $is_locked = (int) $this->input->post('is_locked') ? 1 : 0;
        $reason = trim((string) $this->input->post('reason'));

        if ($deal_id <= 0) {
            return $this->json_response(false, _l('sales_pipeline_deal_not_found'), [], 400);
        }

        if ($is_locked === 1 && $reason === '') {
            return $this->json_response(false, _l('sales_pipeline_override_reason_required'), [], 400);
        }

        $deal = $this->sales_pipeline_model->get($deal_id);
        if (!$deal) {
            return $this->json_response(false, _l('sales_pipeline_deal_not_found'), [], 404);
        }

        // Check permission: Deal owner or Admin or Edit capability
        $can_edit = is_admin($staff_id) || (int) $deal['staff_id'] === $staff_id || has_permission('sales_pipeline', '', 'edit');
        if (!$can_edit) {
            return $this->json_response(false, _l('access_denied'), [], 403);
        }

        if (!$this->sales_pipeline_model->set_deal_manual_lock($deal_id, $is_locked === 1, $staff_id, $reason)) {
            return $this->json_response(false, _l('problem_updating'), [], 500);
        }

        $this->load->library('sales_pipeline/estimate_revision_service');

        // On unlock, immediately resync deal values to current estimate group state
        if ($is_locked === 0) {
            $this->estimate_revision_service->sync_deal($deal_id);
        }

        return $this->json_response(true, _l('sales_pipeline_updated_successfully'), [
            'deal_id'        => $deal_id,
            'is_manual_lock' => $is_locked,
        ]);
    }


    /**
     * Drawer chi tiết nhân viên Dashboard theo tab đang chọn.
     * URL: admin/sales_pipeline/dashboard_staff_pipeline/{staff_id}
     */
    public function dashboard_staff_pipeline($staff_id = null)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (!$this->can_access_dashboard()) {
            return $this->json_response(false, _l('access_denied'), [], 403);
        }

        if (!$staff_id || !is_numeric($staff_id)) {
            return $this->json_response(false, _l('sales_pipeline_invalid_staff'), [], 400);
        }

        $staff_id = (int) $staff_id;
        if (!$this->can_view_dashboard_all() && $staff_id !== (int) get_staff_user_id()) {
            return $this->json_response(false, _l('access_denied'), [], 403);
        }

        $staff = $this->staff_model->get($staff_id);
        if (!$staff || (int) $staff->active !== 1) {
            return $this->json_response(false, _l('sales_pipeline_invalid_staff'), [], 404);
        }

        $dashboard_tab = $this->resolve_dashboard_tab($this->input->get('dashboard_tab'));
        $period = $this->input->get('period') ?: 'this_month';
        $period_anchor = $this->input->get('period_anchor');
        $period_range = $this->sales_pipeline_model->resolve_dashboard_period($period, $period_anchor);
        $metrics = $this->sales_pipeline_model->get_staff_kpi_metrics($staff_id, $period_range);
        $open_deals = $dashboard_tab === 'deals'
            ? $this->sales_pipeline_model->get_staff_open_deals($staff_id, 10)
            : ['deals' => [], 'total' => 0];
        $estimate_follow_ups = $dashboard_tab === 'estimates'
            ? $this->sales_pipeline_model->get_staff_open_estimates($staff_id, 20)
            : [];
        $performance_metric = null;
        if ($dashboard_tab === 'estimates') {
            $performance_ranking = $this->sales_pipeline_model->get_estimate_performance_ranking($period_range);
            foreach ($performance_ranking['leaderboard'] as $ranking_row) {
                if ((int) $ranking_row['staff_id'] === $staff_id) {
                    $performance_metric = $ranking_row;
                    break;
                }
            }
        }

        $actionable_feed = $this->sales_pipeline_model->get_reminder_response_stats([
            'staff_id'      => $staff_id,
            'dashboard_tab' => $dashboard_tab,
            'date_from'     => $period_range['start'],
            'date_to'       => $period_range['end'],
            'limit'         => 20,
        ]);
        $can_open_pipeline_deal = is_admin()
            || has_permission('sales_pipeline', '', 'edit')
            || has_permission('sales_pipeline', '', 'view_deal_details');
        $can_open_estimate = is_admin()
            || staff_can('view', 'estimates')
            || ($staff_id === (int) get_staff_user_id() && staff_can('view_own', 'estimates'));

        $view_data = [
            'staff'                  => $staff,
            'dashboard_tab'          => $dashboard_tab,
            'period'                 => $period,
            'metric'                 => !empty($metrics) ? $metrics[0] : null,
            'open_deals'             => $open_deals,
            'estimate_follow_ups'    => $estimate_follow_ups,
            'performance_metric'     => $performance_metric,
            'actionable_feed'        => $actionable_feed,
            'can_open_pipeline_deal' => $can_open_pipeline_deal,
            'can_open_estimate'      => $can_open_estimate,
            'can_open_staff_profile' => $this->can_view_dashboard_all(),
        ];

        $html = $this->load->view('sales_pipeline/_dashboard_staff_pipeline', $view_data, true);
        return $this->json_response(true, '', ['html' => $html]);
    }

    /**
     * Form thêm/sửa deal
     * URL: admin/sales_pipeline/deal hoặc admin/sales_pipeline/deal/{id}
     */
    public function deal($id = null)

    {
        // Kiểm tra tính hợp lệ của ID nếu được truyền vào
        if ($id !== null && !is_numeric($id)) {
            show_404();
        }

        $is_id = ($id !== null);

        if (!$is_id) {
            if (!has_permission('sales_pipeline', '', 'create')) {
                access_denied('sales_pipeline');
            }
        } else {
            if (!has_permission('sales_pipeline', '', 'edit') && !has_permission('sales_pipeline', '', 'view_deal_details')) {
                access_denied('sales_pipeline');
            }
        }

        // Validation
        $this->form_validation->set_rules('customer_name', _l('sales_pipeline_customer_name'), 'trim|required|max_length[255]');
        $this->form_validation->set_rules('contact_name', _l('sales_pipeline_contact_name'), 'trim|max_length[100]');
        $this->form_validation->set_rules('contact_phone', _l('sales_pipeline_contact_phone'), 'trim|numeric|max_length[10]');
        $this->form_validation->set_rules(
            'contact_email',
            _l('sales_pipeline_contact_email'),
            'trim|valid_email|regex_match[/^[^@\s]+@[^@\s]+\.[^@\s]+$/]|max_length[100]',
            [
                'valid_email' => _l('sales_pipeline_validation_contact_email_email'),
                'regex_match' => _l('sales_pipeline_validation_contact_email_email'),
            ]
        );
        $this->form_validation->set_rules('deal_name', _l('sales_pipeline_deal_name'), 'trim|required|max_length[500]');
        $this->form_validation->set_rules('deal_value', _l('sales_pipeline_deal_value'), 'trim|required|numeric');
        $this->form_validation->set_rules('deal_date', _l('sales_pipeline_expected_date'), 'trim|required');
        $this->form_validation->set_rules('status', _l('sales_pipeline_status'), 'trim|required|numeric');
        $this->form_validation->set_rules('activity_description', _l('sales_pipeline_activity_description'), 'trim|max_length[2000]');


        if ($this->input->post()) {
            if ($this->form_validation->run() !== false) {
                // ---------- Bổ sung lọc XSS ----------
                $post = $this->input->post();
                $post = $this->security->xss_clean($post);
                // ------------------------------------

                // Handle cost_price: convert to float or NULL if empty
                $cost_price_input = $post['cost_price'] ?? null;
                $cost_price = null;
                if ($cost_price_input !== '' && $cost_price_input !== null) {
                    $cost_price = floatval($cost_price_input);
                    if ($cost_price < 0) {
                        $cost_price = null; // Negative values not allowed
                    }
                }

                $post_data = [
                    'customer_name'       => $post['customer_name'] ?? '',
                    'contact_name'        => $post['contact_name'] ?? '',
                    'contact_phone'       => $post['contact_phone'] ?? '',
                    'contact_email'       => $post['contact_email'] ?? '',
                    'source_id'           => $post['source_id'] ?? null,
                    'deal_name'           => $post['deal_name'] ?? '',
                    'deal_value'          => $post['deal_value'] ?? 0,
                    'cost_price'          => $cost_price,  // NEW: cost price instead of profit_margin
                    'deal_date'           => to_sql_date($post['deal_date'] ?? null),
                    'status'              => $post['status'] ?? 0,
                    'staff_id'            => $post['staff_id'] ?? get_staff_user_id(),
                    'contract_signed'     => !empty($post['contract_signed']) ? 1 : 0,
                    'invoice_issued'      => !empty($post['invoice_issued']) ? 1 : 0,
                    'reminder_enabled'    => !empty($post['reminder_enabled']) ? 1 : 0,
                    'reminder_frequency'  => $post['reminder_frequency'] ?? 2,
                    'activity_description' => $post['activity_description'] ?? '',
                ];

                if (!$is_id) {
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
            } else {
                // Populate $data['deal'] from $_POST data on validation failure to preserve filled form values
                $post = $this->input->post();
                $data['deal'] = [
                    'id'                   => $id,
                    'customer_name'        => $post['customer_name'] ?? '',
                    'contact_name'         => $post['contact_name'] ?? '',
                    'contact_phone'        => $post['contact_phone'] ?? '',
                    'contact_email'        => $post['contact_email'] ?? '',
                    'source_id'            => $post['source_id'] ?? null,
                    'deal_name'            => $post['deal_name'] ?? '',
                    'deal_value'           => $post['deal_value'] ?? '',
                    'cost_price'           => $post['cost_price'] ?? '',
                    'deal_date'            => $post['deal_date'] ?? '',
                    'status'               => $post['status'] ?? '',
                    'staff_id'             => $post['staff_id'] ?? get_staff_user_id(),
                    'contract_signed'      => !empty($post['contract_signed']) ? 1 : 0,
                    'invoice_issued'       => !empty($post['invoice_issued']) ? 1 : 0,
                    'reminder_enabled'     => !empty($post['reminder_enabled']) ? 1 : 0,
                    'reminder_frequency'   => $post['reminder_frequency'] ?? 2,
                    'activity_description' => $post['activity_description'] ?? '',
                ];
            }
        }

        if ($is_id && !isset($data['deal'])) {
            $data['deal'] = $this->sales_pipeline_model->get($id);
            if (!$data['deal']) {
                show_404();
            }
        }

        $data['statuses'] = $this->sales_pipeline_model->get_statuses();
        $data['sources']  = $this->sales_pipeline_model->get_sources();
        
        // Chỉ lấy tất cả staff nếu là admin hoặc có quyền view global (Trưởng phòng)
        if (is_admin() || has_permission('sales_pipeline', '', 'view')) {
            $data['staff'] = $this->staff_model->get('', ['active' => 1]);
            $data['can_assign_others'] = true;
        } else {
            // Ngược lại chỉ lấy chính nhân viên đó
            $data['staff'] = [(array) $this->staff_model->get(get_staff_user_id())];
            $data['can_assign_others'] = false;
        }

        $data['title'] = (!$is_id) ? _l('sales_pipeline_new_deal') : _l('sales_pipeline_edit_deal');
        $this->load->view('sales_pipeline/deal', $data);
    }

    /**
     * Xóa deal
     * URL: admin/sales_pipeline/delete/{id}
     */
    public function delete($id = null)
    {
        if (!$id || !is_numeric($id)) {
            set_alert('warning', _l('sales_pipeline_invalid_deal'));
            redirect(admin_url('sales_pipeline'));
        }

        if (!has_permission('sales_pipeline', '', 'delete')) {
            access_denied('sales_pipeline');
        }

        $success = $this->sales_pipeline_model->delete($id);
        if ($success) {
            set_alert('success', _l('sales_pipeline_deal_deleted'));
        }

        // Bảo toàn trạng thái bộ lọc: đọc lại query string từ URL xóa và redirect về đúng trang filter
        $redirect_url = admin_url('sales_pipeline');
        if (!empty($_SERVER['QUERY_STRING'])) {
            $redirect_url .= '?' . $_SERVER['QUERY_STRING'];
        }
        redirect($redirect_url);
    }

    /**
     * Cập nhật trạng thái nhanh (AJAX)
     * URL: admin/sales_pipeline/update_status/{id}
     */
    public function update_status($id = null)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if ($id === null) {
            $id = $this->input->post('pipeline_id');
        }

        if (!$id) {
            return $this->json_response(false, _l('sales_pipeline_missing_id'), [], 400);
        }

        if (!has_permission('sales_pipeline', '', 'edit')) {
            return $this->json_response(false, _l('access_denied'), [], 403);
        }

        $status = $this->input->post('status');
        if (!$status) {
            $this->json_response(false, _l('sales_pipeline_missing_deal_or_status'), [], 400);
        }

        $this->sales_pipeline_model->update(['status' => $status], $id);
        $this->json_response(true, _l('sales_pipeline_status_updated'));
    }

    /**
     * Form phản hồi nhanh cho reminder.
     * URL: admin/sales_pipeline/reminder_response/{reminder_id}
     */
    public function reminder_response($reminder_id = null)
    {
        if (!$reminder_id || !is_numeric($reminder_id)) {
            show_404();
        }

        $reminder = $this->sales_pipeline_model->get_reminder_log((int) $reminder_id);
        if (!$reminder) {
            show_404();
        }

        if (!$this->can_access_reminder($reminder)) {
            access_denied('sales_pipeline');
        }

        $data['reminder'] = $reminder;
        $data['title'] = _l('sales_pipeline_quick_response_title');
        $this->load->view('sales_pipeline/reminder_response', $data);
    }

    /**
     * Nhân viên gửi phản hồi nhắc nhở.
     * Hỗ trợ form POST chuẩn và giữ JSON response cho client AJAX cũ.
     * URL: admin/sales_pipeline/respond_reminder/{reminder_id}
     */
    public function respond_reminder($reminder_id = null)
    {
        $is_ajax = $this->input->is_ajax_request();

        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
        }

        if (!$reminder_id || !is_numeric($reminder_id)) {
            if ($is_ajax) {
                return $this->json_response(false, _l('sales_pipeline_missing_id'), [], 400);
            }
            show_404();
        }

        $reminder_id = (int) $reminder_id;
        $reminder = $this->sales_pipeline_model->get_reminder_log($reminder_id);
        if (!$reminder) {
            if ($is_ajax) {
                return $this->json_response(false, _l('sales_pipeline_reminder_not_found'), [], 404);
            }
            show_404();
        }

        if (!$this->can_access_reminder($reminder)) {
            if ($is_ajax) {
                return $this->json_response(false, _l('access_denied'), [], 403);
            }
            access_denied('sales_pipeline');
        }

        if ((int) ($reminder['response_required'] ?? 1) !== 1) {
            if ($is_ajax) {
                return $this->json_response(false, _l('sales_pipeline_reminder_response_not_required'), [], 422);
            }
            set_alert('warning', _l('sales_pipeline_reminder_response_not_required'));
            redirect(admin_url('sales_pipeline/reminder_response/' . $reminder_id));
        }

        $response = trim((string) $this->input->post('response', false));
        if ($response === '' || mb_strlen($response) > 2000) {
            if ($is_ajax) {
                return $this->json_response(false, _l('sales_pipeline_reminder_response_invalid'), [], 422);
            }
            set_alert('warning', _l('sales_pipeline_reminder_response_invalid'));
            redirect(admin_url('sales_pipeline/reminder_response/' . $reminder_id));
        }

        $result = $this->sales_pipeline_model->submit_reminder_response(
            $reminder_id,
            $response,
            get_staff_user_id()
        );

        if ($result['status'] === 'success') {
            if ($is_ajax) {
                return $this->json_response(true, _l('sales_pipeline_reminder_responded'), $result);
            }
            set_alert('success', _l('sales_pipeline_reminder_responded'));
            redirect(admin_url('sales_pipeline/reminder_response/' . $reminder_id));
        }

        $message_key = 'sales_pipeline_reminder_response_failed';
        $http_code = 500;
        if ($result['status'] === 'already_responded') {
            $message_key = 'sales_pipeline_reminder_already_responded';
            $http_code = 409;
        } elseif ($result['status'] === 'forbidden') {
            $message_key = 'access_denied';
            $http_code = 403;
        } elseif ($result['status'] === 'not_found') {
            $message_key = 'sales_pipeline_reminder_not_found';
            $http_code = 404;
        } elseif ($result['status'] === 'invalid') {
            $message_key = 'sales_pipeline_reminder_response_invalid';
            $http_code = 422;
        } elseif ($result['status'] === 'response_not_required') {
            $message_key = 'sales_pipeline_reminder_response_not_required';
            $http_code = 422;
        } elseif ($result['status'] === 'delivery_pending') {
            $message_key = 'sales_pipeline_reminder_delivery_pending';
            $http_code = 409;
        }

        if ($is_ajax) {
            return $this->json_response(false, _l($message_key), [], $http_code);
        }

        if ($http_code === 403) {
            access_denied('sales_pipeline');
        }
        if ($http_code === 404) {
            show_404();
        }

        set_alert($http_code === 409 ? 'warning' : 'danger', _l($message_key));
        redirect(admin_url('sales_pipeline/reminder_response/' . $reminder_id));
    }

    /**
     * Lấy danh sách Reminder đang chờ xử lý cho Dropdown Notification Bell.
     * URL: admin/sales_pipeline/reminder_bell_feed
     */
    public function reminder_bell_feed()
    {
        if (!$this->reminder_bell_is_enabled()) {
            return $this->json_response(false, _l('page_not_found'), [], 404);
        }

        if (!$this->can_access_reminder_bell()) {
            return $this->json_response(false, _l('access_denied'), [], 403);
        }

        $staff_id = (int) get_staff_user_id();
        $feed = $this->sales_pipeline_model->get_reminder_bell_feed($staff_id, 30);

        return $this->json_response(true, '', $feed, 200);
    }

    /**
     * Xác nhận Informational Reminder (Acknowledge) qua AJAX.
     * URL: admin/sales_pipeline/reminder_bell_acknowledge/{reminder_id}
     */
    public function reminder_bell_acknowledge($reminder_id = null)
    {
        if (!$this->reminder_bell_is_enabled()) {
            return $this->json_response(false, _l('page_not_found'), [], 404);
        }

        if (!$this->can_access_reminder_bell()) {
            return $this->json_response(false, _l('access_denied'), [], 403);
        }

        if (strtoupper($this->input->method()) !== 'POST' || !$this->input->is_ajax_request()) {
            return $this->json_response(false, _l('page_not_found'), [], 405);
        }

        if (!$reminder_id || !is_numeric($reminder_id)) {
            return $this->json_response(false, _l('sales_pipeline_missing_id'), [], 400);
        }

        $reminder_id = (int) $reminder_id;
        $staff_id = (int) get_staff_user_id();

        $result = $this->sales_pipeline_model->acknowledge_reminder($reminder_id, $staff_id);

        if ($result['status'] === 'success' || $result['status'] === 'already_acknowledged') {
            return $this->json_response(true, _l('sales_pipeline_reminder_acknowledged'), $result, 200);
        }

        if ($result['status'] === 'forbidden') {
            return $this->json_response(false, _l('access_denied'), [], 403);
        }

        if ($result['status'] === 'not_found') {
            return $this->json_response(false, _l('sales_pipeline_reminder_not_found'), [], 404);
        }

        if ($result['status'] === 'actionable_not_allowed') {
            return $this->json_response(false, _l('sales_pipeline_reminder_response_required_cannot_dismiss'), [], 422);
        }

        if ($result['status'] === 'invalid') {
            return $this->json_response(false, _l('sales_pipeline_missing_id'), [], 400);
        }

        return $this->json_response(false, _l('sales_pipeline_reminder_acknowledge_failed'), [], 500);
    }

    /** Keep the rollout switch server-authoritative, including stale browser assets. */
    protected function reminder_bell_is_enabled()
    {
        return (int) get_option('sp_reminder_crm_inbox_enabled') === 1;
    }

    /** A Reminder Inbox is only useful to Staff allowed to work in Sales Pipeline. */
    protected function can_access_reminder_bell()
    {
        return is_staff_logged_in()
            && (is_admin()
                || has_permission('sales_pipeline', '', 'view')
                || has_permission('sales_pipeline', '', 'view_own'));
    }

    /**
     * Cập nhật giá nhập (cost price) qua AJAX
     * URL: admin/sales_pipeline/update_cost_price
     * POST: pipeline_id, cost_price
     * URL: admin/sales_pipeline/update_cost_price/{id}
     */
    public function update_cost_price($id = null)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if ($id === null) {
            $id = $this->input->post('pipeline_id');
        }

        if (!$id) {
            return $this->json_response(false, _l('sales_pipeline_missing_id'), [], 400);
        }

        $deal = $this->sales_pipeline_model->get($id);
        if (!$deal) {
            $this->json_response(false, _l('sales_pipeline_deal_not_found'), [], 404);
        }

        // Quyền sửa: Admin, người sở hữu deal, người có quyền edit
        $can_edit = is_admin()
            || $deal['staff_id'] == get_staff_user_id()
            || (isset($deal['imported_by']) && $deal['imported_by'] == get_staff_user_id())
            || has_permission('sales_pipeline', '', 'edit');

        if (!$can_edit) {
            $this->json_response(false, _l('sales_pipeline_no_permission_update_cost_price'), [], 403);
        }

        $cost_price_input = $this->input->post('cost_price');
        $cost_price = ($cost_price_input !== '' && $cost_price_input !== null) ? floatval($cost_price_input) : null;

        if ($cost_price !== null && $cost_price < 0) {
            $this->json_response(false, _l('sales_pipeline_invalid_cost_price'), [], 400);
        }

        $success = $this->sales_pipeline_model->update_cost_price($id, $cost_price);
        if (!$success) {
            return $this->json_response(false, _l('sales_pipeline_update_failed'));
        }

        // Lấy thông tin deal mới sau update để trả về cho UI
        $updated_deal = $this->sales_pipeline_model->get($id);

        if (!$updated_deal) {
            return $this->json_response(false, _l('sales_pipeline_deal_not_found'));
        }

        $actual_profit   = $updated_deal['actual_profit'] !== null ? (float) $updated_deal['actual_profit'] : null;
        $profit_pct      = $updated_deal['profit_percentage'] !== null ? round((float) $updated_deal['profit_percentage'], 1) : null;

        $this->json_response(true, _l('sales_pipeline_cost_price_updated'), [
            'cost_price'              => $updated_deal['cost_price'],
            'cost_price_formatted'    => number_format((float) $updated_deal['cost_price']),
            'actual_profit'           => $actual_profit,
            'actual_profit_formatted' => $actual_profit !== null ? number_format($actual_profit) : null,
            'profit_percentage'       => $profit_pct,
        ]);
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
            set_alert('danger', _l('sales_pipeline_template_file_not_found'));
            redirect(admin_url('sales_pipeline'));
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
            $this->json_response(false, _l('access_denied'), [], 403);
        }

        $data['statuses'] = $this->sales_pipeline_model->get_statuses();
        $data['search'] = $this->input->post_get('search') ? trim($this->input->post_get('search')) : '';
        $data['sort_by'] = $this->input->post_get('sort') ? trim($this->input->post_get('sort')) : 'deal_date';
        $data['sort_type'] = $this->input->post_get('sort_type') ? trim($this->input->post_get('sort_type')) : 'desc';
        $data['quarter'] = $this->input->post_get('quarter') ? trim($this->input->post_get('quarter')) : '';
        $data['year'] = $this->input->post_get('year') ? trim($this->input->post_get('year')) : '';
        $data['staff_id'] = $this->input->post_get('staff_id') ? trim($this->input->post_get('staff_id')) : '';
        $data['contract_signed'] = $this->input->post_get('contract_signed') !== null ? trim($this->input->post_get('contract_signed')) : null;
        $data['invoice_issued'] = $this->input->post_get('invoice_issued') !== null ? trim($this->input->post_get('invoice_issued')) : null;
        
        // Build query string to preserve filter states when clicking kanban cards
        $query_params = [];
        if ($data['quarter']) $query_params['quarter'] = $data['quarter'];
        if ($data['year'])    $query_params['year'] = $data['year'];
        if ($data['staff_id']) $query_params['staff_id'] = $data['staff_id'];
        if ($data['search'])  $query_params['search'] = $data['search'];
        if ($data['contract_signed'] !== null && $data['contract_signed'] !== '') $query_params['contract_signed'] = $data['contract_signed'];
        if ($data['invoice_issued'] !== null && $data['invoice_issued'] !== '') $query_params['invoice_issued'] = $data['invoice_issued'];
        if ($data['sort_by']) $query_params['sort'] = $data['sort_by'];
        if ($data['sort_type']) $query_params['sort_type'] = $data['sort_type'];
        $data['query_string'] = !empty($query_params) ? '?' . http_build_query($query_params) : '';

        $summary_staff_id = $data['staff_id'];
        if (!has_permission('sales_pipeline', '', 'view')) {
            $summary_staff_id = get_staff_user_id();
        }

        $summary = $this->sales_pipeline_model->get_summary(
            $data['quarter'],
            $data['year'],
            $summary_staff_id,
            $data['search'],
            $data['contract_signed'],
            $data['invoice_issued']
        );

        $html = $this->load->view('sales_pipeline/kan-ban', $data, true);
        echo json_encode(['kanban' => $html, 'summary' => $summary]);
        die();
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
            $this->json_response(false, _l('access_denied'), [], 403);
        }

        $status_id = $this->input->post_get('status_id');
        $page      = $this->input->post_get('page') ? (int) $this->input->post_get('page') : 1;
        $search    = $this->input->post_get('search');

        $status = $this->sales_pipeline_model->get_status_by_id($status_id);

        $sort = [
            'sort_by'         => $this->input->post_get('sort'),
            'sort'            => $this->input->post_get('sort_type'),
            'quarter'         => $this->input->post_get('quarter'),
            'year'            => $this->input->post_get('year'),
            'staff_id'        => $this->input->post_get('staff_id'),
            'contract_signed' => $this->input->post_get('contract_signed'),
            'invoice_issued'  => $this->input->post_get('invoice_issued'),
        ];

        $query_params = [];
        foreach (['search' => $search, 'quarter' => $sort['quarter'], 'year' => $sort['year'], 'staff_id' => $sort['staff_id'], 'contract_signed' => $sort['contract_signed'], 'invoice_issued' => $sort['invoice_issued'], 'sort' => $sort['sort_by'], 'sort_type' => $sort['sort']] as $key => $value) {
            if ($value !== null && $value !== '') {
                $query_params[$key] = $value;
            }
        }
        $query_string = !empty($query_params) ? '?' . http_build_query($query_params) : '';

        $deals       = $this->sales_pipeline_model->do_kanban_query($status_id, $search, $page, $sort);
        $total_deals = $this->sales_pipeline_model->do_kanban_query($status_id, $search, 1, $sort, true);
        $total_pages = (int) ceil($total_deals / 10);

        // Render tất cả card thành HTML rồi trả về JSON chuẩn
        $html = '';
        foreach ($deals as $deal) {
            $html .= $this->load->view('sales_pipeline/_kanban_card', ['deal' => $deal, 'status' => $status, 'query_string' => $query_string], true);
        }

        $this->json_response(true, '', [
            'html'        => $html,
            'total_pages' => $total_pages,
            'page'        => $page,
            'total_deals' => (int) $total_deals,
        ]);
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
    public function update_deal_status($id = null, $status_id = null)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if ($id === null) {
            $id = $this->input->post('deal_id');
        }
        
        if (!$id) {
            return $this->json_response(false, _l('sales_pipeline_missing_id'), [], 400);
        }

        if ($status_id === null) {
            $status_id = $this->input->post('status_id');
        }
        if (!$status_id) {
            return $this->json_response(false, _l('sales_pipeline_missing_deal_or_status'), [], 400);
        }

        $deal = $this->sales_pipeline_model->get($id);
        if (!$deal) {
            $this->json_response(false, _l('sales_pipeline_deal_not_found'), [], 404);
        }

        // Quyền sửa
        $can_edit = is_admin()
            || $deal['staff_id'] == get_staff_user_id()
            || (isset($deal['imported_by']) && $deal['imported_by'] == get_staff_user_id())
            || has_permission('sales_pipeline', '', 'edit');

        if (!$can_edit) {
            $this->json_response(false, _l('sales_pipeline_no_permission_update_deal'), [], 403);
        }

        $success = $this->sales_pipeline_model->update_status($id, $status_id);
        if (!$success) {
            $this->json_response(false, _l('sales_pipeline_status_update_failed'));
        }

        // Lấy thông tin status mới
        $status_info = $this->sales_pipeline_model->get_status_by_id($status_id);
        $this->json_response(true, _l('sales_pipeline_status_updated'), [
            'status_name'  => $status_info['name'] ?? '',
            'status_color' => $status_info['color'] ?? '#777',
        ]);
    }

    public function update_status_order()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (!is_admin()) {
            $this->json_response(false, _l('access_denied'), [], 403);
        }

        $order = $this->input->post('order');
        if (empty($order) || !is_array($order)) {
            $this->json_response(false, _l('sales_pipeline_missing_deal_or_status'), [], 400);
        }

        $this->sales_pipeline_model->update_statuses_order($order);
        $this->json_response(true, _l('sales_pipeline_status_updated'));
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
                    set_alert('danger', _l('sales_pipeline_invalid_staff'));
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
                $id = $data['id'] ?? '';
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
                $id = $data['id'] ?? '';
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
            } elseif ($type == 'reminder') {
                $errors = [];
                $normalized = [];
                $toggles = ['sp_reminder_global_enabled','sp_reminder_skip_weekends','sp_reminder_deal_pipeline_enabled','sp_reminder_deal_stale_enabled','sp_reminder_est_daily_enabled','sp_reminder_est_monthly_enabled','sp_reminder_est_weekly_enabled','sp_reminder_lc_draft_enabled','sp_reminder_lc_sent_enabled','sp_reminder_lc_declined_enabled','sp_reminder_lc_expired_enabled','sp_reminder_lc_accepted_enabled','sp_reminder_email_cc_manager_enabled'];
                foreach ($toggles as $key) { $normalized[$key] = isset($data[$key]) ? '1' : '0'; }
                $channels = [
                    'sp_reminder_deal_pipeline_channels' => 'sp_reminder_deal_pipeline_enabled',
                    'sp_reminder_deal_stale_channels' => 'sp_reminder_deal_stale_enabled',
                    'sp_reminder_est_daily_channels' => 'sp_reminder_est_daily_enabled',
                    'sp_reminder_est_monthly_channels' => 'sp_reminder_est_monthly_enabled',
                    'sp_reminder_est_weekly_channels' => 'sp_reminder_est_weekly_enabled',
                    'sp_reminder_lc_draft_channels' => 'sp_reminder_lc_draft_enabled',
                    'sp_reminder_lc_sent_channels' => 'sp_reminder_lc_sent_enabled',
                    'sp_reminder_lc_declined_channels' => 'sp_reminder_lc_declined_enabled',
                    'sp_reminder_lc_expired_channels' => 'sp_reminder_lc_expired_enabled',
                    'sp_reminder_lc_accepted_channels' => 'sp_reminder_lc_accepted_enabled',
                ];
                foreach ($channels as $key => $enabledKey) {
                    $selected = [];
                    if (!empty($data[$key . '_crm'])) { $selected[] = 'crm'; }
                    if (!empty($data[$key . '_email'])) { $selected[] = 'email'; }
                    if ($normalized[$enabledKey] === '1' && !$selected) { $errors[] = _l('sp_reminder_error_channel_required', [$key]); }
                    $normalized[$key] = implode(',', $selected);
                }
                $scope = trim((string) ($data['sp_reminder_email_cc_scope'] ?? 'all'));
                $normalized['sp_reminder_email_cc_scope'] = in_array($scope, ['all', 'critical_only'], true) ? $scope : 'all';
                $rawFallback = trim((string) ($data['sp_reminder_manager_fallback_emails'] ?? ''));
                $validFallbackEmails = [];
                if ($rawFallback !== '') {
                    foreach (explode(',', $rawFallback) as $item) {
                        $email = trim($item);
                        if ($email !== '') {
                            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $validFallbackEmails[] = $email;
                            } else {
                                $errors[] = _l('sp_reminder_error_invalid_fallback_email', [$email]);
                            }
                        }
                    }
                }
                $normalized['sp_reminder_manager_fallback_emails'] = implode(',', array_unique($validFallbackEmails));
                $numbers = [
                    'sp_reminder_sla_hours'              => [1, 720],
                    'sp_reminder_deal_pipeline_min_count'=> [1, 1000],
                    'sp_reminder_deal_stale_cutoff_days' => [1, 3650], 'sp_reminder_deal_stale_max_per_run' => [1, 1000],
                    'sp_reminder_est_daily_threshold'    => [1, 100],  'sp_reminder_est_monthly_d10'         => [1, 100],
                    'sp_reminder_est_monthly_d20'        => [1, 200],  'sp_reminder_est_monthly_final'       => [1, 500],
                    'sp_reminder_est_weekly_target'      => [1, 100000000000], 'sp_reminder_lc_draft_days'  => [1, 90],
                    'sp_reminder_lc_sent_days'           => [1, 90],   'sp_reminder_lc_sent_expiry_days'     => [1, 30], 'sp_reminder_lc_declined_days' => [1, 90],
                ];
                foreach ($numbers as $key => $limits) {
                    $raw = trim((string) ($data[$key] ?? ''));
                    if (!preg_match('/^\d+$|^\d{1,3}([.,\s]\d{3})+$/', $raw)) { $errors[] = _l('sp_reminder_error_invalid_number', [$key, $limits[0], $limits[1]]); continue; }
                    $value = (int) preg_replace('/[.,\s]/', '', $raw);
                    if ($value < $limits[0] || $value > $limits[1]) { $errors[] = _l('sp_reminder_error_invalid_number', [$key, $limits[0], $limits[1]]); continue; }
                    $normalized[$key] = (string) $value;
                }
                foreach (['sp_reminder_deal_pipeline_check_time','sp_reminder_est_daily_time','sp_reminder_est_monthly_time','sp_reminder_est_weekly_midweek_time','sp_reminder_est_weekly_final_time'] as $key) {
                    $value = trim((string) ($data[$key] ?? ''));
                    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) { $errors[] = _l('sp_reminder_error_invalid_time', [$key]); }
                    else { $normalized[$key] = $value; }
                }
                $quietStart = trim((string) ($data['sp_reminder_quiet_hours_start'] ?? ''));
                $quietEnd = trim((string) ($data['sp_reminder_quiet_hours_end'] ?? ''));
                if (($quietStart === '') !== ($quietEnd === '')) { $errors[] = _l('sp_reminder_error_quiet_hours_incomplete'); }
                elseif ($quietStart !== '' && (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $quietStart) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $quietEnd) || $quietStart === $quietEnd)) { $errors[] = _l('sp_reminder_error_invalid_time', [_l('sp_reminder_quiet_hours_label')]); }
                $normalized['sp_reminder_quiet_hours_start'] = $quietStart;
                $normalized['sp_reminder_quiet_hours_end'] = $quietEnd;
                $holidays = [];
                foreach (array_filter(array_map('trim', preg_split('/\R/', (string) ($data['sp_reminder_holiday_dates'] ?? '')))) as $date) {
                    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                    if (!$parsed || $parsed->format('Y-m-d') !== $date) { $errors[] = _l('sp_reminder_error_invalid_date', [$date]); }
                    else { $holidays[] = $date; }
                }
                $normalized['sp_reminder_holiday_dates'] = implode("\n", array_unique($holidays));
                if ($errors) { set_alert('warning', implode('<br>', $errors)); redirect(admin_url('sales_pipeline/settings#reminders')); }
                $save_result = $this->sales_pipeline_model->save_pipeline_settings($normalized);
                $changed = $save_result['changed'];
                if (!$save_result['success']) { set_alert('danger', _l('problem_updating')); }
                else { if ($changed) { log_activity('Sales Pipeline reminder settings updated by Staff #' . get_staff_user_id() . ': ' . implode(', ', $changed)); } set_alert('success', _l('updated_successfully', _l('sales_pipeline_settings_reminders'))); }
                redirect(admin_url('sales_pipeline/settings#reminders'));
            } elseif ($type == 'performance') {
                $errors = [];
                $normalized = [];

                $rawResponseTarget = trim((string) ($data['performance_response_target_percent'] ?? ''));
                if (!preg_match('/^\d+(\.\d+)?$/', $rawResponseTarget)) {
                    $errors[] = _l('performance_score_error_invalid_response_target');
                } else {
                    $val = (float) $rawResponseTarget;
                    if ($val < 1.0 || $val > 100.0) {
                        $errors[] = _l('performance_score_error_invalid_response_target');
                    } else {
                        $normalized['performance_response_target_percent'] = (string) $val;
                    }
                }

                if ($errors) {
                    set_alert('warning', implode('<br>', $errors));
                    redirect(admin_url('sales_pipeline/settings#performance'));
                }

                $save_result = $this->sales_pipeline_model->save_pipeline_settings($normalized);
                $changed = $save_result['changed'];
                if (!$save_result['success']) {
                    set_alert('danger', _l('problem_updating'));
                } else {
                    if ($changed) {
                        log_activity('Sales Pipeline performance score settings updated by Staff #' . get_staff_user_id() . ': ' . implode(', ', $changed));
                    }
                    set_alert('success', _l('updated_successfully', _l('sales_pipeline_settings_performance')));
                }
                redirect(admin_url('sales_pipeline/settings#performance'));
            }
            redirect(admin_url('sales_pipeline/settings'));
        }

        $data['statuses'] = $this->sales_pipeline_model->get_statuses();
        $data['sources'] = $this->sales_pipeline_model->get_sources();
        $data['reminder_options'] = [];
        foreach (sales_pipeline_reminder_rule_default_options() as $key => $default) {
            $data['reminder_options'][$key] = get_option($key) === false ? $default : get_option($key);
        }
        $data['performance_options'] = [];
        foreach (sales_pipeline_performance_score_default_options() as $key => $default) {
            $data['performance_options'][$key] = get_option($key) === false ? $default : get_option($key);
        }
        $this->load->library('sales_pipeline/Reminder_delivery_operations');
        $data['reminder_delivery_health'] = $this->reminder_delivery_operations->health();
        $data['title'] = _l('sales_pipeline_settings');

        $this->load->view('sales_pipeline/settings', $data);
    }

    public function reminder_delivery_retry($deliveryId)
    {
        if (!is_admin()) {
            access_denied('sales_pipeline_settings');
        }
        if ($this->input->method(true) !== 'POST' || !$this->input->is_ajax_request()
            || config_item('csrf_protection') !== true) {
            show_error(_l('sales_pipeline_reminder_delivery_method_not_allowed'), 405);
        }
        $this->load->library('sales_pipeline/Reminder_delivery_operations');
        $success = $this->reminder_delivery_operations->retry((int) $deliveryId, get_staff_user_id());
        $this->json_response($success,
            _l($success ? 'sales_pipeline_reminder_delivery_retry_success' : 'sales_pipeline_reminder_delivery_retry_unavailable'),
            [], $success ? 200 : 409);
    }

    public function reminder_delivery_resume_circuit()
    {
        if (!is_admin()) {
            access_denied('sales_pipeline_settings');
        }
        if ($this->input->method(true) !== 'POST' || !$this->input->is_ajax_request()
            || config_item('csrf_protection') !== true) {
            show_error(_l('sales_pipeline_reminder_delivery_method_not_allowed'), 405);
        }
        $this->load->library('sales_pipeline/Reminder_delivery_operations');
        $success = $this->reminder_delivery_operations->resumeCircuit(get_staff_user_id());
        $this->json_response($success,
            _l($success ? 'sales_pipeline_reminder_delivery_resume_success' : 'sales_pipeline_reminder_delivery_resume_unavailable'),
            [], $success ? 200 : 409);
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
            if ($this->sales_pipeline_model->is_status_used($id)) {
                set_alert('warning', _l('sales_pipeline_cannot_delete_status_in_use'));
            } else {
                $success = $this->sales_pipeline_model->delete_status($id);
                if ($success) {
                    set_alert('success', _l('deleted', _l('sales_pipeline_status')));
                } else {
                    set_alert('warning', _l('problem_deleting', _l('sales_pipeline_status')));
                }
            }
        } elseif ($type == 'source') {
            if ($this->sales_pipeline_model->is_source_used($id)) {
                set_alert('warning', _l('sales_pipeline_cannot_delete_source_in_use'));
            } else {
                $success = $this->sales_pipeline_model->delete_source($id);
                if ($success) {
                    set_alert('success', _l('deleted', _l('sales_pipeline_source')));
                } else {
                    set_alert('warning', _l('problem_deleting', _l('sales_pipeline_source')));
                }
            }
        }

        redirect(admin_url('sales_pipeline/settings'));
    }

    /**
     * Endpoint AJAX - Lọc, Tìm kiếm & Phân trang danh sách Deal
     * Trả về JSON chuẩn (JSON Response Standardization)
     */
    public function ajax_search()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (!has_permission('sales_pipeline', '', 'view') && !has_permission('sales_pipeline', '', 'view_own')) {
            return $this->json_response(false, _l('access_denied'), [], 403);
        }

        $search          = trim($this->input->get('search') ?? '');
        $quarter         = $this->input->get('quarter') ? trim($this->input->get('quarter')) : null;
        $year            = $this->input->get('year') !== null ? trim($this->input->get('year')) : null;
        $staff_id        = $this->input->get('staff_id') ? trim($this->input->get('staff_id')) : null;
        $contract_signed = $this->input->get('contract_signed') !== null ? trim($this->input->get('contract_signed')) : null;
        $invoice_issued  = $this->input->get('invoice_issued') !== null ? trim($this->input->get('invoice_issued')) : null;

        // Phân quyền: Nếu không có quyền view global thì chỉ xem deal của chính mình
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
        if ($contract_signed !== null && $contract_signed !== '') {
            $where[db_prefix() . 'sales_pipeline.contract_signed'] = (int) $contract_signed;
        }
        if ($invoice_issued !== null && $invoice_issued !== '') {
            $where[db_prefix() . 'sales_pipeline.invoice_issued'] = (int) $invoice_issued;
        }

        // Pagination setup
        $per_page = $this->input->get('per_page') ? (int)$this->input->get('per_page') : 25;
        $page     = $this->input->get('page') ? (int)$this->input->get('page') : 1;

        $allowed_per_page = [10, 25, 50, 100];
        if (!in_array($per_page, $allowed_per_page)) {
            $per_page = 25;
        }
        if ($page < 1) {
            $page = 1;
        }
        // Truy vấn dữ liệu & đếm tổng
        $total_deals = $this->sales_pipeline_model->count_deals($where, $search);
        $total_pages = max(1, (int) ceil($total_deals / $per_page));
        if ($page > $total_pages) {
            $page = $total_pages;
        }
        $offset = ($page - 1) * $per_page;

        $deals       = $this->sales_pipeline_model->get('', $where, $per_page, $offset, $search);
        $summary     = $this->sales_pipeline_model->get_summary($quarter, $year, $staff_id, $search, $contract_signed, $invoice_issued);

        $can_view_deal_details = is_admin() || has_permission('sales_pipeline', '', 'view_deal_details');
        $can_delete_deal       = is_admin() || has_permission('sales_pipeline', '', 'delete');
        $can_edit_cost_price   = is_admin();

        // Chuẩn hóa định dạng JSON Response
        $this->json_response(true, '', [
            'deals'      => $deals,
            'summary'    => $summary,
            'pagination' => [
                'total_records' => (int) $total_deals,
                'per_page'      => (int) $per_page,
                'current_page'  => (int) $page,
                'total_pages'   => $total_deals > 0 ? (int) $total_pages : 0,
            ],
            'can_view_deal_details' => $can_view_deal_details,
            'can_delete_deal'       => $can_delete_deal,
            'can_edit_cost_price'   => $can_edit_cost_price,
            'current_user_id'       => get_staff_user_id(),
        ]);
    }

    // =========================================================================
    // HELPER: Chuẩn hóa JSON Response cho tất cả AJAX endpoints
    // =========================================================================

    /**
     * Trả về JSON response chuẩn hóa và kết thúc request.
     *
     * @param bool   $status    Trạng thái thành công / thất bại
     * @param string $message   Thông báo cho Frontend
     * @param array  $data      Dữ liệu bổ sung (gom vào key 'data')
     * @param int    $http_code HTTP Status Code (200, 400, 403, 404...)
     */
    protected function json_response($status = true, $message = '', $data = [], $http_code = 200)
    {
        $response = [
            'status'  => (bool) $status,
            'success' => (bool) $status,
            'message' => $message,
            'data'    => $data,
        ];
        $this->output
             ->set_status_header($http_code)
             ->set_content_type('application/json', 'utf-8')
             ->set_output(json_encode($response))
             ->_display();
        exit;
    }

    private function can_access_dashboard($staff_id = '')
    {
        return is_admin($staff_id)
            || has_permission('sales_pipeline', $staff_id, 'view')
            || has_permission('sales_pipeline', $staff_id, 'view_own');
    }

    private function can_view_dashboard_all($staff_id = '')
    {
        // The Performance Score contract is role-based: only Administrators
        // receive the full cohort. A Staff account may hold `view` permission
        // for other module workflows, but its Dashboard remains personal.
        return is_admin($staff_id);
    }

    private function resolve_dashboard_tab($tab)
    {
        return in_array($tab, ['deals', 'estimates'], true) ? $tab : 'deals';
    }

    /**
     * Preserve the viewer's personal summary while ranking the full Estimate cohort.
     *
     * @param array  $dashboard
     * @param string $period
     * @param bool   $can_view_all
     * @return void
     */
    private function attach_estimate_performance_ranking(&$dashboard, $period, $can_view_all)
    {
        $current_staff_id = (int) get_staff_user_id();
        $ranking = $this->sales_pipeline_model->get_estimate_performance_ranking($period);
        $dashboard['estimates']['leaderboard'] = $this->sales_pipeline_model
            ->project_estimate_performance_ranking(
                $ranking['leaderboard'],
                $can_view_all,
                $current_staff_id
            );
        $dashboard['estimates']['performance_status'] = $ranking['status'];
        $dashboard['estimates']['performance_formula_version'] = $ranking['formula_version'];
        $dashboard['estimates']['performance_configuration_errors'] = $ranking['configuration_errors'];
        $dashboard['viewer'] = [
            'can_view_all'     => (bool) $can_view_all,
            'current_staff_id' => $current_staff_id,
        ];
    }

    /**
     * Chủ reminder luôn được truy cập; admin/người có quyền edit có thể hỗ trợ.
     */
    private function can_access_reminder($reminder)
    {
        return (int) $reminder['staff_id'] === (int) get_staff_user_id()
            || is_admin()
            || has_permission('sales_pipeline', '', 'edit');
    }

    /**
     * Endpoint to lock/unlock financial entities (POST only, CSRF, Permission checked).
     */
    public function set_finance_lock()
    {
        if (!$this->input->is_ajax_request() && $this->input->method() !== 'post') {
            show_error('Method Not Allowed', 405);
        }

        if (!is_admin() && !has_permission('sales_pipeline', '', 'manage_finance_lock')) {
            access_denied('manage_finance_lock');
        }

        $entityType = $this->input->post('entity_type');
        $entityId = (int) $this->input->post('entity_id');
        $lockAction = $this->input->post('action');
        $reference = trim((string) $this->input->post('reference'));
        $reason = trim((string) $this->input->post('reason'));

        if (!in_array($entityType, ['deal', 'estimate_group'], true) || $entityId <= 0 || !in_array($lockAction, ['lock', 'unlock'], true)) {
            return $this->json_response(false, _l('sales_pipeline_finance_lock_invalid_entity'), [], 400);
        }

        if ($lockAction === 'lock' && (empty($reference) || empty($reason))) {
            return $this->json_response(false, _l('sales_pipeline_finance_lock_reference_reason_required'), [], 400);
        }

        $actorStaffId = get_staff_user_id();
        $result = $this->sales_pipeline_model->set_finance_lock(
            $entityType,
            $entityId,
            $lockAction === 'lock',
            $actorStaffId,
            $reference,
            $reason
        );
        if (!$result['success']) {
            $statusCode = $result['reason'] === 'not_found' ? 404 : 500;
            return $this->json_response(false, _l('sales_pipeline_finance_lock_update_failed'), [], $statusCode);
        }

        return $this->json_response(true, _l('sales_pipeline_updated_successfully'), [
            'status'    => $lockAction === 'lock' ? 'locked' : 'unlocked',
            'entity_id' => $entityId,
            'locked_at' => $result['locked_at'],
        ]);
    }
}
