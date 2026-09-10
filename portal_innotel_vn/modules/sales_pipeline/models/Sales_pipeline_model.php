<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once dirname(__DIR__) . '/includes/dashboard_period.php';

class Sales_pipeline_model extends App_Model
{
    private $estimate_copy_source_id;

    public function __construct()
    {
        parent::__construct();
        $this->ensure_schema();
    }

    /**
     * Idempotent self-healing schema sync:
     * Guarantees all required tables & columns up to v1.1.4 exist even if Perfex CRM
     * skipped running App_module_migration due to tblmodules.installed_version match.
     */
    public function ensure_schema()
    {
        if (get_option('sp_schema_v114_synced') !== '1') {
            require_once dirname(__DIR__) . '/includes/estimate_group_schema.php';
            require_once dirname(__DIR__) . '/includes/reminder_repository_schema.php';
            require_once dirname(__DIR__) . '/includes/architecture_113_schema.php';

            sales_pipeline_ensure_estimate_group_schema($this);
            sales_pipeline_ensure_reminder_repository_schema($this);
            sales_pipeline_ensure_deal_estimate_groups_schema($this);
            sales_pipeline_ensure_architecture_113_schema($this);
            sales_pipeline_run_architecture_113_backfill($this);
            sales_pipeline_upgrade_target_options_113();

            update_option('sp_schema_v114_synced', '1');
        }
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
        $data['contract_signed']  = !empty($data['contract_signed']) ? 1 : 0;
        $data['invoice_issued']   = !empty($data['invoice_issued']) ? 1 : 0;
        $data['reminder_enabled'] = isset($data['reminder_enabled']) ? (!empty($data['reminder_enabled']) ? 1 : 0) : 1;

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

        // Xử lý checkbox (chỉ cập nhật nếu trường tồn tại trong payload $data để không ghi đè khi update từng phần)
        if (array_key_exists('contract_signed', $data)) {
            $data['contract_signed'] = !empty($data['contract_signed']) ? 1 : 0;
        }
        if (array_key_exists('invoice_issued', $data)) {
            $data['invoice_issued'] = !empty($data['invoice_issued']) ? 1 : 0;
        }
        if (array_key_exists('reminder_enabled', $data)) {
            $data['reminder_enabled'] = !empty($data['reminder_enabled']) ? 1 : 0;
        }

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
     * Persist the manual synchronization lock and its audit activity atomically.
     * Authorization and request validation remain controller responsibilities.
     *
     * @param int    $deal_id
     * @param bool   $is_locked
     * @param int    $staff_id
     * @param string $reason
     * @return bool
     */
    public function set_deal_manual_lock($deal_id, $is_locked, $staff_id, $reason = '')
    {
        $deal_id = (int) $deal_id;
        $staff_id = (int) $staff_id;
        $is_locked = (bool) $is_locked;
        $reason = trim((string) $reason);
        if ($deal_id <= 0) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->trans_start();
        $this->db->where('id', $deal_id)->update(db_prefix() . 'sales_pipeline', [
            'is_manual_lock'     => $is_locked ? 1 : 0,
            'manual_lock_by'     => $is_locked ? $staff_id : null,
            'manual_lock_at'     => $is_locked ? $now : null,
            'manual_lock_reason' => $is_locked ? $reason : null,
            'datemodified'       => $now,
        ]);

        $activity_message = $is_locked
            ? _l('sales_pipeline_activity_deal_locked', [$reason])
            : _l('sales_pipeline_activity_deal_unlocked');
        $this->add_activity($deal_id, $activity_message, null, null, $staff_id);
        $this->db->trans_complete();

        return $this->db->trans_status();
    }

    /**
     * Persist an approved Finance lock mutation on a Deal or Estimate Group.
     *
     * @param string $entity_type deal|estimate_group
     * @param int    $entity_id
     * @param bool   $is_locked
     * @param int    $staff_id
     * @param string $reference
     * @param string $reason
     * @return array
     */
    public function set_finance_lock($entity_type, $entity_id, $is_locked, $staff_id, $reference = '', $reason = '')
    {
        $tables = [
            'deal'           => db_prefix() . 'sales_pipeline',
            'estimate_group' => db_prefix() . 'sales_pipeline_estimate_groups',
        ];
        $entity_type = (string) $entity_type;
        $entity_id = (int) $entity_id;
        if ($entity_id <= 0 || !isset($tables[$entity_type])) {
            return ['success' => false, 'reason' => 'invalid_entity'];
        }

        $table = $tables[$entity_type];
        $existing = $this->db->select('id')->where('id', $entity_id)->get($table)->row_array();
        if (!$existing) {
            return ['success' => false, 'reason' => 'not_found'];
        }

        $is_locked = (bool) $is_locked;
        $now = date('Y-m-d H:i:s');
        $update = [
            'is_finance_locked'          => $is_locked ? 1 : 0,
            'finance_locked_at'          => $is_locked ? $now : null,
            'finance_locked_by'          => $is_locked ? (int) $staff_id : null,
            'finance_approval_reference' => $is_locked ? trim((string) $reference) : null,
            'finance_lock_reason'        => $is_locked ? trim((string) $reason) : null,
        ];

        $this->db->trans_start();
        $this->db->where('id', $entity_id)->update($table, $update);
        $this->db->trans_complete();

        return [
            'success'    => $this->db->trans_status(),
            'reason'     => $this->db->trans_status() ? null : 'database_error',
            'locked_at'  => $now,
            'is_locked'  => $is_locked,
            'entity_id'  => $entity_id,
            'entity_type'=> $entity_type,
        ];
    }

    /**
     * Save normalized module options in one transaction.
     * Input validation and option allow-listing must be completed by the caller.
     *
     * @param array $normalized_options
     * @return array{success:bool,changed:array}
     */
    public function save_pipeline_settings(array $normalized_options)
    {
        $changed = [];
        $this->db->trans_start();
        foreach ($normalized_options as $key => $value) {
            if ((string) get_option($key) === (string) $value) {
                continue;
            }
            $changed[] = $key;
            update_option($key, $value);
        }
        $this->db->trans_complete();

        return [
            'success' => $this->db->trans_status(),
            'changed' => $changed,
        ];
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

        $this->db->trans_start();

        // 1. Xóa các liên kết trong bảng bridge (không xóa Estimate Group, Version, hay Audit History)
        $bridgeTable = db_prefix() . 'sales_pipeline_deal_estimate_groups';
        if ($this->db->table_exists($bridgeTable)) {
            $this->db->where('pipeline_id', $id);
            $this->db->delete($bridgeTable);
        }

        // 2. Xóa activity liên quan
        $this->db->where('pipeline_id', $id);
        $this->db->delete(db_prefix() . 'sales_pipeline_activity');

        // 3. Xóa log nhắc nhở
        $this->db->where('pipeline_id', $id);
        $this->db->delete(db_prefix() . 'sales_pipeline_reminders_log');

        // 4. Xóa deal row chính
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'sales_pipeline');

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return false;
        }

        if ($deal) {
            log_activity(_l('sales_pipeline_log_delete_deal', [$id, $deal['deal_name']]));
        }

