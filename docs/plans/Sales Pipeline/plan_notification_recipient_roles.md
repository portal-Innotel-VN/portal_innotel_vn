# Kế hoạch tái cấu trúc vai trò và người nhận thông báo Sales Pipeline

Ngày: 2026-09-10. Trạng thái: kế hoạch đề xuất, chưa triển khai code hoặc migration.

## 1. Mục tiêu và phạm vi

Tách quyền truy cập dữ liệu khỏi trách nhiệm nhận thông báo. Admin IT không mặc định nhận cảnh báo kinh doanh; quản lý kinh doanh nhận email CC/WhatsApp; NVKD nhận Bell/email của chính mình và chịu trách nhiệm phản hồi SLA.

Mọi code mới nằm trong `modules/sales_pipeline/`. Core `application/`, `system/`, vendor chỉ đọc. Không sửa helper phân quyền Perfex, không tạo lại hệ thống role toàn CRM. Ba nhóm dưới đây là vai trò nghiệp vụ có thể kiêm nhiệm, không phải ba role ID cố định trong DB.

## 2. Context và bằng chứng basecode

Đã đối chiếu `.agents/AGENTS.md`, `.agents/context/context-loading.md`, context nền tảng `docs/ai/`, `docs/specifications/PERFORMANCE_SCORE.md`, quy tắc audit/integration và code liên quan trong phiên làm việc.

| Nguồn | Hiện trạng đã xác minh |
|---|---|
| `application/helpers/admin_helper.php::has_permission()/staff_can()` | Admin tự động được trả `true`; helper không chứng minh quyền được cấp rõ ràng. |
| `application/models/Staff_model.php::get_staff_permissions()/update_permissions()` | Quyền thực tế theo staff lưu bằng `staff_id`, `feature`, `capability` trong bảng staff permissions. Luồng tạo/cập nhật Admin truyền danh sách quyền rỗng. |
| `application/models/Roles_model.php::update()` | Role lưu permissions dạng serialized; chỉ đồng bộ sang nhân viên khi chọn cập nhật quyền staff. Không được mặc định hợp nhất role permissions với staff permissions để khôi phục quyền đã bị thu hồi. |
| `Reminder_engine::activeManagerStaff()` | Hiện chọn active và `admin OR has_permission(view)`; dùng tại materialization CRM/email, WhatsApp direct và email CC. |
| `Reminder_engine::resolveManagerCCEmails()` | Fallback được xét trước lọc email hợp lệ; chưa phải dự phòng sau khi gửi lỗi. |
| `Reminder_engine::openAuthenticationCircuit()/alertSystemBccIncident()` | Thông báo hạ tầng chọn Admin active riêng. |
| `Sales_pipeline_model` và controller | Dashboard có quyền global/own, cohort quản lý không bán hàng được lọc riêng theo kỳ. Không được thay thế hàng loạt mọi kiểm tra `view`. |
| `Reminder_delivery_operations::retry()` | Reset trạng thái/lỗi khi retry; phải kiểm tra không mở lại bản ghi đã cách ly hoặc người nhận bị thu hồi. |

Chưa xác minh ở DB production: danh sách quyền/role thực tế, quản lý kiêm Admin, địa chỉ email/SĐT dùng chung và thành viên nhóm WhatsApp. Đây là dữ liệu cần báo cáo preview trước rollout, không phải lý do tự sửa quyền người dùng.

## 3. Ma trận nghiệp vụ mục tiêu

| Nhóm | Truy cập | Cảnh báo kinh doanh | Cảnh báo hệ thống | SLA cá nhân |
|---|---|---|---|---|
| Admin IT thuần | Giữ toàn bộ quyền core | Không tự động CC, WhatsApp hay Bell quản lý | Bell lỗi SMTP/BCC/gateway nếu có luồng cảnh báo tương ứng | Không sinh KPI nhắc nhở chỉ vì là Admin |
| Quản lý kinh doanh | Global view hiện hữu | Email CC và WhatsApp theo rule/kênh bật | Không tự động nhận lỗi kỹ thuật | Nếu kiêm bán hàng, phản hồi reminder của chính mình theo luồng staff |
| NVKD | `view_own` hoặc quyền hiện hữu | Bell/email reminder của chính mình | Không | Giữ cơ chế response/SLA hiện hành |

Không bật thêm kênh chỉ vì chọn role. Việc có mặt trong danh sách quản lý chỉ tạo tư cách nhận; vẫn áp dụng rule, severity, global flag, kênh được bật, quiet hours, TTL, quota và địa chỉ hợp lệ.

Trong mô hình mục tiêu, Bell nghiệp vụ dành cho staff owner; không tạo thêm Bell quản lý. Nếu nghiệp vụ sau này cần Bell quản lý, phải có tùy chọn rõ ràng riêng, không suy ra từ quyền global.

