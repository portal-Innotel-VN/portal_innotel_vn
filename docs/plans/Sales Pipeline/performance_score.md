# Performance Score — Điểm hiệu suất Sales Pipeline

> **Module**: Sales Pipeline  
> **Phạm vi**: Bảng xếp hạng tại `/admin/sales_pipeline/dashboard?dashboard_tab=estimates`
> **Trạng thái đặc tả**: Chuẩn nghiệp vụ
> **Trạng thái triển khai**: Đã triển khai một phần
> **Phiên bản công thức**: `performance_score_v1`  
> **Cập nhật lần cuối**: 2026-08-13

Tài liệu này là nguồn sự thật duy nhất cho cách tính **Điểm hiệu suất** (`performance_score`). Kế hoạch triển khai và bố trí giao diện nằm tại [planUI_Dashboard.md](./planUI_Dashboard.md).

---

## 1. Mục tiêu

Điểm hiệu suất gom các chỉ số kinh doanh có đơn vị khác nhau về một thang điểm chung để:

1. Xếp hạng nhân viên theo hiệu suất tổng hợp thay vì một chỉ số đơn lẻ.
2. Cho Staff biết vị trí của mình mà không công khai số liệu cấu thành của người khác.
3. Cân bằng giữa sản lượng Báo giá, giá trị được chấp nhận, chất lượng chuyển đổi và kỷ luật phản hồi.
4. Giữ công thức minh bạch, có phiên bản và có thể kiểm thử lại theo cùng kỳ dữ liệu.

`performance_score` là tên kỹ thuật. Giao diện tiếng Việt luôn dùng **Điểm hiệu suất**, không dùng “Điểm KPI”.

---

## 2. Thành phần và trọng số chuẩn

| Mã | Thành phần | Trọng số chuẩn | Chiều hướng |
|---|---|---:|---|
| `quote_count` | Số Báo giá hợp lệ | 20% | Càng cao càng tốt |
| `accepted_revenue` | Giá trị Báo giá được chấp nhận | 40% | Càng cao càng tốt |
| `acceptance_rate` | Tỷ lệ chấp nhận Báo giá | 25% | Càng cao càng tốt |
| `reminder_response` | Tỷ lệ phản hồi nhắc nhở đúng hạn | 15% | Càng cao càng tốt |
| | **Tổng** | **100%** | |

Trọng số trên là cấu hình mặc định của `performance_score_v1`. Thay đổi trọng số tạo một phiên bản công thức mới và chỉ có hiệu lực từ đầu kỳ kế tiếp; không tính lại ngầm bảng xếp hạng của kỳ đã chốt.

### 2.1 Giai đoạn chưa kích hoạt điểm phản hồi

`reminder_response` là thành phần tương lai. Khi dữ liệu SLA phản hồi chưa đủ tin cậy, thành phần này ở trạng thái `inactive`. Tổng điểm dùng các trọng số đang hoạt động và tự chuẩn hóa lại:

```text
Điểm hiệu suất =
    SUM(điểm thành phần × trọng số chuẩn)
    / SUM(trọng số chuẩn của các thành phần đang hoạt động)
```

Với ba thành phần đầu đang hoạt động, trọng số hiệu dụng là:

| Thành phần | Trọng số hiệu dụng |
|---|---:|
| Số Báo giá | 23,53% |
| Giá trị Báo giá được chấp nhận | 47,06% |
| Tỷ lệ chấp nhận | 29,41% |

Khi kích hoạt `reminder_response`, tạo phiên bản công thức kế tiếp và áp dụng đủ trọng số `20/40/25/15` từ đầu một kỳ mới.

---

## 3. Tập nhân viên được xếp hạng

Mỗi kỳ/bộ lọc phải tạo đúng một **ranking cohort** dùng chung cho Admin và Staff. Một nhân viên thuộc cohort khi:

1. Tài khoản đang hoạt động.
2. Có quyền `view` hoặc `view_own` đối với `sales_pipeline`.
3. Là Staff kinh doanh (`admin = 0`), hoặc là Admin có hoạt động kinh doanh trong kỳ.

