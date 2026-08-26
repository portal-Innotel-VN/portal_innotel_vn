# Đặc tả và hướng dẫn tính Điểm hiệu suất NVKD

## 1. Phạm vi và nguồn tham chiếu

Tài liệu này mô tả công thức đang được dùng cho bảng xếp hạng tab Báo giá của Sales Pipeline trong `portal_18`.

Đã đối chiếu Basecode và tài liệu tại `docs/plans/Sales Pipeline/`:

- `modules/sales_pipeline/libraries/Performance_score_calculator.php`: chuẩn hóa, công thức và xếp hạng.
- `modules/sales_pipeline/models/Sales_pipeline_model.php`: kỳ, cohort và raw metrics.
- `modules/sales_pipeline/includes/performance_score_defaults.php`: target mặc định.
- `modules/sales_pipeline/controllers/Sales_pipeline.php`: dashboard và role projection.
- `modules/sales_pipeline/tests/Performance_score_*_test.php`: kiểm thử hiện có.
- `docs/plans/Sales Pipeline/performance_score.md` và `planUI_Dashboard.md`: ngữ cảnh nghiệp vụ/UI.

Phiên bản công thức hiện tại là `performance_score_v1`. Mô hình dữ liệu dùng Estimate Group làm một nhu cầu Báo giá logic; Clone/Duplicate trong cùng Group không được tăng sản lượng theo số revision. Deal không phải nguồn sự thật của điểm.

## 2. Thành phần và trọng số

| Thành phần | Field kỹ thuật | Trọng số chuẩn | Trạng thái |
|---|---|---:|---|
| Số Báo giá logic | `quote_score` | 20% | Đang hoạt động |
| Doanh thu Accepted | `accepted_revenue_score` | 40% | Đang hoạt động |
| Tỷ lệ chấp nhận | `acceptance_score` | 25% | Đang hoạt động |
| Phản hồi nhắc nhở đúng hạn | `response_score` | 15% | Chưa kích hoạt trong calculator |

Khi `reminder_response` chưa hoạt động, hệ thống chuẩn hóa trên tổng trọng số `85`:

```text
performance_score_raw =
    (quote_score × 20
   + accepted_revenue_score × 40
   + acceptance_score × 25) / 85
```

Trọng số hiệu dụng tương ứng là `23,5294%`, `47,0588%` và `29,4118%`.

## 3. Kỳ và target

Basecode hỗ trợ `this_week`, `this_month`, `this_quarter` và `this_year`. Kỳ không hợp lệ được chuyển về `this_month`.

| Kỳ | Target Báo giá | Target doanh thu Accepted |
|---|---:|---:|
| `this_week` | 5 | 250.000.000 |
| `this_month` | 20 | 1.000.000.000 |
| `this_quarter` | 60 | 3.000.000.000 |
| `this_year` | 240 | 12.000.000.000 |

Các option được dùng:

```text
performance_quote_target_{period}
performance_revenue_target_{period}
performance_acceptance_target_percent
performance_component_cap
performance_min_closed_quotes
```

Target được seed bằng `performance_score_defaults.php`; option còn thiếu được thêm mà không ghi đè cấu hình quản trị viên.

## 4. Cohort và quyền

`get_estimate_performance_ranking()` lấy Staff active có quyền `view` hoặc `view_own` Sales Pipeline. Ranking được tính trên cohort trước khi áp dụng projection:

- Admin nhận toàn bộ leaderboard.
- Staff chỉ nhận dòng của chính mình, nhưng rank vẫn lấy từ toàn cohort.
- Metric của Staff được khởi tạo bằng 0; Admin không có hoạt động có thể bị loại khỏi cohort theo logic hiện tại.
- Staff không nhận component score, email hoặc raw metrics của người khác.

## 5. Chuẩn hóa điểm thành phần

```text
component_score = CLAMP((actual / target) × 100, 0, component_cap)
```

`component_cap` mặc định là `120`. Calculator giữ độ chính xác nội bộ và làm tròn `ranking_score` ở một chữ số thập phân.

## 6. Công thức từng thành phần

### 6.1. Số Báo giá logic

```text
quote_score = CLAMP(
    estimate_count / quote_target × 100,
    0,
    component_cap
)
```

Trong ranking path hiện tại, `estimate_count` là số Estimate Group do Staff sở hữu, có `datecreated` trong kỳ. Các Rev.1/Rev.2/Rev.3 của cùng Group chỉ tính một lần.

Ví dụ target 20, có 15 Group:

```text
quote_score = 15 / 20 × 100 = 75
```

### 6.2. Doanh thu Accepted

Nguồn sự thật là `tblsales_pipeline_estimate_groups`:

```text
accepted_revenue = SUM(decision_value_base)
```

Chỉ lấy Group có `outcome = accepted` và `decision_at` nằm trong kỳ. Owner nhận doanh thu là:

```text
COALESCE(decision_owner_staff_id, owner_staff_id)
```

Công thức:

```text
accepted_revenue_score = CLAMP(
    accepted_revenue / revenue_target × 100,
    0,
    component_cap
)
```

