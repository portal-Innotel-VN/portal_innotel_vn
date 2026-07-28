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
     */
    public function add_activity($pipeline_id, $description, $old_status = null, $new_status = null)
    {
        $this->db->insert(db_prefix() . 'sales_pipeline_activity', [
            'pipeline_id' => $pipeline_id,
            'staff_id'    => get_staff_user_id(),
            'description' => $description,
            'old_status'  => $old_status,
            'new_status'  => $new_status,
            'datecreated' => date('Y-m-d H:i:s'),
        ]);
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
     * @return array
     */
    public function get_summary($quarter = null, $year = null, $staff_id = null, $search = '')
    {
        if ($year === null) {
            $year = date('Y');
        }

        $base_where = [];
        if ($quarter) $base_where['QUARTER(deal_date)'] = $quarter;
        $base_where['YEAR(deal_date)'] = $year;
        if ($staff_id) $base_where['staff_id'] = $staff_id;

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

        $old_cost_price = $deal['cost_price'];

        $this->db->where('id', $deal_id);
        $this->db->update(db_prefix() . 'sales_pipeline', [
            'cost_price'    => $cost_price,
            'datemodified'  => date('Y-m-d H:i:s'),
        ]);

        if ($this->db->affected_rows() > 0) {
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

        $message = 'Deal "' . $deal['deal_name'] . '" — ' . $deal['customer_name']
                 . ' (Doanh số: ' . app_format_money($deal['deal_value'], '') . '): '
                 . 'Tuần này có kết quả chưa? Tại sao chưa đóng được deal?';

        // Gửi notification nội bộ
        add_notification([
            'description'     => 'sales_pipeline_reminder',
            'touserid'        => $deal['staff_id'],
            'fromuserid'      => null,
            'link'            => 'sales_pipeline/deal/' . $deal['id'],
            'additional_data' => serialize([
                $deal['deal_name'],
                $deal['customer_name'],
            ]),
        ]);

        // Gửi email (nếu staff có email)
        if (!empty($staff->email)) {
            $this->load->model('emails_model');

            $email_subject = '[Nhắc nhở Deal của bạn] ' . $deal['deal_name'] . ' - ' . $deal['customer_name'];
            $email_body = '<p>Xin chào <b>' . $staff->firstname . ' ' . $staff->lastname . '</b>,</p>'
                        . '<p>Hệ thống CRM nhắc nhở bạn cập nhật tiến độ deal:</p>'
                        . '<ul>'
                        . '<li><b>Khách hàng:</b> ' . $deal['customer_name'] . '</li>'
                        . '<li><b>Deal:</b> ' . $deal['deal_name'] . '</li>'
                        . '<li><b>Doanh số:</b> ' . number_format($deal['deal_value']) . ' VNĐ</li>'
                        . '<li><b>Ngày tạo:</b> ' . _d($deal['deal_date']) . '</li>'
                        . '</ul>'
                        . '<p><b>Tuần này có kết quả chưa? Tại sao chưa đóng được deal?</b></p>'
                        . '<p><a href="' . admin_url('sales_pipeline/deal/' . $deal['id']) . '">Cập nhật tiến độ tại đây</a></p>';

            $this->emails_model->send_simple_email($staff->email, $email_subject, $email_body);
        }

        // Log nhắc nhở
        $this->db->insert(db_prefix() . 'sales_pipeline_reminders_log', [
            'pipeline_id'   => $deal['id'],
            'staff_id'      => $deal['staff_id'],
            'reminder_type' => 'email',
            'message'       => $message,
            'sent_at'       => date('Y-m-d H:i:s'),
        ]);

        // Cập nhật thời gian nhắc nhở cuối
        $this->db->where('id', $deal['id']);
        $this->db->update(db_prefix() . 'sales_pipeline', [
            'last_reminder_sent' => date('Y-m-d H:i:s'),
        ]);
    }

    // =========================================================================
    // REMINDERS: Phản hồi nhắc nhở
    // =========================================================================

    /**
     * Cập nhật phản hồi của nhân viên cho một reminder
     * @param int $reminder_id ID của reminder log
     * @param string $response Nội dung phản hồi
     * @return bool
     */
    public function update_reminder_response($reminder_id, $response)
    {
        $this->db->where('id', $reminder_id);
        $this->db->update(db_prefix() . 'sales_pipeline_reminders_log', [
            'staff_response' => $response,
            'responded_at'   => date('Y-m-d H:i:s'),
        ]);
        return $this->db->affected_rows() > 0;
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
            $sort_order = $sort['sort'] ?? 'asc';
            if ($sort['sort_by'] == 'datecreated') {
                $this->db->order_by(db_prefix() . 'sales_pipeline.datecreated', $sort_order);
            } elseif ($sort['sort_by'] == 'deal_date') {
                $this->db->order_by(db_prefix() . 'sales_pipeline.deal_date', $sort_order);
            } elseif ($sort['sort_by'] == 'deal_value') {
                $this->db->order_by(db_prefix() . 'sales_pipeline.deal_value', $sort_order);
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
}