Staff không có hoạt động trong kỳ vẫn thuộc cohort và nhận điểm sản lượng bằng `0`; điều này tránh việc không làm phát sinh dữ liệu lại giúp họ biến mất khỏi bảng xếp hạng.

`total_ranked_staff` là số người trong cohort, không phải tổng số tài khoản Staff trong hệ thống.

---

## 4. Kỳ đánh giá và mục tiêu

Điểm dùng đúng kỳ đang chọn trên Dashboard:

- `this_week`
- `this_month`
- `this_quarter`
- `this_year`

Mỗi thành phần có mục tiêu riêng cho từng loại kỳ. Không suy diễn mục tiêu tháng sang tuần bằng phép chia số ngày vì số ngày làm việc và mùa vụ không đồng đều.

Các khóa cấu hình đề xuất:

```text
performance_quote_target_{period}
performance_revenue_target_{period}
performance_acceptance_target_percent
performance_response_target_percent
performance_component_cap
performance_min_closed_quotes
```

Giá trị `performance_component_cap` mặc định là `120`. Nếu thiếu mục tiêu bắt buộc, hệ thống trả trạng thái `not_configured` và không phát sinh thứ hạng giả.

Các giá trị mặc định vận hành cho `performance_score_v1`:

| Kỳ | Mục tiêu Báo giá | Mục tiêu giá trị chấp nhận |
|---|---:|---:|
| `this_week` | 5 | 250.000.000 |
| `this_month` | 20 | 1.000.000.000 |
| `this_quarter` | 60 | 3.000.000.000 |
| `this_year` | 240 | 12.000.000.000 |

Đây là bốn mục tiêu cấu hình độc lập được seed cho installation mới và installation đang hoạt động; không tính động bằng số ngày của kỳ. `add_option` chỉ thêm khóa còn thiếu nên giá trị quản trị viên đã điều chỉnh không bị ghi đè.

---

## 5. Chuẩn hóa từng thành phần

Số lượng, tiền tệ và phần trăm không được cộng trực tiếp. Mỗi thành phần được chuyển về thang `0–120`:

```text
component_score = CLAMP((actual / target) × 100, 0, 120)
```

Ý nghĩa:

- `0`: chưa phát sinh kết quả.
- `100`: hoàn thành đúng mục tiêu.
- `101–120`: vượt mục tiêu.
- `120`: trần điểm, ngăn một thành phần vượt trội bù hoàn toàn các thành phần yếu.

Mọi phép tính nội bộ giữ tối thiểu 4 chữ số thập phân. Chỉ làm tròn khi tạo `ranking_score` và khi hiển thị.

### 5.1 Điểm Số Báo giá

```text
quote_score = CLAMP(
    valid_quote_count / quote_target × 100,
    0,
    120
)
```

Quy tắc dữ liệu:

- Đếm Báo giá logic, không đếm mỗi revision như một Báo giá mới.
- Dùng Estimate Group/nguồn dữ liệu đã khử trùng lặp để chống tạo nhiều phiên bản nhằm tăng điểm.
- Báo giá phải thuộc nhân viên và có ngày tạo nằm trong kỳ đang chọn.
- Báo giá bị xóa/void theo quy tắc hệ thống không được tính.

### 5.2 Điểm Giá trị Báo giá được chấp nhận

```text
accepted_revenue = SUM(decision_value_base)

accepted_revenue_score = CLAMP(
    accepted_revenue / accepted_revenue_target × 100,
    0,
    120
)
```

Quy tắc dữ liệu:

- Nguồn sự thật là `tblsales_pipeline_estimate_groups`, độc lập với Deal.
- Chỉ cộng Estimate Group có `outcome = accepted` và `decision_at` nằm trong kỳ đang chọn.
- Ghi nhận cho `COALESCE(decision_owner_staff_id, owner_staff_id)` theo snapshot quyết định của group.
- Giá trị dùng `decision_value_base`, là tổng của revision được chấp nhận đã quy đổi sang tiền tệ gốc; không dùng tổng của revision hiện tại nếu revision đó không phải revision quyết định.
- Mỗi Estimate Group chỉ đóng góp một lần, bất kể có bao nhiêu revision.
- `decision_at` là thời điểm Estimate chuyển sang trạng thái chấp nhận. Basecode ưu tiên activity log, sau đó dùng thời điểm chuyển thành hóa đơn khi phù hợp và cuối cùng mới dùng thời điểm reconciliation quan sát được.
- Nếu `decision_value_base IS NULL` do thiếu tỷ giá, group đó không được coi là giá trị `0`. Kết quả mang cờ `missing_revenue_rate` và `is_provisional = true` để Admin biết thứ hạng có thể thay đổi sau khi bổ sung tỷ giá.

