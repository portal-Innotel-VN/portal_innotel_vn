<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Sales_pipeline_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // =========================================================================
    // CRUD: Pipeline Deals
    // =========================================================================

    /**
     * Lấy deal theo ID hoặc tất cả với Lợi nhuận tính runtime
     * @param string|int $id
     * @param array $where Điều kiện lọc bổ sung
     * @param int $limit Số record mỗi trang (cho pagination)
     * @param int $offset Vị trí bắt đầu (cho pagination)
     * @return mixed
     */
    public function get($id = '', $where = [], $limit = null, $offset = null, $search = '')
    {
        $this->db->select(
            db_prefix() . 'sales_pipeline.*,' .
            db_prefix() . 'sales_pipeline_statuses.name as status_name,' .
            db_prefix() . 'sales_pipeline_statuses.color as status_color,' .
            db_prefix() . 'sales_pipeline_statuses.is_won,' .
            db_prefix() . 'sales_pipeline_statuses.is_lost,' .
            'CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as staff_name,' .
            // Tính lợi nhuận runtime: deal_value - cost_price (NULL nếu cost_price NULL)
            '(CASE WHEN ' . db_prefix() . 'sales_pipeline.cost_price IS NOT NULL 
                THEN ' . db_prefix() . 'sales_pipeline.deal_value - ' . db_prefix() . 'sales_pipeline.cost_price 
                ELSE NULL END) as actual_profit,' .
            // Tính % lợi nhuận: (profit / deal_value) * 100
            '(CASE WHEN ' . db_prefix() . 'sales_pipeline.cost_price IS NOT NULL AND ' . db_prefix() . 'sales_pipeline.deal_value > 0
                THEN ((' . db_prefix() . 'sales_pipeline.deal_value - ' . db_prefix() . 'sales_pipeline.cost_price) / ' . db_prefix() . 'sales_pipeline.deal_value) * 100
                ELSE NULL END) as profit_percentage,' .
            // Cờ cảnh báo: cost_price còn NULL
            '(CASE WHEN ' . db_prefix() . 'sales_pipeline.cost_price IS NULL THEN 1 ELSE 0 END) as missing_cost_price'
        );
        $this->db->join(
            db_prefix() . 'sales_pipeline_statuses',
            db_prefix() . 'sales_pipeline_statuses.id = ' . db_prefix() . 'sales_pipeline.status',
            'left'
        );
        $this->db->join(
            db_prefix() . 'staff',
            db_prefix() . 'staff.staffid = ' . db_prefix() . 'sales_pipeline.staff_id',
            'left'
        );

        if (!empty($where)) {
            $this->db->where($where);
        }

        if (!empty($search)) {
            $this->db->group_start();
            $this->db->like(db_prefix() . 'sales_pipeline.customer_name', $search);
            $this->db->or_like(db_prefix() . 'sales_pipeline.deal_name', $search);
            $this->db->or_like(db_prefix() . 'sales_pipeline.contact_name', $search);
            $this->db->group_end();
        }

        if (is_numeric($id)) {
            $this->db->where(db_prefix() . 'sales_pipeline.id', $id);
            $deal = $this->db->get(db_prefix() . 'sales_pipeline')->row_array();
            if ($deal) {
                $deal['activity'] = $this->get_activity($id);
            }
            return $deal;
        }

        $this->db->order_by(db_prefix() . 'sales_pipeline.deal_date', 'ASC');

        // Apply pagination if limit is set
        if ($limit !== null) {
            $this->db->limit($limit, $offset);
        }

        return $this->db->get(db_prefix() . 'sales_pipeline')->result_array();
    }

    /**
     * Đếm tổng số deal theo điều kiện lọc (cho pagination)
     * @param array $where Điều kiện lọc
     * @param string $search Từ khóa tìm kiếm
     * @return int
     */
    public function count_deals($where = [], $search = '')
    {
        if (!empty($where)) {
            $this->db->where($where);
        }

        if (!empty($search)) {
            $this->db->group_start();
            $this->db->like(db_prefix() . 'sales_pipeline.customer_name', $search);
            $this->db->or_like(db_prefix() . 'sales_pipeline.deal_name', $search);
            $this->db->or_like(db_prefix() . 'sales_pipeline.contact_name', $search);
            $this->db->group_end();
        }

        return $this->db->count_all_results(db_prefix() . 'sales_pipeline');
    }

    /**
     * Thêm deal mới
     * @param array $data
     * @return int|false Insert ID hoặc false
     */
    public function add($data)
    {
        // Không còn tính profit_margin/expected_profit (đã xóa khỏi DB)
        // Lợi nhuận = deal_value - cost_price (tính khi query)

        $data['datecreated'] = date('Y-m-d H:i:s');
        
        // Nếu addedfrom chưa có, set người hiện tại
        if (!isset($data['addedfrom'])) {
            $data['addedfrom'] = get_staff_user_id();
        }

        // Xử lý checkbox
        $data['contract_signed']  = isset($data['contract_signed']) ? 1 : 0;
        $data['invoice_issued']   = isset($data['invoice_issued']) ? 1 : 0;
        $data['reminder_enabled'] = isset($data['reminder_enabled']) ? 1 : 0;

        // Loại bỏ field không thuộc bảng
        $activity_description = '';
        if (isset($data['activity_description'])) {
            $activity_description = $data['activity_description'];
            unset($data['activity_description']);
        }

        $this->db->insert(db_prefix() . 'sales_pipeline', $data);
        $insert_id = $this->db->insert_id();

        if ($insert_id) {
            // Log activity: tạo mới
            $deal_name = $data['deal_name'] ?? ($data['customer_name'] ?? _l('sales_pipeline_activity_new_deal'));
            $this->add_activity($insert_id, _l('sales_pipeline_activity_deal_created', [$deal_name]), null, $data['status']);

            // Log activity bổ sung nếu có ghi chú
            if (!empty($activity_description)) {
                $this->add_activity($insert_id, $activity_description);
            }

            log_activity(_l('sales_pipeline_log_new_deal', [$insert_id, $deal_name]));
        }

        return $insert_id;
    }

    /**
     * Cập nhật deal
     * @param array $data
     * @param int $id
     * @return bool
     */
    public function update($data, $id)
    {
        // Lấy deal cũ để so sánh trạng thái và cost_price
        $old_deal = $this->get($id);
        $old_status = $old_deal ? $old_deal['status'] : null;
        $old_cost_price = $old_deal ? $old_deal['cost_price'] : null;

        // Không còn tính profit_margin/expected_profit
        
        $data['datemodified'] = date('Y-m-d H:i:s');
        
        // Kiểm tra nếu cost_price được cập nhật từ NULL → có giá trị
        $cost_price_updated = false;
        if (isset($data['cost_price']) && $data['cost_price'] !== null && $old_cost_price === null) {
            $cost_price_updated = true;
        }

        // Xử lý checkbox
        $data['contract_signed']  = isset($data['contract_signed']) ? 1 : 0;
        $data['invoice_issued']   = isset($data['invoice_issued']) ? 1 : 0;
        $data['reminder_enabled'] = isset($data['reminder_enabled']) ? 1 : 0;

        // Xử lý activity
        $activity_description = '';
        if (isset($data['activity_description'])) {
            $activity_description = $data['activity_description'];
            unset($data['activity_description']);
        }

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'sales_pipeline', $data);
        $updated = $this->db->affected_rows() > 0;

        if ($updated || !empty($activity_description)) {
            // Log thay đổi trạng thái
            if (isset($data['status']) && $data['status'] != $old_status) {
                $new_status_name = $this->get_status_name($data['status']);
                $old_status_name = $this->get_status_name($old_status);
                $this->add_activity(
                    $id,
                    _l('sales_pipeline_activity_status_changed', [$old_status_name, $new_status_name]),
                    $old_status,
                    $data['status']
                );
            }

            // Log ghi chú tiến độ
            if (!empty($activity_description)) {
                $this->add_activity($id, $activity_description);
            }

            log_activity(_l('sales_pipeline_log_update_deal', [$id]));
        }

        return $updated;
    }

    /**
     * Xóa deal
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        if (empty($id)) {
            return false;
        }

        $deal = $this->get($id);

        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'sales_pipeline');

        if ($this->db->affected_rows() > 0) {
            // Xóa activity liên quan
            $this->db->where('pipeline_id', $id);
            $this->db->delete(db_prefix() . 'sales_pipeline_activity');

            // Xóa log nhắc nhở
            $this->db->where('pipeline_id', $id);
            $this->db->delete(db_prefix() . 'sales_pipeline_reminders_log');

            if ($deal) {
                log_activity(_l('sales_pipeline_log_delete_deal', [$id, $deal['deal_name']]));
            }

            return true;
        }

        return false;
    }

    /**
     * Tìm deal đã tồn tại (Upsert logic)
     * @param int $staff_id
     * @param string $customer_name
     * @param string $deal_name
     * @param string $year
     * @return int|false ID của deal hoặc false
     */
    public function find_existing_deal($staff_id, $customer_name, $deal_name, $year = null)
    {
        $this->db->select('id');
        $this->db->where('staff_id', $staff_id);
        $this->db->where('customer_name', $customer_name);
        
        if (empty($deal_name)) {
            $this->db->where('(deal_name IS NULL OR deal_name = "")');
        } else {
            $this->db->where('deal_name', $deal_name);
        }

        if ($year) {
            $this->db->where('YEAR(deal_date)', $year);
        }

        $row = $this->db->get(db_prefix() . 'sales_pipeline')->row();
        return $row ? $row->id : false;
    }

    // =========================================================================
    // STATUSES
    // =========================================================================

    /**
     * Lấy tất cả trạng thái
     * @return array
     */
    public function get_statuses()
    {
        $this->db->order_by('order', 'ASC');
        return $this->db->get(db_prefix() . 'sales_pipeline_statuses')->result_array();
    }

    /**
     * Lấy tên trạng thái theo ID
     * @param int $status_id
     * @return string
     */
    public function get_status_name($status_id)
    {
        $this->db->where('id', $status_id);
        $row = $this->db->get(db_prefix() . 'sales_pipeline_statuses')->row();
        return $row ? $row->name : _l('sales_pipeline_status_unknown');
    }

    public function add_status($data)
    {
        $this->db->insert(db_prefix() . 'sales_pipeline_statuses', $data);
        return $this->db->insert_id();
    }

    public function update_status($data, $id)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'sales_pipeline_statuses', $data);
        return $this->db->affected_rows() > 0;
    }

    /**
     * Kiểm tra trạng thái có đang được deal nào sử dụng không
     * @param int $status_id
     * @return bool
     */
    public function is_status_used($status_id)
    {
        $this->db->where('status', $status_id);
        return $this->db->count_all_results(db_prefix() . 'sales_pipeline') > 0;
    }

    public function delete_status($id)
    {
        if ($this->is_status_used($id)) {
            return ['referenced' => true];
        }

        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'sales_pipeline_statuses');
        return ['success' => $this->db->affected_rows() > 0];
    }

    /**
     * Lấy thông tin 1 trạng thái theo ID
     * @param int $status_id
     * @return array|null
     */
    public function get_status_by_id($status_id)
    {
        $this->db->where('id', $status_id);
        return $this->db->get(db_prefix() . 'sales_pipeline_statuses')->row_array();
    }

    /**
     * Cập nhật thứ tự hiển thị hàng loạt cho trạng thái
     * @param array $order_data Mảng [[id, order], [id, order], ...]
     * @return bool
     */
    public function update_statuses_order($order_data)
    {
        foreach ($order_data as $item) {
            $this->db->where('id', $item[0]);
            $this->db->update(db_prefix() . 'sales_pipeline_statuses', ['order' => $item[1]]);
        }
        return true;
    }

    // =========================================================================
    // SOURCES
    // =========================================================================

    public function get_sources()
    {
        $this->db->order_by('name', 'ASC');
        return $this->db->get(db_prefix() . 'sales_pipeline_sources')->result_array();
    }

    public function add_source($data)
    {
        $this->db->insert(db_prefix() . 'sales_pipeline_sources', $data);
        return $this->db->insert_id();
    }

    public function update_source($data, $id)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'sales_pipeline_sources', $data);
        return $this->db->affected_rows() > 0;
    }

    /**
     * Kiểm tra nguồn khách hàng có đang được deal nào sử dụng không
     * @param int $source_id
     * @return bool
     */
    public function is_source_used($source_id)
    {
        $this->db->where('source_id', $source_id);
        return $this->db->count_all_results(db_prefix() . 'sales_pipeline') > 0;
    }

    public function delete_source($id)
    {
        if ($this->is_source_used($id)) {
            return ['referenced' => true];
        }

        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'sales_pipeline_sources');
        return ['success' => $this->db->affected_rows() > 0];
    }

    // =========================================================================
    // ACTIVITY LOG (Timeline tiến độ)
    // =========================================================================

    /**
     * Lấy activity của deal
     * @param int $pipeline_id
     * @return array
     */
    public function get_activity($pipeline_id)
    {
        $this->db->select(
            db_prefix() . 'sales_pipeline_activity.*,' .
            'CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as staff_name'
        );
        $this->db->join(
            db_prefix() . 'staff',
            db_prefix() . 'staff.staffid = ' . db_prefix() . 'sales_pipeline_activity.staff_id',
            'left'
        );
        $this->db->where('pipeline_id', $pipeline_id);
        $this->db->order_by('datecreated', 'DESC');
        return $this->db->get(db_prefix() . 'sales_pipeline_activity')->result_array();
    }

    /**
     * Thêm activity log
     * @param int $pipeline_id
     * @param string $description
     * @param int|null $old_status
     * @param int|null $new_status
     * @param int|null $staff_id Người tạo activity; mặc định là staff đang đăng nhập
     * @return bool
     */
    public function add_activity($pipeline_id, $description, $old_status = null, $new_status = null, $staff_id = null)
    {
        $this->db->insert(db_prefix() . 'sales_pipeline_activity', [
            'pipeline_id' => $pipeline_id,
            'staff_id'    => $staff_id === null ? get_staff_user_id() : (int) $staff_id,
            'description' => $description,
            'old_status'  => $old_status,
            'new_status'  => $new_status,
            'datecreated' => date('Y-m-d H:i:s'),
        ]);

        return $this->db->affected_rows() > 0;
    }

    // =========================================================================
    // THỐNG KÊ & BÁO CÁO
    // =========================================================================

    /**
     * Lấy thống kê tổng quan theo quý
     * @param int|null $quarter Quý (1-4), null = tất cả
     * @param int|null $year Năm
     * @param int|null $staff_id Lọc theo nhân viên
     * @param string $search Từ khóa tìm kiếm
     * @param int|null $contract_signed Lọc hợp đồng đã ký
     * @param int|null $invoice_issued Lọc hóa đơn đã xuất
     * @return array
     */
    public function get_summary($quarter = null, $year = null, $staff_id = null, $search = '', $contract_signed = null, $invoice_issued = null)
    {
        $base_where = [];
        if ($quarter) $base_where['QUARTER(deal_date)'] = $quarter;
        if ($year !== null && $year !== '') $base_where['YEAR(deal_date)'] = $year;
        if ($staff_id) $base_where['staff_id'] = $staff_id;
        if ($contract_signed !== null && $contract_signed !== '') {
            $base_where['contract_signed'] = (int) $contract_signed;
        }
        if ($invoice_issued !== null && $invoice_issued !== '') {
            $base_where['invoice_issued'] = (int) $invoice_issued;
        }

        // 1. Tổng số deal & Tổng giá trị
        $this->db->select('COUNT(id) as total_deals, SUM(deal_value) as total_value');
        $this->db->where($base_where);
        if (!empty($search)) {
            $this->db->group_start();
            $this->db->like('customer_name', $search);
            $this->db->or_like('deal_name', $search);
            $this->db->or_like('contact_name', $search);
            $this->db->group_end();
        }
        $totals = $this->db->get(db_prefix() . 'sales_pipeline')->row_array();

        // 2. Lấy dữ liệu chi tiết
        $this->db->where($base_where);
        if (!empty($search)) {
            $this->db->group_start();
            $this->db->like('customer_name', $search);
            $this->db->or_like('deal_name', $search);
            $this->db->or_like('contact_name', $search);
            $this->db->group_end();
        }
        $deals = $this->db->get(db_prefix() . 'sales_pipeline')->result_array();

        $summary = [
            'total_deals'    => count($deals),
            'total_value'    => 0,
            'total_profit'   => 0,
            'won_deals'      => 0,
            'won_value'      => 0,
            'lost_deals'     => 0,
            'active_deals'   => 0,
            'pending_deals'  => 0,
        ];

        // Lấy danh sách trạng thái won/lost
        $statuses = $this->get_statuses();
        $won_ids  = array_column(array_filter($statuses, function ($s) { return $s['is_won']; }), 'id');
        $lost_ids = array_column(array_filter($statuses, function ($s) { return $s['is_lost']; }), 'id');

        foreach ($deals as $deal) {
            $summary['total_value']  += floatval($deal['deal_value']);
            if ($deal['cost_price'] !== null) {
                $summary['total_profit'] += (floatval($deal['deal_value']) - floatval($deal['cost_price']));
            }

            if (in_array($deal['status'], $won_ids)) {
                $summary['won_deals']++;
                $summary['won_value'] += floatval($deal['deal_value']);
            } elseif (in_array($deal['status'], $lost_ids)) {
                $summary['lost_deals']++;
            } else {
                $summary['active_deals']++;
            }
        }

        return $summary;
    }

    // =========================================================================
    // IMPORT LOG
    // =========================================================================

    /**
     * Ghi log import batch
     * @param array $data
     * @return int Insert ID
     */
    public function log_import_batch($data)
    {
        $log_data = [
            'file_name'      => $data['file_name'],
            'file_path'      => $data['file_path'] ?? null,
            'uploaded_by'    => $data['uploaded_by'] ?? get_staff_user_id(),
            'uploaded_at'    => date('Y-m-d H:i:s'),
            'rows_imported'  => $data['rows_imported'] ?? 0,
            'rows_skipped'   => $data['rows_skipped'] ?? 0,
            'import_status'  => $data['import_status'] ?? 'processing',
            'error_message'  => $data['error_message'] ?? null,
        ];

        $this->db->insert(db_prefix() . 'sales_pipeline_import_log', $log_data);
        return $this->db->insert_id();
    }

    /**
     * Cập nhật log import
     * @param int $log_id
     * @param array $data
     * @return bool
     */
    public function update_import_log($log_id, $data)
    {
        $this->db->where('id', $log_id);
        $this->db->update(db_prefix() . 'sales_pipeline_import_log', $data);
        return $this->db->affected_rows() > 0;
    }

    /**
     * Lấy lịch sử import
     * @param int|null $log_id
     * @return mixed
     */
    public function get_import_logs($log_id = null)
    {
        $this->db->select(
            db_prefix() . 'sales_pipeline_import_log.*,' .
            'CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as uploader_name'
        );
        $this->db->join(
            db_prefix() . 'staff',
            db_prefix() . 'staff.staffid = ' . db_prefix() . 'sales_pipeline_import_log.uploaded_by',
            'left'
        );

        if ($log_id) {
            $this->db->where(db_prefix() . 'sales_pipeline_import_log.id', $log_id);
            return $this->db->get(db_prefix() . 'sales_pipeline_import_log')->row_array();
        }

        $this->db->order_by('uploaded_at', 'DESC');
        return $this->db->get(db_prefix() . 'sales_pipeline_import_log')->result_array();
    }

    // =========================================================================
    // COST PRICE MANAGEMENT
    // =========================================================================

    /**
     * Lấy danh sách deals thiếu giá nhập (cost_price = NULL)
     * @param array $filters ['quarter', 'year', 'staff_id']
     * @return array
     */
    public function get_deals_missing_cost_price($filters = [])
    {
        $this->db->select(
            db_prefix() . 'sales_pipeline.*,' .
            db_prefix() . 'sales_pipeline_statuses.name as status_name,' .
            db_prefix() . 'sales_pipeline_statuses.color as status_color,' .
            'CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as staff_name'
        );
        $this->db->join(
            db_prefix() . 'sales_pipeline_statuses',
            db_prefix() . 'sales_pipeline_statuses.id = ' . db_prefix() . 'sales_pipeline.status',
            'left'
        );
        $this->db->join(
            db_prefix() . 'staff',
            db_prefix() . 'staff.staffid = ' . db_prefix() . 'sales_pipeline.staff_id',
            'left'
        );

        // Chỉ lấy deal chưa có cost_price
        $this->db->where(db_prefix() . 'sales_pipeline.cost_price IS NULL');

        // Filters
        if (!empty($filters['quarter'])) {
            $this->db->where('QUARTER(' . db_prefix() . 'sales_pipeline.deal_date)', $filters['quarter']);
        }
        if (!empty($filters['year'])) {
            $this->db->where('YEAR(' . db_prefix() . 'sales_pipeline.deal_date)', $filters['year']);
        }
        if (!empty($filters['staff_id'])) {
            $this->db->where(db_prefix() . 'sales_pipeline.staff_id', $filters['staff_id']);
        }

        if (isset($filters['limit']) && isset($filters['offset'])) {
            $this->db->limit($filters['limit'], $filters['offset']);
        }

        $this->db->order_by(db_prefix() . 'sales_pipeline.deal_date', 'DESC');
        return $this->db->get(db_prefix() . 'sales_pipeline')->result_array();
    }

    /**
     * Đếm tổng số lượng deals thiếu giá nhập theo bộ lọc
     * @param array $filters
     * @return int
     */
    public function count_deals_missing_cost_price($filters = [])
    {
        $this->db->where('cost_price IS NULL');

        if (!empty($filters['quarter'])) {
            $this->db->where('QUARTER(deal_date)', $filters['quarter']);
        }
        if (!empty($filters['year'])) {
            $this->db->where('YEAR(deal_date)', $filters['year']);
        }
        if (!empty($filters['staff_id'])) {
            $this->db->where('staff_id', $filters['staff_id']);
        }

        return $this->db->count_all_results(db_prefix() . 'sales_pipeline');
    }

    /**
     * Cập nhật cost_price cho deal (AJAX endpoint)
     * @param int $deal_id
     * @param float $cost_price
     * @return bool
     */
    public function update_cost_price($deal_id, $cost_price)
    {
        $deal = $this->get($deal_id);
        if (!$deal) {
            return false;
        }

        $old_cost_price = $deal['cost_price'] !== null ? (float)$deal['cost_price'] : null;
        $new_cost_price = $cost_price !== null ? (float)$cost_price : null;

        // Nếu giá trị cũ và mới giống hệt nhau, không cần update DB nhưng trả về true
        if ($old_cost_price === $new_cost_price) {
            return true;
        }

        $this->db->where('id', $deal_id);
        $updated = $this->db->update(db_prefix() . 'sales_pipeline', [
            'cost_price'    => $cost_price,
            'datemodified'  => date('Y-m-d H:i:s'),
        ]);

        if ($updated) {
            // Log activity
            if ($old_cost_price === null) {
                $this->add_activity($deal_id, _l('sales_pipeline_activity_cost_price_updated_from_null', [number_format($cost_price)]));
                
                // Đánh dấu alert đã resolved
                $this->resolve_cost_price_alert($deal_id);
            } else {
                $this->add_activity($deal_id, _l('sales_pipeline_activity_cost_price_updated', [number_format($old_cost_price), number_format($cost_price)]));
            }

            log_activity(_l('sales_pipeline_log_update_cost_price', [$deal_id]));
            return true;
        }

        return false;
    }

    /**
     * Tạo alert cho deal thiếu giá nhập
     * @param int $pipeline_id
     * @return bool
     */
    public function create_cost_price_alert($pipeline_id)
    {
        // Kiểm tra alert đã tồn tại chưa
        $this->db->where('pipeline_id', $pipeline_id);
        $this->db->where('resolved_at IS NULL');
        $existing = $this->db->get(db_prefix() . 'sales_pipeline_cost_alerts')->row();

        if ($existing) {
            // Cập nhật alert count
            $this->db->where('id', $existing->id);
            $this->db->update(db_prefix() . 'sales_pipeline_cost_alerts', [
                'alert_count'     => $existing->alert_count + 1,
                'last_alert_sent' => date('Y-m-d H:i:s'),
            ]);
            return true;
        }

        // Tạo alert mới
        $this->db->insert(db_prefix() . 'sales_pipeline_cost_alerts', [
            'pipeline_id'     => $pipeline_id,
            'alert_count'     => 1,
            'last_alert_sent' => date('Y-m-d H:i:s'),
            'datecreated'     => date('Y-m-d H:i:s'),
        ]);

        return $this->db->insert_id() > 0;
    }

    /**
     * Đánh dấu alert đã resolved khi cost_price được cập nhật
     * @param int $pipeline_id
     */
    private function resolve_cost_price_alert($pipeline_id)
    {
        $this->db->where('pipeline_id', $pipeline_id);
        $this->db->where('resolved_at IS NULL');
        $this->db->update(db_prefix() . 'sales_pipeline_cost_alerts', [
            'resolved_at' => date('Y-m-d H:i:s'),
            'resolved_by' => get_staff_user_id(),
        ]);
    }

    /**
     * Gửi alert cho các bên liên quan về deal thiếu giá nhập
     * @param array $deal
     */
    public function send_cost_price_alert($deal)
    {
        $alert_recipients = [];

        // 1. Nhân viên phụ trách deal
        $alert_recipients[] = $deal['staff_id'];

        // 2. Người import (nếu khác người phụ trách)
        if (!empty($deal['imported_by']) && $deal['imported_by'] != $deal['staff_id']) {
            $alert_recipients[] = $deal['imported_by'];
        }

        // 3. Admin/Quản lý (role = admin hoặc có permission)
        $this->db->where('admin', 1);
        $this->db->where('active', 1);
        $admins = $this->db->get(db_prefix() . 'staff')->result_array();
        foreach ($admins as $admin) {
            if (!in_array($admin['staffid'], $alert_recipients)) {
                $alert_recipients[] = $admin['staffid'];
            }
        }

        // Gửi notification cho từng người
        foreach ($alert_recipients as $staff_id) {
            add_notification([
                'description'     => 'sales_pipeline_missing_cost_price',
                'touserid'        => $staff_id,
                'fromuserid'      => null,
                'link'            => 'sales_pipeline/deal/' . $deal['id'],
                'additional_data' => serialize([
                    $deal['customer_name'],
                    $deal['deal_name'],
                    number_format($deal['deal_value']),
                ]),
            ]);
        }

        // Lưu log alert
        $this->create_cost_price_alert($deal['id']);

        // Log vào alerted_staff_ids
        $this->db->where('pipeline_id', $deal['id']);
        $this->db->where('resolved_at IS NULL');
        $alert = $this->db->get(db_prefix() . 'sales_pipeline_cost_alerts')->row();
        
        if ($alert) {
            $alerted_ids = json_decode($alert->alerted_staff_ids, true) ?: [];
            $alerted_ids = array_unique(array_merge($alerted_ids, $alert_recipients));
            
            $this->db->where('id', $alert->id);
            $this->db->update(db_prefix() . 'sales_pipeline_cost_alerts', [
                'alerted_staff_ids' => json_encode($alerted_ids),
            ]);
        }
    }

    /**
     * Xử lý alert hàng ngày cho deals thiếu giá nhập
     * Gọi từ CRON job
     */
    public function process_cost_price_alerts()
    {
        // Lấy deals thiếu cost_price (không phân biệt quý/năm)
        $deals = $this->get_deals_missing_cost_price();

        foreach ($deals as $deal) {
            // Kiểm tra xem đã alert trong 24h chưa
            $this->db->where('pipeline_id', $deal['id']);
            $this->db->where('resolved_at IS NULL');
            $this->db->where('last_alert_sent >=', date('Y-m-d H:i:s', strtotime('-24 hours')));
            $recent_alert = $this->db->get(db_prefix() . 'sales_pipeline_cost_alerts')->row();

            if (!$recent_alert) {
                // Gửi alert
                $this->send_cost_price_alert($deal);
            }
        }
    }

    // =========================================================================
    // CRON: Nhắc nhở tự động
    // =========================================================================

    /**
     * Xử lý nhắc nhở hàng tuần
     * Quét deal đang mở → gửi email + notification cho sale
     */
    public function process_weekly_reminders()
    {
        // Lấy danh sách trạng thái đang mở (không phải won/lost)
        $statuses = $this->get_statuses();
        $active_status_ids = [];
        foreach ($statuses as $s) {
            if (!$s['is_won'] && !$s['is_lost']) {
                $active_status_ids[] = $s['id'];
            }
        }

        if (empty($active_status_ids)) {
            return;
        }

        // Lấy deal cần nhắc
        $this->db->where('reminder_enabled', 1);
        $this->db->where_in('status', $active_status_ids);
        $this->db->group_start();
        $this->db->where('last_reminder_sent IS NULL');
        $this->db->or_where('DATEDIFF(NOW(), last_reminder_sent) >= reminder_frequency');
        $this->db->group_end();

        $deals = $this->db->get(db_prefix() . 'sales_pipeline')->result_array();

        foreach ($deals as $deal) {
            $this->send_reminder($deal);
        }
    }

    /**
     * Gửi nhắc nhở cho 1 deal
     * @param array $deal
     */
    private function send_reminder($deal)
    {
        $staff = $this->staff_model->get($deal['staff_id']);
        if (!$staff) {
            return;
        }

        $context = $this->get_reminder_context($deal);
        $context['staff_name'] = preg_replace(
            '/\s+/u',
            ' ',
            trim($staff->firstname . ' ' . $staff->lastname)
        );
        $message = $this->build_reminder_message($context);

        // Reminder phải được tạo trước để email và notification có thể dùng đúng ID.
        $sent_at = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'sales_pipeline_reminders_log', [
            'pipeline_id'   => $deal['id'],
            'staff_id'      => $deal['staff_id'],
            'reminder_type' => 'email',
            'message'       => $message,
            'sent_at'       => $sent_at,
        ]);

        $reminder_id = (int) $this->db->insert_id();
        if ($reminder_id <= 0) {
            return;
        }

        $response_url = admin_url('sales_pipeline/reminder_response/' . $reminder_id);
        $deal_url = admin_url('sales_pipeline/deal/' . $deal['id']);

        // Gửi notification nội bộ
        add_notification([
            'description'     => 'sales_pipeline_reminder',
            'touserid'        => $deal['staff_id'],
            'fromuserid'      => null,
            'link'            => 'sales_pipeline/reminder_response/' . $reminder_id,
            'additional_data' => serialize([
                $deal['deal_name'],
                $deal['customer_name'],
            ]),
        ]);

        // Gửi email (nếu staff có email)
        if (!empty($staff->email)) {
            $this->load->model('emails_model');

            $staff_name = html_escape($context['staff_name']);
            $deal_name = html_escape($deal['deal_name']);
            $customer_name = html_escape($deal['customer_name']);
            $entity_label = html_escape($context['entity_label']);
            $entity_name = html_escape($context['entity_name']);
            $status_name = html_escape($context['status_name']);
            $subject_entity_name = preg_replace('/[\r\n]+/', ' ', $context['entity_name']);
            $email_subject = _l('sales_pipeline_reminder_email_subject', [$subject_entity_name]);
            $email_body = $this->load->view('sales_pipeline/emails/reminder', [
                'staff_name'     => $staff_name,
                'entity_label'   => $entity_label,
                'entity_name'    => $entity_name,
                'customer_name'  => $customer_name,
                'deal_name'      => $deal_name,
                'status_name'    => $status_name,
                'deal_value'     => number_format($deal['deal_value']),
                'deal_date'      => _d($deal['deal_date']),
                'response_url'   => html_escape($response_url),
                'deal_url'       => html_escape($deal_url),
            ], true);

            $this->emails_model->send_simple_email($staff->email, $email_subject, $email_body);
        }

        // Cập nhật thời gian nhắc nhở cuối
        $this->db->where('id', $deal['id']);
        $this->db->update(db_prefix() . 'sales_pipeline', [
            'last_reminder_sent' => $sent_at,
        ]);
    }

    // =========================================================================
    // REMINDERS: Phản hồi nhắc nhở
    // =========================================================================

    /**
     * Lấy reminder cùng thông tin deal để hiển thị quick response form.
     *
     * @param int $reminder_id
     * @return array|null
     */
    public function get_reminder_log($reminder_id)
    {
        $this->db->select(
            'rl.*, sp.id as deal_exists, sp.deal_name, sp.customer_name, sp.deal_value, '
            . 'sp.deal_date, sp.status, sp.estimate_id, ss.name as status_name, ss.color as status_color, '
            . 'CONCAT(s.firstname, " ", s.lastname) as staff_name'
        );
        $this->db->from(db_prefix() . 'sales_pipeline_reminders_log rl');
        $this->db->join(db_prefix() . 'sales_pipeline sp', 'sp.id = rl.pipeline_id', 'left');
        $this->db->join(db_prefix() . 'sales_pipeline_statuses ss', 'ss.id = sp.status', 'left');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = rl.staff_id', 'left');
        $this->db->where('rl.id', (int) $reminder_id);

        $reminder = $this->db->get()->row_array();
        if (!$reminder || empty($reminder['deal_exists'])) {
            return $reminder;
        }

        $context = $this->get_reminder_context($reminder);
        $context['staff_name'] = preg_replace(
            '/\s+/u',
            ' ',
            trim((string) $reminder['staff_name'])
        );
        if ($context['staff_name'] === '') {
            $context['staff_name'] = _l('sales_pipeline_reminder_default_addressee');
        }
        $reminder['staff_name'] = $context['staff_name'];
        $reminder['entity_type'] = $context['entity_type'];
        $reminder['entity_label'] = $context['entity_label'];
        $reminder['entity_name'] = $context['entity_name'];
        $reminder['status_name'] = $context['status_name'];
        $reminder['display_message'] = $this->build_reminder_message($context);

        return $reminder;
    }

    /**
     * Chuẩn hóa loại đối tượng và tên dùng chung cho email/quick response.
     * Pipeline có estimate_id hợp lệ được xem là Báo giá; còn lại là Deal.
     */
    private function get_reminder_context($deal)
    {
        $status_name = trim((string) ($deal['status_name'] ?? ''));
        if ($status_name === '' && !empty($deal['status'])) {
            $status_name = $this->get_status_name($deal['status']);
        }
        if ($status_name === '') {
            $status_name = _l('sales_pipeline_status_unknown');
        }

        $estimate = null;
        if (!empty($deal['estimate_id'])) {
            $estimate = $this->db
                ->select('id, reference_no')
                ->where('id', (int) $deal['estimate_id'])
                ->get(db_prefix() . 'estimates')
                ->row_array();
        }

        if ($estimate) {
            $estimate_number = format_estimate_number($estimate['id']);
            $reference = trim((string) $estimate['reference_no']);
            $entity_name = $estimate_number !== ''
                ? $estimate_number
                : _l('sales_pipeline_reminder_estimate') . ' #' . (int) $estimate['id'];
            if ($reference !== '') {
                $entity_name .= ' - ' . $reference;
            }

            return [
                'entity_type'  => 'estimate',
                'entity_label' => _l('sales_pipeline_reminder_estimate'),
                'entity_name'  => $entity_name,
                'status_name'  => $status_name,
            ];
        }

        $deal_name = trim((string) ($deal['deal_name'] ?? ''));
        if ($deal_name === '') {
            $deal_name = trim((string) ($deal['customer_name'] ?? ''));
        }
        if ($deal_name === '') {
            $deal_name = _l('sales_pipeline_status_unknown');
        }

        return [
            'entity_type'  => 'deal',
            'entity_label' => _l('sales_pipeline_reminder_deal'),
            'entity_name'  => $deal_name,
            'status_name'  => $status_name,
        ];
    }

    /**
     * Nội dung hiển thị trong quick response và được lưu như snapshot của reminder.
     */
    private function build_reminder_message($context)
    {
        $staff_name = preg_replace(
            '/\s+/u',
            ' ',
            trim((string) ($context['staff_name'] ?? ''))
        );
        if ($staff_name === '') {
            $staff_name = _l('sales_pipeline_reminder_default_addressee');
        }

        return implode("\n", [
            _l('sales_pipeline_reminder_snapshot_greeting', [$staff_name, $context['entity_label']]),
            '',
            _l('sales_pipeline_reminder_snapshot_intro'),
            _l('sales_pipeline_reminder_snapshot_yesterday_label') . ' '
                . _l('sales_pipeline_reminder_snapshot_yesterday_body'),
            _l('sales_pipeline_reminder_snapshot_today_label') . ' '
                . _l('sales_pipeline_reminder_snapshot_today_body'),
            _l('sales_pipeline_reminder_snapshot_support_label') . ' '
                . _l('sales_pipeline_reminder_snapshot_support_body'),
            _l('sales_pipeline_reminder_snapshot_pipeline_warning_label') . ' '
                . _l('sales_pipeline_reminder_snapshot_pipeline_warning_body', [$context['entity_label']]),
        ]);
    }

    /**
     * Một reminder chỉ được phản hồi một lần. Row lock ngăn hai request đồng thời
     * cùng cập nhật reminder và tạo activity trùng nhau.
     *
     * @param int    $reminder_id
     * @param string $response
     * @param int    $staff_id Người thực hiện phản hồi
     * @return array
     */
    public function submit_reminder_response($reminder_id, $response, $staff_id)
    {
        $reminder_id = (int) $reminder_id;
        $staff_id = (int) $staff_id;
        $response = trim($response);

        if (
            $reminder_id <= 0
            || $staff_id !== (int) get_staff_user_id()
            || $response === ''
            || mb_strlen($response) > 2000
        ) {
            return ['status' => 'invalid'];
        }

        $this->db->trans_begin();

        $reminder = $this->db->query(
            'SELECT * FROM `' . db_prefix() . 'sales_pipeline_reminders_log` WHERE `id` = ? FOR UPDATE',
            [$reminder_id]
        )->row_array();

        if (!$reminder) {
            $this->db->trans_rollback();
            return ['status' => 'not_found'];
        }

        $can_respond = (int) $reminder['staff_id'] === $staff_id
            || is_admin()
            || has_permission('sales_pipeline', '', 'edit');

        if (!$can_respond) {
            $this->db->trans_rollback();
            return ['status' => 'forbidden'];
        }

        if ($reminder['staff_response'] !== null) {
            $this->db->trans_rollback();
            return ['status' => 'already_responded'];
        }

        $deal_exists = $this->db
            ->select('id')
            ->where('id', (int) $reminder['pipeline_id'])
            ->get(db_prefix() . 'sales_pipeline')
            ->row_array();

        if (!$deal_exists) {
            $this->db->trans_rollback();
            return ['status' => 'deal_not_found'];
        }

        $responded_at = date('Y-m-d H:i:s');
        $this->db->where('id', $reminder_id);
        $this->db->where('staff_response IS NULL', null, false);
        $this->db->update(db_prefix() . 'sales_pipeline_reminders_log', [
            'staff_response' => $response,
            'responded_at'   => $responded_at,
        ]);

        if ($this->db->affected_rows() !== 1) {
            $this->db->trans_rollback();
            return ['status' => 'already_responded'];
        }

        $activity_added = $this->add_activity(
            (int) $reminder['pipeline_id'],
            _l('sales_pipeline_activity_reminder_response') . ' ' . $response,
            null,
            null,
            $staff_id
        );

        if (!$activity_added || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['status' => 'error'];
        }

        if (!$this->db->trans_commit()) {
            $this->db->trans_rollback();
            return ['status' => 'error'];
        }

        return [
            'status'       => 'success',
            'pipeline_id'  => (int) $reminder['pipeline_id'],
            'responded_at' => $responded_at,
        ];
    }

    /**
     * Backward-compatible wrapper; vẫn tuân thủ chính sách phản hồi một lần.
     */
    public function update_reminder_response($reminder_id, $response)
    {
        $result = $this->submit_reminder_response($reminder_id, $response, get_staff_user_id());
        return $result['status'] === 'success';
    }

    // =========================================================================
    // KANBAN VIEW QUERY
    // =========================================================================

    /**
     * Query deals for Kanban view
     * @param int $status_id Status ID
     * @param string $search Search term
     * @param int $page Page number
     * @param array $sort Sort options
     * @param bool $count Whether to return count only
     * @return array|int
     */
    public function do_kanban_query($status_id, $search = '', $page = 1, $sort = [], $count = false)
    {
        $limit = 10; // Số deal hiển thị mỗi cột kanban
        
        $has_view_permission = has_permission('sales_pipeline', '', 'view');
        $staff_id = get_staff_user_id();
        
        $this->db->select(
            db_prefix() . 'sales_pipeline.*,' .
            db_prefix() . 'sales_pipeline_statuses.name as status_name,' .
            db_prefix() . 'sales_pipeline_statuses.color as status_color,' .
            db_prefix() . 'sales_pipeline_statuses.is_won,' .
            db_prefix() . 'sales_pipeline_statuses.is_lost,' .
            'CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as staff_name,' .
            '(CASE WHEN ' . db_prefix() . 'sales_pipeline.cost_price IS NOT NULL 
                THEN ' . db_prefix() . 'sales_pipeline.deal_value - ' . db_prefix() . 'sales_pipeline.cost_price 
                ELSE NULL END) as actual_profit,' .
            '(CASE WHEN ' . db_prefix() . 'sales_pipeline.cost_price IS NOT NULL AND ' . db_prefix() . 'sales_pipeline.deal_value > 0
                THEN ((' . db_prefix() . 'sales_pipeline.deal_value - ' . db_prefix() . 'sales_pipeline.cost_price) / ' . db_prefix() . 'sales_pipeline.deal_value) * 100
                ELSE NULL END) as profit_percentage,' .
            '(CASE WHEN ' . db_prefix() . 'sales_pipeline.cost_price IS NULL THEN 1 ELSE 0 END) as missing_cost_price'
        );

        $this->db->join(
            db_prefix() . 'sales_pipeline_statuses',
            db_prefix() . 'sales_pipeline_statuses.id = ' . db_prefix() . 'sales_pipeline.status',
            'left'
        );
        $this->db->join(
            db_prefix() . 'staff',
            db_prefix() . 'staff.staffid = ' . db_prefix() . 'sales_pipeline.staff_id',
            'left'
        );

        $this->db->where(db_prefix() . 'sales_pipeline.status', $status_id);

        // Search functionality
        if (!empty($search)) {
            $this->db->group_start();
            $this->db->like(db_prefix() . 'sales_pipeline.customer_name', $search);
            $this->db->or_like(db_prefix() . 'sales_pipeline.deal_name', $search);
            $this->db->or_like(db_prefix() . 'sales_pipeline.contact_name', $search);
            $this->db->group_end();
        }

        // Filters from URL/AJAX
        if (!empty($sort['quarter'])) {
            $this->db->where('QUARTER(deal_date)', $sort['quarter']);
        }
        if (!empty($sort['year'])) {
            $this->db->where('YEAR(deal_date)', $sort['year']);
        }
        if (!empty($sort['staff_id'])) {
            $this->db->where(db_prefix() . 'sales_pipeline.staff_id', $sort['staff_id']);
        }
        if (isset($sort['contract_signed']) && $sort['contract_signed'] !== '') {
            $this->db->where(db_prefix() . 'sales_pipeline.contract_signed', (int)$sort['contract_signed']);
        }
        if (isset($sort['invoice_issued']) && $sort['invoice_issued'] !== '') {
            $this->db->where(db_prefix() . 'sales_pipeline.invoice_issued', (int)$sort['invoice_issued']);
        }

        // Permission check: chỉ xem deal của mình nếu không có quyền view global
        if (!$has_view_permission) {
            $this->db->where(db_prefix() . 'sales_pipeline.staff_id', $staff_id);
        }

        if ($count) {
            return $this->db->count_all_results(db_prefix() . 'sales_pipeline');
        }

        // Sorting
        if (!empty($sort['sort_by'])) {
            $sort_order = isset($sort['sort']) && strtolower($sort['sort']) === 'desc' ? 'desc' : 'asc';
            if ($sort['sort_by'] == 'datecreated') {
                $this->db->order_by(db_prefix() . 'sales_pipeline.datecreated', $sort_order);
            } elseif ($sort['sort_by'] == 'deal_date') {
                $this->db->order_by(db_prefix() . 'sales_pipeline.deal_date', $sort_order);
            } elseif ($sort['sort_by'] == 'deal_value') {
                $this->db->order_by(db_prefix() . 'sales_pipeline.deal_value', $sort_order);
            } elseif ($sort['sort_by'] == 'actual_profit') {
                $profit_expression = '(CASE WHEN ' . db_prefix() . 'sales_pipeline.cost_price IS NOT NULL THEN ' . db_prefix() . 'sales_pipeline.deal_value - ' . db_prefix() . 'sales_pipeline.cost_price ELSE NULL END)';
                $this->db->order_by($profit_expression, $sort_order, false);
            }
        } else {
            // Default sorting: by deal_date descending
            $this->db->order_by(db_prefix() . 'sales_pipeline.deal_date', 'desc');
        }

        // Pagination
        if ($page > 1) {
            $page--;
            $position = ($page * $limit);
            $this->db->limit($limit, $position);
        } else {
            $this->db->limit($limit);
        }

        return $this->db->get(db_prefix() . 'sales_pipeline')->result_array();
    }

    // =========================================================================
    // DASHBOARD & ANALYTICS METHODS (NO EMOJI)
    // =========================================================================




    /**
     * Dữ liệu tổng hợp cho Executive Dashboard.
     *
     * @param int|null $staff_id Giới hạn dashboard theo một nhân viên
     * @return array
     */
    public function get_executive_dashboard($staff_id = null, $period = 'this_month')
    {
        $period = $this->resolve_dashboard_period($period);
        $staff_metrics = $this->get_staff_kpi_metrics($staff_id, $period);
        $staff_count = count($staff_metrics);

        $summary = [
            'estimates_today'       => 0,
            'estimates_month'       => 0,
            'revenue_week'          => 0,
            'staff_count'           => $staff_count,
            'estimates_today_goal'  => $staff_count,
            'estimates_month_goal'  => $staff_count * 30,
            'revenue_week_goal'     => $staff_count * 1000000000,
        ];

        foreach ($staff_metrics as $metric) {
            $summary['estimates_today'] += $metric['count_estimates_today'];
            $summary['estimates_month'] += $metric['count_estimates_month'];
            $summary['revenue_week'] += $metric['sum_revenue_this_week'];
        }

        $summary['estimates_today_progress'] = $this->calculate_dashboard_progress(
            $summary['estimates_today'],
            $summary['estimates_today_goal']
        );
        $summary['estimates_month_progress'] = $this->calculate_dashboard_progress(
            $summary['estimates_month'],
            $summary['estimates_month_goal']
        );
        $summary['revenue_week_progress'] = $this->calculate_dashboard_progress(
            $summary['revenue_week'],
            $summary['revenue_week_goal']
        );

        return [
            'summary' => $summary,
            'staff'   => $staff_metrics,
            'periods' => [
                'today'       => date('Y-m-d'),
                'month_start' => date('Y-m-01'),
                'month_end'   => date('Y-m-t'),
                'week_start'  => date('Y-m-d', strtotime('monday this week')),
                'week_end'    => date('Y-m-d', strtotime('sunday this week')),
            ],
            'selected_period' => $period['key'],
            'selected_period_range' => [
                'start' => $period['start'],
                'end'   => $period['end'],
            ],
        ];
    }

    /**
     * Resolve dashboard leaderboard period.
     *
     * @param string|null $period
     * @return array
     */
    public function resolve_dashboard_period($period)
    {
        $today = date('Y-m-d');
        $period = is_string($period) ? trim($period) : '';
        $allowed = ['this_week', 'this_month', 'this_quarter', 'this_year'];
        if (!in_array($period, $allowed, true)) {
            $period = 'this_month';
        }

        if ($period === 'this_week') {
            return [
                'key'   => $period,
                'start' => date('Y-m-d', strtotime('monday this week', strtotime($today))),
                'end'   => date('Y-m-d', strtotime('sunday this week', strtotime($today))),
            ];
        }

        if ($period === 'this_quarter') {
            $month = (int) date('n', strtotime($today));
            $quarter = (int) ceil($month / 3);
            $start_month = (($quarter - 1) * 3) + 1;
            $start = date('Y-' . str_pad((string) $start_month, 2, '0', STR_PAD_LEFT) . '-01', strtotime($today));
            $end = date('Y-m-t', strtotime($start . ' +2 months'));

            return [
                'key'   => $period,
                'start' => $start,
                'end'   => $end,
            ];
        }

        if ($period === 'this_year') {
            return [
                'key'   => $period,
                'start' => date('Y-01-01', strtotime($today)),
                'end'   => date('Y-12-31', strtotime($today)),
            ];
        }

        return [
            'key'   => $period,
            'start' => date('Y-m-01', strtotime($today)),
            'end'   => date('Y-m-t', strtotime($today)),
        ];
    }

    /**
     * KPI theo nhân viên: báo giá hôm nay, báo giá tháng và doanh thu thắng tuần.
     *
     * @param int|null $staff_id
     * @param array    $periods Có thể truyền mốc ngày để kiểm thử hoặc tái sử dụng
     * @return array
     */
    public function get_staff_kpi_metrics($staff_id = null, $periods = [])
    {
        $period = isset($periods['key'], $periods['start'], $periods['end'])
            ? $periods
            : $this->resolve_dashboard_period($periods['period'] ?? 'this_month');

        if ($staff_id === null && !has_permission('sales_pipeline', '', 'view')) {
            $staff_id = get_staff_user_id();
        }

        $today = $periods['today'] ?? date('Y-m-d');
        $month_start = $periods['month_start'] ?? date('Y-m-01', strtotime($today));
        $week_start = $periods['week_start'] ?? date('Y-m-d', strtotime('monday this week', strtotime($today)));
        $week_end = $periods['week_end'] ?? date('Y-m-d', strtotime('sunday this week', strtotime($today)));
        $period_start = $period['start'];
        $period_end = $period['end'];

        $staff_members = $this->get_dashboard_staff_members($staff_id);
        if (empty($staff_members)) {
            return [];
        }

        $metrics = [];
        $staff_ids = [];

        foreach ($staff_members as $staff) {
            $sid = (int) $staff['staffid'];
            $staff_ids[] = $sid;
            $metrics[$sid] = [
                'staff_id'                 => $sid,
                'staff_name'               => trim($staff['firstname'] . ' ' . $staff['lastname']),
                'email'                    => $staff['email'],
                'is_admin'                 => (int) $staff['admin'] === 1,
                'count_estimates_today'    => 0,
                'count_estimates_month'    => 0,
                'sum_revenue_this_week'    => 0,
                'sum_revenue_this_month'   => 0,
                'won_deals_this_week'      => 0,
                'total_pipeline_deals'     => 0,
                'open_pipeline_deals'      => 0,
                'period_estimates'         => 0,
                'period_deals'             => 0,
                'period_won_deals'         => 0,
                'period_revenue'           => 0,
                'period_win_rate'          => 0,
            ];
        }

        $estimate_table = db_prefix() . 'estimates';
        $estimate_owner = '(CASE WHEN ' . $estimate_table . '.sale_agent > 0 THEN '
            . $estimate_table . '.sale_agent ELSE ' . $estimate_table . '.addedfrom END)';

        $this->db->select(
            $estimate_owner . ' as staff_id,'
            . ' SUM(CASE WHEN ' . $estimate_table . '.date = ' . $this->db->escape($today)
            . ' THEN 1 ELSE 0 END) as estimates_today,'
            . ' COUNT(*) as estimates_month',
            false
        );
        $this->db->from($estimate_table);
        $this->db->where($estimate_table . '.date >=', $month_start);
        $this->db->where($estimate_table . '.date <=', $today);
        $this->db->group_start();
        $this->db->where_in($estimate_table . '.sale_agent', $staff_ids);
        $this->db->or_group_start();
        $this->db->where($estimate_table . '.sale_agent', 0);
        $this->db->where_in($estimate_table . '.addedfrom', $staff_ids);
        $this->db->group_end();
        $this->db->group_end();
        $this->db->group_by($estimate_owner, false);

        foreach ($this->db->get()->result_array() as $estimate_metric) {
            $sid = (int) $estimate_metric['staff_id'];
            if (isset($metrics[$sid])) {
                $metrics[$sid]['count_estimates_today'] = (int) $estimate_metric['estimates_today'];
                $metrics[$sid]['count_estimates_month'] = (int) $estimate_metric['estimates_month'];
            }
        }

        $this->db->select(
            $estimate_owner . ' as staff_id, COUNT(*) as period_estimates',
            false
        );
        $this->db->from($estimate_table);
        $this->db->where($estimate_table . '.date >=', $period_start);
        $this->db->where($estimate_table . '.date <=', $period_end);
        $this->db->group_start();
        $this->db->where_in($estimate_table . '.sale_agent', $staff_ids);
        $this->db->or_group_start();
        $this->db->where($estimate_table . '.sale_agent', 0);
        $this->db->where_in($estimate_table . '.addedfrom', $staff_ids);
        $this->db->group_end();
        $this->db->group_end();
        $this->db->group_by($estimate_owner, false);

        foreach ($this->db->get()->result_array() as $period_estimate_metric) {
            $sid = (int) $period_estimate_metric['staff_id'];
            if (isset($metrics[$sid])) {
                $metrics[$sid]['period_estimates'] = (int) $period_estimate_metric['period_estimates'];
            }
        }

        $pipeline_table = db_prefix() . 'sales_pipeline';
        $status_table = db_prefix() . 'sales_pipeline_statuses';
        $escaped_week_start = $this->db->escape($week_start);
        $escaped_week_end = $this->db->escape($week_end);
        $escaped_month_start = $this->db->escape($month_start);
        $escaped_today = $this->db->escape($today);
        $escaped_period_start = $this->db->escape($period_start);
        $escaped_period_end = $this->db->escape($period_end);

        $this->db->select(
            'sp.staff_id,'
            . ' COUNT(sp.id) as total_pipeline_deals,'
            . ' SUM(CASE WHEN ss.is_won = 0 AND ss.is_lost = 0 THEN 1 ELSE 0 END) as open_pipeline_deals,'
            . ' SUM(CASE WHEN ss.is_won = 1 AND sp.deal_date >= ' . $escaped_week_start
            . ' AND sp.deal_date <= ' . $escaped_week_end . ' THEN 1 ELSE 0 END) as won_deals_week,'
            . ' COALESCE(SUM(CASE WHEN ss.is_won = 1 AND sp.deal_date >= ' . $escaped_week_start
            . ' AND sp.deal_date <= ' . $escaped_week_end . ' THEN sp.deal_value ELSE 0 END), 0) as revenue_week,'
            . ' COALESCE(SUM(CASE WHEN ss.is_won = 1 AND sp.deal_date >= ' . $escaped_month_start
            . ' AND sp.deal_date <= ' . $escaped_today . ' THEN sp.deal_value ELSE 0 END), 0) as revenue_month,'
            . ' SUM(CASE WHEN sp.deal_date >= ' . $escaped_period_start
            . ' AND sp.deal_date <= ' . $escaped_period_end . ' THEN 1 ELSE 0 END) as period_deals,'
            . ' SUM(CASE WHEN ss.is_won = 1 AND sp.deal_date >= ' . $escaped_period_start
            . ' AND sp.deal_date <= ' . $escaped_period_end . ' THEN 1 ELSE 0 END) as period_won_deals,'
            . ' COALESCE(SUM(CASE WHEN ss.is_won = 1 AND sp.deal_date >= ' . $escaped_period_start
            . ' AND sp.deal_date <= ' . $escaped_period_end . ' THEN sp.deal_value ELSE 0 END), 0) as period_revenue',
            false
        );
        $this->db->from($pipeline_table . ' sp');
        $this->db->join($status_table . ' ss', 'ss.id = sp.status', 'left');
        $this->db->where_in('sp.staff_id', $staff_ids);
        $this->db->group_by('sp.staff_id');

        foreach ($this->db->get()->result_array() as $pipeline_metric) {
            $sid = (int) $pipeline_metric['staff_id'];
            if (isset($metrics[$sid])) {
                $metrics[$sid]['sum_revenue_this_week'] = (float) $pipeline_metric['revenue_week'];
                $metrics[$sid]['sum_revenue_this_month'] = (float) $pipeline_metric['revenue_month'];
                $metrics[$sid]['won_deals_this_week'] = (int) $pipeline_metric['won_deals_week'];
                $metrics[$sid]['total_pipeline_deals'] = (int) $pipeline_metric['total_pipeline_deals'];
                $metrics[$sid]['open_pipeline_deals'] = (int) $pipeline_metric['open_pipeline_deals'];
                $metrics[$sid]['period_deals'] = (int) $pipeline_metric['period_deals'];
                $metrics[$sid]['period_won_deals'] = (int) $pipeline_metric['period_won_deals'];
                $metrics[$sid]['period_revenue'] = (float) $pipeline_metric['period_revenue'];
            }
        }

        $result = [];
        foreach ($metrics as $metric) {
            $has_activity = $metric['count_estimates_month'] > 0
                || $metric['total_pipeline_deals'] > 0;

            if ($staff_id === null && $metric['is_admin'] && !$has_activity) {
                continue;
            }

            $metric['estimates_today_progress'] = $this->calculate_dashboard_progress(
                $metric['count_estimates_today'],
                1
            );
            $metric['estimates_month_progress'] = $this->calculate_dashboard_progress(
                $metric['count_estimates_month'],
                30
            );
            $metric['revenue_week_progress'] = $this->calculate_dashboard_progress(
                $metric['sum_revenue_this_week'],
                1000000000
            );
            $metric['estimates_today_status'] = $this->resolve_dashboard_kpi_status($metric['estimates_today_progress']);
            $metric['estimates_month_status'] = $this->resolve_dashboard_kpi_status($metric['estimates_month_progress']);
            $metric['revenue_week_status'] = $this->resolve_dashboard_kpi_status($metric['revenue_week_progress']);
            $metric['period_win_rate'] = $metric['period_deals'] > 0
                ? round(($metric['period_won_deals'] / $metric['period_deals']) * 100, 1)
                : 0;
            $metric['overall_progress'] = round((
                $metric['estimates_today_progress']
                + $metric['estimates_month_progress']
                + $metric['revenue_week_progress']
            ) / 3);
            $metric['kpi_status'] = $this->resolve_dashboard_kpi_status($metric['overall_progress']);
            unset($metric['is_admin']);
            $result[] = $metric;
        }

        usort($result, function ($first, $second) {
            if ($first['period_revenue'] == $second['period_revenue']) {
                if ($first['period_estimates'] == $second['period_estimates']) {
                    return strcasecmp($first['staff_name'], $second['staff_name']);
                }
                return $second['period_estimates'] <=> $first['period_estimates'];
            }
            return $second['period_revenue'] <=> $first['period_revenue'];
        });

        return $result;
    }


    /**
     * Danh sách nhân viên có quyền sử dụng Sales Pipeline.
     */
    private function get_dashboard_staff_members($staff_id = null)
    {
        $this->db->select('staffid, firstname, lastname, email, admin');
        $this->db->where('active', 1);
        if ($staff_id !== null) {
            $this->db->where('staffid', (int) $staff_id);
        }
        $this->db->order_by('firstname', 'asc');
        $staff_members = $this->db->get(db_prefix() . 'staff')->result_array();

        if ($staff_id !== null) {
            return $staff_members;
        }

        return array_values(array_filter($staff_members, function ($staff) {
            return staff_can('view', 'sales_pipeline', $staff['staffid'])
                || staff_can('view_own', 'sales_pipeline', $staff['staffid']);
        }));
    }

    private function calculate_dashboard_progress($value, $target)
    {
        if ($target <= 0) {
            return 0;
        }

        return round(min(100, max(0, ((float) $value / (float) $target) * 100)), 1);
    }

    private function resolve_dashboard_kpi_status($progress)
    {
        if ($progress >= 100) {
            return 'success';
        }
        if ($progress >= 60) {
            return 'warning';
        }

        return 'danger';
    }

    /**
     * Thống kê phản hồi nhắc nhở tự động
     */
    public function get_reminder_response_stats($filters = [])
    {
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 20;
        $limit = max(1, min(50, $limit));

        $this->db->select('
            rl.*,
            CONCAT(s.firstname, " ", s.lastname) as staff_name,
            s.email as staff_email,
            sp.deal_name,
            sp.customer_name
        ');
        $this->db->from(db_prefix() . 'sales_pipeline_reminders_log rl');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = rl.staff_id', 'left');
        $this->db->join(db_prefix() . 'sales_pipeline sp', 'sp.id = rl.pipeline_id', 'left');

        if (!empty($filters['staff_id'])) {
            $this->db->where('rl.staff_id', $filters['staff_id']);
        } elseif (array_key_exists('staff_ids', $filters)) {
            $staff_ids = array_values(array_filter(array_map('intval', (array) $filters['staff_ids'])));
            if (empty($staff_ids)) {
                return [];
            }
            $this->db->where_in('rl.staff_id', $staff_ids);
        }
        if (!empty($filters['month'])) {
            $this->db->where('MONTH(rl.sent_at)', $filters['month']);
        }
        if (!empty($filters['year'])) {
            $this->db->where('YEAR(rl.sent_at)', $filters['year']);
        }

        $this->db->order_by('CASE WHEN rl.staff_response IS NULL THEN 0 ELSE 1 END', 'ASC', false);
        $this->db->order_by('COALESCE(rl.responded_at, rl.sent_at)', 'DESC', false);
        $this->db->order_by('rl.id', 'DESC');
        $this->db->limit($limit);

        return $this->db->get()->result_array();
    }

    /**
     * Lấy danh sách deal đang mở (is_won = 0 AND is_lost = 0) và tổng số deal đang mở theo staff_id
     * @param int $staff_id ID nhân viên
     * @param int $limit Giới hạn số record (mặc định 10)
     * @return array ['deals' => array, 'total' => int]
     */
    public function get_staff_open_deals($staff_id, $limit = 10)
    {
        $staff_id = (int) $staff_id;
        if ($staff_id <= 0) {
            return ['deals' => [], 'total' => 0];
        }

        $limit = max(1, min(50, (int) $limit));

        $this->db->select('COUNT(' . db_prefix() . 'sales_pipeline.id) as total');
        $this->db->from(db_prefix() . 'sales_pipeline');
        $this->db->join(
            db_prefix() . 'sales_pipeline_statuses',
            db_prefix() . 'sales_pipeline_statuses.id = ' . db_prefix() . 'sales_pipeline.status',
            'inner'
        );
        $this->db->where(db_prefix() . 'sales_pipeline.staff_id', $staff_id);
        $this->db->where(db_prefix() . 'sales_pipeline_statuses.is_won', 0);
        $this->db->where(db_prefix() . 'sales_pipeline_statuses.is_lost', 0);
        $total = (int) $this->db->get()->row()->total;

        if ($total === 0) {
            return ['deals' => [], 'total' => 0];
        }

        $this->db->select(
            db_prefix() . 'sales_pipeline.id, ' .
            db_prefix() . 'sales_pipeline.deal_name, ' .
            db_prefix() . 'sales_pipeline.customer_name, ' .
            db_prefix() . 'sales_pipeline.deal_value, ' .
            db_prefix() . 'sales_pipeline.deal_date, ' .
            db_prefix() . 'sales_pipeline.status, ' .
            db_prefix() . 'sales_pipeline_statuses.name as status_name, ' .
            db_prefix() . 'sales_pipeline_statuses.color as status_color'
        );
        $this->db->from(db_prefix() . 'sales_pipeline');
        $this->db->join(
            db_prefix() . 'sales_pipeline_statuses',
            db_prefix() . 'sales_pipeline_statuses.id = ' . db_prefix() . 'sales_pipeline.status',
            'inner'
        );
        $this->db->where(db_prefix() . 'sales_pipeline.staff_id', $staff_id);
        $this->db->where(db_prefix() . 'sales_pipeline_statuses.is_won', 0);
        $this->db->where(db_prefix() . 'sales_pipeline_statuses.is_lost', 0);
        $this->db->order_by(db_prefix() . 'sales_pipeline.deal_date', 'DESC');
        $this->db->order_by(db_prefix() . 'sales_pipeline.id', 'DESC');
        $this->db->limit($limit);

        $deals = $this->db->get()->result_array();

        return [
            'deals' => $deals,
            'total' => $total,
        ];
    }

}
