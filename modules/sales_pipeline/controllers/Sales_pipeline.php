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

        $data['statuses'] = $this->sales_pipeline_model->get_statuses();
        $data['staff']    = $this->staff_model->get('', ['active' => 1]);

        // Lọc theo quý và nhân viên
        $quarter  = $this->input->get('quarter') ?: null;
        $year     = $this->input->get('year') ?: date('Y');
        $staff_id = $this->input->get('staff_id') ?: null;

        // Chỉ cho xem deal của mình nếu không có quyền view global
        if (!has_permission('sales_pipeline', '', 'view')) {
            $staff_id = get_staff_user_id();
        }

        $where = [];
        if ($quarter) {
            $where['QUARTER(expected_close_date)'] = $quarter;
        }
        if ($year) {
            $where['YEAR(expected_close_date)'] = $year;
        }
        if ($staff_id) {
            $where[db_prefix() . 'sales_pipeline.staff_id'] = $staff_id;
        }

        $data['deals']   = $this->sales_pipeline_model->get('', $where);
        $data['summary'] = $this->sales_pipeline_model->get_summary($quarter, $year, $staff_id);

        $data['current_quarter']  = $quarter;
        $data['current_year']     = $year;
        $data['current_staff_id'] = $staff_id;

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
            if (!has_permission('sales_pipeline', '', 'edit')) {
                access_denied('sales_pipeline');
            }
        }

        // Validation
        $this->form_validation->set_rules('customer_name', _l('sales_pipeline_customer_name'), 'trim|required');
        $this->form_validation->set_rules('deal_name', _l('sales_pipeline_deal_name'), 'trim|required');
        $this->form_validation->set_rules('deal_value', _l('sales_pipeline_deal_value'), 'trim|required|numeric');
        $this->form_validation->set_rules('expected_close_date', _l('sales_pipeline_expected_date'), 'trim|required');
        $this->form_validation->set_rules('status', _l('sales_pipeline_status'), 'trim|required|numeric');

        if ($this->input->post()) {
            if ($this->form_validation->run() !== false) {
                $post_data = [
                    'customer_name'       => $this->input->post('customer_name'),
                    'contact_name'        => $this->input->post('contact_name'),
                    'contact_phone'       => $this->input->post('contact_phone'),
                    'contact_email'       => $this->input->post('contact_email'),
                    'source'              => $this->input->post('source'),
                    'deal_name'           => $this->input->post('deal_name'),
                    'deal_value'          => $this->input->post('deal_value'),
                    'profit_margin'       => floatval($this->input->post('profit_margin')) / 100,
                    'expected_close_date' => $this->input->post('expected_close_date'),
                    'status'              => $this->input->post('status'),
                    'staff_id'            => $this->input->post('staff_id') ?: get_staff_user_id(),
                    'contract_signed'     => $this->input->post('contract_signed'),
                    'invoice_issued'      => $this->input->post('invoice_issued'),
                    'reminder_enabled'    => $this->input->post('reminder_enabled'),
                    'reminder_frequency'  => $this->input->post('reminder_frequency') ?: 7,
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
                redirect(admin_url('sales_pipeline'));
            }
        }

        if ($id != '') {
            $data['deal'] = $this->sales_pipeline_model->get($id);
            if (!$data['deal']) {
                show_404();
            }
        }

        $data['statuses'] = $this->sales_pipeline_model->get_statuses();
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
     * Trang import Excel
     * URL: admin/sales_pipeline/import
     */
    public function import()
    {
        if (!has_permission('sales_pipeline', '', 'view') && !has_permission('sales_pipeline', '', 'view_own')) {
            access_denied('sales_pipeline');
        }

        if ($this->input->post()) {
            // Xử lý upload file
            if (isset($_FILES['import_file']) && $_FILES['import_file']['size'] > 0) {
                $this->load->library('sales_pipeline/Import_sales_pipeline', [], 'import');

                $staff_id = $this->input->post('staff_id') ?: get_staff_user_id();
                $result   = $this->import->process($_FILES['import_file'], $staff_id);

                if ($result['success']) {
                    set_alert('success', $result['message']);
                } else {
                    set_alert('danger', $result['message']);
                }
                redirect(admin_url('sales_pipeline'));
            }
        }

        $data['staff'] = $this->staff_model->get('', ['active' => 1]);
        $data['title'] = _l('sales_pipeline_import');
        $this->load->view('sales_pipeline/import', $data);
    }
}
