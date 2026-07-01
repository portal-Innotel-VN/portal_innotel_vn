<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Import dữ liệu Excel báo cáo kinh doanh vào Sales Pipeline
 *
 * Cấu trúc file Excel (10 cột theo đúng file báo cáo phòng KD):
 * Col 0: STT
 * Col 1: Ngày (date)
 * Col 2: Tên công ty KH
 * Col 3: Mô tả sản phẩm/dịch vụ (deal name)
 * Col 4: Doanh số (VNĐ)
 * Col 5: % Lợi nhuận
 * Col 6: Lợi nhuận (tự tính, có thể bỏ qua)
 * Col 7: Ký HĐ (x)
 * Col 8: Xuất HĐ (x)
 * Col 9: Ghi chú / Trạng thái
 */
class Import_sales_pipeline
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('sales_pipeline/sales_pipeline_model');
    }

    /**
     * Xử lý file upload và import vào DB
     *
     * @param array $file $_FILES['import_file']
     * @param int $staff_id ID nhân viên phụ trách
     * @return array ['success' => bool, 'imported' => int, 'message' => string]
     */
    public function process($file, $staff_id)
    {
        $result = [
            'success'  => false,
            'imported' => 0,
            'message'  => '',
        ];

        // Validate file type
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xls', 'xlsx'])) {
            $result['message'] = 'Chỉ hỗ trợ file .xls hoặc .xlsx';
            return $result;
        }

        // Upload file tạm
        $upload_dir = get_upload_path_by_type('sales_pipeline') ?: TEMP_FOLDER;
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $temp_file = rtrim($upload_dir, '/') . '/import_' . time() . '.' . $ext;
        $moved = false;
        if (is_uploaded_file($file['tmp_name'])) {
            $moved = move_uploaded_file($file['tmp_name'], $temp_file);
        } else {
            $moved = copy($file['tmp_name'], $temp_file);
        }
        if (!$moved) {
            $result['message'] = 'Không thể upload file. Vui lòng thử lại.';
            return $result;
        }

        try {
            $rows = $this->read_excel($temp_file, $ext);

            if (empty($rows)) {
                $result['message'] = 'File Excel không có dữ liệu hợp lệ.';
                @unlink($temp_file);
                return $result;
            }

            $imported = 0;
            foreach ($rows as $row) {
                $data = $this->map_row_to_deal($row, $staff_id);
                if ($data) {
                    $insert_id = $this->CI->sales_pipeline_model->add($data);
                    if ($insert_id) {
                        $imported++;
                    }
                }
            }

            $result['success']  = true;
            $result['imported'] = $imported;
            $result['message']  = 'Import thành công ' . $imported . ' deal.';

        } catch (Exception $e) {
            $result['message'] = 'Lỗi đọc file: ' . $e->getMessage();
        }

        // Xóa file tạm
        @unlink($temp_file);

        return $result;
    }

    /**
     * Đọc file Excel thành array rows
     *
     * @param string $file_path
     * @param string $ext
     * @return array
     */
    /**
     * Đọc file Excel thành array rows sử dụng Python helper
     *
     * @param string $file_path
     * @param string $ext
     * @return array
     */
    private function read_excel($file_path, $ext)
    {
        $python_script = __DIR__ . '/parse_excel.py';
        
        // Tìm đường dẫn thực thi python3 có sẵn trên macOS có cài đặt thư viện
        $python_path = 'python3';
        $common_paths = [
            '/Users/dieterhoang/.pyenv/versions/3.12.9/bin/python3',
            '/usr/local/bin/python3',
            '/Library/Frameworks/Python.framework/Versions/3.14/bin/python3',
            '/usr/bin/python3',
            '/opt/homebrew/bin/python3',
            '/Users/dieterhoang/.pyenv/shims/python3',
        ];
        foreach ($common_paths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $python_path = $path;
                break;
            }
        }

        // Escape parameters for CLI security
        $cmd = escapeshellcmd($python_path) . ' ' . escapeshellarg($python_script) . ' ' . escapeshellarg($file_path) . ' 2>&1';
        
        $output = shell_exec($cmd);
        
        if (empty($output)) {
            log_activity('Import_sales_pipeline - Python output is empty');
            return [];
        }
        
        $data = json_decode($output, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_activity('Import_sales_pipeline - Non-JSON output (could be python/cmd error): ' . $output);
            return [];
        }
        
        if (isset($data['error'])) {
            log_activity('Import_sales_pipeline - Python error: ' . $data['error']);
            return [];
        }
        
        return is_array($data) ? $data : [];
    }

    /**
     * Map 1 row Excel → deal data
     *
     * @param array $row
     * @param int $staff_id
     * @return array|false
     */
    private function map_row_to_deal($row, $staff_id)
    {
        // Bỏ qua row header, row tổng, row rỗng
        if (count($row) < 5) {
            return false;
        }

        // Col 2 = Tên KH, Col 3 = Mô tả deal — phải có
        $customer_name = isset($row[2]) ? trim($row[2]) : '';
        $deal_name     = isset($row[3]) ? trim($row[3]) : '';

        if (empty($customer_name) || empty($deal_name)) {
            return false;
        }

        // Bỏ qua row tổng (QUÍ 1, THÁNG 4-6, 6 THÁNG, etc.)
        $skip_keywords = ['QUÍ', 'THÁNG', 'Doanh số', '6 THÁNG', '12 THÁNG', 'Các khách hàng'];
        foreach ($skip_keywords as $kw) {
            if (stripos($customer_name, $kw) !== false || stripos($deal_name, $kw) !== false) {
                return false;
            }
        }

        // Col 4 = Doanh số
        $deal_value = isset($row[4]) ? floatval($row[4]) : 0;
        if ($deal_value <= 0) {
            return false;
        }

        // Col 5 = % Lợi nhuận
        $profit_margin = isset($row[5]) ? floatval($row[5]) : 0;

        // Col 1 = Ngày
        $expected_close_date = $this->parse_date($row[1] ?? null);
        if (!$expected_close_date) {
            $expected_close_date = date('Y-m-d'); // Mặc định ngày hôm nay
        }

        // Col 7 = Ký HĐ, Col 8 = Xuất HĐ
        $contract_signed = (isset($row[7]) && strtolower(trim($row[7])) === 'x') ? 1 : 0;
        $invoice_issued  = (isset($row[8]) && strtolower(trim($row[8])) === 'x') ? 1 : 0;

        // Col 9 = Ghi chú → dùng làm activity description
        $notes = isset($row[9]) ? trim($row[9]) : '';

        // Xác định trạng thái từ ghi chú
        $status = $this->detect_status($notes, $contract_signed, $invoice_issued);

        $data = [
            'staff_id'            => $staff_id,
            'customer_name'       => $customer_name,
            'deal_name'           => $deal_name,
            'deal_value'          => $deal_value,
            'profit_margin'       => $profit_margin,
            'expected_close_date' => $expected_close_date,
            'status'              => $status,
            'contract_signed'     => $contract_signed,
            'invoice_issued'      => $invoice_issued,
            'reminder_enabled'    => 1,
            'reminder_frequency'  => 7,
        ];

        // Ghi chú → activity
        if (!empty($notes)) {
            $data['activity_description'] = $notes;
        }

        return $data;
    }

    /**
     * Phân tích ghi chú để tự động xác định trạng thái deal
     *
     * @param string $notes
     * @param int $contract_signed
     * @param int $invoice_issued
     * @return int Status ID
     */
    private function detect_status($notes, $contract_signed, $invoice_issued)
    {
        $notes_lower = mb_strtolower($notes, 'UTF-8');

        // Ưu tiên trạng thái kết thúc trước
        if ($invoice_issued) {
            return 6; // Đã xuất hóa đơn
        }
        if ($contract_signed || strpos($notes_lower, 'đã ký h') !== false || strpos($notes_lower, 'đã ký hd') !== false) {
            return 5; // Đã ký hợp đồng
        }
        if (strpos($notes_lower, 'nghiệm thu') !== false || strpos($notes_lower, 'đã triển khai') !== false) {
            return 7; // Đã triển khai
        }
        if (strpos($notes_lower, 'chọn ncc khác') !== false || strpos($notes_lower, 'không hợp tác') !== false) {
            return 9; // KH chọn NCC khác
        }
        if (strpos($notes_lower, 'không phê duyệt') !== false) {
            return 10; // Không phê duyệt
        }
        if (strpos($notes_lower, 'vượt ngân sách') !== false) {
            return 11; // Vượt ngân sách
        }
        if (strpos($notes_lower, 'tạm ngưng') !== false) {
            return 8; // Tạm ngưng
        }
        if (strpos($notes_lower, 'đang trình') !== false || strpos($notes_lower, 'đang duyệt') !== false) {
            return 4; // KH đang duyệt
        }
        if (strpos($notes_lower, 'đã gửi') !== false) {
            return 3; // Đã gửi báo giá
        }
        if (strpos($notes_lower, 'đang chào giá') !== false || strpos($notes_lower, 'đang báo giá') !== false) {
            return 2; // Đang báo giá
        }
        if (strpos($notes_lower, 'tư vấn') !== false || strpos($notes_lower, 'bom') !== false) {
            return 1; // Đang tư vấn
        }

        return 1; // Mặc định: Đang tư vấn
    }

    /**
     * Parse date từ Excel
     *
     * @param mixed $value
     * @return string|false Y-m-d format
     */
    private function parse_date($value)
    {
        if (empty($value)) {
            return false;
        }

        // Nếu là số (Excel serial date)
        if (is_numeric($value)) {
            $unix = ($value - 25569) * 86400;
            return date('Y-m-d', $unix);
        }

        // Nếu là string dạng dd/mm/yyyy
        if (is_string($value)) {
            $parts = explode('/', $value);
            if (count($parts) === 3) {
                return $parts[2] . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            }

            $timestamp = strtotime($value);
            if ($timestamp) {
                return date('Y-m-d', $timestamp);
            }
        }

        return false;
    }
}
