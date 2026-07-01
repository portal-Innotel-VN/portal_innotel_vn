<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Import Tech Reports Library
 * Handles Excel import for tech daily reports using PHPSpreadsheet
 */
class Import_tech_reports
{
    protected $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->model('tech_reports_model');
    }

    /**
     * Import tech reports from Excel file
     * @param string $file_path Path to Excel file
     * @return array Result with success status, imported count, and errors
     */
    public function import($file_path)
    {
        if (!file_exists($file_path)) {
            return [
                'success' => false,
                'message' => 'File not found',
                'imported' => 0,
                'total' => 0,
                'errors' => []
            ];
        }

        try {
            // Load PHPSpreadsheet
            require_once(APPPATH . 'third_party/PHPSpreadsheet/vendor/autoload.php');

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $rows = $worksheet->toArray();
            
            // Validate headers (first row)
            if (empty($rows) || count($rows) < 2) {
                return [
                    'success' => false,
                    'message' => 'File is empty or invalid',
                    'imported' => 0,
                    'total' => 0,
                    'errors' => []
                ];
            }

            $headers = $rows[0];
            $expected_headers = [
                'staff_id',
                'report_date',
                'task_description',
                'task_category',
                'hours_spent',
                'status',
                'assigned_by',
                'related_task_id',
                'notes'
            ];

            // Validate headers (flexible matching)
            $header_map = $this->map_headers($headers, $expected_headers);
            if (!$header_map) {
                return [
                    'success' => false,
                    'message' => 'Invalid file format. Please use the template.',
                    'imported' => 0,
                    'total' => 0,
                    'errors' => []
                ];
            }

            // Process data rows
            $imported = 0;
            $total = count($rows) - 1; // Exclude header
            $errors = [];
            $created_by = get_staff_user_id();

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $row_number = $i + 1;

                try {
                    // Skip empty rows
                    if ($this->is_empty_row($row)) {
                        $total--;
                        continue;
                    }

                    // Map data using header mapping
                    $data = [];
                    foreach ($header_map as $field => $col_index) {
                        $data[$field] = isset($row[$col_index]) ? trim($row[$col_index]) : '';
                    }

                    // Validate required fields
                    $validation_errors = $this->validate_row($data, $row_number);
                    if (!empty($validation_errors)) {
                        $errors = array_merge($errors, $validation_errors);
                        continue;
                    }

                    // Prepare data for insertion
                    $insert_data = [
                        'staff_id' => $data['staff_id'],
                        'report_date' => $this->parse_date($data['report_date']),
                        'task_description' => $data['task_description'],
                        'task_category' => $data['task_category'],
                        'hours_spent' => floatval($data['hours_spent']),
                        'status' => !empty($data['status']) ? $data['status'] : 'in_progress',
                        'assigned_by' => !empty($data['assigned_by']) ? $data['assigned_by'] : null,
                        'related_task_id' => !empty($data['related_task_id']) ? $data['related_task_id'] : null,
                        'notes' => !empty($data['notes']) ? $data['notes'] : null,
                        'created_by' => $created_by,
                    ];

                    // Insert report
                    $result = $this->ci->tech_reports_model->add($insert_data);
                    
                    if ($result) {
                        $imported++;
                    } else {
                        $errors[] = "Row {$row_number}: Failed to insert report";
                    }

                } catch (Exception $e) {
                    $errors[] = "Row {$row_number}: " . $e->getMessage();
                }
            }

            return [
                'success' => true,
                'message' => 'Import completed',
                'imported' => $imported,
                'total' => $total,
                'errors' => $errors
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'imported' => 0,
                'total' => 0,
                'errors' => []
            ];
        }
    }

    /**
     * Map headers to column indices
     * @param array $headers Headers from Excel
     * @param array $expected Expected field names
     * @return array|false Header map or false if invalid
     */
    private function map_headers($headers, $expected)
    {
        $map = [];
        $found = 0;

        foreach ($expected as $field) {
            for ($i = 0; $i < count($headers); $i++) {
                $header = strtolower(trim($headers[$i]));
                $field_lower = strtolower(str_replace('_', ' ', $field));
                
                // Flexible matching
                if ($header == $field || $header == $field_lower || 
                    str_replace('_', '', $header) == str_replace('_', '', $field)) {
                    $map[$field] = $i;
                    $found++;
                    break;
                }
            }
        }

        // Require at least the mandatory fields
        $mandatory = ['staff_id', 'report_date', 'task_description'];
        foreach ($mandatory as $field) {
            if (!isset($map[$field])) {
                return false;
            }
        }

        return $map;
    }

    /**
     * Check if row is empty
     * @param array $row Row data
     * @return bool
     */
    private function is_empty_row($row)
    {
        foreach ($row as $cell) {
            if (!empty(trim($cell))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Validate row data
     * @param array $data Row data
     * @param int $row_number Row number for error messages
     * @return array Validation errors
     */
    private function validate_row($data, $row_number)
    {
        $errors = [];

        // Required fields
        if (empty($data['staff_id'])) {
            $errors[] = "Row {$row_number}: Staff ID is required";
        } elseif (!$this->staff_exists($data['staff_id'])) {
            $errors[] = "Row {$row_number}: Staff ID {$data['staff_id']} not found";
        }

        if (empty($data['report_date'])) {
            $errors[] = "Row {$row_number}: Report date is required";
        } elseif (!$this->is_valid_date($data['report_date'])) {
            $errors[] = "Row {$row_number}: Invalid date format (use YYYY-MM-DD)";
        }

        if (empty($data['task_description'])) {
            $errors[] = "Row {$row_number}: Task description is required";
        }

        // Optional but validated if present
        if (!empty($data['task_category'])) {
            $valid_categories = ['development', 'bug_fix', 'maintenance', 'support', 'meeting', 'research', 'documentation', 'other'];
            if (!in_array($data['task_category'], $valid_categories)) {
                $errors[] = "Row {$row_number}: Invalid task category";
            }
        }

        if (!empty($data['hours_spent'])) {
            if (!is_numeric($data['hours_spent']) || floatval($data['hours_spent']) < 0) {
                $errors[] = "Row {$row_number}: Invalid hours spent";
            }
        }

        if (!empty($data['status'])) {
            $valid_statuses = ['not_started', 'in_progress', 'completed', 'blocked'];
            if (!in_array($data['status'], $valid_statuses)) {
                $errors[] = "Row {$row_number}: Invalid status";
            }
        }

        if (!empty($data['assigned_by']) && !$this->staff_exists($data['assigned_by'])) {
            $errors[] = "Row {$row_number}: Assigned by staff ID {$data['assigned_by']} not found";
        }

        return $errors;
    }

    /**
     * Check if staff exists
     * @param int $staff_id Staff ID
     * @return bool
     */
    private function staff_exists($staff_id)
    {
        return $this->ci->db->where('staffid', $staff_id)
                           ->count_all_results(db_prefix() . 'staff') > 0;
    }

    /**
     * Validate date format
     * @param string $date Date string
     * @return bool
     */
    private function is_valid_date($date)
    {
        $parsed = $this->parse_date($date);
        return $parsed !== false;
    }

    /**
     * Parse date from various formats
     * @param string $date Date string
     * @return string|false Date in Y-m-d format or false
     */
    private function parse_date($date)
    {
        // Try Y-m-d format
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if ($d && $d->format('Y-m-d') === $date) {
            return $date;
        }

        // Try d/m/Y format
        $d = DateTime::createFromFormat('d/m/Y', $date);
        if ($d) {
            return $d->format('Y-m-d');
        }

        // Try m/d/Y format
        $d = DateTime::createFromFormat('m/d/Y', $date);
        if ($d) {
            return $d->format('Y-m-d');
        }

        // Try Excel serial date (numeric)
        if (is_numeric($date)) {
            $unix_date = ($date - 25569) * 86400;
            return date('Y-m-d', $unix_date);
        }

        return false;
    }

    /**
     * Download import template
     */
    public function download_template()
    {
        require_once(APPPATH . 'third_party/PHPSpreadsheet/vendor/autoload.php');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = [
            'staff_id' => 'Staff ID',
            'report_date' => 'Report Date (YYYY-MM-DD)',
            'task_description' => 'Task Description',
            'task_category' => 'Task Category',
            'hours_spent' => 'Hours Spent',
            'status' => 'Status',
            'assigned_by' => 'Assigned By (Staff ID)',
            'related_task_id' => 'Related Task ID',
            'notes' => 'Notes'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()
                  ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                  ->getStartColor()->setARGB('FFE0E0E0');
            $col++;
        }

        // Add sample data
        $sheet->setCellValue('A2', '1');
        $sheet->setCellValue('B2', date('Y-m-d'));
        $sheet->setCellValue('C2', 'Example: Fix login bug');
        $sheet->setCellValue('D2', 'bug_fix');
        $sheet->setCellValue('E2', '3.5');
        $sheet->setCellValue('F2', 'completed');
        $sheet->setCellValue('G2', '2');
        $sheet->setCellValue('H2', '');
        $sheet->setCellValue('I2', 'Fixed authentication issue');

        // Add instructions
        $instructions_row = 4;
        $sheet->setCellValue('A' . $instructions_row, 'Instructions:');
        $sheet->getStyle('A' . $instructions_row)->getFont()->setBold(true);
        
        $instructions = [
            'Task Categories: development, bug_fix, maintenance, support, meeting, research, documentation, other',
            'Status: not_started, in_progress, completed, blocked',
            'Date Format: YYYY-MM-DD (e.g., ' . date('Y-m-d') . ')',
            'Staff ID and Assigned By must be valid staff IDs from your system',
            'Required fields: staff_id, report_date, task_description',
        ];

        $row = $instructions_row + 1;
        foreach ($instructions as $instruction) {
            $sheet->setCellValue('A' . $row, $instruction);
            $sheet->mergeCells('A' . $row . ':I' . $row);
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Output
        $filename = 'tech_reports_template.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