## 4. Chính sách xác định quản lý kinh doanh

### 4.1 Hai chế độ rõ ràng, không OR ngầm hai danh sách

Option đề xuất `sp_reminder_manager_recipient_source`:

- `explicit_view` (mặc định): chọn staff active có dòng quyền `feature='sales_pipeline' AND capability='view'` được lưu thực tế. Không dùng `is_admin()` hay `has_permission()` để tự thêm người nhận. Một dòng quyền tồn tại là cấp quyền rõ ràng, không phân biệt nó được tạo thủ công hay đồng bộ từ role.
- `selected_staff`: dùng đúng danh sách `sp_reminder_manager_recipient_staff_ids` do Admin chỉ định. Mỗi staff phải active và có quyền truy cập global hiệu lực; Admin được chọn rõ ràng có thể nhận trong chế độ này. Người chỉ có `view_own` bị từ chối để tránh lộ cảnh báo của đồng nghiệp. Chọn người nhận không cấp thêm quyền truy cập.

Chế độ danh sách chỉ định thay thế hoàn toàn danh sách tự động; không tự bổ sung Admin hoặc staff có view ngoài danh sách. Admin kiêm quản lý nên dùng chế độ này vì core có thể xóa quyền lưu riêng của Admin khi cập nhật tài khoản.

Chính sách dữ liệu rỗng: không có người nhận hợp lệ thì không gửi quản lý; ghi lý do trong preview/audit. Không fallback sang toàn bộ Admin. Luồng Bell/email của NVKD vẫn hoạt động.

### 4.2 Resolver tập trung

Library đề xuất `libraries/Reminder_recipient_resolver.php`:

- `explicitViewStaffIds()`: đọc hàng loạt staff active và bảng `db_prefix().'staff_permissions'`, tránh N+1 và Admin shortcut.
- `businessManagers()`: áp dụng chế độ cấu hình, active và quyền xem dữ liệu; trả ID cùng lý do `explicit_view`/`selected_staff`.
- `technicalAdmins()`: tập Admin active phục vụ cảnh báo hạ tầng, giữ độc lập.
- `resolveManagerEmails($ownerId)` / `resolveManagerWhatsAppRecipients($ownerId)`: lọc địa chỉ hợp lệ, loại owner nếu trùng, khử trùng email không phân biệt hoa thường/JID chuẩn hóa.
- Cache chỉ trong vòng xử lý; làm mới tại đầu dispatch/retry để thay đổi active/quyền/danh sách có hiệu lực. Không dùng cache stale từ lần materialization trước.

Không sửa `has_permission()`, không join bảng `roles_permissions` giả định. Đọc quyền qua schema thực tế đã xác minh. SQL trong module dùng Query Builder hoặc bind parameters và `db_prefix()`.

## 5. Tích hợp từng kênh và các trường hợp biên

### Email

- `resolveManagerCCEmails()` gọi resolver; giữ rule `manager_cc`, scope `all/critical_only` và loại địa chỉ người nhận chính.
- Nếu rule gửi email manager riêng và CC không áp dụng, dùng cùng resolver. Giữ hành vi tránh vừa CC vừa tạo manager email riêng cho cùng event.
- Chuẩn hóa/lọc email hợp lệ trước khi kết luận không có địa chỉ quản lý.
- Fallback cũ: không tự chuyển danh sách địa chỉ tự do sang chính sách mới. Preview chỉ ra các email cũ; đề xuất map sang staff quản lý hợp lệ. Không gửi email ngoài staff theo fallback ngầm. Nếu tổ chức cần mailbox phân phối ngoài staff, đó là phạm vi bổ sung phải được cấu hình đích danh và audit riêng.
- Việc SMTP gửi lỗi chỉ đi vào retry đúng người nhận, không tìm người nhận khác.

### WhatsApp

- Giữ `group_only`/`direct_only`/`both`; direct lấy từ resolver, group lấy Group JID được cấu hình rõ ràng.
- Một nhóm là một địa chỉ nhận, không nhân thành số quản lý. `both` tạo group + tập JID cá nhân duy nhất.
- Group JID không chứng minh tất cả thành viên có quyền CRM. Preview phải cho Admin xác nhận đúng nhóm quản lý; deeplink tiếp tục yêu cầu đăng nhập và kiểm tra quyền. Không tự thêm/xóa thành viên nhóm.
- Không sửa idempotency key của delivery hiện hữu. Không thay người nhận của bản ghi đã từng gọi provider rồi dùng lại key cũ.

### Staff owner và người kiêm nhiệm

