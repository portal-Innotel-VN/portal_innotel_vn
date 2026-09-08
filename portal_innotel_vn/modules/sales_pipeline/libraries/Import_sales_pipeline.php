<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Import dữ liệu Excel báo cáo kinh doanh vào Sales Pipeline
 *
 * Cấu trúc file Excel Template chuẩn MỚI (8 cột A→H):
 *   Col 0 (A): Ngày tạo       — Date YYYY-MM-DD (đã chuẩn hóa bởi parse_excel.py)
 *   Col 1 (B): Tên công ty KH — String, bắt buộc
 *   Col 2 (C): Mô tả SP/DV    — String, nullable
 *   Col 3 (D): Giá bán VNĐ    — Integer, bắt buộc
 *   Col 4 (E): Giá nhập VNĐ   — Integer, nullable (QUAN TRỌNG: Cho phép NULL nếu Sale không biết)
 *   Col 5 (F): Lợi nhuận VNĐ  — SKIP (công thức Excel — Backend tự tính runtime)
 *   Col 6 (G): Trạng thái     — String dropdown (mapping → status_id)
 *   Col 7 (H): Ghi chú        — String, nullable
 *
 * Dữ liệu thực đọc từ dòng 7-56 (dòng 1-6 là tiêu đề + ví dụ mẫu).
 * 
 * MÔ HÌNH MỚI: Lợi nhuận = Giá bán - Giá nhập (tính runtime, không lưu DB)
 */
class Import_sales_pipeline
{
    protected $CI;

    /**
     * [BUG FIX #2] Giới hạn file tối đa 10MB (bytes) để chặn Zip Bomb / tràn bộ nhớ.
     */
    const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    /**
     * Bảng mapping: Tên trạng thái dropdown → status_id trong tblsales_pipeline_statuses.
     * [BUG FIX #4] Key được chuẩn hóa lowercase bằng mb_strtolower() trong buildStatusMap()
     * để so sánh case-insensitive (VD: "đang tư vấn" == "Đang tư vấn").
     */
    private $status_map = [];

    // contract_signed và invoice_issued mặc định = 0 khi import.
    // Nhân viên kinh doanh sẽ tự cập nhật trên form deal.

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('sales_pipeline/sales_pipeline_model');

