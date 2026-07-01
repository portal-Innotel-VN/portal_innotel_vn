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
     * Lấy deal theo ID hoặc tất cả
     * @param string|int $id
     * @param array $where Điều kiện lọc bổ sung
     * @return mixed
     */
    public function get($id = '', $where = [])
    {
        $this->db->select(
            db_prefix() . 'sales_pipeline.*,' .
            db_prefix() . 'sales_pipeline_statuses.name as status_name,' .
            db_prefix() . 'sales_pipeline_statuses.color as status_color,' .
            db_prefix() . 'sales_pipeline_statuses.is_won,' .
            db_prefix() . 'sales_pipeline_statuses.is_lost,' .
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

        if (!empty($where)) {
            $this->db->where($where);
        }

        if (is_numeric($id)) {
            $this->db->where(db_prefix() . 'sales_pipeline.id', $id);
            $deal = $this->db->get(db_prefix() . 'sales_pipeline')->row_array();
            if ($deal) {
                $deal['activity'] = $this->get_activity($id);
            }
            return $deal;
        }

        $this->db->order_by(db_prefix() . 'sales_pipeline.expected_close_date', 'ASC');

        return $this->db->get(db_prefix() . 'sales_pipeline')->result_array();
    }

    /**
     * Thêm deal mới
     * @param array $data
     * @return int|false Insert ID hoặc false
     */
    public function add($data)
    {
        // Tự tính lợi nhuận dự kiến
        if (isset($data['deal_value']) && isset($data['profit_margin'])) {
            $data['expected_profit'] = floatval($data['deal_value']) * floatval($data['profit_margin']);
        }

        $data['datecreated'] = date('Y-m-d H:i:s');
        $data['addedfrom']   = get_staff_user_id();

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
            $this->add_activity($insert_id, 'Tạo deal mới: ' . $data['deal_name'], null, $data['status']);

            // Log activity bổ sung nếu có ghi chú
            if (!empty($activity_description)) {
                $this->add_activity($insert_id, $activity_description);
            }

            log_activity('Sales Pipeline - Deal mới [ID: ' . $insert_id . '] ' . $data['deal_name']);
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
        // Lấy deal cũ để so sánh trạng thái
        $old_deal = $this->get($id);
        $old_status = $old_deal ? $old_deal['status'] : null;

        // Tự tính lợi nhuận
        if (isset($data['deal_value']) && isset($data['profit_margin'])) {
            $data['expected_profit'] = floatval($data['deal_value']) * floatval($data['profit_margin']);
        }

        $data['datemodified'] = date('Y-m-d H:i:s');

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
                    'Đổi trạng thái: ' . $old_status_name . ' → ' . $new_status_name,
                    $old_status,
                    $data['status']
                );
            }

            // Log ghi chú tiến độ
            if (!empty($activity_description)) {
                $this->add_activity($id, $activity_description);
            }

            log_activity('Sales Pipeline - Cập nhật deal [ID: ' . $id . ']');
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
                log_activity('Sales Pipeline - Xóa deal [ID: ' . $id . '] ' . $deal['deal_name']);
            }

            return true;
        }

        return false;
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
        return $row ? $row->name : 'Không xác định';
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
     * @return array
     */
    public function get_summary($quarter = null, $year = null, $staff_id = null)
    {
        if ($year === null) {
            $year = date('Y');
        }

        $where = [];
        if ($quarter) {
            $where['QUARTER(expected_close_date)'] = $quarter;
        }
        $where['YEAR(expected_close_date)'] = $year;

        if ($staff_id) {
            $where['staff_id'] = $staff_id;
        }

        $this->db->where($where);
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
            $summary['total_profit'] += floatval($deal['expected_profit']);

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

            $email_subject = '[Nhắc nhở Pipeline] ' . $deal['deal_name'] . ' - ' . $deal['customer_name'];
            $email_body = '<p>Xin chào <b>' . $staff->firstname . ' ' . $staff->lastname . '</b>,</p>'
                        . '<p>Hệ thống CRM nhắc nhở bạn cập nhật tiến độ deal:</p>'
                        . '<ul>'
                        . '<li><b>Khách hàng:</b> ' . $deal['customer_name'] . '</li>'
                        . '<li><b>Deal:</b> ' . $deal['deal_name'] . '</li>'
                        . '<li><b>Doanh số:</b> ' . number_format($deal['deal_value']) . ' VNĐ</li>'
                        . '<li><b>Ngày dự kiến:</b> ' . _d($deal['expected_close_date']) . '</li>'
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
}
