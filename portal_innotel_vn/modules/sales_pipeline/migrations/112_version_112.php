<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Additive Reminder SLA response tracking and Performance Score options. */
class Migration_Version_112 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        require_once module_dir_path('sales_pipeline', 'includes/reminder_repository_schema.php');
        sales_pipeline_ensure_reminder_repository_schema($CI);

        require_once module_dir_path('sales_pipeline', 'includes/reminder_rule_defaults.php');
        sales_pipeline_seed_reminder_rule_options();

        require_once module_dir_path('sales_pipeline', 'includes/performance_score_defaults.php');
        sales_pipeline_seed_performance_score_options();

        if (get_option('sp_reminder_sla_hours') === false) {
            add_option('sp_reminder_sla_hours', '24');
        }

        if (get_option('performance_response_target_percent') === false) {
            add_option('performance_response_target_percent', '90');
        }
    }

    /**
     * Non-destructive rollback:
     * Dữ liệu nhắc nhở và phản hồi là audit data có giá trị pháp lý/hoạt động.
     * Không drop cột `response_sla_hours`, `response_due_at` hay index `idx_reminder_sla_eval`.
     * Nếu rollback code về version 1.0.11:
     * - Calculator 1.0.11 tự động bỏ qua cột SLA và tiếp tục tính trên mẫu số 85.
     * - Các option `sp_reminder_sla_hours` và `performance_response_target_percent` được giữ lại
     *   để bảo toàn cấu hình nếu tái kích hoạt trong tương lai.
     */
    public function down()
    {
        // Intentionally non-destructive to preserve audit history and avoid data loss.
    }
}