        return true;
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
        $this->load->library('sales_pipeline/Reminder_engine');
        return $this->reminder_engine->process();
    }

    public function process_reminder_rules()
    {
        return $this->process_weekly_reminders();
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
        if (!$reminder) {
            return $reminder;
        }

        $snapshot = json_decode((string) ($reminder['snapshot_json'] ?? ''), true);
        $snapshot = is_array($snapshot) ? $snapshot : [];
        $reminder['snapshot'] = $snapshot;
        $reminder['response_required'] = (int) ($reminder['response_required'] ?? 1);
        $reminder['display_message'] = (string) ($reminder['message'] ?? '');

        if ($reminder['entity_type'] === 'estimate') {
            $reminder['entity_label'] = _l('sales_pipeline_reminder_estimate');
            $reminder['entity_name'] = $snapshot['estimate_number'] ?? ('#' . (int) $reminder['entity_id']);
            $reminder['customer_name'] = $snapshot['customer_name'] ?? '';
            $reminder['deal_value'] = $snapshot['estimate_total'] ?? 0;
            $reminder['deal_date'] = $snapshot['expirydate'] ?? null;
            $reminder['status_name'] = $snapshot['status_label'] ?? '';
            $canViewEstimate = is_admin()
                || staff_can('view', 'estimates')
                || ((int) $reminder['staff_id'] === (int) get_staff_user_id() && staff_can('view_own', 'estimates'));
            $reminder['entity_url'] = $canViewEstimate
                ? admin_url('estimates/list_estimates/' . (int) $reminder['entity_id'] . '#' . (int) $reminder['entity_id'])
                : null;
        } elseif (in_array($reminder['entity_type'], ['staff_estimate_period', 'staff_deal_period', 'staff_deal_backlog'], true)) {
            $reminder['entity_label'] = in_array($reminder['entity_type'], ['staff_deal_period', 'staff_deal_backlog'], true)
                ? ($reminder['entity_type'] === 'staff_deal_backlog' ? _l('sales_pipeline_reminder_deal_backlog') : _l('sales_pipeline_reminder_deal_period'))
                : _l('sales_pipeline_reminder_estimate_period');
            $reminder['entity_name'] = $reminder['title'] ?: $reminder['rule_code'];
            $reminder['customer_name'] = '-';
            $reminder['deal_value'] = null;
            $severity = strtolower((string) ($reminder['severity'] ?? ''));
            $severityKey = 'sales_pipeline_severity_' . $severity;
            $translatedSeverity = _l($severityKey);
            $reminder['status_name'] = ($translatedSeverity !== $severityKey)
                ? $translatedSeverity
                : strtoupper((string) ($reminder['severity'] ?? ''));
            $reminder['entity_url'] = in_array($reminder['entity_type'], ['staff_deal_period', 'staff_deal_backlog'], true)
                ? admin_url('sales_pipeline/dashboard')
                : admin_url('sales_pipeline/dashboard?dashboard_tab=estimates');
        } else {
            $reminder['entity_label'] = _l('sales_pipeline_reminder_deal');
            $reminder['entity_name'] = trim((string) $reminder['deal_name']) ?: trim((string) $reminder['customer_name']);
            $reminder['customer_name'] = (string) $reminder['customer_name'];
            $reminder['deal_value'] = (float) ($reminder['deal_value'] ?? 0);
            $reminder['deal_date'] = $reminder['deal_date'] ?? null;
            $reminder['status_name'] = trim((string) $reminder['status_name']) ?: _l('sales_pipeline_status_unknown');
            $reminder['entity_url'] = !empty($reminder['pipeline_id'])
                ? admin_url('sales_pipeline/deal/' . (int) $reminder['pipeline_id']) : null;
        }

        // The stored entity type is authoritative. A Deal may be linked to an
        // estimate, but its reminder must still open and describe that Deal.
        $context = [
            'entity_type'  => $reminder['entity_type'],
            'entity_label' => $reminder['entity_label'] ?? _l('sales_pipeline_reminder_deal'),
            'entity_name'  => $reminder['entity_name'] ?? '',
            'status_name'  => $reminder['status_name'] ?? '',
        ];
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
        if ($reminder['display_message'] === '') {
            $reminder['display_message'] = $this->build_reminder_message($context);
        }
        $reminder = $this->hydrate_reminder_response_snapshot($reminder, $snapshot);

        return $reminder;
    }

    /**
     * Prepare every presentation field once in the backend. The response view
     * only renders this snapshot and never guesses whether a reminder is for a
     * Deal or an Estimate.
     */
    private function hydrate_reminder_response_snapshot(array $reminder, array $snapshot)
    {
        $isEstimate = $reminder['entity_type'] === 'estimate';
        $isDeal = $reminder['entity_type'] === 'deal';
        $amount = (float) ($reminder['deal_value'] ?? 0);
        $amountLabel = $isEstimate
            ? _l('sales_pipeline_reminder_total_amount')
            : _l('sales_pipeline_reminder_revenue');

        $contextDate = in_array($reminder['entity_type'], ['staff_estimate_period', 'staff_deal_period', 'staff_deal_backlog'], true)
            ? trim((string) (!empty($reminder['sent_at']) ? $reminder['sent_at'] : ($reminder['created_at'] ?? '')))
            : trim((string) ($reminder['deal_date'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $contextDate)) {
            $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', substr($contextDate, 0, 10));
            if ($parsedDate instanceof DateTimeImmutable) {
                $contextDate = $parsedDate->format('d/m/y');
            }
        }
        $reminder['context_date_label'] = $isEstimate
            ? _l('sales_pipeline_reminder_estimate_expiry')
            : _l('sales_pipeline_date');
        $reminder['context_date'] = $contextDate !== '' ? $contextDate : '-';
        $reminder['quick_response_title'] = _l('sales_pipeline_quick_response_title');
        $reminder['context_cards'] = [
            ['label' => $reminder['entity_label'], 'value' => $reminder['entity_name'] ?: '-'],
            ['label' => _l('sales_pipeline_customer_name'), 'value' => $reminder['customer_name'] ?: '-'],
            ['label' => _l('sales_pipeline_status'), 'value' => $reminder['status_name'] ?: '-'],
            ['label' => $reminder['context_date_label'], 'value' => $reminder['context_date']],
        ];
        $reminder['target_label'] = _l('sales_pipeline_target');
        $reminder['target_summary'] = ($isDeal || $isEstimate)
            ? _l('sales_pipeline_reminder_snapshot_target', [
                $reminder['entity_label'],
                $reminder['entity_name'],
                $reminder['customer_name'] ?: '-',
                $amountLabel,
                number_format($amount, 0, ',', '.'),
            ])
            : $reminder['display_message'];
        if ($reminder['entity_type'] === 'staff_estimate_period') {
            $period = (string) ($reminder['period_key'] ?? '');
            $snapshotActual = (float) ($snapshot['actual_count'] ?? 0);
            $snapshotRequired = (float) ($snapshot['required_count'] ?? 0);
            if (in_array($reminder['rule_code'], ['ESTIMATE_DAILY_MIN_COUNT', 'ESTIMATE_MONTHLY_MIN_COUNT'], true)) {
                $reminder['reason_text'] = _l('sales_pipeline_reminder_period_count_reason', [
                    number_format($snapshotActual, 0, ',', '.'),
                    number_format($snapshotRequired, 0, ',', '.'),
                ]);
                if ($reminder['rule_code'] === 'ESTIMATE_MONTHLY_MIN_COUNT') {
                    $periodDate = DateTimeImmutable::createFromFormat('!Y-m', $period);
                    $periodLabel = $periodDate ? $periodDate->format('m/y') : $period;
                    $reminder['target_summary'] = _l('sales_pipeline_reminder_period_month_target', [$periodLabel]);
                } else {
                    $periodDate = DateTimeImmutable::createFromFormat('!Y-m-d', $period);
                    $periodLabel = $periodDate ? $periodDate->format('d/m/y') : $period;
                    $reminder['target_summary'] = _l('sales_pipeline_reminder_period_day_target', [$periodLabel]);
                }
            } elseif ($reminder['rule_code'] === 'ESTIMATE_WEEKLY_MIN_REVENUE') {
                $revenue = number_format((float) ($snapshot['accepted_revenue'] ?? 0), 0, ',', '.');
                $requiredRevenue = number_format((float) ($snapshot['required_revenue'] ?? 0), 0, ',', '.');
                $reminder['reason_text'] = _l('sales_pipeline_reminder_period_revenue_reason', [$revenue, $requiredRevenue]);
                $weekParts = explode('-W', $period, 2);
                $weekStart = count($weekParts) === 2
                    ? (new DateTimeImmutable())->setISODate((int) $weekParts[0], (int) $weekParts[1], 1)
                    : null;
                $weekEnd = $weekStart ? $weekStart->modify('+6 days') : null;
                $range = $weekStart && $weekEnd
                    ? _l('sales_pipeline_date_range', [$weekStart->format('d/m/y'), $weekEnd->format('d/m/y')])
                    : $period;
                $reminder['target_summary'] = _l('sales_pipeline_reminder_period_week_target', [$range]);
            }
        } elseif ($reminder['entity_type'] === 'staff_deal_period' && $reminder['rule_code'] === 'DEAL_PIPELINE_MIN_COUNT') {
            $snapshotActual = (int) ($snapshot['actual_count'] ?? 0);
            $snapshotRequired = (int) ($snapshot['required_count'] ?? 0);
            $reminder['reason_text'] = _l('sales_pipeline_reminder_deal_period_count_reason', [
                number_format($snapshotActual, 0, ',', '.'),
                number_format($snapshotRequired, 0, ',', '.'),
            ]);
            $reminder['target_summary'] = _l('sales_pipeline_reminder_deal_period_week_target', [
                (string) ($reminder['period_key'] ?? ''),
            ]);
        } elseif ($reminder['rule_code'] === 'DEAL_STALE_FOLLOW_UP') {
            $reminder['reason_text'] = _l('sales_pipeline_deal_stale_reason');
        } elseif ($reminder['rule_code'] === 'DEAL_STALE_BACKLOG') {
            $reminder['reason_text'] = _l('sales_pipeline_deal_stale_backlog_title');
        }
        $reminder['target_question'] = _l('sales_pipeline_reminder_snapshot_target_question');
        $reminder['snapshot_intro'] = _l('sales_pipeline_reminder_snapshot_intro');
        $reminder['snapshot_questions'] = [
            [
                'label' => _l('sales_pipeline_reminder_snapshot_yesterday_label'),
                'body'  => _l('sales_pipeline_reminder_snapshot_yesterday_body'),
            ],
            [
                'label' => _l('sales_pipeline_reminder_snapshot_today_label'),
                'body'  => _l('sales_pipeline_reminder_snapshot_today_body'),
            ],
            [
                'label' => _l('sales_pipeline_reminder_snapshot_support_label'),
                'body'  => _l('sales_pipeline_reminder_snapshot_support_body'),
            ],
        ];
        $reminder['snapshot_warning_label'] = _l('sales_pipeline_reminder_snapshot_pipeline_warning_label');
        $reminder['snapshot_warning_body'] = _l(
            'sales_pipeline_reminder_snapshot_pipeline_warning_body',
            [$reminder['entity_label']]
        );
        $reminder['detail_action_label'] = _l('sales_pipeline_reminder_view_entity', [$reminder['entity_label']]);

        return $reminder;
    }

    /**
     * Chuẩn hóa loại đối tượng và tên dùng chung cho email/quick response.
     * Pipeline có estimate_id hợp lệ được xem là Báo giá; còn lại là Deal.
     *
     * @deprecated Replaced by Reminder_engine context builders. Kept temporarily for rollback compatibility.
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

        $can_respond = (int) $reminder['staff_id'] === $staff_id;

        if (!$can_respond) {
            $this->db->trans_rollback();
            return ['status' => 'forbidden'];
        }

        $this->load->library('sales_pipeline/Reminder_response_guard');
        $guard_status = $this->reminder_response_guard->check($reminder);
        if ($guard_status !== 'allowed') {
            $this->db->trans_rollback();
            return ['status' => $guard_status];
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

        if (!empty($reminder['pipeline_id'])) {
            $activity_added = $this->add_activity(
                (int) $reminder['pipeline_id'],
                _l('sales_pipeline_activity_reminder_response') . ' ' . $response,
                null, null, $staff_id
            );
            if (!$activity_added) {
                $this->db->trans_rollback();
                return ['status' => 'error'];
            }
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['status' => 'error'];
        }

        if (!$this->db->trans_commit()) {
            $this->db->trans_rollback();
            return ['status' => 'error'];
        }

        return [
            'status'       => 'success',
            'pipeline_id'  => !empty($reminder['pipeline_id']) ? (int) $reminder['pipeline_id'] : null,
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

    /**
     * Lấy danh sách Reminder đang chờ xử lý trong Notification Bell cho một Staff cụ thể.
     * Chỉ lấy các reminder mà CRM delivery tương ứng đã ở trạng thái sent.
     *
     * @param int $staff_id
     * @param int $limit
     * @return array
     */
    public function get_reminder_bell_feed($staff_id, $limit = 30)
    {
        $staff_id = (int) $staff_id;
        $limit = max(1, min(50, (int) $limit));

        $emptyFeed = [
            'pending_count' => 0,
            'items'         => [],
        ];

        if ($staff_id <= 0) {
            return $emptyFeed;
        }

        $remindersTable = db_prefix() . 'sales_pipeline_reminders_log';
        $deliveriesTable = db_prefix() . 'sales_pipeline_reminder_deliveries';

        // Fail closed: without the repository acknowledgement schema and the
        // delivery audit there is no proof that a CRM reminder was published.
        if (!$this->db->table_exists($remindersTable)
            || !$this->db->table_exists($deliveriesTable)
            || !$this->db->field_exists('acknowledged_at', $remindersTable)
            || !$this->db->field_exists('acknowledged_by', $remindersTable)) {
            return $emptyFeed;
        }

        $this->db->select('COUNT(DISTINCT r.id) AS pending_count', false);
        $this->applyReminderBellPendingScope($staff_id, $remindersTable, $deliveriesTable);
        $countRow = $this->db->get()->row_array();
        $pendingCount = (int) ($countRow['pending_count'] ?? 0);

        $this->db->select('r.id, r.pipeline_id, r.rule_code, r.entity_type, r.entity_id, r.period_key, r.checkpoint, r.severity, r.response_required, r.title, r.message, r.created_at, r.sent_at');
        $this->applyReminderBellPendingScope($staff_id, $remindersTable, $deliveriesTable);
        $this->db->group_by('r.id');

        // Sắp xếp theo severity rồi thời gian mới nhất. Không ép Actionable lên
        // trước vì backlog lớn sẽ làm Informational (có nút acknowledge) bị đói.
        $this->db->order_by("CASE
            WHEN r.severity = 'critical' THEN 1
            WHEN r.severity = 'warning' THEN 2
            ELSE 3 END", 'ASC', false);
        $this->db->order_by('r.created_at', 'DESC');
        $this->db->order_by('r.id', 'DESC');
        $this->db->limit($limit);

        $rows = $this->db->get()->result_array();

        $items = [];
        foreach ($rows as $row) {
            $entity_url = '';
            if (!empty($row['entity_type']) && !empty($row['entity_id'])) {
                if (($row['entity_type'] === 'deal' || (!empty($row['pipeline_id']) && $row['entity_type'] !== 'estimate'))
                    && (is_admin()
                        || has_permission('sales_pipeline', '', 'view')
                        || has_permission('sales_pipeline', '', 'view_own'))) {
                    $entity_url = admin_url('sales_pipeline/deal/' . ($row['entity_id'] ?: $row['pipeline_id']));
                } elseif ($row['entity_type'] === 'estimate'
                    && function_exists('user_can_view_estimate')
                    && user_can_view_estimate((int) $row['entity_id'])) {
                    $entity_url = admin_url('estimates/list_estimates/' . $row['entity_id']);
                }
            } elseif (!empty($row['pipeline_id'])
                && (is_admin()
                    || has_permission('sales_pipeline', '', 'view')
                    || has_permission('sales_pipeline', '', 'view_own'))) {
                $entity_url = admin_url('sales_pipeline/deal/' . $row['pipeline_id']);
            }

            $isActionable = (int) ($row['response_required'] ?? 1) === 1;

            $items[] = [
                'id'                 => (int) $row['id'],
                'rule_code'          => (string) ($row['rule_code'] ?? ''),
                'entity_type'        => (string) ($row['entity_type'] ?? 'deal'),
                'entity_id'          => (int) ($row['entity_id'] ?: ($row['pipeline_id'] ?? 0)),
                'severity'           => (string) ($row['severity'] ?: 'warning'),
                'response_required'  => (int) ($row['response_required'] ?? 1),
                'title'              => (string) ($row['title'] ?: _l('sales_pipeline_rule_reminder')),
                'message'            => (string) ($row['message'] ?? ''),
                'created_at'         => (string) ($row['created_at'] ?: $row['sent_at']),
                'time_ago'           => time_ago($row['created_at'] ?: $row['sent_at']),
                'quick_response_url' => $isActionable
                    ? admin_url('sales_pipeline/reminder_response/' . $row['id'])
                    : null,
                'entity_url'         => $entity_url,
            ];
        }

        return [
            'pending_count' => $pendingCount,
            'items'         => $items,
        ];
    }

    /** Apply the shared ownership, delivery and pending-state predicates. */
    private function applyReminderBellPendingScope($staff_id, $remindersTable, $deliveriesTable)
    {
        $staffId = (int) $staff_id;
        $recipientKey = $this->db->escape((string) $staffId);

        $this->db->from($remindersTable . ' r');
        $this->db->join(
            $deliveriesTable . ' d',
            'd.reminder_id = r.id'
                . " AND d.channel = 'crm'"
                . " AND d.status = 'sent'"
                . ' AND (d.recipient_staff_id = ' . $staffId
                . ' OR (d.recipient_staff_id IS NULL AND d.recipient_key = ' . $recipientKey . '))',
            'inner'
        );
        $this->db->where('r.staff_id', $staffId);
        $this->db->group_start();
        $this->db->group_start();
        $this->db->where('r.response_required', 1);
        $this->db->where('r.staff_response IS NULL', null, false);
        $this->db->group_end();
        $this->db->or_group_start();
        $this->db->where('r.response_required', 0);
        $this->db->where('r.acknowledged_at IS NULL', null, false);
        $this->db->group_end();
        $this->db->group_end();
    }

    /**
     * Xác nhận Informational Reminder (Acknowledge)
     *
     * @param int $reminder_id
     * @param int $staff_id
     * @return array
     */
    public function acknowledge_reminder($reminder_id, $staff_id)
    {
        $reminder_id = (int) $reminder_id;
        $staff_id = (int) $staff_id;

        if ($reminder_id <= 0 || $staff_id <= 0 || $staff_id !== (int) get_staff_user_id()) {
            return ['status' => 'invalid'];
        }

        $remindersTable = db_prefix() . 'sales_pipeline_reminders_log';

        $this->db->trans_begin();

        $reminder = $this->db->query(
            'SELECT * FROM `' . $remindersTable . '` WHERE `id` = ? FOR UPDATE',
            [$reminder_id]
        )->row_array();

        if (!$reminder) {
            $this->db->trans_rollback();
            return ['status' => 'not_found'];
        }

        if ((int) $reminder['staff_id'] !== $staff_id) {
            $this->db->trans_rollback();
            return ['status' => 'forbidden'];
        }

        if ((int) ($reminder['response_required'] ?? 1) === 1) {
            $this->db->trans_rollback();
            return ['status' => 'actionable_not_allowed'];
        }

        if (!empty($reminder['acknowledged_at']) && $reminder['acknowledged_at'] !== '0000-00-00 00:00:00') {
            $this->db->trans_rollback();
            return [
                'status'          => 'already_acknowledged',
                'acknowledged_at' => $reminder['acknowledged_at'],
            ];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->where('id', $reminder_id);
        $this->db->update($remindersTable, [
            'acknowledged_at' => $now,
            'acknowledged_by' => $staff_id,
        ]);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['status' => 'error'];
        }

        $this->db->trans_commit();

        return [
            'status'          => 'success',
            'reminder_id'     => $reminder_id,
            'acknowledged_at' => $now,
        ];
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


    // =========================================================================
    // ESTIMATE GROUPS & REVISIONS (INDEPENDENT FROM DEALS)
    // =========================================================================

    /**
     * Duplicate an estimate while preserving its Estimate-only revision group.
     * The after_estimate_added hook performs the actual revision linking.
     *
     * @param int $source_estimate_id
     * @return int|false
     */
    public function duplicate_estimate_revision($source_estimate_id)
    {
        $this->load->model('estimates_model');
        $this->load->library('sales_pipeline/Estimate_revision_service');
        $this->estimate_revision_service->set_copy_context((int) $source_estimate_id, 'module_copy');
        $this->estimate_copy_source_id = (int) $source_estimate_id;

        $new_id = $this->estimates_model->copy((int) $source_estimate_id);
        $this->estimate_copy_source_id = null;
        $this->estimate_revision_service->clear_copy_context();

        return $new_id;
    }

    /**
     * Return the source estimate currently being copied by the module route.
     *
     * @deprecated Copy context is owned by Estimate_revision_service::get_copy_context().
     * @return int|null
     */
    public function get_estimate_copy_source_id()
    {
        return $this->estimate_copy_source_id ? (int) $this->estimate_copy_source_id : null;
    }

    /**
     * Register a newly created estimate as a standalone group or revision.
     *
     * @param int      $estimate_id
     * @param int|null $source_estimate_id
     * @return int|false Estimate group id
     */
    public function handle_estimate_added($estimate_id, $source_estimate_id = null)
    {
        if (!$this->estimate_group_schema_available()) {
            return false;
        }

        $this->load->library('sales_pipeline/Estimate_revision_service');
        $context = $source_estimate_id ? [
            'source_estimate_id' => (int) $source_estimate_id,
            'parent_estimate_id' => (int) $source_estimate_id,
            'link_method'        => 'module_copy',
        ] : null;

        $result = $this->estimate_revision_service->handle_estimate_added((int) $estimate_id, $context);
        return $result['estimate_group_id'] ? (int) $result['estimate_group_id'] : false;
    }

    /**
     * Get valid candidate source estimates for a customer to populate the revision dropdown.
     * Respects permissions and strict accepted policy.
     *
     * @param int $clientId
     * @param int|null $staffId
     * @param int $limit
     * @return array
     */
    public function get_customer_estimate_revision_sources($clientId, $staffId = null, $limit = 50)
    {
        $clientId = (int) $clientId;
        if ($clientId <= 0) {
            return [];
        }

        if ($staffId === null) {
            $staffId = get_staff_user_id() ? (int) get_staff_user_id() : 0;
        }

        $isManager = is_admin($staffId)
            || (function_exists('has_permission') && has_permission('sales_pipeline', (string) $staffId, 'manage_estimate_revisions'));

        // Eligible candidate statuses: draft (1), sent (2), declined (3), expired (5)
        // Accepted (4) is an explicit Manager/Admin-only exception for the override flow.
        $candidateStatuses = [1, 2, 3, 5];
        if ($isManager) {
            $candidateStatuses[] = 4;
        }
        $canViewAll = is_admin($staffId)
            || (function_exists('staff_can') && staff_can('view', 'estimates', $staffId));
        $canViewOwn = function_exists('staff_can') && staff_can('view_own', 'estimates', $staffId);

        if (!$canViewAll && !$canViewOwn) {
            return [];
        }

        $estimateTable = db_prefix() . 'estimates';
        $groupTable = db_prefix() . 'sales_pipeline_estimate_groups';
        $versionTable = db_prefix() . 'sales_pipeline_estimate_versions';
        $currenciesTable = db_prefix() . 'currencies';

        $this->db->select('e.id, e.number, e.prefix, e.number_format, e.date, e.expirydate, e.total, e.currency, e.status, e.sale_agent, e.addedfrom, '
            . 'c.symbol as currency_symbol, '
            . 'COALESCE(ev.estimate_group_id, grp.id) as estimate_group_id, '
            . 'COALESCE(ev.revision_no, 1) as revision_no, '
            . 'COALESCE(grp.outcome, "pending") as outcome');
        $this->db->from($estimateTable . ' e');
        $this->db->join($currenciesTable . ' c', 'c.id = e.currency', 'left');
        $this->db->join($versionTable . ' ev', 'ev.estimate_id = e.id', 'left');
        $this->db->join($groupTable . ' grp', 'grp.id = ev.estimate_group_id', 'left');
        $this->db->where('e.clientid', $clientId);
        $this->db->where_in('e.status', $candidateStatuses);

        // Permission filter: view own vs view all
        if (!$canViewAll && $canViewOwn) {
            $this->db->group_start();
            $this->db->where('e.sale_agent', $staffId);
            $this->db->or_where('e.addedfrom', $staffId);
            $this->db->group_end();
        }

        // Accepted policy: Staff cannot see accepted quotes in candidate dropdown
        if (!$isManager) {
            $this->db->where('e.status !=', 4);
            $this->db->group_start();
            $this->db->where('grp.outcome !=', 'accepted');
            $this->db->or_where('grp.outcome IS NULL', null, false);
            $this->db->group_end();
        }

        $this->db->order_by('e.datecreated', 'desc');
        $this->db->limit(max(1, min(100, (int) $limit)));
        $rows = $this->db->get()->result_array();

        $results = [];
        foreach ($rows as $row) {
            $isAccepted = (int) $row['status'] === 4 || $row['outcome'] === 'accepted';
            $statusLabel = function_exists('format_estimate_status')
                ? strip_tags(format_estimate_status($row['status'], '', false))
                : (string) $row['status'];

            $estimateNumber = function_exists('format_estimate_number')
                ? format_estimate_number($row['id'])
                : (string) $row['id'];

            $results[] = [
                'estimate_id'       => (int) $row['id'],
                'estimate_number'   => $estimateNumber,
                'estimate_group_id' => $row['estimate_group_id'] ? (int) $row['estimate_group_id'] : null,
                'revision_no'       => (int) $row['revision_no'],
                'total'             => (float) $row['total'],
                'total_formatted'   => function_exists('app_format_money') ? app_format_money($row['total'], $row['currency']) : number_format((float) $row['total'], 2),
                'currency_symbol'   => $row['currency_symbol'] ?: '',
                'status'            => (int) $row['status'],
                'status_label'      => $statusLabel,
                'date'              => $row['date'] && function_exists('_d') ? _d($row['date']) : ($row['date'] ?: ''),
                'expirydate'        => $row['expirydate'] && function_exists('_d') ? _d($row['expirydate']) : ($row['expirydate'] ?: ''),
                'is_accepted'       => $isAccepted,
            ];
        }

        return $results;
    }

    /**
     * Smart Prompt: Retrieve and rank candidate estimates for revision suggestions.
     * Read-only, deterministic scoring, 60-day cutoff, only current_estimate_id of groups.
     *
     * @param int $clientId
     * @param int|null $projectId
     * @param int|null $staffId
     * @param int $limit
     * @return array
     */
    public function get_estimate_revision_candidates($clientId, $projectId = null, $staffId = null, $limit = 10)
    {
        $clientId = (int) $clientId;
        if ($clientId <= 0) {
            return [];
        }

        if ($staffId === null) {
            $staffId = get_staff_user_id() ? (int) get_staff_user_id() : 0;
        }

        $isManager = is_admin($staffId)
            || (function_exists('has_permission') && has_permission('sales_pipeline', (string) $staffId, 'manage_estimate_revisions'));

        // Whitelist of valid candidate statuses: draft (1), sent (2), declined (3), expired (5)
        // Accepted (4) is an explicit Manager/Admin-only exception for override flow.
        $candidateStatuses = [1, 2, 3, 5];
        if ($isManager) {
            $candidateStatuses[] = 4;
        }

        $estimateTable = db_prefix() . 'estimates';
        $groupTable = db_prefix() . 'sales_pipeline_estimate_groups';
        $versionTable = db_prefix() . 'sales_pipeline_estimate_versions';
        $currenciesTable = db_prefix() . 'currencies';
        $clientsTable = db_prefix() . 'clients';
        $projectsTable = db_prefix() . 'projects';
        $staffTable = db_prefix() . 'staff';

        $this->db->select('e.id, e.number, e.prefix, e.number_format, e.date, e.expirydate, e.total, e.currency, e.status, e.datecreated, '
            . 'e.sale_agent, e.addedfrom, e.project_id, '
            . 'c.symbol as currency_symbol, '
            . 'cl.company as customer_name, '
            . 'p.name as project_name, '
            . 'grp.id as estimate_group_id, '
            . 'COALESCE(grp.owner_staff_id, e.sale_agent, e.addedfrom) as owner_staff_id, '
            . 'COALESCE(ev.revision_no, 1) as revision_no, '
            . 'COALESCE(grp.outcome, "pending") as outcome, '
            . 'st.firstname as owner_firstname, st.lastname as owner_lastname');
        $this->db->from($estimateTable . ' e');
        $this->db->join($clientsTable . ' cl', 'cl.userid = e.clientid', 'left');
        $this->db->join($currenciesTable . ' c', 'c.id = e.currency', 'left');
        $this->db->join($projectsTable . ' p', 'p.id = e.project_id', 'left');
        $this->db->join($versionTable . ' ev', 'ev.estimate_id = e.id', 'left');
        $this->db->join($groupTable . ' grp', 'grp.id = ev.estimate_group_id', 'left');
        $this->db->join($staffTable . ' st', 'st.staffid = COALESCE(grp.owner_staff_id, e.sale_agent, e.addedfrom)', 'left');

        // Hard Filter 1: client_id
        $this->db->where('e.clientid', $clientId);

        // Hard Filter 2: 60-day system window (datecreated)
        $this->db->where('e.datecreated >= DATE_SUB(NOW(), INTERVAL 60 DAY)', null, false);

        // Hard Filter 3: Candidate must belong to an Estimate Group and be its current_estimate_id
        $this->db->where('grp.id IS NOT NULL', null, false);
        $this->db->where('grp.current_estimate_id = e.id', null, false);

        // Hard Filter 4: Explicit status whitelist.
        $this->db->where_in('e.status', $candidateStatuses);

        // Hard Filter 5: Accepted Group policy (Staff cannot see accepted candidates; Manager can)
        if (!$isManager) {
            $this->db->where('grp.outcome !=', 'accepted');
        }

        $rows = $this->db->get()->result_array();

        if (empty($rows)) {
            return [];
        }

        $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
        $fourteenDaysAgo = date('Y-m-d H:i:s', strtotime('-14 days'));
        $candidates = [];

        foreach ($rows as $row) {
            $estimateId = (int) $row['id'];

            // Hard Filter 5: Permission check
            if (function_exists('user_can_view_estimate') && $staffId > 0) {
                if (!user_can_view_estimate($estimateId, $staffId)) {
                    continue;
                }
            }

            $score = 0;
            $reasonCodes = [];

            // Rule 1: Same project (+40)
            if ($projectId !== null && (int) $projectId > 0 && (int) $row['project_id'] === (int) $projectId) {
                $score += 40;
                $reasonCodes[] = 'same_project';
            }

            // Rule 2: Same owner (+30)
            $ownerStaffId = (int) $row['owner_staff_id'];
            if ($ownerStaffId > 0 && $ownerStaffId === (int) $staffId) {
                $score += 30;
                $reasonCodes[] = 'same_owner';
            }

            // Rule 3: Recently expired / declined / draft / sent in last 30 days (+25)
            $estDateCreated = $row['datecreated'];
            $estStatus = (int) $row['status'];
            if ($estDateCreated >= $thirtyDaysAgo) {
                if ($estStatus === 5) {
                    $score += 25;
                    $reasonCodes[] = 'recently_expired';
                } elseif ($estStatus === 3) {
                    $score += 25;
                    $reasonCodes[] = 'recently_declined';
                } elseif ($estStatus === 1 || $estStatus === 2) {
                    $score += 25;
                    $reasonCodes[] = 'recently_active';
                }
            }

            // Rule 4: Recent activity in last 14 days (+15)
            if ($estDateCreated >= $fourteenDaysAgo) {
                $score += 15;
                $reasonCodes[] = 'recent_activity';
            }

            $isAccepted = ($estStatus === 4 || $row['outcome'] === 'accepted');
            $statusLabel = function_exists('format_estimate_status')
                ? strip_tags(format_estimate_status($row['status'], '', false))
                : (string) $row['status'];

            $estimateNumber = function_exists('format_estimate_number')
                ? format_estimate_number($estimateId)
                : (string) $estimateId;

            $ownerName = trim(($row['owner_firstname'] ?? '') . ' ' . ($row['owner_lastname'] ?? ''));

            $candidates[] = [
                'estimate_id'       => $estimateId,
                'estimate_group_id' => $row['estimate_group_id'] ? (int) $row['estimate_group_id'] : null,
                'estimate_number'   => $estimateNumber,
                'revision_no'       => (int) $row['revision_no'],
                'customer_name'     => $row['customer_name'] ?: '',
                'project_id'        => $row['project_id'] ? (int) $row['project_id'] : null,
                'project_name'      => $row['project_name'] ?: '',
                'owner_staff_id'    => $ownerStaffId,
                'owner_name'        => $ownerName,
                'total'             => (float) $row['total'],
                'total_formatted'   => function_exists('app_format_money') ? app_format_money($row['total'], $row['currency']) : number_format((float) $row['total'], 2),
                'currency_symbol'   => $row['currency_symbol'] ?: '',
                'status'            => $estStatus,
                'status_label'      => $statusLabel,
                'date'              => $row['date'] && function_exists('_d') ? _d($row['date']) : ($row['date'] ?: ''),
                'expirydate'        => $row['expirydate'] && function_exists('_d') ? _d($row['expirydate']) : ($row['expirydate'] ?: ''),
                'is_accepted'       => $isAccepted,
                'score'             => $score,
                'reason_codes'      => $reasonCodes,
                'datecreated'       => $row['datecreated'],
            ];
        }

        // Sort descending by score, then by datecreated DESC
        usort($candidates, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return strcmp($b['datecreated'], $a['datecreated']);
            }
            return ($b['score'] > $a['score']) ? 1 : -1;
        });

        $limit = max(1, min(20, (int) $limit));
        return array_slice($candidates, 0, $limit);
    }

    /**
     * Synchronize an Estimate group after status/value changes.
     *
     * @param int         $estimate_id
     * @param string      $source
     * @param string|null $effective_at
     * @return bool
     */
    public function sync_estimate_group_by_estimate($estimate_id, $source = 'hook', $effective_at = null)
    {
        if (!$this->estimate_group_schema_available()) {
            return false;
        }

        $version = $this->get_estimate_version_by_estimate((int) $estimate_id);
        if (!$version) {
            $group_id = $this->handle_estimate_added((int) $estimate_id);
            if (!$group_id) {
                return false;
            }
            $version = $this->get_estimate_version_by_estimate((int) $estimate_id);
        }

        $this->refresh_estimate_version_snapshot((int) $estimate_id);
        return $this->sync_estimate_group((int) $version['estimate_group_id'], $source, $effective_at);
    }

    /**
     * Reconcile linked estimates with the current Perfex status.
     * Activity timestamps are preferred; observed time is only a final fallback.
     *
     * @param int $limit
     * @return int Number of Estimate groups checked
     */
    public function reconcile_estimate_groups($limit = 100)
    {
        if (!$this->estimate_group_schema_available()) {
            return 0;
        }

        $limit = max(1, min(5000, (int) $limit));
        $group_table = db_prefix() . 'sales_pipeline_estimate_groups';

        // R-02 fix: use last_reconciled_at cursor so every group is eventually
        // reached even when the total exceeds the batch limit.
        // Groups never reconciled (NULL) sort first; within the same cursor
        // value groups are ordered by id to make the scan deterministic.
        if (!$this->db->field_exists('last_reconciled_at', $group_table)) {
            // Column not yet added (migration 107 pending); fall back to old order.
            $ids = $this->db
                ->select('id')
                ->order_by('COALESCE(datemodified, datecreated)', 'asc', false)
                ->limit($limit)
                ->get($group_table)
                ->result_array();
        } else {
            $ids = $this->db
                ->select('id')
                ->order_by('COALESCE(last_reconciled_at, \'1970-01-01\')', 'asc', false)
                ->order_by('id', 'asc')
                ->limit($limit)
                ->get($group_table)
                ->result_array();
        }

        $now = date('Y-m-d H:i:s');
        require_once module_dir_path('sales_pipeline', 'libraries/Finance_lock_guard.php');
        $lockGuard = new Finance_lock_guard();

        foreach ($ids as $row) {
            $group_id = (int) $row['id'];
            $group = $this->db->where('id', $group_id)->get($group_table)->row_array();
            if (!$group) {
                continue;
            }

            // 1. Finance lock guard: Never mutate finance-locked group
            if (!$lockGuard->can_modify($group)) {
                if ($this->db->field_exists('last_reconciled_at', $group_table)) {
                    $this->db->where('id', $group_id)->update($group_table, ['last_reconciled_at' => $now]);
                }
                continue;
            }

            // 2. Refresh version snapshots before group synchronization
            $this->reconcile_group_version_snapshots($group_id);

            // 3. Sync estimate group
            $this->sync_estimate_group($group_id, 'reconciliation');

            // Always stamp last_reconciled_at even when outcome did not change
            // so the group moves to the back of the queue for the next sweep.
            if ($this->db->field_exists('last_reconciled_at', $group_table)) {
                $this->db->where('id', $group_id)->update($group_table, ['last_reconciled_at' => $now]);
            }
        }

        return count($ids);
    }

    /**
     * Refresh missing currency exchange rates on estimate versions belonging to a group.
     *
     * @param int $group_id
     * @return void
     */
    public function reconcile_group_version_snapshots($group_id)
    {
        $version_table = db_prefix() . 'sales_pipeline_estimate_versions';
        $versions = $this->db
            ->from($version_table)
            ->where('estimate_group_id', (int) $group_id)
            ->get()
            ->result_array();

        if (empty($versions)) {
            return;
        }

        require_once module_dir_path('sales_pipeline', 'libraries/Quote_currency_resolver.php');
        $currencyResolver = new Quote_currency_resolver();

        foreach ($versions as $v) {
            // If already has base_total and exchange_rate_to_base, skip to remain idempotent
            if ($v['base_total'] !== null && $v['exchange_rate_to_base'] !== null) {
                continue;
            }

            $sourceCur = (int) $v['source_currency_id'];
            $baseCur = (int) $v['base_currency_id'];
            $sourceTotal = (float) $v['source_total'];
            $targetDate = !empty($v['date_linked']) ? $v['date_linked'] : date('Y-m-d H:i:s');

            $rateResult = $currencyResolver->resolve($v['estimate_id'], $sourceCur, $baseCur, $targetDate);
            if ($rateResult['rate'] !== null && (float) $rateResult['rate'] > 0) {
                $rate = (float) $rateResult['rate'];
                $baseTotal = round($sourceTotal * $rate, 2);
                $this->db->where('id', (int) $v['id'])->update($version_table, [
                    'exchange_rate_to_base' => $rate,
                    'base_total'            => $baseTotal,
                ]);
            }
        }
    }

    /**
     * Get estimate group record by ID.
     *
     * @param int $group_id
     * @return array|null
     */
    public function get_estimate_group($group_id)
    {
        $group_table = db_prefix() . 'sales_pipeline_estimate_groups';
        return $this->db->where('id', (int) $group_id)->get($group_table)->row_array();
    }


    /**
     * Remove an estimate revision before Perfex deletes the estimate record.
     *
     * @param int $estimate_id
     * @return void
     */
    public function handle_estimate_deleted($estimate_id)
    {
        if (!$this->estimate_group_schema_available()) {
            return;
        }

        $version_table = db_prefix() . 'sales_pipeline_estimate_versions';
        $group_table = db_prefix() . 'sales_pipeline_estimate_groups';
        $history_table = db_prefix() . 'sales_pipeline_estimate_outcome_history';
        $version = $this->get_estimate_version_by_estimate((int) $estimate_id);
        if (!$version) {
            return;
        }

        $group_id = (int) $version['estimate_group_id'];
        $this->db->trans_start();
        $this->db->where('estimate_id', (int) $estimate_id)->delete($version_table);

        $remaining = $this->db
            ->select('estimate_id')
            ->where('estimate_group_id', $group_id)
            ->order_by('revision_no', 'desc')
            ->get($version_table)
            ->result_array();

        if (!$remaining) {
            $this->db->where('estimate_group_id', $group_id)->delete($history_table);
            $this->db->where('id', $group_id)->delete($group_table);
            $this->db->trans_complete();
            return;
        }

        $current_estimate_id = (int) $remaining[0]['estimate_id'];
        $origin_estimate_id = (int) end($remaining)['estimate_id'];
        $this->db->where('id', $group_id)->update($group_table, [
            'current_estimate_id' => $current_estimate_id,
            'origin_estimate_id'  => $origin_estimate_id,
            'datemodified'        => date('Y-m-d H:i:s'),
        ]);
        $this->db->trans_complete();
        $this->sync_estimate_group($group_id, 'estimate_deleted');
    }

    /**
     * Quote-tab summary and staff leaderboard.
     *
     * @param int|null $staff_id
     * @param array     $period
     * @return array
     */
    public function get_estimate_dashboard_metrics($staff_id = null, $period = [])
    {
        $empty = [
            'summary' => [
                'estimate_count'             => 0,
                'accepted_revenue'           => 0,
                'accepted_period_count'      => 0,
                'declined_period_count'      => 0,
                'closed_period_count'        => 0,
                'missing_revenue_rate_count' => 0,
                'acceptance_rate'            => null,
                'currency_symbol'            => '',
                'currency_name'              => '',
            ],
            'leaderboard' => [],
        ];

        if (!$this->estimate_group_schema_available()) {
            return $empty;
        }

        $period = isset($period['start'], $period['end'])
            ? $period
            : $this->resolve_dashboard_period('this_month');
        $period_start = $period['start'] . ' 00:00:00';
        $period_end_exclusive = date('Y-m-d 00:00:00', strtotime($period['end'] . ' +1 day'));

        $staff_members = $this->get_dashboard_staff_members($staff_id);
        if (!$staff_members) {
            return $empty;
        }

        $staff_metrics = [];
        $staff_ids = [];
        foreach ($staff_members as $staff) {
            $sid = (int) $staff['staffid'];
            $staff_ids[] = $sid;
            $staff_metrics[$sid] = [
                'staff_id'          => $sid,
                'staff_name'        => trim($staff['firstname'] . ' ' . $staff['lastname']),
                'email'             => $staff['email'],
                'estimate_count'    => 0,
                'accepted_revenue'  => 0,
                'accepted_count'    => 0,
                'declined_count'    => 0,
                'closed_count'      => 0,
                'missing_revenue_rate_count' => 0,
                'acceptance_rate'   => null,
            ];
        }

        $group_table = db_prefix() . 'sales_pipeline_estimate_groups';
        $version_table = db_prefix() . 'sales_pipeline_estimate_versions';
        $estimate_table = db_prefix() . 'estimates';
        // R-01 fix: count Estimate Groups (one logical quote), not individual revisions.
        // A group is "valid" when it has at least one non-draft revision (status IN 2,3,4,5).
        require_once module_dir_path('sales_pipeline', 'libraries/Quote_count_repository.php');
        $quoteRepo = new Quote_count_repository();
        $quoteCounts = $quoteRepo->get_counts_by_staff($staff_ids, $period_start, $period_end_exclusive);

        foreach ($quoteCounts as $sid => $count) {
            if (isset($staff_metrics[$sid])) {
                $staff_metrics[$sid]['estimate_count'] = (int) $count;
            }
        }

        $decision_owner = 'COALESCE(grp.decision_owner_staff_id, grp.owner_staff_id)';
        $decision_rows = $this->db
            ->select(
                $decision_owner . ' as staff_id,'
                . ' SUM(CASE WHEN grp.outcome = "accepted" THEN 1 ELSE 0 END) as accepted_count,'
                . ' SUM(CASE WHEN grp.outcome = "declined" THEN 1 ELSE 0 END) as declined_count,'
                . ' COALESCE(SUM(CASE WHEN grp.outcome = "accepted" THEN grp.decision_value_base ELSE 0 END), 0) as accepted_revenue,'
                . ' SUM(CASE WHEN grp.outcome = "accepted" AND grp.decision_value_base IS NULL THEN 1 ELSE 0 END) as missing_revenue_rate_count',
                false
            )
            ->from($group_table . ' grp')
            ->where_in($decision_owner, $staff_ids, false)
            ->where('grp.decision_at >=', $period_start)
            ->where('grp.decision_at <', $period_end_exclusive)
            ->group_by($decision_owner, false)
            ->get()
            ->result_array();

        foreach ($decision_rows as $row) {
            $sid = (int) $row['staff_id'];
            if (!isset($staff_metrics[$sid])) {
                continue;
            }
            $staff_metrics[$sid]['accepted_count'] = (int) $row['accepted_count'];
            $staff_metrics[$sid]['declined_count'] = (int) $row['declined_count'];
            $staff_metrics[$sid]['accepted_revenue'] = (float) $row['accepted_revenue'];
            $staff_metrics[$sid]['missing_revenue_rate_count'] = (int) $row['missing_revenue_rate_count'];
        }

        $summary = $empty['summary'];
        $leaderboard = [];
        foreach ($staff_metrics as $metric) {
            $metric['closed_count'] = $metric['accepted_count'] + $metric['declined_count'];
            $metric['acceptance_rate'] = $metric['closed_count'] > 0
                ? round(($metric['accepted_count'] / $metric['closed_count']) * 100, 1)
                : null;

            $has_activity = $metric['estimate_count'] > 0 || $metric['closed_count'] > 0;
            if (!$has_activity) {
                continue;
            }

            $summary['estimate_count'] += $metric['estimate_count'];
            $summary['accepted_revenue'] += $metric['accepted_revenue'];
            $summary['accepted_period_count'] += $metric['accepted_count'];
            $summary['declined_period_count'] += $metric['declined_count'];
            $summary['missing_revenue_rate_count'] += $metric['missing_revenue_rate_count'];
            $leaderboard[] = $metric;
        }

        $summary['closed_period_count'] = $summary['accepted_period_count'] + $summary['declined_period_count'];
        $summary['acceptance_rate'] = $summary['closed_period_count'] > 0
            ? round(($summary['accepted_period_count'] / $summary['closed_period_count']) * 100, 1)
            : null;

        $base_currency = $this->db
            ->select('name, symbol')
            ->where('isdefault', 1)
            ->get(db_prefix() . 'currencies')
            ->row_array();
        if ($base_currency) {
            $summary['currency_name'] = $base_currency['name'];
            $summary['currency_symbol'] = $base_currency['symbol'];
        }

        usort($leaderboard, function ($first, $second) {
            if ($first['accepted_revenue'] != $second['accepted_revenue']) {
                return $second['accepted_revenue'] <=> $first['accepted_revenue'];
            }
            $first_rate = $first['acceptance_rate'] === null ? -1 : $first['acceptance_rate'];
            $second_rate = $second['acceptance_rate'] === null ? -1 : $second['acceptance_rate'];
            if ($first_rate != $second_rate) {
                return $second_rate <=> $first_rate;
            }
            if ($first['estimate_count'] !== $second['estimate_count']) {
                return $second['estimate_count'] <=> $first['estimate_count'];
            }
            return strcasecmp($first['staff_name'], $second['staff_name']);
        });

        return ['summary' => $summary, 'leaderboard' => $leaderboard];
    }

    /**
     * Build the Estimate-only performance ranking on the full eligible cohort.
     * Role-aware projection must happen only after this method assigns ranks.
     *
     * @param string|array $period
     * @return array
     */
    public function get_estimate_performance_ranking($period = 'this_month')
    {
        $period = is_array($period) && isset($period['key'], $period['start'], $period['end'])
            ? $period
            : $this->resolve_dashboard_period($period);
        $config = $this->get_performance_score_config($period['key']);
        $calculated_at = date('Y-m-d H:i:s');
        $config['calculated_at'] = $calculated_at;
        require_once module_dir_path('sales_pipeline', 'libraries/Performance_score_dispatcher.php');
        require_once module_dir_path('sales_pipeline', 'libraries/Performance_score_service.php');
        $dispatcher = new Performance_score_dispatcher();
        $formula_version = $dispatcher->resolve_formula_version($period['key'], $period['start'], $period['end']);
        $config['formula_version'] = $formula_version;

        if (!$this->estimate_group_schema_available()) {
            return [
                'formula_version'      => $formula_version,
                'calculated_at'        => $calculated_at,
                'status'               => 'not_configured',
                'configuration_errors' => ['estimate_group_schema'],
                'leaderboard'          => [],
            ];
        }

        $reminder_sla_available = $this->reminder_sla_schema_available();
        $staff_members = $this->get_dashboard_staff_members(null);
        $metrics = [];
        $staff_ids = [];
        foreach ($staff_members as $staff) {
            $staff_id = (int) $staff['staffid'];
            $staff_ids[] = $staff_id;
            $metrics[$staff_id] = [
                'staff_id'                   => $staff_id,
                'staff_name'                 => trim($staff['firstname'] . ' ' . $staff['lastname']),
                'email'                      => $staff['email'],
                'is_manager'                 => (int) $staff['admin'] === 1
                    || has_permission('sales_pipeline', (string) $staff_id, 'view'),
                'estimate_count'             => 0,
                'accepted_revenue'           => 0.0,
                'accepted_count'             => 0,
                'declined_count'             => 0,
                'missing_revenue_rate_count' => 0,
                'eligible_reminders'         => $reminder_sla_available ? 0 : null,
                'on_time_reminders'          => $reminder_sla_available ? 0 : null,
            ];
        }

        if (!$staff_ids) {
            return [
                'formula_version'      => $formula_version,
                'calculated_at'        => $calculated_at,
                'status'               => 'ready',
                'configuration_errors' => [],
                'leaderboard'          => [],
            ];
        }

        $group_table = db_prefix() . 'sales_pipeline_estimate_groups';
        $period_start = $period['start'] . ' 00:00:00';
        $period_end_exclusive = date('Y-m-d 00:00:00', strtotime($period['end'] . ' +1 day'));

        require_once module_dir_path('sales_pipeline', 'libraries/Quote_count_repository.php');
        $quoteRepo = new Quote_count_repository();
        $quoteCounts = $quoteRepo->get_counts_by_staff($staff_ids, $period_start, $period_end_exclusive);

        foreach ($quoteCounts as $staff_id => $cnt) {
            if (isset($metrics[$staff_id])) {
                $metrics[$staff_id]['estimate_count'] = (int) $cnt;
            }
        }

        $decision_owner = 'COALESCE(decision_owner_staff_id, owner_staff_id)';
        $decision_rows = $this->db
            ->select(
                $decision_owner . ' as staff_id,'
                . ' SUM(CASE WHEN outcome = "accepted" THEN 1 ELSE 0 END) as accepted_count,'
                . ' SUM(CASE WHEN outcome = "declined" THEN 1 ELSE 0 END) as declined_count,'
                . ' COALESCE(SUM(CASE WHEN outcome = "accepted" THEN decision_value_base ELSE 0 END), 0) as accepted_revenue,'
                . ' SUM(CASE WHEN outcome = "accepted" AND decision_value_base IS NULL THEN 1 ELSE 0 END) as missing_revenue_rate_count',
                false
            )
            ->from($group_table)
            ->where_in($decision_owner, $staff_ids, false)
            ->where('decision_at >=', $period_start)
            ->where('decision_at <', $period_end_exclusive)
            ->group_by($decision_owner, false)
            ->get()
            ->result_array();

        foreach ($decision_rows as $row) {
            $staff_id = (int) $row['staff_id'];
            if (!isset($metrics[$staff_id])) {
                continue;
            }
            $metrics[$staff_id]['accepted_count'] = (int) $row['accepted_count'];
            $metrics[$staff_id]['declined_count'] = (int) $row['declined_count'];
            $metrics[$staff_id]['accepted_revenue'] = (float) $row['accepted_revenue'];
            $metrics[$staff_id]['missing_revenue_rate_count'] = (int) $row['missing_revenue_rate_count'];
        }

        // Aggregate reminder response SLA metrics if schema is available
        if ($reminder_sla_available) {
            $reminder_table = db_prefix() . 'sales_pipeline_reminders_log';
            $this->db
                ->select(
                    'staff_id, '
                    . 'COUNT(id) as eligible_reminders, '
                    . 'SUM(CASE WHEN responded_at IS NOT NULL AND responded_at <= response_due_at THEN 1 ELSE 0 END) as on_time_reminders',
                    false
                )
                ->from($reminder_table)
                ->where_in('staff_id', $staff_ids)
                ->where_in('entity_type', ['estimate', 'staff_estimate_period'])
                ->where('response_required', 1)
                ->where('response_due_at IS NOT NULL', null, false)
                ->where('response_due_at >=', $period_start)
                ->where('response_due_at <', $period_end_exclusive)
                ->where('response_due_at <=', $calculated_at);

            if ($this->db->field_exists('data_quality_status', $reminder_table)) {
                $this->db->where("COALESCE(data_quality_status, 'verified') != 'legacy_unverified'", null, false);
            }

            $reminder_rows = $this->db
                ->group_by('staff_id')
                ->get()
                ->result_array();

            foreach ($reminder_rows as $r_row) {
                $s_id = (int) $r_row['staff_id'];
                if (isset($metrics[$s_id])) {
                    $metrics[$s_id]['eligible_reminders'] = (int) $r_row['eligible_reminders'];
                    $metrics[$s_id]['on_time_reminders'] = (int) $r_row['on_time_reminders'];
                }
            }
        }

        $cohort = [];
        foreach ($metrics as $metric) {
            $has_sales_activity = $metric['estimate_count'] > 0
                || $metric['accepted_count'] > 0
                || $metric['declined_count'] > 0;
            if ($metric['is_manager'] && !$has_sales_activity) {
                continue;
            }
            unset($metric['is_manager']);
            $cohort[] = $metric;
        }

        $score_service = new Performance_score_service($this);
        $runtime = $score_service->calculate_runtime(
            [
                'type'  => $period['key'],
                'start' => $period['start'],
                'end'   => $period['end'],
            ],
            $cohort,
            $config,
            $calculated_at
        );

        return [
            'formula_version'      => $runtime['formula_version'],
            'calculated_at'        => $runtime['calculated_at'],
            'status'               => 'ready',
            'configuration_errors' => [],
            'leaderboard'          => array_values($runtime['cohort']),
        ];
    }

    /**
     * Apply the server-side Admin/Staff projection after ranking the full cohort.
     *
     * @param array $leaderboard
     * @param bool  $can_view_all
     * @param int   $current_staff_id
     * @return array
     */
    public function project_estimate_performance_ranking($leaderboard, $can_view_all, $current_staff_id)
    {
        $this->load->library('sales_pipeline/Performance_score_calculator');
        return $this->performance_score_calculator->project_leaderboard(
            $leaderboard,
            (bool) $can_view_all,
            (int) $current_staff_id
        );
    }

    /**
     * Resolve versioned performance targets from tbloptions.
     * Missing period-specific targets fall back to sensible defaults.
     *
     * @param string $period_key
     * @return array
     */
    public function get_performance_score_config($period_key)
    {
        $period_key = in_array($period_key, ['this_week', 'this_month', 'this_quarter', 'this_year'], true)
            ? $period_key
            : 'this_month';

        $default_targets = [
            'this_week'    => ['quote' => 5,   'revenue' => 250000000],
            'this_month'   => ['quote' => 20,  'revenue' => 1000000000],
            'this_quarter' => ['quote' => 60,  'revenue' => 3000000000],
            'this_year'    => ['quote' => 240, 'revenue' => 12000000000],
        ];

        $quote_target = get_option('performance_quote_target_' . $period_key);
        $revenue_target = get_option('performance_revenue_target_' . $period_key);
        $response_target = get_option('performance_response_target_percent');

        return [
            'formula_version'   => 'performance_score_v1',
            'quote_target'      => (is_numeric($quote_target) && (float) $quote_target > 0)
                                    ? $quote_target
                                    : $default_targets[$period_key]['quote'],
            'revenue_target'    => (is_numeric($revenue_target) && (float) $revenue_target > 0)
                                    ? $revenue_target
                                    : $default_targets[$period_key]['revenue'],
            'acceptance_target' => (is_numeric(get_option('performance_acceptance_target_percent')) && (float) get_option('performance_acceptance_target_percent') > 0)
                                    ? get_option('performance_acceptance_target_percent')
                                    : 50,
            'response_target'   => (is_numeric($response_target) && (float) $response_target > 0)
                                    ? $response_target
                                    : 90,
            'component_cap'     => (is_numeric(get_option('performance_component_cap')) && (float) get_option('performance_component_cap') > 0)
                                    ? get_option('performance_component_cap')
                                    : 120,
            'min_closed_quotes' => (is_numeric(get_option('performance_min_closed_quotes')) && (int) get_option('performance_min_closed_quotes') > 0)
                                    ? get_option('performance_min_closed_quotes')
                                    : 3,
        ];
    }

    public function reminder_sla_schema_available()
    {
        $table = db_prefix() . 'sales_pipeline_reminders_log';

        return $this->db->table_exists($table)
            && $this->db->field_exists('response_due_at', $table)
            && $this->db->field_exists('response_sla_hours', $table);
    }

    private function estimate_group_schema_available()
    {
        return $this->db->table_exists(db_prefix() . 'sales_pipeline_estimate_groups')
            && $this->db->table_exists(db_prefix() . 'sales_pipeline_estimate_versions')
            && $this->db->table_exists(db_prefix() . 'sales_pipeline_estimate_outcome_history');
    }

    private function get_estimate_version_by_estimate($estimate_id)
    {
        return $this->db
            ->where('estimate_id', (int) $estimate_id)
            ->get(db_prefix() . 'sales_pipeline_estimate_versions')
            ->row_array();
    }

    private function get_estimate_snapshot($estimate_id)
    {
        $estimate = $this->db
            ->select('id, clientid, sale_agent, addedfrom, status, currency, total, datecreated, invoiced_date')
            ->where('id', (int) $estimate_id)
            ->get(db_prefix() . 'estimates')
            ->row_array();
        if (!$estimate) {
            return null;
        }

        $base_currency = $this->db
            ->select('id')
            ->where('isdefault', 1)
            ->get(db_prefix() . 'currencies')
            ->row_array();
        $base_currency_id = $base_currency ? (int) $base_currency['id'] : 0;
        $source_currency_id = (int) $estimate['currency'];
        $this->load->library('sales_pipeline/Quote_currency_resolver');
        $candidate_rate = null;
        if ($source_currency_id !== $base_currency_id) {
            $candidate_rate = hooks()->apply_filters('sales_pipeline_quote_exchange_rate', null, [
                'estimate_id'       => (int) $estimate['id'],
                'source_currency_id'=> $source_currency_id,
                'base_currency_id'  => $base_currency_id,
                'captured_at'       => $estimate['datecreated'],
                'rate_unit'         => Quote_currency_resolver::RATE_UNIT,
            ]);
        }
        $currency_snapshot = $this->quote_currency_resolver->resolve(
            $source_currency_id,
            $base_currency_id,
            $estimate['total'],
            $candidate_rate
        );

        $estimate['owner_staff_id'] = (int) $estimate['sale_agent'] > 0
            ? (int) $estimate['sale_agent']
            : (int) $estimate['addedfrom'];
        $estimate['base_currency_id'] = $base_currency_id;
        $estimate['exchange_rate_to_base'] = $currency_snapshot['exchange_rate_to_base'];
        $estimate['base_total'] = $currency_snapshot['base_total'];

        return $estimate;
    }

    /**
     * @deprecated Replaced by Estimate_revision_service. Do not add new callers.
     */
    private function create_estimate_group($estimate_id, $grouping_source)
    {
        $snapshot = $this->get_estimate_snapshot($estimate_id);
        if (!$snapshot) {
            return false;
        }

        $existing = $this->get_estimate_version_by_estimate($estimate_id);
        if ($existing) {
            return (int) $existing['estimate_group_id'];
        }

        $outcome = (int) $snapshot['status'] === 4
            ? 'accepted'
            : ((int) $snapshot['status'] === 3 ? 'declined' : 'pending');
        $status_event = $outcome === 'pending'
            ? ['effective_at' => null, 'source' => null]
            : $this->find_estimate_status_event($estimate_id, (int) $snapshot['status']);
        $group_table = db_prefix() . 'sales_pipeline_estimate_groups';
        $version_table = db_prefix() . 'sales_pipeline_estimate_versions';
        $now = date('Y-m-d H:i:s');

        $this->db->trans_start();
        $this->db->insert($group_table, [
            'client_id'               => (int) $snapshot['clientid'],
            'owner_staff_id'          => (int) $snapshot['owner_staff_id'],
            'decision_owner_staff_id' => $outcome === 'pending' ? null : (int) $snapshot['owner_staff_id'],
            'decision_estimate_id'    => $outcome === 'pending' ? null : (int) $estimate_id,
            'decision_value_base'     => $outcome === 'accepted' ? $snapshot['base_total'] : null,
            'current_estimate_id'     => (int) $estimate_id,
            'origin_estimate_id'      => (int) $estimate_id,
            'outcome'                 => $outcome,
            'decision_at'             => $status_event['effective_at'],
            'decision_source'         => $status_event['source'],
            'source_currency_id'      => (int) $snapshot['currency'],
            'base_currency_id'        => (int) $snapshot['base_currency_id'],
            'created_value_base'      => $snapshot['base_total'],
            'grouping_source'         => $grouping_source,
            'datecreated'             => $snapshot['datecreated'] ?: $now,
            'datemodified'            => $now,
        ]);
        $group_id = (int) $this->db->insert_id();

        if ($group_id) {
            $this->insert_estimate_version($group_id, $snapshot, 1);
            if ($outcome !== 'pending' && $status_event['effective_at']) {
                $this->record_estimate_outcome_history(
                    $group_id,
                    (int) $estimate_id,
                    'pending',
                    $outcome,
                    $status_event['effective_at'],
                    $status_event['source'] ?: 'observed'
                );
            }
        }
        $this->db->trans_complete();

        return $this->db->trans_status() && $group_id ? $group_id : false;
    }

    /**
     * @deprecated Replaced by Estimate_revision_service. Do not add new callers.
     */
    private function append_estimate_revision($group_id, $estimate_id)
    {
        $snapshot = $this->get_estimate_snapshot($estimate_id);
        if (!$snapshot) {
            return false;
        }

        $group_table = db_prefix() . 'sales_pipeline_estimate_groups';
        $version_table = db_prefix() . 'sales_pipeline_estimate_versions';
        $now = date('Y-m-d H:i:s');
        $this->db->trans_start();
        $locked = $this->db->query(
            'SELECT * FROM `' . $group_table . '` WHERE id = ? FOR UPDATE',
            [(int) $group_id]
        )->row_array();
        if (!$locked) {
            $this->db->trans_rollback();
            return false;
        }

        $max_revision = $this->db
            ->select_max('revision_no', 'max_revision')
            ->where('estimate_group_id', (int) $group_id)
            ->get($version_table)
            ->row_array();
        $revision_no = ((int) ($max_revision['max_revision'] ?? 0)) + 1;
        $this->insert_estimate_version((int) $group_id, $snapshot, $revision_no);

        $update = [
            'current_estimate_id' => (int) $estimate_id,
            'datemodified'        => $now,
        ];
        if ($locked['outcome'] !== 'accepted') {
            if ($locked['outcome'] !== 'pending') {
                $this->record_estimate_outcome_history(
                    (int) $group_id,
                    (int) $estimate_id,
                    $locked['outcome'],
                    'pending',
                    $now,
                    'duplicate'
                );
            }
            $update['outcome'] = 'pending';
            $update['decision_at'] = null;
            $update['decision_source'] = null;
            $update['decision_owner_staff_id'] = null;
            $update['decision_estimate_id'] = null;
            $update['decision_value_base'] = null;
        }
        $this->db->where('id', (int) $group_id)->update($group_table, $update);
        $this->db->trans_complete();

        return $this->db->trans_status() ? (int) $group_id : false;
    }

    /**
     * @deprecated Legacy helper for create_estimate_group()/append_estimate_revision().
     */
    private function insert_estimate_version($group_id, $snapshot, $revision_no)
    {
        return $this->db->insert(db_prefix() . 'sales_pipeline_estimate_versions', [
            'estimate_group_id'    => (int) $group_id,
            'estimate_id'          => (int) $snapshot['id'],
            'revision_no'          => (int) $revision_no,
            'source_currency_id'   => (int) $snapshot['currency'],
            'source_total'         => (float) $snapshot['total'],
            'exchange_rate_to_base'=> $snapshot['exchange_rate_to_base'],
            'base_currency_id'     => (int) $snapshot['base_currency_id'],
            'base_total'           => $snapshot['base_total'],
            'rate_captured_at'     => $snapshot['exchange_rate_to_base'] === null ? null : date('Y-m-d H:i:s'),
            'date_linked'          => date('Y-m-d H:i:s'),
        ]);
    }

    private function refresh_estimate_version_snapshot($estimate_id)
    {
        $snapshot = $this->get_estimate_snapshot($estimate_id);
        $version = $this->get_estimate_version_by_estimate($estimate_id);
        if (!$snapshot || !$version) {
            return;
        }

        $this->db->where('estimate_id', (int) $estimate_id)->update(
            db_prefix() . 'sales_pipeline_estimate_versions',
            [
                'source_currency_id'    => (int) $snapshot['currency'],
                'source_total'          => (float) $snapshot['total'],
                'exchange_rate_to_base' => $snapshot['exchange_rate_to_base'],
                'base_currency_id'      => (int) $snapshot['base_currency_id'],
                'base_total'            => $snapshot['base_total'],
                'rate_captured_at'      => $snapshot['exchange_rate_to_base'] === null ? null : date('Y-m-d H:i:s'),
            ]
        );

        if ((int) $version['revision_no'] === 1) {
            $opportunity = $this->db
                ->select('outcome')
                ->where('id', (int) $version['estimate_group_id'])
                ->get(db_prefix() . 'sales_pipeline_estimate_groups')
                ->row_array();
            if ($opportunity && $opportunity['outcome'] === 'pending') {
                $this->db->where('id', (int) $version['estimate_group_id'])->update(
                    db_prefix() . 'sales_pipeline_estimate_groups',
                    [
                        'source_currency_id' => (int) $snapshot['currency'],
                        'base_currency_id'   => (int) $snapshot['base_currency_id'],
                        'created_value_base' => $snapshot['base_total'],
                        'datemodified'       => date('Y-m-d H:i:s'),
                    ]
                );
            }
        }
    }

    public function sync_estimate_group($group_id, $source = 'sync', $effective_at = null)
    {
        $group_table = db_prefix() . 'sales_pipeline_estimate_groups';
        $version_table = db_prefix() . 'sales_pipeline_estimate_versions';
        $estimate_table = db_prefix() . 'estimates';
        $group = $this->db->where('id', (int) $group_id)->get($group_table)->row_array();
        if (!$group) {
            return false;
        }

        require_once module_dir_path('sales_pipeline', 'libraries/Finance_lock_guard.php');
        $lockGuard = new Finance_lock_guard();
        if (!$lockGuard->can_modify($group)) {
            return true;
        }

        $accepted = $this->db
            ->select('e.id, e.status, e.sale_agent, e.addedfrom, e.invoiced_date')
            ->from($version_table . ' ev')
            ->join($estimate_table . ' e', 'e.id = ev.estimate_id')
            ->where('ev.estimate_group_id', (int) $group_id)
            ->where('e.status', 4)
            ->order_by('ev.revision_no', 'desc')
            ->limit(1)
            ->get()
            ->row_array();

        $decision_estimate = null;
        if ($accepted) {
            $new_outcome = 'accepted';
            $decision_estimate = $accepted;
        } else {
            $current = $this->db
                ->select('id, status, sale_agent, addedfrom, invoiced_date')
                ->where('id', (int) $group['current_estimate_id'])
                ->get($estimate_table)
                ->row_array();
            $new_outcome = $current && (int) $current['status'] === 3 ? 'declined' : 'pending';
            if ($new_outcome === 'declined') {
                $decision_estimate = $current;
            }
        }

        $now = date('Y-m-d H:i:s');
        $update = ['datemodified' => $now];
        $event_source = $source;
        if ($new_outcome === 'pending') {
            $decision_at = null;
            $update['decision_at'] = null;
            $update['decision_source'] = null;
            $update['decision_owner_staff_id'] = null;
            $update['decision_estimate_id'] = null;
            $update['decision_value_base'] = null;
        } else {
            if ($effective_at) {
                $decision_at = $effective_at;
            } else {
                $status_event = $this->find_estimate_status_event(
                    (int) $decision_estimate['id'],
                    $new_outcome === 'accepted' ? 4 : 3
                );
                $decision_at = $status_event['effective_at'];
                $event_source = $status_event['source'] ?: $source;
            }
            $decision_owner = (int) $decision_estimate['sale_agent'] > 0
                ? (int) $decision_estimate['sale_agent']
                : (int) $decision_estimate['addedfrom'];
            $update['decision_at'] = $decision_at;
            $update['decision_source'] = $event_source;
            $update['decision_owner_staff_id'] = $decision_owner;
            $update['decision_estimate_id'] = (int) $decision_estimate['id'];
            $decision_version = $this->get_estimate_version_by_estimate((int) $decision_estimate['id']);
            $update['decision_value_base'] = $new_outcome === 'accepted' && $decision_version
                ? $decision_version['base_total']
                : null;
        }

        $outcome_changed = $group['outcome'] !== $new_outcome;
        $this->load->library('sales_pipeline/Estimate_group_reconciliation_policy');
        $should_persist = $this->estimate_group_reconciliation_policy->should_persist(
            $group,
            $new_outcome,
            $decision_estimate ? (int) $decision_estimate['id'] : null,
            $update['decision_value_base']
        );
        if (!$should_persist) {
            return true;
        }

        $update['outcome'] = $new_outcome;
        $this->db->trans_start();
        $this->db->where('id', (int) $group_id)->update($group_table, $update);
        if ($outcome_changed) {
            $this->record_estimate_outcome_history(
                (int) $group_id,
                $decision_estimate ? (int) $decision_estimate['id'] : (int) $group['current_estimate_id'],
                $group['outcome'],
                $new_outcome,
                $decision_at ?: $now,
                $event_source ?: $source
            );
        }
        $this->db->trans_complete();

        return $this->db->trans_status();
    }

    private function find_estimate_status_event($estimate_id, $status)
    {
        $activity_table = db_prefix() . 'sales_activity';
        $this->db->select('date');
        $this->db->from($activity_table);
        $this->db->where('rel_type', 'estimate');
        $this->db->where('rel_id', (int) $estimate_id);
        $this->db->group_start();
        if ((int) $status === 4) {
            $this->db->where_in('description', [
                'estimate_activity_client_accepted',
                'estimate_activity_client_accepted_and_converted',
            ]);
        } else {
            $this->db->where('description', 'estimate_activity_client_declined');
        }
        $this->db->or_like('additional_data', '<new_status>' . (int) $status . '</new_status>', 'both', false);
        $this->db->or_like('additional_data', '<status>' . (int) $status . '</status>', 'both', false);
        $this->db->group_end();
        $activity = $this->db->order_by('date', 'desc')->limit(1)->get()->row_array();
        if ($activity) {
            return ['effective_at' => $activity['date'], 'source' => 'activity_log'];
        }

        if ((int) $status === 4) {
            $estimate = $this->db
                ->select('invoiced_date')
                ->where('id', (int) $estimate_id)
                ->get(db_prefix() . 'estimates')
                ->row_array();
            if ($estimate && !empty($estimate['invoiced_date'])) {
                return ['effective_at' => $estimate['invoiced_date'], 'source' => 'invoice_conversion'];
            }
        }

        return ['effective_at' => date('Y-m-d H:i:s'), 'source' => 'observed'];
    }

    private function record_estimate_outcome_history(
        $group_id,
        $estimate_id,
        $previous_outcome,
        $new_outcome,
        $effective_at,
        $source
    ) {
        return $this->db->insert(db_prefix() . 'sales_pipeline_estimate_outcome_history', [
            'estimate_group_id' => (int) $group_id,
            'estimate_id'          => $estimate_id ? (int) $estimate_id : null,
            'previous_outcome'     => $previous_outcome,
            'new_outcome'          => $new_outcome,
            'effective_at'         => $effective_at,
            'observed_at'          => date('Y-m-d H:i:s'),
            'source'               => $source,
            'changed_by'           => is_staff_logged_in() ? get_staff_user_id() : null,
        ]);
    }




    /**
     * Dữ liệu tổng hợp cho Executive Dashboard.
     *
     * @param int|null $staff_id Giới hạn dashboard theo một nhân viên
     * @param string|array $period Loại kỳ hoặc phạm vi đã chuẩn hóa
     * @param string|null $period_anchor Ngày thuộc kỳ lịch sử (Y-m-d)
     * @return array
     */
    public function get_executive_dashboard($staff_id = null, $period = 'this_month', $period_anchor = null)
    {
        $period = is_array($period) && isset($period['key'], $period['start'], $period['end'])
            ? $period
            : $this->resolve_dashboard_period($period, $period_anchor);
        $staff_metrics = $this->get_staff_kpi_metrics($staff_id, $period);
        $quote_metrics = $this->get_estimate_dashboard_metrics($staff_id, $period);
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

        $metrics = [
            'open_deals'             => 0,
            'period_deals'           => 0,
            'period_won_deals'       => 0,
            'period_revenue'         => 0,
            'revenue_this_month'     => 0,
            'period_win_rate'        => 0,
        ];

        foreach ($staff_metrics as $metric) {
            $summary['estimates_today'] += $metric['count_estimates_today'];
            $summary['estimates_month'] += $metric['count_estimates_month'];
            $summary['revenue_week'] += $metric['sum_revenue_this_week'];
            $metrics['open_deals'] += $metric['open_pipeline_deals'];
            $metrics['period_deals'] += $metric['period_deals'];
            $metrics['period_won_deals'] += $metric['period_won_deals'];
            $metrics['period_revenue'] += $metric['period_revenue'];
            $metrics['revenue_this_month'] += $metric['sum_revenue_this_month'];
        }

        $metrics['period_win_rate'] = $metrics['period_deals'] > 0
            ? round(($metrics['period_won_deals'] / $metrics['period_deals']) * 100, 1)
            : 0;

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

        $dashboard = [
            'summary' => $summary,
            'metrics' => $metrics,
            'staff'   => $staff_metrics,
            'deals'   => [
                'summary'     => $metrics,
                'leaderboard' => $staff_metrics,
            ],
            'estimates' => $quote_metrics,
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
                'anchor' => $period['anchor'] ?? date('Y-m-d'),
            ],
            'revenue_kpi'          => $this->get_revenue_timeseries($staff_id, $period),
            'estimate_revenue_kpi' => $this->get_estimate_revenue_timeseries($staff_id, $period),
        ];

        return $dashboard;
    }

    /**
     * Xác định các mốc thời gian (buckets) cho Sparkline Area Chart theo kỳ.
     *
     * @param string $period_key
     * @return array
     */
    private function resolve_revenue_timeseries_buckets($period_key = 'this_month')
    {
        $period = is_array($period_key) && isset($period_key['key'], $period_key['start'], $period_key['end'])
            ? $period_key
            : $this->resolve_dashboard_period($period_key);
        $key = $period['key'];

        $buckets = [];
        $current_range = ['start' => $period['start'], 'end' => $period['end']];
        $previous_range = [];

        if ($key === 'this_week') {
            $cur_start = $period['start']; // Thứ Hai tuần này
            $prev_start = date('Y-m-d', strtotime('-7 days', strtotime($cur_start)));
            $prev_end   = date('Y-m-d', strtotime('-1 day', strtotime($cur_start)));
            $previous_range = ['start' => $prev_start, 'end' => $prev_end];

            $day_labels = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
            for ($i = 0; $i < 7; $i++) {
                $c_date = date('Y-m-d', strtotime("+$i days", strtotime($cur_start)));
                $p_date = date('Y-m-d', strtotime("+$i days", strtotime($prev_start)));
                $buckets[] = [
                    'label'          => $day_labels[$i] ?? ('D' . ($i + 1)),
                    'current_start'  => $c_date,
                    'current_end'    => $c_date,
                    'previous_start' => $p_date,
                    'previous_end'   => $p_date,
                ];
            }
        } elseif ($key === 'this_quarter') {
            $cur_start = $period['start'];
            $cur_end   = $period['end'];
            $month = (int) date('n', strtotime($cur_start));
            $quarter = (int) ceil($month / 3);

            $prev_quarter = $quarter === 1 ? 4 : $quarter - 1;
            $prev_year = $quarter === 1 ? ((int) date('Y', strtotime($cur_start)) - 1) : (int) date('Y', strtotime($cur_start));
            $prev_start_month = (($prev_quarter - 1) * 3) + 1;
            $prev_start = date("$prev_year-" . str_pad((string) $prev_start_month, 2, '0', STR_PAD_LEFT) . "-01");
            $prev_end   = date('Y-m-t', strtotime($prev_start . ' +2 months'));
            $previous_range = ['start' => $prev_start, 'end' => $prev_end];

            // Chia Quý thành 12–13 tuần (mỗi tuần 1 mốc)
            $w_cur_start = strtotime($cur_start);
            $w_cur_final = strtotime($cur_end);
            $w_prev_start = strtotime($prev_start);
            $w_prev_final = strtotime($prev_end);

            $week_idx = 1;
            while ($w_cur_start <= $w_cur_final) {
                $w_cur_end_ts = min(strtotime('+6 days', $w_cur_start), $w_cur_final);
                $w_prev_end_ts = min(strtotime('+6 days', $w_prev_start), $w_prev_final);

                $c_start_d = date('Y-m-d', $w_cur_start);
                $c_end_d   = date('Y-m-d', $w_cur_end_ts);
                $p_start_d = date('Y-m-d', $w_prev_start);
                $p_end_d   = date('Y-m-d', $w_prev_end_ts);

                $buckets[] = [
                    'label'          => 'T' . $week_idx,
                    'current_start'  => $c_start_d,
                    'current_end'    => $c_end_d,
                    'previous_start' => $p_start_d,
                    'previous_end'   => $p_end_d,
                ];

                $w_cur_start = strtotime('+1 day', $w_cur_end_ts);
                $w_prev_start = strtotime('+1 day', $w_prev_end_ts);
                $week_idx++;
            }
        } elseif ($key === 'this_year') {
            $year = (int) date('Y', strtotime($period['start']));
            $prev_year = $year - 1;
            $previous_range = ['start' => "$prev_year-01-01", 'end' => "$prev_year-12-31"];

            for ($i = 1; $i <= 12; $i++) {
                $m_str = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                $c_start = "$year-$m_str-01";
                $c_end   = date('Y-m-t', strtotime($c_start));
                $p_start = "$prev_year-$m_str-01";
                $p_end   = date('Y-m-t', strtotime($p_start));
                $buckets[] = [
                    'label'          => 'T' . $i,
                    'current_start'  => $c_start,
                    'current_end'    => $c_end,
                    'previous_start' => $p_start,
                    'previous_end'   => $p_end,
                ];
            }
        } else {
            // this_month (toàn bộ 28–31 ngày, mỗi ngày 1 mốc)
            $cur_start = $period['start'];
            $cur_end   = $period['end'];
            $cur_days  = (int) date('t', strtotime($cur_start));

            $prev_month_ts = strtotime('-1 month', strtotime($cur_start));
            $prev_start    = date('Y-m-01', $prev_month_ts);
            $prev_end      = date('Y-m-t', $prev_month_ts);
            $prev_days     = (int) date('t', strtotime($prev_start));
            $previous_range = ['start' => $prev_start, 'end' => $prev_end];

            for ($day = 1; $day <= $cur_days; $day++) {
                $day_str = str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                $c_date = date('Y-m-', strtotime($cur_start)) . $day_str;

                if ($day <= $prev_days) {
                    $p_date = date('Y-m-', strtotime($prev_start)) . $day_str;
                } else {
                    $p_date = '1970-01-01'; // ngoài phạm vi ngày tháng trước
                }

                $buckets[] = [
                    'label'          => $day_str,
                    'current_start'  => $c_date,
                    'current_end'    => $c_date,
                    'previous_start' => $p_date,
                    'previous_end'   => $p_date,
                ];
            }
        }

        return [
            'period'         => $period,
            'buckets'        => $buckets,
            'current_range'  => $current_range,
            'previous_range' => $previous_range,
        ];
    }

    /**
     * Lấy chuỗi thời gian doanh thu Deal (kỳ hiện tại vs kỳ trước) phục vụ Sparkline Area Chart.
     *
     * @param int|null $staff_id
     * @param string   $period_key
     * @return array
     */
    public function get_revenue_timeseries($staff_id = null, $period_key = 'this_month')
    {
        $resolved = $this->resolve_revenue_timeseries_buckets($period_key);
        $buckets = $resolved['buckets'];
        $current_range = $resolved['current_range'];
        $previous_range = $resolved['previous_range'];

        $staff_members = $this->get_dashboard_staff_members($staff_id);
        if (empty($staff_members)) {
            return [
                'current_total'    => 0,
                'previous_total'   => 0,
                'change_pct'       => 0,
                'change_pct_raw'   => 0,
                'change_direction' => 'flat',
                'series'           => [
                    'current'  => [],
                    'previous' => [],
                    'labels'   => [],
                ],
                'formatted'        => [
                    'current_total'  => sales_pipeline_compact_money(0),
                    'previous_total' => sales_pipeline_compact_money(0),
                ],
            ];
        }

        $staff_ids = array_map(function ($s) {
            return (int) $s['staffid'];
        }, $staff_members);

        $overall_start = min($current_range['start'], $previous_range['start']);
        $overall_end   = max($current_range['end'], $previous_range['end']);

        $pipeline_table = db_prefix() . 'sales_pipeline';
        $status_table   = db_prefix() . 'sales_pipeline_statuses';

        $this->db->select('sp.deal_date, COALESCE(SUM(sp.deal_value), 0) as daily_revenue', false);
        $this->db->from($pipeline_table . ' sp');
        $this->db->join($status_table . ' ss', 'ss.id = sp.status', 'left');
        $this->db->where('ss.is_won', 1);
        $this->db->where('sp.deal_date >=', $overall_start);
        $this->db->where('sp.deal_date <=', $overall_end);
        $this->db->where_in('sp.staff_id', $staff_ids);
        $this->db->group_by('sp.deal_date');

        $rows = $this->db->get()->result_array();
        $daily_map = [];
        foreach ($rows as $row) {
            $daily_map[$row['deal_date']] = (float) $row['daily_revenue'];
        }

        $current_series = [];
        $previous_series = [];
        $labels = [];

        foreach ($buckets as $bucket) {
            $labels[] = $bucket['label'];

            $c_val = 0;
            $c_curr = strtotime($bucket['current_start']);
            $c_last = strtotime($bucket['current_end']);
            while ($c_curr <= $c_last) {
                $d_str = date('Y-m-d', $c_curr);
                if (isset($daily_map[$d_str])) {
                    $c_val += $daily_map[$d_str];
                }
                $c_curr = strtotime('+1 day', $c_curr);
            }
            $current_series[] = round($c_val, 2);

            $p_val = 0;
            $p_curr = strtotime($bucket['previous_start']);
            $p_last = strtotime($bucket['previous_end']);
            while ($p_curr <= $p_last) {
                $d_str = date('Y-m-d', $p_curr);
                if (isset($daily_map[$d_str])) {
                    $p_val += $daily_map[$d_str];
                }
                $p_curr = strtotime('+1 day', $p_curr);
            }
            $previous_series[] = round($p_val, 2);
        }

        $current_total = array_sum($current_series);
        $previous_total = array_sum($previous_series);

        if ($previous_total > 0) {
            $change_pct = round((($current_total - $previous_total) / $previous_total) * 100, 1);
        } else {
            $change_pct = $current_total > 0 ? 100.0 : 0.0;
        }

        if ($change_pct > 0) {
            $change_direction = 'up';
        } elseif ($change_pct < 0) {
            $change_direction = 'down';
        } else {
            $change_direction = 'flat';
        }

        return [
            'current_total'    => $current_total,
            'previous_total'   => $previous_total,
            'change_pct'       => abs($change_pct),
            'change_pct_raw'   => $change_pct,
            'change_direction' => $change_direction,
            'series'           => [
                'current'  => $current_series,
                'previous' => $previous_series,
                'labels'   => $labels,
            ],
            'formatted'        => [
                'current_total'  => sales_pipeline_compact_money($current_total),
                'previous_total' => sales_pipeline_compact_money($previous_total),
            ],
        ];
    }

    /**
     * Lấy chuỗi thời gian doanh thu Báo giá đã chấp nhận (kỳ hiện tại vs kỳ trước) phục vụ Sparkline Area Chart.
     *
     * @param int|null $staff_id
     * @param string   $period_key
     * @return array
     */
    public function get_estimate_revenue_timeseries($staff_id = null, $period_key = 'this_month')
    {
        $resolved = $this->resolve_revenue_timeseries_buckets($period_key);
        $buckets = $resolved['buckets'];
        $current_range = $resolved['current_range'];
        $previous_range = $resolved['previous_range'];

        $staff_members = $this->get_dashboard_staff_members($staff_id);
        if (empty($staff_members) || !$this->estimate_group_schema_available()) {
            return [
                'current_total'    => 0,
                'previous_total'   => 0,
                'change_pct'       => 0,
                'change_pct_raw'   => 0,
                'change_direction' => 'flat',
                'series'           => [
                    'current'  => [],
                    'previous' => [],
                    'labels'   => [],
                ],
                'formatted'        => [
                    'current_total'  => sales_pipeline_compact_money(0),
                    'previous_total' => sales_pipeline_compact_money(0),
                ],
            ];
        }

        $staff_ids = array_map(function ($s) {
            return (int) $s['staffid'];
        }, $staff_members);

        $overall_start = min($current_range['start'], $previous_range['start']) . ' 00:00:00';
        $overall_end   = max($current_range['end'], $previous_range['end']) . ' 23:59:59';

        $group_table = db_prefix() . 'sales_pipeline_estimate_groups';
        $decision_owner = 'COALESCE(grp.decision_owner_staff_id, grp.owner_staff_id)';

        $this->db->select(
            'DATE(grp.decision_at) as decision_date, '
            . 'COALESCE(SUM(grp.decision_value_base), 0) as daily_revenue',
            false
        );
        $this->db->from($group_table . ' grp');
        $this->db->where('grp.outcome', 'accepted');
        $this->db->where('grp.decision_at >=', $overall_start);
        $this->db->where('grp.decision_at <=', $overall_end);
        $this->db->where_in($decision_owner, $staff_ids, false);
        $this->db->group_by('DATE(grp.decision_at)');

        $rows = $this->db->get()->result_array();
        $daily_map = [];
        foreach ($rows as $row) {
            $daily_map[$row['decision_date']] = (float) $row['daily_revenue'];
        }

        $current_series = [];
        $previous_series = [];
        $labels = [];

        foreach ($buckets as $bucket) {
            $labels[] = $bucket['label'];

            $c_val = 0;
            $c_curr = strtotime($bucket['current_start']);
            $c_last = strtotime($bucket['current_end']);
            while ($c_curr <= $c_last) {
                $d_str = date('Y-m-d', $c_curr);
                if (isset($daily_map[$d_str])) {
                    $c_val += $daily_map[$d_str];
                }
                $c_curr = strtotime('+1 day', $c_curr);
            }
            $current_series[] = round($c_val, 2);

            $p_val = 0;
            $p_curr = strtotime($bucket['previous_start']);
            $p_last = strtotime($bucket['previous_end']);
            while ($p_curr <= $p_last) {
                $d_str = date('Y-m-d', $p_curr);
                if (isset($daily_map[$d_str])) {
                    $p_val += $daily_map[$d_str];
                }
                $p_curr = strtotime('+1 day', $p_curr);
            }
            $previous_series[] = round($p_val, 2);
        }

        $current_total = array_sum($current_series);
        $previous_total = array_sum($previous_series);

        if ($previous_total > 0) {
            $change_pct = round((($current_total - $previous_total) / $previous_total) * 100, 1);
        } else {
            $change_pct = $current_total > 0 ? 100.0 : 0.0;
        }

        if ($change_pct > 0) {
            $change_direction = 'up';
        } elseif ($change_pct < 0) {
            $change_direction = 'down';
        } else {
            $change_direction = 'flat';
        }

        return [
            'current_total'    => $current_total,
            'previous_total'   => $previous_total,
            'change_pct'       => abs($change_pct),
            'change_pct_raw'   => $change_pct,
            'change_direction' => $change_direction,
            'series'           => [
                'current'  => $current_series,
                'previous' => $previous_series,
                'labels'   => $labels,
            ],
            'formatted'        => [
                'current_total'  => sales_pipeline_compact_money($current_total),
                'previous_total' => sales_pipeline_compact_money($previous_total),
            ],
        ];
    }

    /**
     * Resolve dashboard leaderboard period.
     *
     * @param string|null $period
     * @param string|null $anchor
     * @return array
     */
    public function resolve_dashboard_period($period, $anchor = null)
    {
        return sales_pipeline_resolve_dashboard_period($period, $anchor);
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
                'is_manager'               => (int) $staff['admin'] === 1
                    || has_permission('sales_pipeline', (string) $sid, 'view'),
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

        require_once module_dir_path('sales_pipeline', 'libraries/Quote_count_repository.php');
        $quoteRepo = new Quote_count_repository();
        $period_end_exclusive = date('Y-m-d 00:00:00', strtotime($period_end . ' +1 day'));
        $period_quote_counts = $quoteRepo->get_counts_by_staff($staff_ids, $period_start . ' 00:00:00', $period_end_exclusive);

        foreach ($period_quote_counts as $sid => $cnt) {
            if (isset($metrics[$sid])) {
                $metrics[$sid]['period_estimates'] = (int) $cnt;
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
            $has_sales_activity = $metric['period_deals'] > 0
                || $metric['period_estimates'] > 0
                || $metric['period_revenue'] > 0;

            if ($staff_id === null && $metric['is_manager'] && !$has_sales_activity) {
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
            unset($metric['is_manager']);
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
            rl.id,
            rl.pipeline_id,
            rl.entity_id,
            rl.staff_id,
            rl.title,
            rl.message,
            rl.response_required,
            rl.severity,
            rl.staff_response,
            rl.responded_at,
            rl.sent_at,
            rl.entity_type,
            rl.rule_code,
            rl.snapshot_json,
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
        if (!empty($filters['date_from'])) {
            $date_from_str = trim((string) $filters['date_from']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from_str)) {
                $this->db->where('COALESCE(rl.responded_at, rl.sent_at, rl.created_at) >=', $date_from_str . ' 00:00:00');
            }
        }
        if (!empty($filters['date_to'])) {
            $date_to_str = trim((string) $filters['date_to']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to_str)) {
                $next_day_exclusive = date('Y-m-d 00:00:00', strtotime($date_to_str . ' +1 day'));
                $this->db->where('COALESCE(rl.responded_at, rl.sent_at, rl.created_at) <', $next_day_exclusive);
            }
        }

        if (!empty($filters['month'])) {
            $this->db->where('MONTH(rl.sent_at)', $filters['month']);
        }
        if (!empty($filters['year'])) {
            $this->db->where('YEAR(rl.sent_at)', $filters['year']);
        }

        $dashboard_tab = isset($filters['dashboard_tab']) ? trim((string) $filters['dashboard_tab']) : 'deals';
        if ($dashboard_tab === 'estimates') {
            $this->db->where_in('rl.entity_type', ['staff_estimate_period', 'estimate']);
        } else {
            $this->db->where_in('rl.entity_type', ['deal', 'staff_deal_period', 'staff_deal_backlog']);
        }

        $this->db->order_by('CASE WHEN rl.staff_response IS NULL THEN 0 ELSE 1 END', 'ASC', false);
        $this->db->order_by('COALESCE(rl.responded_at, rl.sent_at, rl.created_at)', 'DESC', false);
        $this->db->order_by('rl.id', 'DESC');
        $this->db->limit($limit);

        return $this->db->get()->result_array();
    }

    /** Latest lifecycle reminder per Estimate for the staff follow-up queue. */
    public function get_staff_estimate_follow_ups($staff_id, $limit = 20)
    {
        $staff_id = (int) $staff_id;
        if ($staff_id <= 0) {
            return [];
        }
        $limit = max(1, min(50, (int) $limit));
        $rules = [
            'ESTIMATE_DRAFT_TOO_LONG', 'ESTIMATE_SENT_NO_RESPONSE', 'ESTIMATE_DECLINED_RECENT',
            'ESTIMATE_EXPIRED', 'ESTIMATE_ACCEPTED_NOT_INVOICED',
        ];
        $table = db_prefix() . 'sales_pipeline_reminders_log';
        $subquery = '(SELECT MAX(inner_rl.id) FROM `' . $table . '` inner_rl'
            . ' WHERE inner_rl.staff_id = rl.staff_id AND inner_rl.entity_type = "estimate"'
            . ' AND inner_rl.entity_id = rl.entity_id)';
        return $this->db->select('rl.id,rl.entity_id,rl.rule_code,rl.title,rl.message,rl.severity,rl.response_required,rl.staff_response,rl.responded_at,rl.sent_at,rl.created_at,rl.snapshot_json')
            ->from($table . ' rl')->where('rl.staff_id', $staff_id)->where('rl.entity_type', 'estimate')
            ->where_in('rl.rule_code', $rules)->where('rl.id = ' . $subquery, null, false)
            ->order_by('CASE rl.severity WHEN "critical" THEN 0 ELSE 1 END', 'ASC', false)
            ->order_by('COALESCE(rl.sent_at,rl.created_at)', 'DESC', false)->limit($limit)->get()->result_array();
    }

    /**
     * Lấy danh sách Báo giá đang mở (chưa đóng) theo nhân viên.
     * Nguồn: tblestimates với status IN (1, 2) — Draft, Sent.
     * Không dùng sales_pipeline_reminders_log làm nguồn chính.
     *
     * @param int $staff_id ID nhân viên
     * @param int $limit    Giới hạn số record
     * @return array
     */
    public function get_staff_open_estimates($staff_id, $limit = 20)
    {
        $staff_id = (int) $staff_id;
        if ($staff_id <= 0) {
            return [];
        }
        $limit = max(1, min(50, (int) $limit));

        $this->db->select([
            'e.id',
            'e.number',
            'e.prefix',
            'e.clientid',
            'e.subtotal',
            'e.total',
            'e.status',
            'e.datecreated',
            'e.expirydate',
            'e.datesend',
            'c.company as customer_name',
        ]);
        $this->db->from(db_prefix() . 'estimates e');
        $this->db->join(db_prefix() . 'clients c', 'c.userid = e.clientid', 'left');
        $this->db->where_in('e.status', [1, 2]);
        $this->db->where('(CASE WHEN e.sale_agent > 0 THEN e.sale_agent ELSE e.addedfrom END) = ' . $staff_id, null, false);
        $this->db->order_by('e.datecreated', 'DESC');
        $this->db->order_by('e.id', 'DESC');
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

    /**
     * Reconcile missing or legacy-inferred first_sent_at on estimate groups.
     *
     * Protected by unified advisory lock '{db_prefix}sales_pipeline:first_sent_reconcile'
     * and request cooldown for dashboard opportunistic execution.
     *
     * @param int $limit Batch size
     * @param int|null $cursor ID cursor (id > $cursor). If null, uses persistent option checkpoint 'sp_first_sent_reconcile_cursor'.
     * @param bool $force If true (migration/cron), bypasses cooldown and waits for lock up to 10s.
     * @param bool $lockAlreadyHeld If true, the caller owns the unified lock and
     *                              this method must not acquire/release it.
     * @return array
     */
    public function reconcile_missing_first_sent_groups($limit = 100, $cursor = null, $force = false, $lockAlreadyHeld = false)
    {
        $limit = max(1, min(1000, (int) $limit));
        $lockName = db_prefix() . 'sales_pipeline:first_sent_reconcile';
        $cooldownOption = 'sp_first_sent_reconcile_last_run';
        $cursorOption = 'sp_first_sent_reconcile_cursor';
        $cooldownSeconds = 300; // 5 minutes

        $emptyResult = [
            'status'              => 'unknown',
            'reason'              => null,
            'scanned'             => 0,
            'captured'            => 0,
            'updated_from_legacy' => 0,
            'reversed_to_null'    => 0,
            'unchanged'           => 0,
            'no_evidence'         => 0,
            'error_count'         => 0,
            'cursor_before'       => ($cursor === null) ? (int) get_option($cursorOption) : max(0, (int) $cursor),
            'next_cursor'         => ($cursor === null) ? (int) get_option($cursorOption) : max(0, (int) $cursor),
            'has_more'            => false,
            'cycle_completed'     => false,
        ];

        if (!$this->db || !$this->db->table_exists(db_prefix() . 'sales_pipeline_estimate_groups')) {
            return array_merge($emptyResult, [
                'status'      => 'failed',
                'reason'      => 'table_missing',
                'error_count' => 1,
            ]);
        }

        // 1. Check cooldown for opportunistic calls (Dashboard / Drawer / unforced cron)
        if (!$force) {
            $lastRun = $this->get_reconcile_last_run();
            if ($lastRun > 0 && (time() - $lastRun) < $cooldownSeconds) {
                $curCursor = $this->get_reconcile_cursor();
                return array_merge($emptyResult, [
                    'status'        => 'skipped_cooldown',
                    'reason'        => 'cooldown_active',
                    'cursor_before' => $curCursor,
                    'next_cursor'   => $curCursor,
                ]);
            }
        }

        // 2. Acquire unified advisory lock unless the caller already owns it.
        $lockAcquiredHere = false;
        if (!$lockAlreadyHeld) {
            $timeout = $force ? 10 : 0;
            $lockRes = $this->db->query("SELECT GET_LOCK('{$lockName}', {$timeout}) as is_locked")->row();
            if (!$lockRes || (int) $lockRes->is_locked !== 1) {
                if ($force) {
                    throw new RuntimeException("Could not acquire advisory lock {$lockName} after {$timeout}s");
                }
                $curCursor = $this->get_reconcile_cursor();
                return array_merge($emptyResult, [
                    'status'        => 'skipped_lock',
                    'reason'        => 'advisory_lock_held',
                    'cursor_before' => $curCursor,
                    'next_cursor'   => $curCursor,
                ]);
            }
            $lockAcquiredHere = true;
        }

        try {
            $this->load->library('sales_pipeline/Quote_first_sent_service');
            $groupsTable = db_prefix() . 'sales_pipeline_estimate_groups';
            $versionsTable = db_prefix() . 'sales_pipeline_estimate_versions';
            $estimatesTable = db_prefix() . 'estimates';
            $activityTable = db_prefix() . 'sales_activity';

            // Resolve cursor inside the lock to guarantee no concurrent race
            $usePersistentCursor = ($cursor === null);
            if ($usePersistentCursor) {
                $cursor = $this->get_reconcile_cursor();
            } else {
                $cursor = max(0, (int) $cursor);
            }
            $cursorBefore = $cursor;

            // Query batch of candidate groups starting from cursor
            $groups = $this->db
                ->select('id, first_sent_at, first_sent_source, first_sent_estimate_id')
                ->where('id >', $cursor)
                ->order_by('id', 'ASC')
                ->limit($limit)
                ->get($groupsTable)
                ->result_array();

            $scanned = count($groups);

            // If empty, table scan cycle has completed (or table is empty); reset cursor to 0
            if ($scanned === 0) {
                if ($usePersistentCursor && $cursorBefore > 0) {
                    $this->set_reconcile_cursor(0);
                }
                $this->set_reconcile_last_run(time());

                return [
                    'status'              => 'processed',
                    'reason'              => null,
                    'scanned'             => 0,
                    'captured'            => 0,
                    'updated_from_legacy' => 0,
                    'reversed_to_null'    => 0,
                    'unchanged'           => 0,
                    'no_evidence'         => 0,
                    'error_count'         => 0,
                    'cursor_before'       => $cursorBefore,
                    'next_cursor'         => 0,
                    'has_more'            => false,
                    'cycle_completed'     => true,
                ];
            }

            $captured = 0;
            $updatedFromLegacy = 0;
            $reversedToNull = 0;
            $unchanged = 0;
            $noEvidence = 0;
            $errors = 0;
            $lastSuccessfulGroupId = $cursorBefore;
            $batchError = null;

            foreach ($groups as $grp) {
                $groupId = (int) $grp['id'];

                try {
                    // Check estimate versions in this group
                    $estimates = $this->db
                        ->select('e.id, e.status, e.sent, e.datesend, e.date, e.datecreated, e.invoiceid, e.invoiced_date')
                        ->from($versionsTable . ' v')
                        ->join($estimatesTable . ' e', 'e.id = v.estimate_id')
                        ->where('v.estimate_group_id', $groupId)
                        ->order_by('e.id', 'ASC')
                        ->get()
                        ->result_array();

                    if (empty($estimates)) {
                        $noEvidence++;
                        $lastSuccessfulGroupId = $groupId;
                        continue;
                    }

                    // Check activities for estimates in this group
                    $estimateIds = array_column($estimates, 'id');
                    $activities = [];
                    if (!empty($estimateIds) && $this->db->table_exists($activityTable)) {
                        $activities = $this->db
                            ->where('rel_type', 'estimate')
                            ->where_in('rel_id', $estimateIds)
                            ->order_by('date', 'ASC')
                            ->get($activityTable)
                            ->result_array();
                    }

                    // Find strongest evidence across versions
                    $bestEvidence = ['source' => null, 'occurred_at' => null, 'estimate_id' => null];
                    $bestRank = Quote_first_sent_service::RANK_NONE;

                    foreach ($estimates as $est) {
                        $estActivities = array_filter($activities, function ($act) use ($est) {
                            return (int) $act['rel_id'] === (int) $est['id'];
                        });

                        $ev = $this->quote_first_sent_service->extract_estimate_evidence($est, $estActivities);
                        if ($ev['source'] && $ev['occurred_at']) {
                            $rank = $this->quote_first_sent_service->get_source_rank($ev['source']);
                            if ($rank > $bestRank || ($rank === $bestRank && strtotime($ev['occurred_at']) < strtotime($bestEvidence['occurred_at']))) {
                                $bestRank = $rank;
                                $bestEvidence = [
                                    'source'      => $ev['source'],
                                    'occurred_at' => $ev['occurred_at'],
                                    'estimate_id' => (int) $est['id'],
                                ];
                            }
                        }
                    }

                    // Policy A: Remediation for Group 145 & legacy inferred with NO real sent evidence
                    $currentSource = $grp['first_sent_source'];
                    $isCurrentInferred = ($this->quote_first_sent_service->get_source_rank($currentSource) === Quote_first_sent_service::RANK_INFERRED_LEGACY);

                    if ($isCurrentInferred && $bestRank === Quote_first_sent_service::RANK_NONE) {
                        // Reverse inferred without real evidence back to NULL
                        $this->db->where('id', $groupId)->update($groupsTable, [
                            'first_sent_at'          => null,
                            'first_sent_source'      => null,
                            'first_sent_estimate_id' => null,
                        ]);
                        $reversedToNull++;
                        $lastSuccessfulGroupId = $groupId;
                        continue;
                    }

                    if ($bestRank === Quote_first_sent_service::RANK_NONE) {
                        $noEvidence++;
                        $lastSuccessfulGroupId = $groupId;
                        continue;
                    }

                    // Evaluate transition
                    $shouldReplace = $this->quote_first_sent_service->evaluate_evidence_transition(
                        $grp['first_sent_source'],
                        $grp['first_sent_at'],
                        $bestEvidence['source'],
                        $bestEvidence['occurred_at']
                    );

                    if ($shouldReplace) {
                        $candidateAt = $bestEvidence['occurred_at'];
                        $escapedAt = $this->db->escape($candidateAt);
                        $escapedSource = $this->db->escape($bestEvidence['source']);
                        $escapedEstId = (int) $bestEvidence['estimate_id'];

                        $this->db->query("
                            UPDATE `{$groupsTable}`
                            SET `first_sent_estimate_id` = {$escapedEstId},
                                `first_sent_source` = {$escapedSource},
                                `first_sent_at` = {$escapedAt}
                            WHERE `id` = {$groupId}
                        ");

                        if ($isCurrentInferred) {
                            $updatedFromLegacy++;
                        } else {
                            $captured++;
                        }
                    } else {
                        $unchanged++;
                    }

                    $lastSuccessfulGroupId = $groupId;
                } catch (\Throwable $groupEx) {
                    $errors++;
                    $batchError = $groupEx->getMessage();
                    log_message('error', "sales_pipeline reconcile_missing_first_sent error on group {$groupId}: " . $groupEx->getMessage());
                    // Stop processing this batch so we do not advance cursor past the failing group
                    break;
                }
            }

            // Error handling: if errors occurred, checkpoint advances ONLY up to lastSuccessfulGroupId
            if ($errors > 0) {
                $nextCursor = $lastSuccessfulGroupId;
                if ($usePersistentCursor && $lastSuccessfulGroupId > $cursorBefore) {
                    $this->set_reconcile_cursor($lastSuccessfulGroupId);
                }
                return [
                    'status'              => 'failed',
                    'reason'              => 'group_error: ' . $batchError,
                    'scanned'             => $scanned,
                    'captured'            => $captured,
                    'updated_from_legacy' => $updatedFromLegacy,
                    'reversed_to_null'    => $reversedToNull,
                    'unchanged'           => $unchanged,
                    'no_evidence'         => $noEvidence,
                    'error_count'         => $errors,
                    'cursor_before'       => $cursorBefore,
                    'next_cursor'         => $nextCursor,
                    'has_more'            => true,
                    'cycle_completed'     => false,
                ];
            }

            // No errors: determine whether cycle completed or more batches exist
            $cycleCompleted = false;
            $hasMore = ($scanned === $limit);

            if (!$hasMore) {
                // Scanned all remaining rows in table -> reset cursor to 0
                $cycleCompleted = true;
                $nextCursor = 0;
                if ($usePersistentCursor) {
                    $this->set_reconcile_cursor(0);
                }
            } else {
                // Batch full -> advance cursor to last processed group
                $nextCursor = $lastSuccessfulGroupId;
                if ($usePersistentCursor) {
                    $this->set_reconcile_cursor($nextCursor);
                }
            }

            // Update cooldown option on successful completion
            $this->set_reconcile_last_run(time());

            return [
                'status'              => 'processed',
                'reason'              => null,
                'scanned'             => $scanned,
                'captured'            => $captured,
                'updated_from_legacy' => $updatedFromLegacy,
                'reversed_to_null'    => $reversedToNull,
                'unchanged'           => $unchanged,
                'no_evidence'         => $noEvidence,
                'error_count'         => 0,
                'cursor_before'       => $cursorBefore,
                'next_cursor'         => $nextCursor,
                'has_more'            => $hasMore,
                'cycle_completed'     => $cycleCompleted,
            ];
        } catch (\Throwable $e) {
            log_message('error', "sales_pipeline reconcile_missing_first_sent fatal error: " . $e->getMessage());
            return array_merge($emptyResult, [
                'status'        => 'failed',
                'reason'        => 'fatal: ' . $e->getMessage(),
                'error_count'   => max(1, $errors ?? 1),
                'cursor_before' => $cursorBefore ?? 0,
                'next_cursor'   => $cursorBefore ?? 0,
            ]);
        } finally {
            if ($lockAcquiredHere) {
                $this->db->query("SELECT RELEASE_LOCK('{$lockName}')");
            }
        }
    }

    public function get_reconcile_cursor()
    {
        $row = $this->db->select('value')->where('name', 'sp_first_sent_reconcile_cursor')->get(db_prefix() . 'options')->row();
        return $row ? (int) $row->value : 0;
    }

    public function set_reconcile_cursor($cursor)
    {
        $cursor = max(0, (int) $cursor);
        $name = 'sp_first_sent_reconcile_cursor';
        if (!option_exists($name)) {
            add_option($name, (string) $cursor, 0);
        } else {
            update_option($name, (string) $cursor, 0);
        }
    }

    public function get_reconcile_last_run()
    {
        $row = $this->db->select('value')->where('name', 'sp_first_sent_reconcile_last_run')->get(db_prefix() . 'options')->row();
        return $row ? (int) $row->value : 0;
    }

    public function set_reconcile_last_run($time)
    {
        $name = 'sp_first_sent_reconcile_last_run';
        if (!option_exists($name)) {
            add_option($name, (string) $time, 0);
        } else {
            update_option($name, (string) $time, 0);
        }
    }
}
