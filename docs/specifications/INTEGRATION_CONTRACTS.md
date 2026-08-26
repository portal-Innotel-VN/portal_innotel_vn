# Integration Contracts — Estimate Workflow

## Hook chính

- `before_estimate_added`: thu intent và loại namespace module khỏi payload core.
- `after_estimate_added`: tạo standalone hoặc append revision.
- `after_estimate_updated`: refresh snapshot và sync Group.
- `estimate_accepted` / `estimate_declined`: đồng bộ Group outcome.
- `before_estimate_deleted`: cập nhật Version/Group trước khi core xóa Estimate.

## Endpoint chính

| Endpoint | Mục đích |
|---|---|
| `GET estimate_revision_sources` | Dropdown chọn source thủ công |
| `GET estimate_revision_candidates` | Smart Prompt read-only |
| `POST link_estimate_revision` | Manual Link |
| `POST unlink_estimate_revision` | Manual Unlink |
| `GET estimate_version_history` | Version tree và audit timeline |
| `POST set_deal_manual_lock` | Khóa/mở sync Deal |

## Result contract

```php
[
    'success'           => true,
    'action'            => 'standalone|linked|unlinked|fallback_standalone',
    'estimate_id'       => 123,
    'estimate_group_id' => 45,
    'revision_no'       => 2,
    'warning_code'      => null,
    'message_key'       => 'sales_pipeline_revision_linked',
]
```

Controller không được làm mất `action`, `warning_code` hoặc `message_key` của service.
