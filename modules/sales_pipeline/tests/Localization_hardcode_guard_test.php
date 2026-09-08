<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

function localization_guard_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$moduleRoot = dirname(__DIR__);
$dashboardJs = file_get_contents($moduleRoot . '/assets/js/dashboard.js');
$revisionJs = file_get_contents($moduleRoot . '/assets/js/estimate_revision.js');
$staffPipelineView = file_get_contents($moduleRoot . '/views/_dashboard_staff_pipeline.php');
$reminderEmailView = file_get_contents($moduleRoot . '/views/emails/reminder.php');
$helper = file_get_contents($moduleRoot . '/helpers/sales_pipeline_helper.php');
$model = file_get_contents($moduleRoot . '/models/Sales_pipeline_model.php');
$module = file_get_contents($moduleRoot . '/sales_pipeline.php');

foreach (["' tỷ'", "' tr'", "' đ'", "' VNĐ'", "name: 'Kỳ này'", "name: 'Kỳ trước'", "'vi-VN'"] as $literal) {
    localization_guard_assert(strpos($dashboardJs, $literal) === false, "Dashboard JS still hardcodes {$literal}");
}
foreach ([
    'Tìm kiếm báo giá...',
    'Không tìm thấy kết quả phù hợp.',
    "i18n.sourceDateShort || 'Tạo'",
    "i18n.sourceExpiryShort || 'Hạn'",
    "s.indexOf('nháp')",
    "s.indexOf('chấp nhận')",
] as $literal) {
    localization_guard_assert(strpos($revisionJs, $literal) === false, "Estimate Revision JS still hardcodes {$literal}");
}
foreach (['50 điểm (Cần tăng tốc)', '80 điểm (Đạt chuẩn)', '100 điểm (Mục tiêu chuẩn 100% KPI)', '(Chiếm 20% trọng số)', '(Chiếm 40% trọng số)', '(Chiếm 25% trọng số)', '(Chiếm 15% trọng số)'] as $literal) {
    localization_guard_assert(strpos($staffPipelineView, $literal) === false, "Staff performance view still hardcodes {$literal}");
}
localization_guard_assert(strpos($reminderEmailView, "?: 'Anh/Chị'") === false, 'Reminder email recipient fallback must be localized');
localization_guard_assert(strpos($model, ". ' đến ' .") === false, 'Date range separator must be localized');
foreach ([": 'Xuất sắc'", ": 'Đạt chuẩn'", ": 'Cần tăng tốc'", ": 'Báo động'", ': "Cách mốc 100% KPI', ': "Cách vạch đạt chuẩn'] as $literal) {
    localization_guard_assert(strpos($helper, $literal) === false, "Performance helper still hardcodes {$literal}");
}

$requiredKeys = [
    'sales_pipeline_currency_billion',
    'sales_pipeline_currency_million',
    'sales_pipeline_currency_vnd',
    'sales_pipeline_js_locale',
    'sales_pipeline_kpi_current_period',
    'sales_pipeline_kpi_previous_period',
    'sales_pipeline_search_source_estimates',
    'sales_pipeline_no_matching_source_estimates',
    'sales_pipeline_performance_weight_hint',
    'sales_pipeline_performance_marker_accelerate',
    'sales_pipeline_performance_marker_standard',
    'sales_pipeline_performance_marker_target',
    'sales_pipeline_date_range',
    'sales_pipeline_response_processing_error',
    'sales_pipeline_reminder_email_recipient_fallback',
];

foreach (['english', 'vietnamese'] as $language) {
    $lang = [];
    require $moduleRoot . '/language/' . $language . '/sales_pipeline_lang.php';
    foreach ($requiredKeys as $key) {
        localization_guard_assert(isset($lang[$key]) && $lang[$key] !== '', "Missing {$language} translation for {$key}");
    }
}

foreach (['currencyBillion', 'currencyMillion', 'currencyVnd', 'currentPeriod', 'previousPeriod',
          'searchPlaceholder', 'noSearchResults'] as $property) {
    localization_guard_assert(strpos($module, "'{$property}'") !== false, "PHP-to-JS i18n payload is missing {$property}");
}

echo "PASS: Sales Pipeline visible strings use bilingual localization contracts\n";