- Không loại bỏ reminder cá nhân của quản lý chỉ vì họ có view, nếu reminder gắn với hoạt động bán hàng thực tế của họ.
- Rule về thiếu sản lượng/toàn roster phải được rà riêng: hiện một số query dùng `active/admin=0`, có thể đưa quản lý thuần vào đối tượng bị nhắc. Tách resolver người nhận quản lý khỏi bộ chọn đối tượng bị nhắc; áp dụng tiêu chí quản lý không bán hàng đã thống nhất cho cohort nghiệp vụ, giữ NVKD chưa có hoạt động.
- Không thêm bảng xếp hạng hoặc tăng target vì một người được chỉ định nhận cảnh báo. Dashboard giữ nguyên quyền truy cập và tiêu chí sales-in-period. Không thay cờ quản lý Dashboard bằng danh sách nhận CC; đây là hai khái niệm khác nhau.
- `recipient_type='manager'` không khởi động SLA; phản hồi quản lý không được ghi thay phản hồi owner. Những reminder/SLA đã phát sinh giữ lịch sử, không xóa hoặc tính lại ngầm.

## 6. Outbox, quyền bị thu hồi và audit

Kiểm tra người nhận không chỉ tại materialization mà cả trước provider call:

1. Với delivery chưa gửi, kiểm tra staff active, còn thuộc tập người nhận được phép, địa chỉ vẫn phù hợp và kênh còn bật.
2. Người nhận bị loại: giữ bản ghi, đánh dấu không gửi với mã lý do chính sách; không chuyển ngầm sang người khác. CC động cũng phải qua kiểm tra này.
3. `processing` đang gửi không thể thu hồi tin đã rời CRM. Không cập nhật hàng loạt các bản ghi worker khác đang xử lý; dùng claim/lock hiện hữu.
4. `uncertain`/`unverified` chỉ được đối soát. Thu hồi quyền không được biến chúng thành retry thường. Kết quả provider đến muộn vẫn cập nhật audit, không gửi lần nữa.
5. Manual retry cũng gọi cùng guard người nhận và guard kết quả chưa xác định.
6. Chính sách mặc định chỉ áp dụng cho sự kiện mới; không tự bổ sung quản lý mới vào backlog lịch sử. Với backlog chưa gửi đến người không còn hợp lệ, lập danh sách preview và xử lý có audit.

Audit cấu hình cần actor, thời gian, policy version, mode và staff ID thêm/bớt; không log secret, nội dung nhạy cảm hoặc toàn bộ SĐT/email. Dùng cơ chế audit của module sau khi truy vết, không chỉnh/xóa lịch sử cũ. Nếu cơ chế hiện có không lưu đủ, tạo migration mới cho audit tối thiểu, không sửa migration đã chạy.

## 7. Settings và tương thích nâng cấp

- Section “Người nhận cảnh báo kinh doanh”: chọn nguồn `Quyền Xem (Chung) được cấp` / `Danh sách chỉ định`; multiselect staff khi chọn danh sách.
- Preview hiển thị staff, lý do được chọn, email/SĐT đã che một phần, trường hợp bị loại và group đích; không gửi tin từ preview.
- Tách phần “Cảnh báo hệ thống cho Admin” bằng giải thích đúng hành vi; không thêm luồng gửi email/WhatsApp lỗi hệ thống ngoài các kênh đang được triển khai.
- Admin/POST/CSRF cho lưu cấu hình; validate ID phía server. Text Việt/Anh, giữ form names/IDs đang dùng và tuân thủ skill UI khi thực thi giao diện.
- Options đề xuất thêm: `sp_reminder_manager_recipient_source`, `sp_reminder_manager_recipient_staff_ids` (JSON ID unique), `sp_reminder_recipient_policy_version`, `sp_reminder_recipient_policy_v2_enabled`.
- Migration mới seed idempotent, không ghi đè lựa chọn đã tồn tại; version/migration number chốt theo HEAD tại thời điểm triển khai.
- Cờ v2 mặc định tắt cho nâng cấp để preview sự thay đổi trước rollout. Không coi cờ tắt là hoàn thành yêu cầu: khi bật, mọi đường gửi phải dùng resolver mới. Không tự bật WhatsApp toàn cục.

## 8. Phases thực hiện

