# Implementation report — Chuẩn hóa người nhận quản lý

Ngày: 2026-09-09. Phạm vi: bộ chọn người nhận nghiệp vụ trong Reminder Engine.

## Thay đổi

- `modules/sales_pipeline/libraries/Reminder_engine.php`: thay `activeAdminStaff()`/cache bằng `activeManagerStaff()`/cache; chọn nhân sự active là Admin hoặc có `sales_pipeline:view` thông qua permission helper hiện hữu.
- Chuyển ba nơi gọi: materialization CRM/email, WhatsApp direct và email CC. Các cảnh báo SMTP/BCC vẫn query Admin active riêng như trước.
- Không sửa core/vendor, schema, quyền người dùng, quy tắc SLA hoặc các thay đổi WhatsApp khác đang có trong worktree.

## Bằng chứng kiểm thử

- Test hồi quy mới `modules/sales_pipeline/tests/Reminder_manager_recipients_test.php` gọi các method thực tế của engine với DB/permission fixture, không gửi tin thật.
- Trước sửa: FAIL `crm: expected active admin + global-view manager, got [1]`.
- Sau sửa: PASS recipient CRM/email/WhatsApp, group + direct, CC gồm quản lý global, loại inactive/view-own-only, không CC chính người phụ trách, giữ ownership của staff delivery và thông báo hạ tầng chỉ đến Admin active.
- `php -l` đạt cho engine và test mới.
- Test hồi quy đạt: `Reminder_rule_settings_test.php`, `Reminder_delivery_operations_test.php`, `Reminder_sla_reconcile_test.php`, `Reminder_delivery_whatsapp_test.php`.

## Giới hạn xác minh

Chạy trên PHP CLI 8.5.7, chưa chạy DB-backed hoặc gửi email/WhatsApp thật cho quản lý có quyền global. Test fixture mô phỏng kết quả permission helper; không kiểm chứng việc gán Role trên DB thực tế. Không kết luận các lỗi gateway/idempotency ở review trước đã được sửa. Admin kỹ thuật vẫn nhận cảnh báo nghiệp vụ theo điều kiện `admin OR view` đã chọn.