### 5.2.1 Ranh giới với Lợi nhuận

Cấu trúc Estimate hiện tại chỉ snapshot `source_total`, tỷ giá và `base_total`; `tblitemable` của Estimate có số lượng/đơn giá bán nhưng không có giá vốn. Vì vậy `performance_score_v1` **không tính Lợi nhuận** và không đọc `tblsales_pipeline.cost_price` của Deal.

Muốn bổ sung Lợi nhuận Báo giá trong một phiên bản sau, phải thiết kế nguồn giá vốn riêng cho Estimate, snapshot chi phí theo từng revision và lưu lợi nhuận tại thời điểm chấp nhận. Khi chưa có cấu trúc đó, “Giá trị Báo giá được chấp nhận” là thành phần tài chính duy nhất đủ nguồn dữ liệu cho tab `estimates`.

### 5.3 Điểm Tỷ lệ chấp nhận Báo giá

```text
closed_quote_count = accepted_count + declined_count

acceptance_rate =
    accepted_count / closed_quote_count × 100

acceptance_score = CLAMP(
    acceptance_rate / acceptance_target_percent × 100,
    0,
    120
)
```

Quy tắc dữ liệu:

- Dùng kết quả cuối của Estimate Group, không dùng từng revision.
- `accepted_count` và `declined_count` được ghi nhận theo `decision_at` trong kỳ.
- Báo giá còn `draft`, `sent`, `expired` hoặc chưa có quyết định không nằm trong mẫu số.
- Nếu `closed_quote_count = 0`, `acceptance_score = 0` và kết quả mang cờ `is_provisional = true`.
- Nếu `closed_quote_count < performance_min_closed_quotes`, vẫn tính điểm nhưng đánh dấu `is_provisional = true` để tránh diễn giải quá mức tỷ lệ từ mẫu nhỏ.

### 5.4 Điểm Phản hồi nhắc nhở đúng hạn — giai đoạn tương lai

Không dùng số lần vi phạm tuyệt đối vì người nhận nhiều nhắc nhở sẽ có xác suất vi phạm cao hơn. Dùng tỷ lệ trên số reminder bắt buộc phản hồi:

```text
eligible_reminders = reminder có response_required = 1
                    và đã đến hạn phản hồi trong kỳ

on_time_response_rate =
    on_time_response_count / eligible_reminder_count × 100

response_score = CLAMP(
    on_time_response_rate / response_target_percent × 100,
    0,
    120
)
```

Điều kiện kích hoạt thành phần:

1. Có định nghĩa SLA và lưu được `response_due_at` hoặc dữ liệu tương đương.
2. Phân biệt được phản hồi đúng hạn, trễ hạn và chưa phản hồi.
3. Chỉ tính `response_required = 1`; reminder chỉ thông báo không nằm trong mẫu số.
4. Mỗi reminder chỉ được tính một lần.
5. Khi `eligible_reminder_count = 0`, thành phần ở trạng thái `not_applicable` đối với nhân viên đó và công thức phải chuẩn hóa lại trên các trọng số áp dụng, thay vì tự động cho `0` hoặc `100`.