        // [BUG FIX #4 + #5] Build status_map động từ DB thay vì hardcode
        $this->buildStatusMap();
    }

    /**
     * [BUG FIX #4 + #5] Xây dựng bảng mapping trạng thái từ DB.
     * Key = mb_strtolower(tên trạng thái) để so sánh case-insensitive.
     * Value = status_id.
     */
    private function buildStatusMap()
    {
        $statuses = $this->CI->sales_pipeline_model->get_statuses();
        $this->status_map = [];
        foreach ($statuses as $status) {
            // Chuẩn hóa key lowercase để map_status_to_id() so sánh case-insensitive
            $key = mb_strtolower(trim($status['name']), 'UTF-8');
            $this->status_map[$key] = (int) $status['id'];
        }
    }

    /**
     * Xử lý file upload và import vào DB
     *
     * @param array $file     $_FILES['import_file']
     * @param int   $staff_id ID nhân viên phụ trách (từ UI hoặc get_staff_user_id())
     * @return array ['success' => bool, 'imported' => int, 'updated' => int, 'skipped' => int, 'message' => string, 'log_id' => int]
     */
    public function process($file, $staff_id)
    {
        $result = [
            'success'  => false,
            'imported' => 0,
            'updated'  => 0,
            'skipped'  => 0,
            'message'  => '',
            'log_id'   => null,
        ];

        // --- Validate định dạng file ---
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xls', 'xlsx'])) {
            $result['message'] = _l('sales_pipeline_import_only_excel_supported');
            return $result;
        }

        // --- [BUG FIX #2] Kiểm tra kích thước file — chặn Zip Bomb / tràn bộ nhớ ---
        if ($file['size'] > self::MAX_FILE_SIZE) {
            $max_mb = self::MAX_FILE_SIZE / (1024 * 1024);
            $result['message'] = _l('sales_pipeline_import_file_too_large', [(int) $max_mb]);
            return $result;
        }

        // --- Upload file tạm ---
        $upload_dir = get_upload_path_by_type('sales_pipeline') ?: TEMP_FOLDER;
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $temp_file = rtrim($upload_dir, '/') . '/import_' . time() . '_' . uniqid() . '.' . $ext;
        $moved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $temp_file)
            : copy($file['tmp_name'], $temp_file);

        if (!$moved) {
            $result['message'] = _l('sales_pipeline_import_upload_failed');
            return $result;
        }

        // --- Tạo log import batch ---
        $log_id = $this->CI->sales_pipeline_model->log_import_batch([
            'file_name'     => $file['name'],
            'file_path'     => $temp_file,
            'uploaded_by'   => get_staff_user_id(), // Luôn là người thực hiện import
            'import_status' => 'processing',
        ]);

        try {
            $rows = $this->read_excel($temp_file, $ext);

            if (empty($rows)) {
                $result['message'] = _l('sales_pipeline_import_no_valid_data');
                $this->CI->sales_pipeline_model->update_import_log($log_id, [
                    'import_status' => 'failed',
                    'error_message' => $result['message'],
                    'rows_imported' => 0,
                    'rows_skipped'  => 0,
                ]);
                @unlink($temp_file);
                return $result;
            }

            $imported      = 0;
            $updated_count = 0;
            $skipped       = 0;

            /**
             * [BUG FIX #5] Truy vấn động danh sách status Won/Lost từ DB 1 lần
             * thay vì hardcode [5,6,7] / [9,10,11] — và tránh query trong vòng lặp.
             */
            $all_statuses    = $this->CI->sales_pipeline_model->get_statuses();
            $won_status_ids  = array_column(array_filter($all_statuses, function ($s) { return $s['is_won']; }), 'id');
            $lost_status_ids = array_column(array_filter($all_statuses, function ($s) { return $s['is_lost']; }), 'id');
            $closed_status_ids = array_merge($won_status_ids, $lost_status_ids);

            foreach ($rows as $row) {
                $data = $this->map_row_to_deal($row, $staff_id, $log_id);

                if ($data === false) {
                    $skipped++;
                    continue;
                }

                // Kiểm tra deal đã tồn tại chưa (theo staff + tên KH + tên deal + năm)
                $deal_year   = date('Y', strtotime($data['deal_date']));
                $existing_id = $this->CI->sales_pipeline_model->find_existing_deal(
                    $staff_id,
                    $data['customer_name'],
                    $data['deal_name'],
                    $deal_year
                );

                if ($existing_id) {
                    $existing_deal = $this->CI->sales_pipeline_model->get($existing_id);

                    // Bỏ qua nếu deal đã đóng (Won hoặc Lost) để tránh ghi đè lịch sử
                    $current_status = isset($existing_deal['status']) ? (int)$existing_deal['status'] : 0;

                    if (in_array($current_status, $closed_status_ids)) {
                        $skipped++;
                        continue;
                    }

                    // Cập nhật: loại bỏ các trường định danh và audit
                    $update_data = $data;
                    unset($update_data['staff_id']);
                    unset($update_data['customer_name']);
                    unset($update_data['deal_name']);
                    unset($update_data['import_batch_id']);
                    unset($update_data['addedfrom']);

                    $updated = $this->CI->sales_pipeline_model->update($update_data, $existing_id);
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
            }

            // Cập nhật log thành công
            $this->CI->sales_pipeline_model->update_import_log($log_id, [
                'import_status' => 'completed',
                'rows_imported' => $imported + $updated_count,
                'rows_skipped'  => $skipped,
            ]);

            $result['success']  = true;
            $result['imported'] = $imported;
            $result['updated']  = $updated_count;
            $result['skipped']  = $skipped;
            $result['log_id']   = $log_id;
            $result['message']  = _l('sales_pipeline_import_result_summary', [$imported, $updated_count, $skipped]);
            $result['success']  = true;

        } catch (Exception $e) {
            $result['message'] = _l('sales_pipeline_import_read_error') . ': ' . $e->getMessage();
            $this->CI->sales_pipeline_model->update_import_log($log_id, [
                'import_status' => 'failed',
                'error_message' => $e->getMessage(),
                'rows_imported' => $result['imported'],
                'rows_skipped'  => $result['skipped'],
            ]);
        }

        @unlink($temp_file);
        return $result;
    }

    // =========================================================================
    // ETL: EXTRACT — Đọc file Excel qua PhpSpreadsheet
    // =========================================================================

    /**
     * Đọc file Excel thành array of rows qua PhpSpreadsheet
     *
     * @param  string $file_path Đường dẫn file tạm
     * @param  string $ext       xls | xlsx (không sử dụng, PhpSpreadsheet tự detect)
     * @return array  Mảng các dòng dữ liệu (mỗi dòng là array 8 phần tử)
     */
    private function read_excel($file_path, $ext)
    {
        try {
            // Load spreadsheet using IOFactory (auto-detects format)
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $sheet = $spreadsheet->getActiveSheet();

            $rows = [];
            $dataStartRow = 7;  // Dòng 7 trong Excel (index 1-based)
            $dataEndRow   = 106; // Dòng 56 trong Excel
            $numCols      = 8;  // Cột A đến H

            // Đọc từng dòng từ 7-56
            for ($rowIndex = $dataStartRow; $rowIndex <= $dataEndRow; $rowIndex++) {
                $rowData = [];

                // Đọc 8 cột (A-H)
                for ($colIndex = 1; $colIndex <= $numCols; $colIndex++) {
                    $cell = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex) . $rowIndex);
                    $value = $cell->getValue();

                    // Cột A (index 1): Ngày tạo - chuẩn hóa về YYYY-MM-DD
                    if ($colIndex === 1) {
                        $rowData[] = $this->normalize_date_php($cell);
                    } else {
                        // Các cột khác: lấy giá trị thô
                        if ($value === null || $value === '') {
                            $rowData[] = '';
                        } else {
                            $rowData[] = $value;
                        }
                    }
                }

                // Bỏ qua dòng rỗng (Cột A và Cột B đều trống)
                $colA = isset($rowData[0]) ? trim((string)$rowData[0]) : '';
                $colB = isset($rowData[1]) ? trim((string)$rowData[1]) : '';
                if ($colA === '' && $colB === '') {
                    continue;
                }

                $rows[] = $rowData;
            }

            return $rows;

        } catch (\Exception $e) {
            log_activity(_l('sales_pipeline_log_import_read_error', [$e->getMessage()]));
            return [];
        }
    }

    /**
     * Chuẩn hóa giá trị ngày từ Excel cell sang chuỗi YYYY-MM-DD
     *
     * Xử lý 3 trường hợp:
     * 1. Excel serial number (số float, VD: 45678.0 → 2025-02-15)
     * 2. String DD/MM/YYYY (VD: "15/02/2025")
     * 3. String YYYY-MM-DD (đã chuẩn)
     *
     * @param  \PhpOffice\PhpSpreadsheet\Cell\Cell $cell
     * @return string YYYY-MM-DD hoặc chuỗi rỗng nếu không hợp lệ
     */
    private function normalize_date_php($cell)
    {
        $value = $cell->getValue();

        // Trường hợp rỗng
        if ($value === null || $value === '') {
            return '';
        }

        // Trường hợp 1: Excel serial number (numeric)
        if (is_numeric($value) && $value > 1) {
            try {
                // PhpSpreadsheet có helper để convert serial date sang PHP DateTime
                $dateObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                return $dateObj->format('Y-m-d');
            } catch (\Exception $e) {
                log_activity(_l('sales_pipeline_log_import_date_parse_error', [$e->getMessage()]));
            }
        }

        // Trường hợp 2 & 3: String
        $strValue = trim((string)$value);

        // Dạng DD/MM/YYYY
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $strValue, $matches)) {
            $day   = (int)$matches[1];
            $month = (int)$matches[2];
            $year  = (int)$matches[3];
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        // Dạng YYYY-MM-DD (đã chuẩn)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $strValue)) {
            return $strValue;
        }

        // Fallback: thử strtotime
        $ts = strtotime($strValue);
        if ($ts && $ts > 0) {
            return date('Y-m-d', $ts);
        }

        return '';
    }

    // =========================================================================
    // ETL: TRANSFORM — Map dòng Excel → deal data
    // =========================================================================

    /**
     * Map 1 dòng Excel (8 phần tử) sang mảng dữ liệu deal để insert/update DB.
     *
     * Mapping cột (index 0-based) - PHIÊN BẢN MỚI:
     *   0 (A) → deal_date       | Date YYYY-MM-DD
     *   1 (B) → customer_name   | String, bắt buộc
     *   2 (C) → deal_name       | String, nullable
     *   3 (D) → deal_value      | Integer >= 0 (Giá bán)
     *   4 (E) → cost_price      | Integer >= 0, nullable (Giá nhập - CHO PHÉP NULL)
     *   5 (F) → (bỏ qua)        | Công thức Excel — Backend tự tính runtime
     *   6 (G) → status          | status_id qua $status_map
     *   7 (H) → notes           | String, nullable
     *
     * @param  array $row      8 phần tử từ parse_excel.py
     * @param  int   $staff_id ID nhân viên phụ trách
     * @param  int   $log_id   Import batch ID
     * @return array|false     Mảng dữ liệu sẵn sàng insert, hoặc false nếu không hợp lệ
     */
    private function map_row_to_deal($row, $staff_id, $log_id)
    {
        // Đảm bảo đủ 8 phần tử
        while (count($row) < 8) {
            $row[] = '';
        }

        // --- Col B (1): Tên công ty KH — BẮT BUỘC ---
        $customer_name = trim((string)$row[1]);
        if (empty($customer_name)) {
            return false;
        }

        // --- Col A (0): Ngày tạo ---
        $deal_date = $this->parse_date($row[0]);
        if (!$deal_date) {
            $deal_date = date('Y-m-d'); // Fallback: ngày hôm nay
        }

        // --- Col C (2): Mô tả sản phẩm/DV ---
        $deal_name = trim((string)$row[2]);
        if (empty($deal_name)) {
            $deal_name = null;
        }

        // --- Col D (3): Giá bán (Doanh số) ---
        $deal_value = (float)str_replace([',', ' '], '', (string)$row[3]);
        /**
         * [BUG FIX #3] Chặn giá trị INF/NAN sinh ra khi ép kiểu (float)
         * với chuỗi lớn bất thường (VD: "1e999" → INF). MySQL sẽ lỗi INSERT.
         */
        if (!is_finite($deal_value) || $deal_value < 0) {
            $deal_value = 0;
        }

        // --- Col E (4): Giá nhập (Cost Price) ---
        // QUAN TRỌNG: Cho phép NULL nếu Sale không được phép biết giá nhập
        $cost_price = null;
        if (isset($row[4]) && $row[4] !== '' && $row[4] !== null) {
            $cp = (float)str_replace([',', ' '], '', (string)$row[4]);
            // [BUG FIX #3] Chặn INF/NAN cho cost_price
            if (is_finite($cp) && $cp > 0) {
                $cost_price = $cp;
            }
        }

        // --- Col F (5): Lợi nhuận VNĐ — BỎ QUA hoàn toàn (tính runtime khi query) ---
        // Không lưu vào DB. Lợi nhuận = deal_value - cost_price (tính khi SELECT)

        // --- Col G (6): Trạng thái (Dropdown string → status_id) ---
        $status_text = trim((string)$row[6]);
        $status_id   = $this->map_status_to_id($status_text);

        // Mặc định = 0, nhân viên sẽ tự cập nhật trên form deal
        $contract_signed = 0;
        $invoice_issued  = 0;

        // --- Col H (7): Ghi chú ---
        $notes = trim((string)$row[7]);
        if (empty($notes)) {
            $notes = null;
        }

        // --- Tính reporting_period từ deal_date ---
        $reporting_period = $this->extract_reporting_period($deal_date);

        return [
            // Trường định danh
            'staff_id'         => $staff_id,
            'customer_name'    => $customer_name,
            'deal_name'        => $deal_name,

            // Tài chính
            'deal_value'       => $deal_value,          // Giá bán (bắt buộc)
            'cost_price'       => $cost_price,          // Giá nhập (NULL nếu Sale không biết)

            // Ngày & kỳ báo cáo
            'deal_date'        => $deal_date,
            'reporting_period' => $reporting_period,

            // Trạng thái (nguồn sự thật duy nhất)
            'status'           => $status_id,
            'contract_signed'  => $contract_signed,
            'invoice_issued'   => $invoice_issued,

            // Phân loại mở rộng — để NULL, không dùng heuristic từ ghi chú
            'confidence_level' => null,
            'deal_phase'       => null,

            // Nhắc nhở — mặc định bật, tần suất 3 ngày
            'reminder_enabled'   => 1,
            'reminder_frequency' => 3,

            // Ghi chú & Audit
            'notes'           => $notes,
            'addedfrom'       => $staff_id,           // Người phụ trách deal
            'imported_by'     => get_staff_user_id(), // Người thực hiện import
            'import_batch_id' => $log_id,
        ];
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Mapping: Tên trạng thái (string từ Dropdown Excel) → status_id trong DB.
     * Mặc định trả về 1 (Đang tư vấn) nếu không khớp.
     *
     * @param  string $status_text
     * @return int
     */
    private function map_status_to_id($status_text)
    {
        /**
         * [BUG FIX #4] Chuẩn hóa lowercase trước khi tra bảng mapping.
         * $this->status_map đã được build với key = mb_strtolower() trong buildStatusMap().
         * Nhờ vậy "đang tư vấn" == "Đang Tư Vấn" == "ĐANG TƯ VẤN".
         */
        $status_text = mb_strtolower(trim($status_text), 'UTF-8');
        return isset($this->status_map[$status_text])
            ? $this->status_map[$status_text]
            : 1; // Mặc định: Đang tư vấn
    }

    /**
     * Parse date từ Python output (đã chuẩn hóa YYYY-MM-DD)
     * hoặc fallback xử lý các định dạng phổ biến.
     *
     * @param  mixed $value
     * @return string|false Y-m-d format
     */
    private function parse_date($value)
    {
        if (empty($value)) {
            return false;
        }

        $val = (string)$value;

        // Đã chuẩn hóa YYYY-MM-DD từ parse_excel.py
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
            return $val;
        }

        // Dạng DD/MM/YYYY (fallback)
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $val, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        // Thử strtotime
        $ts = strtotime($val);
        if ($ts && $ts > 0) {
            return date('Y-m-d', $ts);
        }

        return false;
    }

    /**
     * Tính reporting_period (VD: Q1-2026, Q3-2026) từ deal_date.
     *
     * @param  string $date Y-m-d format
     * @return string
     */
    private function extract_reporting_period($date)
    {
        $ts      = strtotime($date);
        $month   = (int)date('n', $ts);
        $year    = date('Y', $ts);
        $quarter = (int)ceil($month / 3);
        return 'Q' . $quarter . '-' . $year;
    }

    /**
     * Xử lý ghi chú có ký tự "→" thành các activity log riêng biệt.
     *
     * @param int    $pipeline_id
     * @param string $notes
     */
    private function process_activity_notes($pipeline_id, $notes)
    {
        $activities = preg_split('/→|->/', $notes);
        foreach ($activities as $activity) {
            $activity = trim($activity);
            if (!empty($activity) && mb_strlen($activity) > 3) {
                $this->CI->sales_pipeline_model->add_activity($pipeline_id, $activity);
            }
        }
    }
}
