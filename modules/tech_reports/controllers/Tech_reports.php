<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Tech Reports Controller
 * Handles daily tech work reports, compliance tracking, and staff management
 */
class Tech_reports extends AdminController
{
    /**
     * Constructor - load model and check permissions
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->model('tech_reports_model');
    }

    /**
     * Index - List all tech reports with filters
     */
    public function index()
    {
        if (!has_permission('tech_reports', '', 'view')) {
            access_denied('tech_reports');
        }

        // Handle AJAX request for datatables
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('tech_reports', 'tables/reports_table'));
        }

        $data['title'] = _l('tech_reports');
        $data['staff_members'] = $this->staff_model->get();
        $data['task_categories'] = $this->get_task_categories();
        
        $this->load->view('manage', $data);
    }

    /**
     * Dashboard - Overview for managers
     */
    public function dashboard()
    {
        if (!has_permission('tech_reports', '', 'view')) {
            access_denied('tech_reports');
        }

        $data['title'] = _l('tech_reports_dashboard');
        
        // Get date range from request or default to this month
        $start_date = $this->input->get('start_date') ?: date('Y-m-01');
        $end_date = $this->input->get('end_date') ?: date('Y-m-t');
        
        $data['start_date'] = $start_date;
        $data['end_date'] = $end_date;
        
        // Get statistics
        $data['daily_summary'] = $this->tech_reports_model->get_daily_summary_range($start_date, $end_date);
        $data['compliance_report'] = $this->tech_reports_model->get_staff_compliance_report($start_date, $end_date);
        $data['tech_staff'] = $this->tech_reports_model->get_tech_staff();
        
        // Calculate totals
        $data['total_reports'] = 0;
        $data['total_hours'] = 0;
        $data['compliance_rate'] = 0;
        
        if (!empty($data['daily_summary'])) {
            foreach ($data['daily_summary'] as $summary) {
                $data['total_reports'] += $summary['report_count'];
                $data['total_hours'] += $summary['total_hours'];
            }
        }
        
        if (!empty($data['compliance_report'])) {
            $compliant = 0;
            $total = count($data['compliance_report']);
            foreach ($data['compliance_report'] as $comp) {
                if ($comp['compliance_rate'] >= 80) {
                    $compliant++;
                }
            }
            $data['compliance_rate'] = $total > 0 ? round(($compliant / $total) * 100, 1) : 0;
        }
        
        $this->load->view('dashboard', $data);
    }

    /**
     * Report form - Add/Edit report
     * @param int $id Report ID (optional for edit)
     */
    public function report($id = '')
    {
        if ($this->input->post()) {
            $this->handle_report_form($id);
            return;
        }

        // Check permissions
        if ($id == '') {
            if (!has_permission('tech_reports', '', 'create')) {
                access_denied('tech_reports');
            }
        } else {
            if (!has_permission('tech_reports', '', 'edit')) {
                access_denied('tech_reports');
            }
        }

        $data['report'] = null;
        if (is_numeric($id)) {
            $data['report'] = $this->tech_reports_model->get($id);
            if (!$data['report']) {
                show_404();
            }
        }

        $data['staff_members'] = $this->staff_model->get();
        $data['task_categories'] = $this->get_task_categories();
        $data['title'] = $id == '' ? _l('tech_report_add') : _l('tech_report_edit');

        $this->load->view('report_form', $data);
    }

    /**
     * Handle report form submission
     * @param int $id Report ID (optional for edit)
     */
    private function handle_report_form($id = '')
    {
        $data = [
            'staff_id' => $this->input->post('staff_id'),
            'report_date' => $this->input->post('report_date'),
            'task_description' => $this->input->post('task_description'),
            'task_category' => $this->input->post('task_category'),
            'hours_spent' => $this->input->post('hours_spent'),
            'status' => $this->input->post('status'),
            'assigned_by' => $this->input->post('assigned_by'),
            'related_task_id' => $this->input->post('related_task_id') ?: null,
            'notes' => $this->input->post('notes'),
        ];

        // Validation
        if (empty($data['staff_id']) || empty($data['report_date']) || empty($data['task_description'])) {
            set_alert('danger', _l('required_fields_missing'));
            redirect(admin_url('tech_reports/report/' . $id));
            return;
        }

        if ($id == '') {
            // Create
            if (!has_permission('tech_reports', '', 'create')) {
                access_denied('tech_reports');
            }

            $data['created_by'] = get_staff_user_id();
            $insert_id = $this->tech_reports_model->add($data);

            if ($insert_id) {
                // Handle followers
                $followers = $this->input->post('followers');
                if (!empty($followers) && is_array($followers)) {
                    foreach ($followers as $follower_id) {
                        $this->tech_reports_model->add_follower($insert_id, $follower_id);
                    }
                }

                set_alert('success', _l('tech_report_created_successfully'));
                redirect(admin_url('tech_reports'));
            } else {
                set_alert('danger', _l('tech_report_create_failed'));
                redirect(admin_url('tech_reports/report'));
            }
        } else {
            // Update
            if (!has_permission('tech_reports', '', 'edit')) {
                access_denied('tech_reports');
            }

            $success = $this->tech_reports_model->update($id, $data);

            if ($success) {
                // Update followers
                $followers = $this->input->post('followers');
                if (!empty($followers) && is_array($followers)) {
                    // Delete existing followers
                    $this->db->where('report_id', $id);
                    $this->db->delete(db_prefix() . 'tech_report_followers');

                    // Add new followers
                    foreach ($followers as $follower_id) {
                        $this->tech_reports_model->add_follower($id, $follower_id);
                    }
                }

                set_alert('success', _l('tech_report_updated_successfully'));
            } else {
                set_alert('warning', _l('tech_report_update_failed'));
            }

            redirect(admin_url('tech_reports'));
        }
    }

    /**
     * Delete report
     * @param int $id Report ID
     */
    public function delete($id)
    {
        if (!has_permission('tech_reports', '', 'delete')) {
            access_denied('tech_reports');
        }

        if (!is_numeric($id)) {
            redirect(admin_url('tech_reports'));
        }

        $success = $this->tech_reports_model->delete($id);

        if ($success) {
            set_alert('success', _l('tech_report_deleted_successfully'));
        } else {
            set_alert('warning', _l('tech_report_delete_failed'));
        }

        redirect(admin_url('tech_reports'));
    }

    /**
     * Compliance report view
     */
    public function compliance()
    {
        if (!has_permission('tech_reports', '', 'view')) {
            access_denied('tech_reports');
        }

        $data['title'] = _l('tech_reports_compliance');
        
        // Get date range from request or default to this month
        $start_date = $this->input->get('start_date') ?: date('Y-m-01');
        $end_date = $this->input->get('end_date') ?: date('Y-m-t');
        
        $data['start_date'] = $start_date;
        $data['end_date'] = $end_date;
        
        // Get compliance data
        $data['compliance_report'] = $this->tech_reports_model->get_compliance_report($start_date, $end_date);
        $data['tech_staff'] = $this->tech_reports_model->get_tech_staff();
        
        // Handle AJAX request for datatables
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('tech_reports', 'tables/compliance_table'));
        }

        $this->load->view('compliance_report', $data);
    }

    /**
     * Update compliance status (resolve non-compliance)
     */
    public function update_compliance()
    {
        if (!has_permission('tech_reports', '', 'edit')) {
            access_denied('tech_reports');
        }

        if ($this->input->post()) {
            $log_id = $this->input->post('log_id');
            $notes = $this->input->post('notes');
            
            $success = $this->tech_reports_model->update_compliance_status($log_id, $notes);
            
            if ($success) {
                set_alert('success', _l('tech_compliance_updated_successfully'));
            } else {
                set_alert('warning', _l('tech_compliance_update_failed'));
            }
        }

        redirect(admin_url('tech_reports/compliance'));
    }

    /**
     * Import Excel file
     */
    public function import()
    {
        if (!has_permission('tech_reports', '', 'create')) {
            access_denied('tech_reports');
        }

        if ($this->input->post()) {
            $this->handle_import();
            return;
        }

        $data['title'] = _l('tech_reports_import');
        $data['staff_members'] = $this->staff_model->get();
        
        $this->load->view('import', $data);
    }

    /**
     * Handle Excel import
     */
    private function handle_import()
    {
        if (!has_permission('tech_reports', '', 'create')) {
            access_denied('tech_reports');
        }

        // Load import library
        $this->load->library('tech_reports/Import_tech_reports');

        if (!empty($_FILES['file_excel']['name'])) {
            $upload_path = module_dir_path('tech_reports', 'uploads/');
            
            // Create uploads directory if not exists
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0755, true);
            }

            $config['upload_path'] = $upload_path;
            $config['allowed_types'] = 'xlsx|xls';
            $config['max_size'] = 5120; // 5MB
            $config['encrypt_name'] = true;

            $this->load->library('upload', $config);

            if ($this->upload->do_upload('file_excel')) {
                $upload_data = $this->upload->data();
                $file_path = $upload_data['full_path'];

                try {
                    $result = $this->import_tech_reports->import($file_path);

                    // Delete uploaded file
                    unlink($file_path);

                    if ($result['success']) {
                        set_alert('success', sprintf(
                            _l('tech_reports_import_success'),
                            $result['imported'],
                            $result['total']
                        ));
                        
                        if (!empty($result['errors'])) {
                            set_alert('warning', _l('tech_reports_import_with_errors') . '<br>' . implode('<br>', $result['errors']));
                        }
                    } else {
                        set_alert('danger', _l('tech_reports_import_failed') . ': ' . $result['message']);
                    }
                } catch (Exception $e) {
                    set_alert('danger', _l('tech_reports_import_failed') . ': ' . $e->getMessage());
                }
            } else {
                set_alert('danger', $this->upload->display_errors('', ''));
            }
        } else {
            set_alert('danger', _l('file_not_selected'));
        }

        redirect(admin_url('tech_reports/import'));
    }

    /**
     * Download import template
     */
    public function download_template()
    {
        if (!has_permission('tech_reports', '', 'view')) {
            access_denied('tech_reports');
        }

        $this->load->library('tech_reports/Import_tech_reports');
        $this->import_tech_reports->download_template();
    }

    /**
     * Manage tech department staff
     */
    public function manage_staff()
    {
        if (!has_permission('tech_reports', '', 'edit')) {
            access_denied('tech_reports');
        }

        if ($this->input->post()) {
            $this->handle_staff_update();
            return;
        }

        $data['title'] = _l('tech_reports_manage_staff');
        $data['tech_staff'] = $this->tech_reports_model->get_tech_staff();
        $data['all_staff'] = $this->staff_model->get();
        
        $this->load->view('manage_staff', $data);
    }

    /**
     * Handle staff update
     */
    private function handle_staff_update()
    {
        if (!has_permission('tech_reports', '', 'edit')) {
            access_denied('tech_reports');
        }

        $staff_ids = $this->input->post('staff_ids');
        $roles = $this->input->post('roles');
        $is_active = $this->input->post('is_active');

        if (empty($staff_ids) || !is_array($staff_ids)) {
            set_alert('warning', _l('no_staff_selected'));
            redirect(admin_url('tech_reports/manage_staff'));
            return;
        }

        $success_count = 0;
        foreach ($staff_ids as $staff_id) {
            $data = [
                'staff_id' => $staff_id,
                'role' => isset($roles[$staff_id]) ? $roles[$staff_id] : 'member',
                'is_active' => isset($is_active[$staff_id]) ? 1 : 0,
            ];

            if ($this->tech_reports_model->add_tech_staff($data)) {
                $success_count++;
            }
        }

        if ($success_count > 0) {
            set_alert('success', sprintf(_l('tech_staff_updated_successfully'), $success_count));
        } else {
            set_alert('warning', _l('tech_staff_update_failed'));
        }

        redirect(admin_url('tech_reports/manage_staff'));
    }

    /**
     * Get task categories
     * @return array
     */
    private function get_task_categories()
    {
        return [
            'development' => _l('tech_category_development'),
            'bug_fix' => _l('tech_category_bug_fix'),
            'maintenance' => _l('tech_category_maintenance'),
            'support' => _l('tech_category_support'),
            'meeting' => _l('tech_category_meeting'),
            'research' => _l('tech_category_research'),
            'documentation' => _l('tech_category_documentation'),
            'other' => _l('tech_category_other'),
        ];
    }

    /**
     * Dashboard widget - for homepage
     */
    public function dashboard_widget()
    {
        if (!has_permission('tech_reports', '', 'view')) {
            return;
        }

        $data['today_reports'] = $this->tech_reports_model->get('', [
            'report_date' => date('Y-m-d')
        ]);

        $data['my_reports'] = $this->tech_reports_model->get('', [
            'staff_id' => get_staff_user_id(),
            'report_date' => date('Y-m-d')
        ]);

        $data['pending_compliance'] = [];
        $compliance_data = $this->tech_reports_model->get_compliance_report(date('Y-m-01'), date('Y-m-d'));
        
        foreach ($compliance_data as $comp) {
            if ($comp['compliance_rate'] < 80) {
                $data['pending_compliance'][] = $comp;
            }
        }

        $this->load->view('dashboard_widget', $data);
    }

    /**
     * Export reports to Excel
     */
    public function export()
    {
        if (!has_permission('tech_reports', '', 'view')) {
            access_denied('tech_reports');
        }

        $start_date = $this->input->get('start_date') ?: date('Y-m-01');
        $end_date = $this->input->get('end_date') ?: date('Y-m-t');
        $staff_id = $this->input->get('staff_id') ?: '';

        $filters = [
            'date_from' => $start_date,
            'date_to' => $end_date,
        ];

        if ($staff_id) {
            $filters['staff_id'] = $staff_id;
        }

        $reports = $this->tech_reports_model->get('', $filters);

        // Load PHPSpreadsheet
        require_once(APPPATH . 'third_party/PHPSpreadsheet/vendor/autoload.php');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $headers = [
            _l('tech_report_id'),
            _l('tech_report_staff'),
            _l('tech_report_date'),
            _l('tech_report_task'),
            _l('tech_report_category'),
            _l('tech_report_hours'),
            _l('tech_report_status'),
            _l('tech_report_assigned_by'),
            _l('tech_report_notes'),
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Data
        $row = 2;
        foreach ($reports as $report) {
            $sheet->setCellValue('A' . $row, $report['id']);
            $sheet->setCellValue('B' . $row, $report['staff_name']);
            $sheet->setCellValue('C' . $row, $report['report_date']);
            $sheet->setCellValue('D' . $row, $report['task_description']);
            $sheet->setCellValue('E' . $row, $report['task_category']);
            $sheet->setCellValue('F' . $row, $report['hours_spent']);
            $sheet->setCellValue('G' . $row, $report['status']);
            $sheet->setCellValue('H' . $row, $report['assigned_by_name']);
            $sheet->setCellValue('I' . $row, $report['notes']);
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Output
        $filename = 'tech_reports_' . date('Y-m-d_His') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