Mỗi Group chỉ đóng góp một lần. Không dùng tổng các revision và không join sang Deal để suy diễn lợi nhuận. Nếu `decision_value_base` thiếu do tỷ giá, metric gắn cờ `missing_revenue_rate` và kết quả là provisional.

### 6.3. Tỷ lệ chấp nhận

```text
closed_count = accepted_count + declined_count
acceptance_rate = accepted_count / closed_count × 100
acceptance_score = CLAMP(
    acceptance_rate / acceptance_target_percent × 100,
    0,
    component_cap
)
```

Estimate chưa có quyết định cuối không nằm trong mẫu số. Nếu `closed_count < performance_min_closed_quotes`, hệ thống vẫn tính điểm nhưng thêm `insufficient_closed_quotes` và `is_provisional = true`.

### 6.4. Phản hồi nhắc nhở

Calculator hiện trả:

```text
response_score = null
component_status.reminder_response = inactive
```

Khi SLA đủ tin cậy, công thức dự kiến:

```text
on_time_response_rate = on_time_response_count / eligible_reminder_count × 100
response_score = CLAMP(
    on_time_response_rate / response_target_percent × 100,
    0,
    component_cap
)
```

Chỉ reminder có `response_required = 1` và đã đến hạn mới nằm trong mẫu số.

## 7. Ví dụ tính điểm

Giả sử target tháng là 20 Báo giá, 1.000.000.000 doanh thu và acceptance target 50%. Raw metrics:

```text
estimate_count = 15
accepted_revenue = 800.000.000
accepted_count = 6
declined_count = 4
```

Kết quả:

```text
quote_score = 75
accepted_revenue_score = 80
acceptance_rate = 6 / (6 + 4) × 100 = 60%
acceptance_score = 60 / 50 × 100 = 120
```

Vì response inactive:

```text
performance_score_raw = (75 × 20 + 80 × 40 + 120 × 25) / 85
                       = 91,7647
ranking_score = ROUND(91,7647, 1) = 91,8
```

`performance_score_raw` phục vụ audit; `performance_score`/`ranking_score` phục vụ hiển thị và xếp hạng.

## 8. Quy tắc xếp hạng

1. Tính toàn bộ cohort trong cùng kỳ và bộ lọc.
2. Sắp xếp `ranking_score DESC`.
3. Tên chỉ dùng để ổn định thứ tự hiển thị khi đồng điểm.
4. Dùng standard competition ranking: `1, 2, 2, 4`.
5. `total_ranked_staff` là số người trong cohort trước projection.

## 9. Projection dữ liệu

Staff chỉ nhận các field của bản thân:

```text
staff_id
staff_name
estimate_count
accepted_revenue
acceptance_rate
performance_score
rank
total_ranked_staff
is_provisional
```

Admin có thể nhận toàn bộ leaderboard và breakdown theo contract. Không dùng CSS/JavaScript làm lớp bảo mật; projection phải được thực hiện ở server.

## 10. Cờ chất lượng dữ liệu

Các cờ có thể xuất hiện:

- `missing_revenue_rate`;
- `insufficient_closed_quotes`;
- `not_configured`;
- `is_provisional`.

Calculator trả `not_configured` nếu config truyền vào thiếu hoặc không hợp lệ. Runtime model hiện fallback về target mặc định khi option thiếu; hai behavior này phải được phân biệt khi kiểm tra.

## 11. Ranh giới với Deal và sai lệch cần lưu ý

Performance Score không đọc `tblsales_pipeline.cost_price` và không tính profit. Muốn bổ sung profit phải tạo formula version mới với cost snapshot theo Estimate Version.

Basecode có hai đường metric:

1. `get_estimate_dashboard_metrics()` cho summary/metric UI cũ.
2. `get_estimate_performance_ranking()` cho Performance Score và leaderboard.

Không trộn hai đường này khi giải thích điểm. Ranking path hiện đếm Group theo `datecreated`; query runtime hiện không lọc status `IN (2,3,4,5)` như một số metric dashboard cũ. Nếu nghiệp vụ yêu cầu chỉ đếm Group có revision hợp lệ, phải cập nhật query, test và tài liệu cùng lúc.

## 12. Checklist xác minh

- Đạt đúng target của ba component hoạt động cho điểm `100`.
- Component không vượt `120`.
- Response inactive dùng mẫu số trọng số `85`.
- Clone/Duplicate không tăng count theo số revision.
- Accepted revenue dùng `decision_value_base` và decision owner.
- Acceptance rate dùng accepted/declined Group.
- Staff được xếp hạng từ full cohort trước projection.
- Đồng điểm tạo hạng `1, 2, 2, 4`.
- Staff không nhận component/raw data của người khác.

Lệnh kiểm thử hiện có:

```bash
php modules/sales_pipeline/tests/Performance_score_calculator_test.php
php modules/sales_pipeline/tests/Performance_score_period_options_test.php
php modules/sales_pipeline/tests/Performance_score_language_render_test.php
```

Chỉ kết luận điểm hợp lệ khi formula version, kỳ, target, raw metrics, Estimate Group và projection quyền nhất quán.
