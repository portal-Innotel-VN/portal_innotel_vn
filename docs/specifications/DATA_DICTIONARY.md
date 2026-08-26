# Data Dictionary — Sales Pipeline Estimates

## Bảng chính

| Bảng | Vai trò |
|---|---|
| `tblestimates` | Chứng từ Báo giá vật lý |
| `tblsales_pipeline_estimate_groups` | Nhu cầu Báo giá logic |
| `tblsales_pipeline_estimate_versions` | Quan hệ Group–Estimate và revision metadata |
| `tblsales_pipeline_estimate_outcome_history` | Lịch sử outcome |
| `tblsales_pipeline_estimate_group_events` | Audit link/unlink/fallback/override |
| `tblsales_pipeline_deal_estimate_groups` | Bridge Deal–Group |

## Group fields

| Field | Ý nghĩa |
|---|---|
| `owner_staff_id` | Owner sản lượng Group |
| `decision_owner_staff_id` | Owner revision quyết định |
| `origin_estimate_id` | Estimate khởi tạo Group |
| `current_estimate_id` | Revision hiện tại |
| `decision_estimate_id` | Revision quyết định outcome |
| `decision_value_base` | Giá trị Accepted theo base currency |
| `outcome` | pending, accepted hoặc declined |
| `decision_at` | Thời điểm quyết định dùng cho kỳ KPI |

## Version fields

| Field | Ý nghĩa |
|---|---|
| `estimate_group_id` | Group chứa revision |
| `estimate_id` | Estimate vật lý |
| `revision_no` | Số revision không renumber |
| `parent_estimate_id` | Revision cha |
| `link_method` | origin, copy, declared_revision, manual_link, manual_unlink... |
| `linked_by` | Staff tạo quan hệ |
| `base_total` | Tổng đã quy đổi tại thời điểm snapshot |

## Estimate status

`1 Draft`, `2 Sent`, `3 Declined`, `4 Accepted`, `5 Expired`.
