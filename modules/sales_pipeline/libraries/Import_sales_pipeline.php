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
     * @return array ['success' => bool, 'imported' => int, 'skipped' => int, 'message' => string, 'log_id' => int]
     */
    public function process($file, $staff_id)
    {
        $result = [
            'success'  => false,
            'imported' => 0,
            'skipped'  => 0,
            'message'  => '',
            'log_id'   => null,
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

        // Tạo log import batch
        $log_id = $this->CI->sales_pipeline_model->log_import_batch([
            'file_name'     => $file['name'],
            'file_path'     => $temp_file,
            'uploaded_by'   => $staff_id,
            'import_status' => 'processing',
        ]);

        try {
            $rows = $this->read_excel($temp_file, $ext);

            if (empty($rows)) {
                $result['message'] = 'File Excel không có dữ liệu hợp lệ.';
                $this->CI->sales_pipeline_model->update_import_log($log_id, [
                    'import_status'  => 'failed',
                    'error_message'  => $result['message'],
                    'rows_imported'  => 0,
                    'rows_skipped'   => 0,
                ]);
                @unlink($temp_file);
                return $result;
            }

            $imported = 0;
            $updated_count = 0;
            $skipped = 0;
            
            foreach ($rows as $row) {
                $data = $this->map_row_to_deal($row, $staff_id, $log_id);
                if ($data) {
                    $year = date('Y', strtotime($data['deal_date']));
                    $existing_id = $this->CI->sales_pipeline_model->find_existing_deal($staff_id, $data['customer_name'], $data['deal_name'], $year);
                    
                    if ($existing_id) {
                        $existing_deal = $this->CI->sales_pipeline_model->get($existing_id);
                        
                        // Bỏ qua nếu deal đã đóng (Won hoặc Lost)
                        if (isset($existing_deal['is_won']) && $existing_deal['is_won'] == 1) {
                            $skipped++;
                            continue;
                        }
                        if (isset($existing_deal['is_lost']) && $existing_deal['is_lost'] == 1) {
                            $skipped++;
                            continue;
                        }
                        
                        // Loại bỏ các trường định danh khỏi bản cập nhật
                        unset($data['staff_id']);
                        unset($data['customer_name']);
                        unset($data['deal_name']);
                        unset($data['import_batch_id']); // Giữ log ID của lần đầu tạo
                        
                        $updated = $this->CI->sales_pipeline_model->update($data, $existing_id);
                        if ($updated) {
                            $updated_count++;
                            if (!empty($data['notes'])) {
                                $this->process_activity_notes($existing_id, $data['notes']);
                            }
                        } else {
                            $skipped++;
                        }
                    } else {
                        // Thêm mới
                        $insert_id = $this->CI->sales_pipeline_model->add($data);
                        if ($insert_id) {
                            $imported++;
                            if (!empty($data['notes'])) {
                                $this->process_activity_notes($insert_id, $data['notes']);
                            }
                        } else {
                            $skipped++;
                        }
                    }
                } else {
                    $skipped++;
                }
            }

            // Cập nhật log thành công
            $this->CI->sales_pipeline_model->update_import_log($log_id, [
                'import_status'  => 'completed',
                'rows_imported'  => $imported + $updated_count,
                'rows_skipped'   => $skipped,
            ]);

            $result['success']  = true;
            $result['imported'] = $imported;
            $result['updated']  = $updated_count;
            $result['skipped']  = $skipped;
            $result['log_id']   = $log_id;
            $result['message']  = 'Import thành công: Thêm mới ' . $imported . ', Cập nhật ' . $updated_count . ', Bỏ qua ' . $skipped . ' dòng.';

        } catch (Exception $e) {
            $result['message'] = 'Lỗi đọc file: ' . $e->getMessage();
            
            // Cập nhật log lỗi
            $this->CI->sales_pipeline_model->update_import_log($log_id, [
                'import_status'  => 'failed',
                'error_message'  => $e->getMessage(),
                'rows_imported'  => $result['imported'],
                'rows_skipped'   => $result['skipped'],
            ]);
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
     * Map 1 row Excel → deal data (với các trường mở rộng)
     *
     * @param array $row
     * @param int $staff_id
     * @param int $log_id Import batch ID
     * @return array|false
     */
    private function map_row_to_deal($row, $staff_id, $log_id)
    {
        // Bỏ qua row header, row tổng, row rỗng
        if (count($row) < 5) {
            return false;
        }

        // Col 2 = Tên KH, Col 3 = Mô tả deal — phải có
        $customer_name = isset($row[2]) ? trim($row[2]) : '';
        $deal_name     = isset($row[3]) ? trim($row[3]) : '';

        if (empty($customer_name)) {
            return false;
        }

        // Bỏ qua row tổng (QUÍ 1, THÁNG 4-6, 6 THÁNG, etc.)
        $skip_keywords = ['QUÍ', 'THÁNG', 'Doanh số', '6 THÁNG', '12 THÁNG', 'Các khách hàng', 'TỔNG'];
        foreach ($skip_keywords as $kw) {
            if (stripos($customer_name, $kw) !== false || stripos($deal_name, $kw) !== false) {
                return false;
            }
        }

        // Col 4 = Doanh số
        $deal_value = isset($row[4]) ? floatval($row[4]) : 0;
        
        // Col 5 = % Lợi nhuận (có thể là 10 hoặc 0.1 - chuẩn hóa về decimal)
        $profit_margin = isset($row[5]) ? floatval($row[5]) : 0;
        if ($profit_margin > 1) {
            $profit_margin = $profit_margin / 100; // 10% → 0.1
        }

        // Col 1 = Ngày
        $deal_date = $this->parse_date($row[1] ?? null);
        if (!$deal_date) {
            $deal_date = date('Y-m-d'); // Mặc định ngày hôm nay
        }

        // Col 7 = Ký HĐ, Col 8 = Xuất HĐ
        $contract_signed = (isset($row[7]) && strtolower(trim($row[7])) === 'x') ? 1 : 0;
        $invoice_issued  = (isset($row[8]) && strtolower(trim($row[8])) === 'x') ? 1 : 0;

        // Col 9 = Ghi chú → lưu vào notes và phân tích
        $notes = isset($row[9]) ? trim($row[9]) : '';

        // Xác định các trường mở rộng
        $confidence_level = $this->detect_confidence_level($notes, $contract_signed, $invoice_issued);
        $deal_phase = $this->detect_deal_phase($deal_value, $contract_signed, $invoice_issued, $notes);
        $reporting_period = $this->extract_reporting_period($deal_date, $notes);
        
        // Xác định trạng thái từ ghi chú
        $status = $this->detect_status($notes, $contract_signed, $invoice_issued);

        // Phân biệt Deal vs Prospect
        $is_prospect = ($deal_value <= 0 || empty($deal_name));

        $data = [
            'staff_id'            => $staff_id,
            'customer_name'       => $customer_name,
            'deal_name'           => $is_prospect ? null : $deal_name,
            'deal_value'          => $is_prospect ? 0 : $deal_value,
            'profit_margin'       => $profit_margin,
            'deal_date'           => $deal_date,
            'status'              => $status,
            'contract_signed'     => $contract_signed,
            'invoice_issued'      => $invoice_issued,
            'reminder_enabled'    => 1,
            'reminder_frequency'  => 7,
            'confidence_level'    => $confidence_level,
            'deal_phase'          => $deal_phase,
            'reporting_period'    => $reporting_period,
            'import_batch_id'     => $log_id,
            'notes'               => $notes,
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
     * Xác định mức độ tin cậy (confidence_level)
     *
     * @param string $notes
     * @param int $contract_signed
     * @param int $invoice_issued
     * @return string prospect|tracking|confirmed
     */
    private function detect_confidence_level($notes, $contract_signed, $invoice_issued)
    {
        $notes_lower = mb_strtolower($notes, 'UTF-8');

        // CHẮC CHẮN RA PO / Đã ký HĐ → confirmed
        if ($contract_signed || $invoice_issued || 
            strpos($notes_lower, 'chắc chắn') !== false || 
            strpos($notes_lower, 'đã ký') !== false ||
            strpos($notes_lower, 'đã duyệt') !== false) {
            return 'confirmed';
        }

        // CẦN THEO DÕI / Đang duyệt → tracking
        if (strpos($notes_lower, 'cần theo dõi') !== false || 
            strpos($notes_lower, 'đang theo dõi') !== false ||
            strpos($notes_lower, 'đang trình') !== false ||
            strpos($notes_lower, 'đang duyệt') !== false) {
            return 'tracking';
        }

        // Không có giá trị hoặc chỉ tư vấn → prospect
        return 'prospect';
    }

    /**
     * Xác định giai đoạn deal (deal_phase)
     *
     * @param float $deal_value
     * @param int $contract_signed
     * @param int $invoice_issued
     * @param string $notes
     * @return string lead|active|won|lost
     */
    private function detect_deal_phase($deal_value, $contract_signed, $invoice_issued, $notes)
    {
        $notes_lower = mb_strtolower($notes, 'UTF-8');

        // Won: Đã xuất HĐ, triển khai thành công
        if ($invoice_issued || 
            strpos($notes_lower, 'nghiệm thu') !== false || 
            strpos($notes_lower, 'đã triển khai') !== false ||
            strpos($notes_lower, 'hoàn thành') !== false) {
            return 'won';
        }

        // Lost: KH chọn NCC khác, không phê duyệt, vượt ngân sách
        if (strpos($notes_lower, 'chọn ncc khác') !== false || 
            strpos($notes_lower, 'không hợp tác') !== false ||
            strpos($notes_lower, 'không phê duyệt') !== false ||
            strpos($notes_lower, 'vượt ngân sách') !== false ||
            strpos($notes_lower, 'hủy') !== false) {
            return 'lost';
        }

        // Active: Có giá trị, đang chào giá, duyệt, ký HĐ
        if ($deal_value > 0 && ($contract_signed || 
            strpos($notes_lower, 'đang báo giá') !== false ||
            strpos($notes_lower, 'đã gửi') !== false ||
            strpos($notes_lower, 'đang duyệt') !== false ||
            strpos($notes_lower, 'đã ký') !== false)) {
            return 'active';
        }

        // Lead: Prospect, chưa có giá trị cụ thể
        return 'lead';
    }

    /**
     * Trích xuất reporting_period từ ngày hoặc ghi chú
     *
     * @param string $date Y-m-d format
     * @param string $notes
     * @return string Q1-2026, Q2-2026, etc.
     */
    private function extract_reporting_period($date, $notes)
    {
        // Ưu tiên từ ghi chú nếu có đề cập quý/tháng
        $notes_upper = mb_strtoupper($notes, 'UTF-8');
        
        // Tìm pattern "QUÍ X" hoặc "Q X"
        if (preg_match('/(QUÍ|Q)\s*([1-4])/u', $notes_upper, $matches)) {
            $quarter = $matches[2];
            $year = date('Y', strtotime($date));
            return 'Q' . $quarter . '-' . $year;
        }

        // Tính từ ngày
        $timestamp = strtotime($date);
        $month = (int)date('n', $timestamp);
        $year = date('Y', $timestamp);
        
        $quarter = ceil($month / 3);
        return 'Q' . $quarter . '-' . $year;
    }

    /**
     * Xử lý ghi chú có ký tự "→" thành các activity riêng
     *
     * @param int $pipeline_id
     * @param string $notes
     */
    private function process_activity_notes($pipeline_id, $notes)
    {
        // Tách các ghi chú bằng dấu "→"
        $activities = preg_split('/→|->/', $notes);
        
        foreach ($activities as $activity) {
            $activity = trim($activity);
            if (!empty($activity) && strlen($activity) > 3) {
                // Thêm activity log riêng
                $this->CI->sales_pipeline_model->add_activity(
                    $pipeline_id,
                    $activity
                );
            }
        }
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