| Phase | Công việc | Điều kiện hoàn thành |
|---|---|---|
| A — Inventory | Truy vết hook Cron → engine → recipient → outbox → adapter, email CC, Bell, manual retry; thống kê quyền rõ ràng/role và backlog | Báo cáo before/after không lộ secret, định vị đầy đủ call sites và cơ chế audit |
| B — Resolver | Implement truy vấn explicit view, selected staff, phân biệt technical admins; test độc lập | Admin thuần bị loại; manager active đúng quyền được chọn; không N+1 |
| C — Delivery | Tích hợp CRM/email/WhatsApp, dispatch revalidation, CC/fallback, dedupe, bảo vệ uncertain và manual retry | Cùng chính sách ở mọi đường gửi; lịch sử outbox/SLA giữ nguyên |
| D — Settings/migration | Options, preview, validation, language, audit cấu hình; rà cohort bị nhắc | Xem và lưu đúng policy; chỉ định nhận không tăng quyền hay KPI |
| E — Verification | Test hành vi resolver/engine/DB và UI; regression Dashboard/SLA/idempotency | Ma trận bên dưới đạt; phân biệt mock và DB-backed |
| F — Canary/rollout | Chốt danh sách người nhận, dừng worker ngắn để đồng bộ policy/backlog, bật v2 có kiểm soát | Một vòng Cron thật gửi đúng người; không gửi Admin IT, không tăng attempts/duplicate bất thường |

## 9. Ma trận kiểm thử bắt buộc

1. Admin active không có explicit view, không selected: chỉ cảnh báo kỹ thuật; không CC/WhatsApp/Bell quản lý.
2. Non-admin active có dòng view: nhận theo mode explicit; view_own-only không nhận cảnh báo đồng nghiệp.
3. Selected mode: chỉ danh sách đã chọn; Admin kiêm nhiệm được chọn nhận; staff khác có view nhưng không selected không nhận.
4. Staff inactive, bị thu hồi view hoặc bị bỏ chọn sau enqueue: không gửi ở dispatch/manual retry. Không dùng role template để khôi phục quyền staff đã thu hồi.
5. Đổi mode/đích không replay delivery cũ đã sent/uncertain; giữ idempotency và audit.
6. Owner cũng là manager, email/JID dùng chung: không trùng người nhận trong cùng kênh; staff delivery/SLA vẫn đúng.
7. CC tắt, critical_only, rule cấm CC, không có manager, email sai, fallback cũ: kết quả đúng và preview giải thích; không tự fallback Admin.
8. WhatsApp group_only/direct_only/both: số hàng đúng group + JID unique; nhóm rõ ràng, không gửi vì nhầm mode.
9. Manager không bán hàng không bị kéo vào cohort nhắc sản lượng chỉ vì view; NVKD zero activity vẫn được nhắc; manager có hoạt động vẫn có reminder owner hợp lệ.
10. Dashboard: quyền global/own, cohort trong kỳ, rank trước projection, staff_count/target và SLA không thay đổi do cấu hình người nhận.
11. Uncertain/unverified, GET đối soát, lỗi SMTP/gateway: không đưa bản ghi cách ly về gửi thường; không tốn quota vì đối soát; technical alert vẫn Admin-only.
12. Request không có quyền/CSRF, payload ID giả, HTML trong tên/lỗi: bị chặn hoặc escape đúng; không lộ secret trong preview/log.

Test phải gọi code resolver/engine thực tế, kiểm tra outbox/CC/provider spy và trạng thái DB; không chỉ so sánh chuỗi SQL hoặc tự gán giá trị kỳ vọng. Trước provider call canary phải có đích và nội dung test được người dùng cho phép. Lint PHP mọi file đổi; chạy test module liên quan, không tuyên bố toàn suite nếu chưa có danh sách/kết quả đầy đủ.

## 10. Rollback và điều kiện nghiệm thu

- Không rollback bằng cách tự bật lại `Admin OR view` rồi tiếp tục gửi, vì sẽ quay lại lỗi sai người nhận.
- Nếu v2 lỗi: tạm dừng cảnh báo quản lý, duy trì staff Bell/email và cảnh báo hệ thống; giữ outbox/audit. Có thể phục hồi code/cấu hình cũ chỉ sau khi xác định lại danh sách và backlog.
- Sao lưu cấu hình và DB theo quy trình vận hành; không xóa delivery đã gửi hoặc idempotency gateway.
- Nghiệm thu cuối: Admin IT thuần không nhận kinh doanh; manager đúng nguồn cấu hình nhận; NVKD nhận reminder cá nhân; không đổi quyền và KPI; test canary DB-backed đạt; tài liệu người dùng mô tả đúng người nhận và fallback.

## 11. File dự kiến ảnh hưởng

- Mới: `libraries/Reminder_recipient_resolver.php`, test recipient policy và migration mới trong module.
- Sửa: `libraries/Reminder_engine.php`, `libraries/Reminder_delivery_operations.php` nếu guard retry nằm tại đây, defaults/schema bootstrap, controller, `views/settings.php`, language Việt/Anh.
- Rà có điều kiện: model/selector/evaluator cho cohort bị nhắc và revalidation; không thay rộng mọi `is_admin/view`.
- Đồng bộ khi triển khai: context Sales Pipeline, đặc tả reminder/KPI liên quan, user guide và báo cáo implementation riêng từng phase. Kế hoạch này không phải bằng chứng các phase đã hoàn thành.
