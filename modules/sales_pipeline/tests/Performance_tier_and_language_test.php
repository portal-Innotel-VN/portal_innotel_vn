<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);

require_once dirname(__DIR__) . '/helpers/sales_pipeline_helper.php';

function _l($key, $params = []) {
    static $lang = null;
    if ($lang === null) {
        $lang = [];
        require dirname(__DIR__) . '/language/vietnamese/sales_pipeline_lang.php';
    }
    $str = isset($lang[$key]) ? $lang[$key] : $key;
    if (!empty($params)) {
        if (!is_array($params)) {
            $params = [$params];
        }
        return vsprintf($str, $params);
    }
    return $str;
}

function assert_equals($expected, $actual, $msg = '') {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$msg} — Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

// 1. Test tier resolution for 4 levels
// Level 1: >= 100 -> Excellent
$tier1 = sales_pipeline_resolve_performance_tier(105.0);
assert_equals('excellent', $tier1['key'], 'Score >= 100 must be excellent');
assert_equals('Xuất sắc', $tier1['label'], 'Tier label for >= 100');
assert_equals('sp-performance-tier-badge--excellent', $tier1['badge_class']);
assert_equals('sp-score--excellent', $tier1['score_class']);

// Level 2: 80 - 99.9 -> Good
$tier2 = sales_pipeline_resolve_performance_tier(88.5);
assert_equals('good', $tier2['key'], 'Score 80-99.9 must be good');
assert_equals('Đạt chuẩn', $tier2['label'], 'Tier label for 80-99.9');
assert_equals('sp-performance-tier-badge--good', $tier2['badge_class']);
assert_equals('sp-score--good', $tier2['score_class']);

// Level 3: 50 - 79.9 -> Warning
$tier3 = sales_pipeline_resolve_performance_tier(64.2);
assert_equals('warning', $tier3['key'], 'Score 50-79.9 must be warning');
assert_equals('Cần tăng tốc', $tier3['label'], 'Tier label for 50-79.9');
assert_equals('sp-performance-tier-badge--warning', $tier3['badge_class']);
assert_equals('sp-score--warning', $tier3['score_class']);

// Level 4: < 50 -> Critical
$tier4 = sales_pipeline_resolve_performance_tier(44.1);
assert_equals('critical', $tier4['key'], 'Score < 50 must be critical');
assert_equals('Báo động', $tier4['label'], 'Tier label for < 50');
assert_equals('sp-performance-tier-badge--critical', $tier4['badge_class']);
assert_equals('sp-score--critical', $tier4['score_class']);

// 2. Test language key: 'sales_pipeline_performance_revenue_component' and 'sales_pipeline_performance_breakdown'
assert_equals('Điểm giá trị Báo giá đã chấp nhận', _l('sales_pipeline_performance_revenue_component'));
assert_equals('Bảng Điểm Hiệu Suất', _l('sales_pipeline_performance_breakdown'));
assert_equals('Đang tải thông tin nhân viên...', _l('sales_pipeline_dashboard_estimates_loading'));
assert_equals('Deal', _l('sales_pipeline_deal'));

echo "PASS: Performance tier resolution and language keys test\n";
