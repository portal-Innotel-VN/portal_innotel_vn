<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tech_reports_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // =========================================================================
    // CRUD: Daily Reports
    // =========================================================================

    /**
     * Lấy báo cáo theo ID hoặc tất cả
     * @param string|int $id
     * @param array $where Điều kiện lọc bổ sung
     * @return mixed
     */
    public function get($id = '', $where = [])
    {
        $this->db->select(
            db_prefix() . 'tech_daily_reports.*,' .
            'CONCAT(staff.firstname, " ", staff.lastname) as staff_name,' .
            'CONCAT(assigned.firstname, " ", assigned.lastname) as assigned_by_name,' .
            'CONCAT(creator.firstname, " ", creator.lastname) as created_by_name'
        );
        $this->db->join(
            db_prefix() . 'staff as staff',
            'staff.staffid = ' . db_prefix() . 'tech_daily_reports.staff_id',
            'left'
        );
        $this->db->join(
            db_prefix() . 'staff as assigned',
            'assigned.staffid = ' . db_prefix() . 'tech_daily_reports.assigned_by',
            'left'
        );
        $this->db->join(
            db_prefix() . 'staff as creator',
            'creator.staffid = ' . db_prefix() . 'tech_daily_reports.created_by',
            'left'
        );

        if (!empty($where)) {
            $this->db->where($where);
        }

        if (is_numeric($id)) {
            $this->db->where(db_prefix() . 'tech_daily_reports.id', $id);
            $report = $this->db->get(db_prefix() . 'tech_daily_reports')->row_array();
            if ($report) {
                $report['followers'] = $this->get_followers($id);
            }
            return $report;
        }

        $this->db->order_by(db_prefix() . 'tech_daily_reports.report_date', 'DESC');
        $this->db->order_by(db_prefix() . 'tech_daily_reports.datecreated', 'DESC');

        return $this->db->get(db_prefix() . 'tech_daily_reports')->result_array();
    }

    /**
     * Thêm báo cáo mới
     * @param array $data
     * @return int|false Insert ID hoặc false
     */
    public function add($data)
    {
        $data['datecreated'] = date('Y-m-d H:i:s');
        $data['created_by']  = get_staff_user_id();
        
        // Nếu không có staff_id, mặc định là người tạo
        if (!isset($data['staff_id']) || empty($data['staff_id'])) {
            $data['staff_id'] = get_staff_user_id();
        }

        // Xử lý followers
        $followers = [];
        if (isset($data['followers'])) {
            $followers = $data['followers'];
            unset($data['followers']);
        }

        $this->db->insert(db_prefix() . 'tech_daily_reports', $data);
        $insert_id = $this->db->insert_id();

        if ($insert_id) {
            // Thêm followers
            if (!empty($followers)) {
                foreach ($followers as $follower_id) {
                    $this->add_follower($insert_id, $follower_id);
                }
            }

            // Cập nhật compliance log nếu báo cáo cho ngày hôm nay
            if ($data['report_date'] == date('Y-m-d')) {
                $this->update_compliance_status($data['staff_id'], $data['report_date'], true);
            }

            log_activity('Tech Reports - Báo cáo mới [ID: ' . $insert_id . '] ' . substr($data['task_description'], 0, 50));
        }

        return $insert_id;
    }

    /**
     * Cập nhật báo cáo
     * @param array $data
     * @param int $id
     * @return bool
     */
    public function update($data, $id)
    {
        $data['datemodified'] = date('Y-m-d H:i:s');

        // Xử lý followers
        $followers = [];
        if (isset($data['followers'])) {
            $followers = $data['followers'];
            unset($data['followers']);
        }

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'tech_daily_reports', $data);
        $updated = $this->db->affected_rows() > 0;

        if ($updated || isset($followers)) {
            // Cập nhật followers
            if (isset($followers)) {
                // Xóa followers cũ
                $this->db->where('report_id', $id);
                $this->db->delete(db_prefix() . 'tech_report_followers');

                // Thêm followers mới
                foreach ($followers as $follower_id) {
                    $this->add_follower($id, $follower_id);
                }
            }

            log_activity('Tech Reports - Cập nhật báo cáo [ID: ' . $id . ']');
        }

        return $updated;
    }

    /**
     * Xóa báo cáo
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        $report = $this->get($id);

        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'tech_daily_reports');

        if ($this->db->affected_rows() > 0) {
            // Xóa followers
            $this->db->where('report_id', $id);
            $this->db->delete(db_prefix() . 'tech_report_followers');

            if ($report) {
                log_activity('Tech Reports - Xóa báo cáo [ID: ' . $id . ']');
            }

            return true;
        }

        return false;
    }

    // =========================================================================
    // FOLLOWERS
    // =========================================================================

    /**
     * Lấy danh sách người theo dõi của báo cáo
     * @param int $report_id
     * @return array
     */
    public function get_followers($report_id)
    {
        $this->db->select(
            db_prefix() . 'tech_report_followers.*,' .
            'CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as staff_name'
        );
        $this->db->join(
            db_prefix() . 'staff',
            db_prefix() . 'staff.staffid = ' . db_prefix() . 'tech_report_followers.staff_id',
            'left'
        );
        $this->db->where('report_id', $report_id);
        return $this->db->get(db_prefix() . 'tech_report_followers')->result_array();
    }

    /**
     * Thêm người theo dõi
     * @param int $report_id
     * @param int $staff_id
     */
    public function add_follower($report_id, $staff_id)
    {
        $this->db->insert(db_prefix() . 'tech_report_followers', [
            'report_id'   => $report_id,
            'staff_id'    => $staff_id,
            'datecreated' => date('Y-m-d H:i:s'),
        ]);
    }

    // =========================================================================
    // TECH DEPARTMENT MANAGEMENT
    // =========================================================================

    /**
     * Lấy danh sách nhân viên phòng kỹ thuật
     * @param bool $active_only Chỉ lấy active
     * @return array
     */
    public function get_tech_staff($active_only = true)
    {
        $this->db->select(
            db_prefix() . 'tech_department_staff.*,' .
            'CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as staff_name,' .
            db_prefix() . 'staff.email'
        );
        $this->db->join(
            db_prefix() . 'staff',
            db_prefix() . 'staff.staffid = ' . db_prefix() . 'tech_department_staff.staff_id',
            'left'
        );

        if ($active_only) {
            $this->db->where(db_prefix() . 'tech_department_staff.is_active', 1);
            $this->db->where(db_prefix() . 'staff.active', 1);
        }

        return $this->db->get(db_prefix() . 'tech_department_staff')->result_array();
    }

    /**
     * Thêm nhân viên vào phòng kỹ thuật
     * @param int $staff_id
     * @param string $role
     * @return bool
     */
    public function add_tech_staff($staff_id, $role = null)
    {
        // Check xem đã có chưa
        $exists = $this->db->get_where(db_prefix() . 'tech_department_staff', ['staff_id' => $staff_id])->row();
        
        if ($exists) {
            // Update
            $this->db->where('staff_id', $staff_id);
            $this->db->update(db_prefix() . 'tech_department_staff', [
                'is_active' => 1,
                'role'      => $role,
            ]);
        } else {
            // Insert
            $this->db->insert(db_prefix() . 'tech_department_staff', [
                'staff_id'    => $staff_id,
                'role'        => $role,
                'datecreated' => date('Y-m-d H:i:s'),
            ]);
        }

        return true;
    }

    // =========================================================================
    // COMPLIANCE CHECK (Cron Job Logic)
    // =========================================================================

    /**
     * Kiểm tra tuân thủ nhập báo cáo hàng ngày
     * Chạy bởi cron cuối ngày
     */
    public function check_daily_compliance($force = false)
    {
        $today = date('Y-m-d');
        $check_time = date('H:i');

        // Chỉ chạy sau 17:00 (hoặc nếu ép buộc bằng tham số)
        if (!$force && $check_time < '17:00') {
            return;
        }

        // Lấy danh sách nhân viên phòng kỹ thuật
        $tech_staff = $this->get_tech_staff(true);

        foreach ($tech_staff as $staff) {
            // Check xem đã nhập báo cáo hôm nay chưa
            $this->db->where('staff_id', $staff['staff_id']);
            $this->db->where('report_date', $today);
            $has_report = $this->db->count_all_results(db_prefix() . 'tech_daily_reports') > 0;

            // Ghi log compliance
            $this->log_compliance($staff['staff_id'], $today, $has_report);

            // Nếu chưa nhập → gửi thông báo
            if (!$has_report) {
                $this->send_compliance_reminder($staff);
            }
        }
    }

    /**
     * Ghi log tuân thủ
     * @param int $staff_id
     * @param string $check_date
     * @param bool $has_submitted
     */
    private function log_compliance($staff_id, $check_date, $has_submitted)
    {
        // Check xem đã có log chưa
        $this->db->where('staff_id', $staff_id);
        $this->db->where('check_date', $check_date);
        $existing = $this->db->get(db_prefix() . 'tech_compliance_log')->row();

        if ($existing) {
            // Update
            $this->db->where('id', $existing->id);
            $this->db->update(db_prefix() . 'tech_compliance_log', [
                'has_submitted' => $has_submitted ? 1 : 0,
            ]);
        } else {
            // Insert
            $this->db->insert(db_prefix() . 'tech_compliance_log', [
                'staff_id'      => $staff_id,
                'check_date'    => $check_date,
                'has_submitted' => $has_submitted ? 1 : 0,
                'datecreated'   => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Cập nhật trạng thái compliance khi nhân viên nhập báo cáo
     * @param int $staff_id
     * @param string $report_date
     * @param bool $has_submitted
     */
    public function update_compliance_status($staff_id, $report_date = null, $has_submitted = null)
    {
        if (is_numeric($staff_id) && is_string($report_date) && ($has_submitted !== null)) {
            $this->db->where('staff_id', $staff_id);
            $this->db->where('check_date', $report_date);
            $existing = $this->db->get(db_prefix() . 'tech_compliance_log')->row();

            if ($existing) {
                $this->db->where('id', $existing->id);
                $this->db->update(db_prefix() . 'tech_compliance_log', [
                    'has_submitted' => $has_submitted ? 1 : 0,
                ]);
            }
            return true;
        } else {
            // Trường hợp gọi từ controller: cập nhật ghi chú giải quyết tuân thủ bằng ID dòng log
            $log_id = $staff_id;
            $notes = $report_date;

            $this->db->where('id', $log_id);
            $this->db->update(db_prefix() . 'tech_compliance_log', [
                'resolved_by' => get_staff_user_id(),
                'resolved_at' => date('Y-m-d H:i:s'),
                'notes'       => $notes,
            ]);
            return $this->db->affected_rows() > 0;
        }
    }

    /**
     * Gửi nhắc nhở cho nhân viên chưa nhập báo cáo
     * @param array $staff
     */
    private function send_compliance_reminder($staff)
    {
        // Gửi notification nội bộ
        add_notification([
            'description'     => 'tech_reports_reminder',
            'touserid'        => $staff['staff_id'],
            'fromuserid'      => null,
            'link'            => 'tech_reports/report',
            'additional_data' => serialize([date('d/m/Y')]),
        ]);

        // Gửi email
        if (!empty($staff['email'])) {
            $this->load->model('emails_model');

            $email_subject = '[Nhắc nhở] Nhập báo cáo công việc ngày ' . date('d/m/Y');
            $email_body = '<p>Xin chào <b>' . $staff['staff_name'] . '</b>,</p>'
                        . '<p>Hệ thống CRM nhắc nhở bạn nhập báo cáo công việc cho ngày hôm nay (<b>' . date('d/m/Y') . '</b>).</p>'
                        . '<p>Theo quy định của phòng Kỹ thuật, nhân viên cần nhập báo cáo công việc hàng ngày trước 17:00.</p>'
                        . '<p><a href="' . admin_url('tech_reports/report') . '">Nhập báo cáo tại đây</a></p>'
                        . '<p>Cảm ơn bạn!</p>';

            $this->emails_model->send_simple_email($staff['email'], $email_subject, $email_body);
        }

        // Cập nhật log đã gửi thông báo
        $this->db->where('staff_id', $staff['staff_id']);
        $this->db->where('check_date', date('Y-m-d'));
        $this->db->update(db_prefix() . 'tech_compliance_log', [
            'notified'    => 1,
            'notified_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // =========================================================================
    // STATISTICS & DASHBOARD
    // =========================================================================

    /**
     * Thống kê tổng quan
     * @param string|null $date Ngày cụ thể, null = hôm nay
     * @return array
     */
    public function get_daily_summary($date = null)
    {
        if (!$date) {
            $date = date('Y-m-d');
        }

        $tech_staff = $this->get_tech_staff(true);
        $total_staff = count($tech_staff);

        // Đếm số nhân viên đã nhập
        $this->db->distinct();
        $this->db->select('staff_id');
        $this->db->where('report_date', $date);
        $submitted_count = $this->db->get(db_prefix() . 'tech_daily_reports')->num_rows();

        // Lấy danh sách đã nhập và chưa nhập
        $staff_ids = array_column($tech_staff, 'staff_id');
        
        $this->db->select('staff_id');
        $this->db->where('report_date', $date);
        $this->db->where_in('staff_id', $staff_ids);
        $submitted_ids = array_column(
            $this->db->get(db_prefix() . 'tech_daily_reports')->result_array(),
            'staff_id'
        );

        $not_submitted_ids = array_diff($staff_ids, $submitted_ids);

        return [
            'date'              => $date,
            'total_staff'       => $total_staff,
            'submitted_count'   => $submitted_count,
            'not_submitted_count' => $total_staff - $submitted_count,
            'compliance_rate'   => $total_staff > 0 ? round(($submitted_count / $total_staff) * 100, 1) : 0,
            'submitted_ids'     => $submitted_ids,
            'not_submitted_ids' => $not_submitted_ids,
        ];
    }

    /**
     * Báo cáo tuân thủ theo khoảng thời gian
     * @param string $from_date
     * @param string $to_date
     * @return array
     */
    public function get_compliance_report($from_date, $to_date)
    {
        $this->db->select('check_date, COUNT(*) as total, SUM(has_submitted) as submitted');
        $this->db->where('check_date >=', $from_date);
        $this->db->where('check_date <=', $to_date);
        $this->db->group_by('check_date');
        $this->db->order_by('check_date', 'DESC');

        return $this->db->get(db_prefix() . 'tech_compliance_log')->result_array();
    }

    /**
     * Thống kê tổng số giờ và số báo cáo theo từng ngày trong khoảng thời gian
     * @param string $from_date
     * @param string $to_date
     * @return array
     */
    public function get_daily_summary_range($from_date, $to_date)
    {
        $this->db->select('report_date, SUM(hours_spent) as total_hours, COUNT(*) as report_count');
        $this->db->where('report_date >=', $from_date);
        $this->db->where('report_date <=', $to_date);
        $this->db->group_by('report_date');
        $this->db->order_by('report_date', 'ASC');

        return $this->db->get(db_prefix() . 'tech_daily_reports')->result_array();
    }

    /**
     * Báo cáo tuân thủ của từng nhân viên phòng kỹ thuật
     * @param string $from_date
     * @param string $to_date
     * @return array
     */
    public function get_staff_compliance_report($from_date, $to_date)
    {
        // 1. Tính toán số ngày làm việc kỳ vọng (Mon-Fri)
        $start = new DateTime($from_date);
        $end   = new DateTime($to_date);
        $end->modify('+1 day'); // Bao gồm cả ngày kết thúc
        
        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($start, $interval, $end);
        
        $expected_days = 0;
        $work_dates = [];
        foreach ($daterange as $date) {
            $day_of_week = $date->format('N'); // 1 = Thứ hai, 7 = Chủ nhật
            if ($day_of_week < 6) { // Thứ hai đến thứ sáu
                $expected_days++;
                $work_dates[] = $date->format('Y-m-d');
            }
        }

        // 2. Lấy nhân sự phòng kỹ thuật đang hoạt động
        $tech_staff = $this->get_tech_staff(true);
        $report = [];

        foreach ($tech_staff as $staff) {
            $submitted = 0;
            if (!empty($work_dates)) {
                $this->db->select('report_date');
                $this->db->distinct();
                $this->db->where('staff_id', $staff['staff_id']);
                $this->db->where_in('report_date', $work_dates);
                $res = $this->db->get(db_prefix() . 'tech_daily_reports')->result_array();
                $submitted = count($res);
            }

            $missing = max(0, $expected_days - $submitted);
            $rate = $expected_days > 0 ? round(($submitted / $expected_days) * 100, 1) : 100;

            $report[] = [
                'staff_id'          => $staff['staff_id'],
                'staff_name'        => $staff['staff_name'],
                'role'              => $staff['role'],
                'reports_submitted' => $submitted,
                'reports_missing'   => $missing,
                'compliance_rate'   => $rate,
            ];
        }

        return $report;
    }
}
