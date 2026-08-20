# Kế hoạch Thực thi: Tính năng Carbon Copy (CC) Quản lý trong Email Nhắc nhở

> **Module**: Sales Pipeline  
> **Tài liệu liên quan**:  
> - [workflow_reminder.md](file:///Users/dieterhoang/Developer/portal_18/docs/plans/Sales%20Pipeline/workflow_reminder.md)  
> - [core_plane.md](file:///Users/dieterhoang/Developer/portal_18/docs/plans/Sales%20Pipeline/core_plane.md)  
> - [rule_engine.md](file:///Users/dieterhoang/Developer/portal_18/docs/plans/Sales%20Pipeline/rule_engine.md)  
> **Phiên bản tài liệu**: 1.5 (Đã thực thi — Implemented)  
> **Ngày cập nhật**: 2026-08-18  
> **Trạng thái**: Đã hoàn thành thực thi (Implemented & Verified)

---

## 1. Prompt Ngữ cảnh Phân tích Nghiệp vụ

> ### 📋 PROMPT NGỮ CẢNH: Phân tích Nghiệp vụ — Tính năng Carbon Copy (CC) Người Quản lý
>
> *"Trong hệ thống Quản lý Bán hàng (Sales Pipeline) của CRM, tính năng Nhắc nhở Tự động (Smart Reminder) định kỳ quét các Cơ hội (Deal) bị quá hạn chăm sóc, các Báo giá (Estimate) chưa đạt chỉ tiêu ngày/tháng/doanh thu tuần, hoặc các Báo giá gặp rủi ro vòng đời (Draft lâu, Sent không phản hồi, Khách từ chối, Hết hạn, Accepted chưa xuất hóa đơn). Hệ thống tự động phát thông báo qua 2 kênh: Thông báo chuông CRM (🔔) và Email cá nhân (✉️) trực tiếp đến nhân viên kinh doanh phụ trách.*
>
> *Tuy nhiên, để đảm bảo tính minh bạch, nâng cao kỷ luật phản hồi và giúp Cấp quản lý (Manager/Admin) nắm bắt tức thời tình hình chậm trễ của cấp dưới mà không cần phải chủ động đăng nhập tra cứu báo cáo, hệ thống cần bổ sung cơ chế **Carbon Copy (CC)** email nhắc nhở đến Quản lý/Admin phụ trách.*
>
> *Yêu cầu đặt ra là: Khi một lá thư nhắc nhở được gửi tới nhân viên ABCD, Quản lý của ABCD sẽ nhận được bản sao (CC) cùng lúc. Quản lý có thể đọc trọn vẹn nội dung nhắc nhở, hiểu rõ nguyên nhân cảnh báo, và khi nhấp vào nút hành động 'Phản hồi nhanh' trong email, hệ thống sẽ mở giao diện theo dõi với quyền Giám sát (Read-only), xem được tiến độ giải trình của nhân viên mà không được phép can thiệp gửi phản hồi thay cho nhân viên."*

---

## 2. Mục tiêu & Nguyên tắc Kiến trúc

1. **Giám sát thời gian thực (Real-time Supervision)**: Cấp quản lý nhận được thông tin cảnh báo cùng thời điểm với nhân viên, loại bỏ độ trễ thông tin.
2. **Nâng cao trách nhiệm giải trình (Accountability)**: Nhân viên nhận thức được cấp trên đang theo dõi deal/báo giá này, từ đó tăng tỷ lệ phản hồi đúng hạn (SLA).
3. **Phân định rõ ràng vai trò (Role Separation)**:
   - **Nhân viên (To)**: Là đối tượng hành động, bắt buộc nhập phản hồi giải trình (3 câu hỏi định hướng).
   - **Quản lý (CC)**: Là đối tượng giám sát, chỉ xem (Read-only), theo dõi câu trả lời của nhân viên và đánh giá rủi ro.
4. **NGUYÊN TẮC BẤT DI BẤT DỊCH — ZERO CORE MODIFICATION**:
   - **Tuyệt đối KHÔNG chỉnh sửa bất kỳ file nào trong Core framework CRM** (bao gồm [application/models/Emails_model.php](file:///Users/dieterhoang/Developer/portal_18/application/models/Emails_model.php)).
   - Toàn bộ logic CC, phân giải quản lý, deduplication delivery, cấu hình và giao diện được đóng gói **100% bên trong module `sales_pipeline`**.
5. **Chính sách Khử trùng lặp Delivery Quản lý (Manager Delivery Deduplication)**:
   - Khi CC Quản lý được kích hoạt, **CC sẽ gắn trực tiếp vào email gửi cho Nhân viên** (`recipient_type = 'staff'`).
   - Tại `Reminder_engine::materializeDeliveries()`, nếu CC đang áp dụng cho sự kiện này thì **bỏ qua việc tạo hàng delivery kênh `email` riêng cho `recipient_type = 'manager'`**; giữ nguyên delivery kênh `crm` (chuông thông báo) cho Manager nếu rule yêu cầu.
6. **Định danh Quản lý & Khử trùng lặp Email (Manager Scope & Self-exclusion)**:
   - Quản lý nhận CC được lấy từ: **Các tài khoản Quản trị viên (Admin) đang hoạt động** kết hợp danh sách **Email Quản lý dự phòng (Fallback emails)**.
   - Hàm phân giải CC bắt buộc **loại trừ tuyệt đối địa chỉ email của chính nhân viên bị nhắc nhở** (`$staffId`), ngăn chặn trường hợp email nhân viên trùng với Admin hoặc nằm trong danh sách fallback nhập tay.

---

## 3. Thiết kế Kỹ thuật Tích hợp (100% Module Scope)

### 3.1 Cơ chế Context-Driven Filter Hook (Không sửa Core)

Trong [Emails_model.php:195-231](file:///Users/dieterhoang/Developer/portal_18/application/models/Emails_model.php#L195-L231), Core đã có sẵn filter hook và logic xử lý `$cnf['cc']`:
```php
$cnf = hooks()->apply_filters('before_send_simple_email', $cnf);
// ...
if (isset($cnf['cc'])) {
    $this->email->cc($cnf['cc']);
}
```

Để khai thác logic này một cách an toàn mà **không cần `remove_filter()`** và **không sửa `Emails_model.php`**:

1. **Đăng ký filter 1 lần duy nhất** trong `modules/sales_pipeline/sales_pipeline.php`:
   ```php
   hooks()->add_filter('before_send_simple_email', 'sales_pipeline_inject_reminder_email_cc');

   function sales_pipeline_inject_reminder_email_cc($cnf)
   {
       if (class_exists('Reminder_engine', false) && !empty(Reminder_engine::$currentEmailCC)) {
           $cnf['cc'] = Reminder_engine::$currentEmailCC;
       }
       return $cnf;
   }
   ```
2. **Quản lý vòng đời trạng thái trong `Reminder_engine`**:
   ```php
   try {
       Reminder_engine::$currentEmailCC = $ccFormattedString;
       $success = (bool) $this->CI->emails_model->send_simple_email($to, $subject, $body);
   } finally {
       Reminder_engine::$currentEmailCC = null;
   }
   ```
   > **Lợi ích**:
   > - ✅ **0 dòng code Core bị thay đổi**.
   > - ✅ Mọi email khác trong hệ thống (Ticket, Invoice, Auth, Leads...) đi qua `send_simple_email` đều thấy `Reminder_engine::$currentEmailCC = null` nên hoàn toàn không bị ảnh hưởng.
   > - ✅ Hoạt động ổn định trên mọi phiên bản Perfex CRM mà không lo ngại thiếu hàm `remove_filter()`.

---

### 3.2 Khử trùng lặp Delivery Quản lý trong `materializeDeliveries()`

Trong [Reminder_engine.php:326](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/libraries/Reminder_engine.php#L326), khi tạo các hàng delivery cho một Reminder Record:

```php
private function materializeDeliveries($reminderId, array $event)
{
    $recipients = [['type' => 'staff', 'id' => $event['staff_id']]];
    if (in_array('manager', $event['recipients'], true)) {
        foreach ($this->CI->db->select('staffid')->where('active', 1)->where('admin', 1)
            ->where('staffid !=', $event['staff_id'])->get(db_prefix() . 'staff')->result_array() as $admin) {
            $recipients[] = ['type' => 'manager', 'id' => (int) $admin['staffid']];
        }
    }

    $isCCApplicable = $this->isManagerCCApplicable($event['severity'] ?? 'warning');

    foreach ($recipients as $recipient) {
        $staff = $this->activeStaff($recipient['id']);
        if (!$staff) { continue; }
        foreach ($event['channels'] as $channel) {
            // ĐIỂM DEDUP: Nếu CC Manager đang áp dụng cho sự kiện này, bỏ qua việc tạo email delivery riêng cho manager
            if ($recipient['type'] === 'manager' && $channel === 'email' && $isCCApplicable) {
                continue;
            }

            $key = $channel === 'email' ? trim((string) $staff->email) : (string) $recipient['id'];
            if ($key === '') { continue; }
            $this->CI->db->query('INSERT IGNORE INTO `' . db_prefix() . 'sales_pipeline_reminder_deliveries`'
                . ' (`reminder_id`,`channel`,`recipient_type`,`recipient_key`,`status`,`created_at`) VALUES (?,?,?,?,?,?)',
                [$reminderId, $channel, $recipient['type'], $key, 'pending', date('Y-m-d H:i:s')]);
        }
    }
}
```

---

### 3.3 Hàm Phân giải Quản lý & Khử trùng lặp Email Nhân viên

Xây dựng phương thức `resolveManagerCCEmails($staffId, $severity)` và helper `isManagerCCApplicable($severity)` trong [Reminder_engine.php](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/libraries/Reminder_engine.php):

```php
class Reminder_engine
{
    public static $currentEmailCC = null;

    /**
     * Kiểm tra xem tính năng CC Manager có áp dụng cho severity hiện tại không
     */
    private function isManagerCCApplicable($severity = 'warning')
    {
        if ($this->option('sp_reminder_email_cc_manager_enabled') !== '1') {
            return false;
        }
        $scope = $this->option('sp_reminder_email_cc_scope') ?: 'all';
        if ($scope === 'critical_only' && $severity !== 'critical') {
            return false;
        }
        return true;
    }

    /**
     * Phân giải danh sách email Quản lý / Admin để gắn CC (Đã lọc trừ email nhân viên)
     *
     * @param int    $staffId  ID nhân viên nhận nhắc nhở chính
     * @param string $severity Mức độ nghiêm trọng (info, warning, critical)
     * @return array Danh sách email quản lý hợp lệ
     */
    private function resolveManagerCCEmails($staffId, $severity = 'warning')
    {
        if (!$this->isManagerCCApplicable($severity)) {
            return [];
        }

        // Lấy email của chính nhân viên bị nhắc nhở để bảo đảm không bị CC lại chính mình
        $staffObj = $this->CI->db->select('email')->where('staffid', (int) $staffId)->get(db_prefix() . 'staff')->row_array();
        $staffEmail = !empty($staffObj['email']) ? strtolower(trim((string) $staffObj['email'])) : '';

        $rawEmails = [];

        // 1. Lấy danh sách Quản trị viên (Admin) đang hoạt động
        $admins = $this->CI->db->select('email')
            ->from(db_prefix() . 'staff')
            ->where('active', 1)
            ->where('admin', 1)
            ->where('staffid !=', (int) $staffId)
            ->get()->result_array();

        foreach ($admins as $admin) {
            $rawEmails[] = trim((string) $admin['email']);
        }

        // 2. Fallback sang danh sách email cấu hình nếu không có Admin nào khác
        if (empty($rawEmails)) {
            $fallback = (string) $this->option('sp_reminder_manager_fallback_emails');
            if ($fallback !== '') {
                foreach (explode(',', $fallback) as $raw) {
                    $rawEmails[] = trim($raw);
                }
            }
        }

        // 3. Chuẩn hóa, lọc email hợp lệ và loại trừ tuyệt đối email của chính nhân viên
        $cleanEmails = [];
        foreach ($rawEmails as $email) {
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if ($staffEmail !== '' && strtolower($email) === $staffEmail) {
                continue; // Bỏ qua nếu trùng email nhân viên
            }
            $cleanEmails[] = $email;
        }

        return array_values(array_unique($cleanEmails));
    }
}
```

---

### 3.4 Bổ sung `r.staff_id` và `r.severity` vào Query Dispatcher

Cập nhật query tại [Reminder_engine.php:391](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/libraries/Reminder_engine.php#L391):

```sql
SELECT d.*, r.title, r.message, r.entity_type, r.entity_id, r.pipeline_id, r.rule_code,
       r.staff_id, r.severity, r.sent_at AS reminder_sent_at
FROM tblsales_pipeline_reminder_deliveries d
JOIN tblsales_pipeline_reminders_log r ON r.id = d.reminder_id
WHERE d.status IN ('pending', 'failed')
  AND d.attempt_count < 3
  AND (d.next_retry_at IS NULL OR d.next_retry_at <= NOW())
ORDER BY d.id ASC LIMIT 100
```

Trong luồng gửi email:
```php
elseif ($row['channel'] === 'email') {
    $this->CI->load->model('emails_model');
    $recipient = $this->CI->db->where('email', $row['recipient_key'])
        ->get(db_prefix() . 'staff')->row();

    $staffId = !empty($row['staff_id']) ? (int) $row['staff_id'] : ($recipient ? (int) $recipient->staffid : 0);
    $severity = !empty($row['severity']) ? (string) $row['severity'] : 'warning';

    $body = $this->CI->load->view('sales_pipeline/emails/reminder', [
        'staff_name'   => $recipient ? trim($recipient->firstname . ' ' . $recipient->lastname) : '',
        'title'        => $row['title'],
        'message'      => $row['message'],
        'response_url' => admin_url('sales_pipeline/reminder_response/' . $row['reminder_id']),
        'entity_url'   => $this->entityUrl($row['entity_type'], $row['entity_id']),
    ], true);

    // Phân giải danh sách CC Quản lý (chỉ gắn khi gửi cho Staff)
    $ccList = [];
    if ($row['recipient_type'] === 'staff') {
        $ccList = $this->resolveManagerCCEmails($staffId, $severity);
    }
    $ccString = !empty($ccList) ? implode(', ', $ccList) : null;

    // Gửi email qua Emails_model kèm Context-Driven CC
    try {
        self::$currentEmailCC = $ccString;
        $success = (bool) $this->CI->emails_model->send_simple_email(
            $row['recipient_key'],
            $row['title'],
            $body
        );
    } finally {
        self::$currentEmailCC = null;
    }
}
```

---

### 3.5 Cấu hình Mặc định & Chuẩn hóa Dữ liệu trong Module

#### 1. Khai báo mặc định tại [modules/sales_pipeline/includes/reminder_rule_defaults.php](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/includes/reminder_rule_defaults.php#L9):
```php
function sales_pipeline_reminder_rule_default_options()
{
    return [
        // ... các options hiện có
        'sp_reminder_email_cc_manager_enabled' => '1',
        'sp_reminder_email_cc_scope'           => 'all', // 'all' hoặc 'critical_only'
        'sp_reminder_manager_fallback_emails'  => '',
    ];
}
```

#### 2. Chuẩn hóa & Validate trong [modules/sales_pipeline/controllers/Sales_pipeline.php](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/controllers/Sales_pipeline.php#L1091):
```php
// Toggle
$toggles[] = 'sp_reminder_email_cc_manager_enabled';

// Scope
$scope = trim((string) ($data['sp_reminder_email_cc_scope'] ?? 'all'));
$normalized['sp_reminder_email_cc_scope'] = in_array($scope, ['all', 'critical_only'], true) ? $scope : 'all';

// Fallback emails
$rawFallback = trim((string) ($data['sp_reminder_manager_fallback_emails'] ?? ''));
$validEmails = [];
if ($rawFallback !== '') {
    foreach (explode(',', $rawFallback) as $item) {
        $email = trim($item);
        if ($email !== '') {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validEmails[] = $email;
            } else {
                $errors[] = _l('sp_reminder_error_invalid_fallback_email', [$email]);
            }
        }
    }
}
$normalized['sp_reminder_manager_fallback_emails'] = implode(',', array_unique($validEmails));
```

---

### 3.6 Tinh chỉnh UX Giao diện Quick Response (`reminder_response.php`)

View [modules/sales_pipeline/views/reminder_response.php](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/views/reminder_response.php) đã có sẵn `$canRespond = (int) $reminder['staff_id'] === (int) get_staff_user_id()`. 

Bổ sung Banner thông báo dành cho Quản lý khi xem ở chế độ giám sát (`!$canRespond`):

```php
<?php if (!$canRespond): ?>
    <div class="alert alert-info sp-reminder-supervisor-banner" role="alert" style="margin-top: 15px; border-left: 4px solid var(--crm-info);">
        <i class="fa fa-eye" aria-hidden="true" style="margin-right: 6px;"></i>
        <?php echo _l('sp_reminder_supervisor_mode_notice', [html_escape($reminder['staff_name'] ?? _l('sales_pipeline_assigned_staff'))]); ?>
    </div>
<?php endif; ?>
```

---

### 3.7 Quản lý Ngôn ngữ Tập trung (Localization)

Cập nhật đồng bộ cả 2 file ngôn ngữ trong module:

#### Tiếng Việt (`modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php`):
```php
$lang['sp_settings_reminder_email_cc_manager']          = 'Gửi bản sao (CC) email nhắc nhở đến Quản lý';
$lang['sp_settings_reminder_email_cc_manager_help']     = 'Tự động CC Quản trị viên/Quản lý khi gửi email nhắc nhở đến nhân viên kinh doanh.';
$lang['sp_settings_reminder_email_cc_scope']            = 'Phạm vi gửi CC';
$lang['sp_settings_reminder_email_cc_scope_all']        = 'Tất cả các nhắc nhở';
$lang['sp_settings_reminder_email_cc_scope_critical']   = 'Chỉ các nhắc nhở nghiêm trọng (Critical)';
$lang['sp_settings_reminder_email_cc_fallback']         = 'Email Quản lý dự phòng (ngăn cách bằng dấu phẩy)';
$lang['sp_reminder_error_invalid_fallback_email']       = 'Địa chỉ email dự phòng không hợp lệ: %s';
$lang['sp_reminder_supervisor_mode_notice']             = 'Bạn đang xem thông báo nhắc nhở của nhân viên %s ở chế độ Giám sát (Chỉ xem).';
```

#### Tiếng Anh (`modules/sales_pipeline/language/english/sales_pipeline_lang.php`):
```php
$lang['sp_settings_reminder_email_cc_manager']          = 'Carbon Copy (CC) reminder emails to Managers';
$lang['sp_settings_reminder_email_cc_manager_help']     = 'Automatically CC Administrators/Managers when sending reminder emails to sales staff.';
$lang['sp_settings_reminder_email_cc_scope']            = 'CC Scope';
$lang['sp_settings_reminder_email_cc_scope_all']        = 'All reminders';
$lang['sp_settings_reminder_email_cc_scope_critical']   = 'Critical reminders only';
$lang['sp_settings_reminder_email_cc_fallback']         = 'Fallback Manager Emails (comma separated)';
$lang['sp_reminder_error_invalid_fallback_email']       = 'Invalid fallback email address: %s';
$lang['sp_reminder_supervisor_mode_notice']             = 'You are viewing %s\'s reminder notification in Supervisor mode (Read-only).';
```

---

## 4. Danh sách các File Thay đổi (Scope of Changes)

| STT | File | Vị trí | Mục đích |
|---|---|---|---|
| 1 | [sales_pipeline.php](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/sales_pipeline.php) | Module Root | Đăng ký filter hook `before_send_simple_email`. |
| 2 | [reminder_rule_defaults.php](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/includes/reminder_rule_defaults.php) | Module Includes | Khai báo giá trị mặc định cho 3 option CC mới. |
| 3 | [Sales_pipeline.php](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/controllers/Sales_pipeline.php) | Module Controller | Normalize và validation cho cấu hình CC trong settings. |
| 4 | [views/settings.php](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/views/settings.php) | Module View | Thêm UI toggle/scope/fallback email vào tab Cài đặt Nhắc nhở. |
| 5 | [Reminder_engine.php](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/libraries/Reminder_engine.php) | Module Library | Thêm dedup manager delivery trong `materializeDeliveries()`, sửa query lấy `staff_id`/`severity`, thêm `resolveManagerCCEmails()` (loại trừ email nhân viên) và gán `self::$currentEmailCC`. |
| 6 | [reminder_response.php](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/views/reminder_response.php) | Module View | Thêm Supervisor Banner khi xem ở vai trò Quản lý. |
| 7 | [vietnamese/sales_pipeline_lang.php](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php) | Module Language | Đồng bộ từ khóa tiếng Việt. |
| 8 | [english/sales_pipeline_lang.php](file:///Users/dieterhoang/Developer/portal_18/modules/sales_pipeline/language/english/sales_pipeline_lang.php) | Module Language | Đồng bộ từ khóa tiếng Anh. |

> 🚫 **Các file KHÔNG được phép chỉnh sửa**:
> - `application/models/Emails_model.php` (Core Model) — **GIỮ NGUYÊN 100%**.
> - Bất kỳ file nào trong thư mục `application/` hoặc `system/`.

---

## 5. Ma trận Kiểm thử (Testing Matrix)

| # | Trường hợp kiểm thử | Dữ liệu đầu vào | Kết quả kỳ vọng |
|---|---|---|---|
| **TC-01** | Bật CC cho tất cả nhắc nhở | `cc_enabled = 1`, `cc_scope = 'all'` | Nhân viên nhận email chính (`To`), Quản lý nhận được email bản sao (`CC`). |
| **TC-02** | Chỉ CC cho nhắc nhở Critical | `cc_enabled = 1`, `cc_scope = 'critical_only'`, Rule có `severity = 'warning'` | Email gửi đến nhân viên bình thường, **không** có header CC. |
| **TC-03** | Chỉ CC cho nhắc nhở Critical (Vi phạm nặng) | `cc_enabled = 1`, `cc_scope = 'critical_only'`, Rule có `severity = 'critical'` | Email có header CC gửi đến Quản lý. |
| **TC-04** | Tắt tính năng CC | `cc_enabled = 0` | Email chỉ gửi tới nhân viên, không có CC. |
| **TC-05** | Nhân viên là Admin | Nhân viên bị nhắc nhở chính là một Admin | Không CC lại chính email của nhân viên đó trong danh sách CC. |
| **TC-06** | Fallback email trùng email nhân viên | Cấu hình fallback chứa email của nhân viên `$staffId` | Email của nhân viên bị lọc bỏ khỏi danh sách CC trước khi gửi. |
| **TC-07** | Dedup Manager Delivery tại `WEEKLY_FINAL` | Rule có `recipients = ['staff', 'manager']`, bật CC | `tblsales_pipeline_reminder_deliveries` **chỉ tạo delivery email cho staff** (kèm CC manager) và delivery crm cho manager; **không tạo delivery email riêng cho manager**. |
| **TC-08** | Quản lý click nút Phản hồi nhanh | Link `/admin/sales_pipeline/reminder_response/{id}` | Mở trang thành công; hiển thị banner Giám sát; không hiển thị textarea nhập liệu. |
| **TC-09** | Quản lý cố tình gửi POST phản hồi | Gửi POST tới `/admin/sales_pipeline/respond_reminder/{id}` | Bị từ chối với mã lỗi quyền truy cập (Model đã chặn). |
| **TC-10** | Tính cô lập hoàn toàn với Core | Gửi email từ các module khác (Invoice, Ticket, Auth...) | Hoạt động bình thường, `Reminder_engine::$currentEmailCC` là `null`, không dính CC ngoài ý muốn. |

---

## 6. Tiêu chí Nghiệm thu (Acceptance Criteria)

1. ✅ **Không sửa đổi Core**: Không có bất kỳ dòng code nào bị thay đổi trong `application/models/Emails_model.php` hoặc thư mục Core.
2. ✅ **Khử trùng lặp Delivery**: `materializeDeliveries()` tự động bỏ qua delivery email riêng cho Manager khi CC Manager đang áp dụng cho sự kiện đó.
3. ✅ **Loại trừ email nhân viên**: `resolveManagerCCEmails()` loại trừ triệt để email của nhân viên bị nhắc nhở dù xuất phát từ Admin query hay fallback list.
4. ✅ **Đóng gói hoàn toàn trong Module**: Mọi logic cấu hình, resolve quản lý và inject CC đều nằm trong `modules/sales_pipeline/`.
5. ✅ **Cấu hình chuẩn hóa**: Cấu hình có đầy đủ giá trị mặc định trong `reminder_rule_defaults.php` và được validate an toàn trong Controller.
6. ✅ **Dispatcher chính xác**: Query `dispatchPendingDeliveries()` lấy trực tiếp `r.staff_id` và `r.severity` từ database.
7. ✅ **Quyền & Trải nghiệm Quản lý**: Quản lý nhấp vào link trong email được điều hướng đến trang xem chi tiết mà không gặp lỗi 403, form ở chế độ Read-only kèm thông báo Giám sát rõ ràng.
8. ✅ **Đa ngôn ngữ đầy đủ**: Cập nhật cả 2 bản ngôn ngữ tiếng Việt và tiếng Anh.
