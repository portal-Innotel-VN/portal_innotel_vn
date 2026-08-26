# Domain Model — Sales Pipeline

## Khái niệm cốt lõi

| Khái niệm | Định nghĩa |
|---|---|
| Estimate | Chứng từ Báo giá vật lý trong Perfex CRM |
| Estimate Group | Một nhu cầu Báo giá logic của khách hàng |
| Estimate Version | Một Estimate thuộc Group, có số revision |
| Deal | Cơ hội kinh doanh tùy chọn, độc lập với Estimate Group |
| Outcome | Kết quả cuối của Group: pending, accepted hoặc declined |
| Decision Estimate | Revision quyết định kết quả Accepted/Declined |

## Quan hệ

```text
Customer
└── Estimate Group
    ├── Rev.1 / Estimate A
    ├── Rev.2 / Estimate B
    └── Rev.N / Estimate N

Deal 1 ── 0..N Estimate Groups
Estimate Group ── 0..1 Deal
```

## Quyền sở hữu và KPI

- `owner_staff_id` của Group là owner sản lượng và không đổi khi append revision.
- `decision_owner_staff_id` là owner của revision quyết định.
- Doanh thu Accepted ghi cho decision owner.
- Clone/Duplicate không tạo thêm Group và không tăng số Báo giá logic.

## Source of truth

- Kết quả chuỗi: `sales_pipeline_estimate_groups.outcome`.
- Revision hiện tại: `current_estimate_id`.
- Doanh thu chấp nhận: `decision_value_base`.
- Estimate status là tín hiệu để đồng bộ Group, không thay thế Group outcome.