> [!NOTE]
> **Cập nhật kích hoạt:** Thành phần này đã được kích hoạt động trong `performance_score_v1` theo kế hoạch [`analysis_reminder_response_score.md`](file:///Users/dieterhoang/Developer/portal_18/docs/plans/Sales%20Pipeline/analysis_reminder_response_score.md). Quy tắc cũ yêu cầu tạo formula version mới đã được thay thế bằng cơ chế chuyển trạng thái component per-staff (`active` khi có nhắc nhở đến hạn / `not_applicable` trên mẫu số 85 khi không có).

---

## 6. Công thức tổng

Khi cả bốn thành phần áp dụng:

```text
performance_score =
      quote_score      × 0,20
    + accepted_revenue_score × 0,40
    + acceptance_score × 0,25
    + response_score   × 0,15
```

Tổng quát cho trường hợp có thành phần `inactive/not_applicable`:

```text
performance_score =
    SUM(component_score × standard_weight)
    / SUM(applicable_standard_weight)
```

Ví dụ khi bốn thành phần đều áp dụng:

| Thành phần | Điểm thành phần | Trọng số | Điểm đóng góp |
|---|---:|---:|---:|
| Số Báo giá | 90 | 20% | 18,00 |
| Giá trị Báo giá được chấp nhận | 110 | 40% | 44,00 |
| Tỷ lệ chấp nhận | 80 | 25% | 20,00 |
| Phản hồi đúng hạn | 95 | 15% | 14,25 |
| | | **Điểm hiệu suất** | **96,25** |

Lưu:

- `performance_score_raw`: kết quả chính xác dùng audit.
- `ranking_score`: `ROUND(performance_score_raw, 1)`, dùng xếp hạng.
- UI hiển thị `ranking_score` với một chữ số thập phân, ví dụ `96,3 điểm`.

---

## 7. Quy tắc xếp hạng

1. Tính điểm cho toàn bộ cohort trong cùng kỳ và cùng bộ lọc.
2. Sắp xếp `ranking_score DESC`.
3. Dùng **standard competition ranking**: `1, 2, 2, 4`.
4. Hai người có cùng `ranking_score` hiển thị cùng hạng.
5. Trong nhóm đồng hạng, sắp tên tăng dần chỉ để giao diện ổn định; tên không làm thay đổi hạng.
6. Staff nhận `rank` và `total_ranked_staff` từ kết quả toàn cohort trước khi response bị thu gọn về dòng cá nhân.

Ví dụ:

| Nhân viên | Điểm | Hạng |
|---|---:|---:|
| A | 101,4 | 1 |
| B | 96,3 | 2 |
| C | 96,3 | 2 |
| D | 91,0 | 4 |

Không dùng doanh thu, số Báo giá hoặc tên nhân viên làm tie-break ngầm cho thứ hạng.

---

## 8. Hợp đồng dữ liệu và bảo mật

Projection tối thiểu dùng chung:

```php
[
    'staff_id'          => 12,
    'staff_name'        => 'Nguyễn Văn A',
    'performance_score' => 96.3,
    'rank'              => 3,
    'total_ranked_staff'=> 18,
    'is_provisional'    => false,
]
```

Admin có thể nhận toàn bộ raw metrics và component scores để kiểm tra. Staff chỉ nhận:

- Dòng dữ liệu của chính mình.
- Các chỉ số cá nhân được yêu cầu trên UI.
- `rank`, `total_ranked_staff` và `performance_score` của chính mình.

Payload dành cho Staff không chứa điểm, raw metrics, email hoặc dữ liệu chi tiết của người khác. Việc ẩn cột bằng CSS/JavaScript không được xem là kiểm soát quyền.

---

## 9. Tính ổn định và audit

Mỗi lần tính phải xác định được:

```text
formula_version
period_key
period_start
period_end
filter_signature
component_targets
standard_weights
calculated_at
data_quality_flags
```

Trọng số/mục tiêu thay đổi giữa kỳ chỉ có hiệu lực từ kỳ tiếp theo. Nếu cần xem lại lịch sử đã chốt, dùng phiên bản và target của kỳ đó thay vì cấu hình hiện tại.

Các cờ dữ liệu tối thiểu:

- `missing_revenue_rate`
- `insufficient_closed_quotes`
- `missing_response_sla`
- `not_configured`
- `is_provisional`

---

## 10. Tiêu chí nghiệm thu công thức

1. Tổng trọng số chuẩn của `performance_score_v1` bằng `100%`.
2. Thành phần chưa hoạt động/không áp dụng được loại khỏi mẫu số trọng số.
3. Mỗi component score nằm trong `0–120`.
4. Revision Báo giá không làm tăng `valid_quote_count`.
5. Estimate Group thiếu `decision_value_base` không bị coi là doanh thu bằng `0`; kết quả được gắn cờ tạm tính.
6. Reminder chỉ thông báo không ảnh hưởng điểm phản hồi.
7. Nhân viên đồng `ranking_score` nhận cùng hạng theo chuỗi `1, 2, 2, 4`.
8. Rank của Staff được tính trên toàn cohort trước khi chỉ giữ lại dòng của Staff.
9. Admin và Staff nhìn cùng một `performance_score/rank` cho cùng nhân viên, cùng kỳ và cùng bộ lọc.
10. Staff không nhận dữ liệu cấu thành của nhân viên khác trong HTML hoặc AJAX payload.

---

## 11. Phân tầng cấp độ và Trực quan hóa tiến độ (Performance Tiers & Progress Bar)

Để thúc đẩy động lực và cảnh báo kịp thời cho đội ngũ kinh doanh, hệ thống quy chuẩn hóa **4 cấp độ hiệu suất** dựa trên thang điểm 0 – 100 (trần 120):

### 11.1. Bảng phân tầng 4 cấp độ chuẩn

| Mức điểm tổng kết | Cấp độ / Trạng thái | Mã định danh (`key`) | Màu sắc nhận diện | Tác động quản trị & KPI |
|:---:|:---:|:---:|:---:|---|
| **$\ge 100$ điểm** | **XUẤT SẮC** | `excellent` | Xanh lục bảo (`#047857`) / Nền `#ecfdf5` | Vượt chỉ tiêu toàn diện (Overachiever). Khen thưởng, vinh danh. |
| **$80 - 99,9$ điểm** | **ĐẠT CHUẨN** | `good` | Xanh lá (`#15803d`) / Nền `#f0fdf4` | Đạt kỳ vọng KPI (On Track). Giữ vững phong độ. |
| **$50 - 79,9$ điểm** | **CẦN TĂNG TỐC** | `warning` | Vàng cam (`#b45309`) / Nền `#fffbeb` | Chưa đạt chuẩn (Needs Focus). Cần đẩy mạnh gửi báo giá và bám sát deal. |
| **$< 50$ điểm** | **BÁO ĐỘNG** | `critical` | Đỏ đậm (`#dc2626`) / Nền `#fef2f2` | Nguy cơ rớt KPI (Critical / At Risk). Cảnh báo khẩn cấp cần hành động ngay. |

### 11.2. Công thức tính khoảng cách vạch chuẩn (Gap Indicator)

- **Nếu điểm $< 80,0$**: Khoảng cách tính tới mốc Đạt chuẩn:
  $$\text{gap} = 80,0 - \text{score}$$
  *Thông điệp*: `"Cách vạch đạt chuẩn (80 điểm): còn [gap] điểm"`
- **Nếu điểm $80,0 \le \text{score} < 100,0$**: Khoảng cách tính tới mốc hoàn thành 100% KPI:
  $$\text{gap} = 100,0 - \text{score}$$
  *Thông điệp*: `"Cách mốc 100% KPI: còn [gap] điểm"`
- **Nếu điểm $\ge 100,0$**:
  *Thông điệp*: `"Đã đạt chuẩn hiệu suất (Vượt chỉ tiêu)"`

### 11.3. Quy chuẩn hiển thị giao diện (UI Specs)

1. **Nguyên tắc No-Icon**: Tuyệt đối không dùng icon trong badge cấp độ và thanh đo tiến độ; dùng kiểu chữ đậm (typography) và mã màu CSS chuyên nghiệp.
2. **Hộp trạng thái tích hợp (Integrated Metric Tag)** tại Drawer:
   - Cấu trúc: `[ Điểm hiệu suất: X điểm │ TÊN TRẠNG THÁI ]`
   - Vế trái nền trắng với số điểm mang màu cấp độ.
   - Vạch phân cách dọc thanh mảnh.
   - Vế phải nền màu pastel với chữ in hoa trạng thái tương ứng.
3. **Thanh đo tiến độ trực quan (Visual Progress Bar)**:
   - Thang đo: 0 đến 120 điểm.
   - Dải màu thanh bar tự động chuyển màu theo cấp độ hiện tại (Gradient Đỏ $\rightarrow$ Vàng $\rightarrow$ Xanh).
   - Đánh dấu 5 mốc cố định: `0`, `50`, `80`, `100`, `120`.
