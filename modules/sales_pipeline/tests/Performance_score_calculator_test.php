<?php

require_once dirname(__DIR__) . '/libraries/Performance_score_calculator.php';

function assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function assert_true($actual, $message)
{
    assert_same(true, (bool) $actual, $message);
}

$calculator = new Performance_score_calculator();
$config = [
    'formula_version'   => 'performance_score_v1',
    'quote_target'      => 20,
    'revenue_target'    => 1000000000,
    'acceptance_target' => 50,
    'component_cap'     => 120,
    'min_closed_quotes' => 3,
];

$perfect = $calculator->calculate_leaderboard([[
    'staff_id'                  => 10,
    'staff_name'                => 'Perfect Staff',
    'email'                     => 'perfect@example.test',
    'estimate_count'            => 20,
    'accepted_revenue'          => 1000000000,
    'accepted_count'            => 5,
    'declined_count'            => 5,
    'missing_revenue_rate_count'=> 0,
]], $config);

assert_same(100.0, $perfect['leaderboard'][0]['performance_score'], 'Đạt đúng ba target phải nhận 100 điểm');
assert_same(23.5294, $perfect['leaderboard'][0]['effective_weights']['quote_score'], 'Trọng số Báo giá phải được quy đổi trên tổng 85');
assert_same('inactive', $perfect['leaderboard'][0]['component_status']['reminder_response'], 'Điểm phản hồi phải inactive trong v1');

$capped = $calculator->calculate_leaderboard([[
    'staff_id'                  => 11,
    'staff_name'                => 'Capped Staff',
    'estimate_count'            => 200,
    'accepted_revenue'          => 10000000000,
    'accepted_count'            => 20,
    'declined_count'            => 0,
    'missing_revenue_rate_count'=> 0,
]], $config);

assert_same(120.0, $capped['leaderboard'][0]['quote_score'], 'Điểm thành phần phải bị chặn tại 120');
assert_same(120.0, $capped['leaderboard'][0]['performance_score'], 'Tổng điểm không vượt trần khi mọi thành phần vượt target');

$ranking = $calculator->calculate_leaderboard([
    ['staff_id' => 1, 'staff_name' => 'A', 'estimate_count' => 24, 'accepted_revenue' => 1200000000, 'accepted_count' => 6, 'declined_count' => 4, 'missing_revenue_rate_count' => 0],
    ['staff_id' => 2, 'staff_name' => 'B', 'estimate_count' => 20, 'accepted_revenue' => 1000000000, 'accepted_count' => 5, 'declined_count' => 5, 'missing_revenue_rate_count' => 0],
    ['staff_id' => 3, 'staff_name' => 'C', 'estimate_count' => 20, 'accepted_revenue' => 1000000000, 'accepted_count' => 5, 'declined_count' => 5, 'missing_revenue_rate_count' => 0],
    ['staff_id' => 4, 'staff_name' => 'D', 'estimate_count' => 10, 'accepted_revenue' => 500000000, 'accepted_count' => 2, 'declined_count' => 3, 'missing_revenue_rate_count' => 0],
], $config);

assert_same([1, 2, 2, 4], array_column($ranking['leaderboard'], 'rank'), 'Đồng điểm phải dùng standard competition ranking 1,2,2,4');
assert_same([4, 4, 4, 4], array_column($ranking['leaderboard'], 'total_ranked_staff'), 'Mỗi dòng phải mang tổng cohort');

$provisional = $calculator->calculate_leaderboard([[
    'staff_id'                  => 20,
    'staff_name'                => 'Provisional Staff',
    'estimate_count'            => 3,
    'accepted_revenue'          => 0,
    'accepted_count'            => 1,
    'declined_count'            => 0,
    'missing_revenue_rate_count'=> 1,
]], $config);

assert_true($provisional['leaderboard'][0]['is_provisional'], 'Thiếu tỷ giá hoặc mẫu nhỏ phải tạo điểm tạm tính');
assert_true(in_array('missing_revenue_rate', $provisional['leaderboard'][0]['data_quality_flags'], true), 'Phải trả cờ missing_revenue_rate');
assert_true(in_array('insufficient_closed_quotes', $provisional['leaderboard'][0]['data_quality_flags'], true), 'Phải trả cờ insufficient_closed_quotes');

$staffProjection = $calculator->project_leaderboard($ranking['leaderboard'], false, 3);
assert_same(1, count($staffProjection), 'Projection Staff chỉ được trả một dòng cá nhân');
assert_same(3, $staffProjection[0]['staff_id'], 'Projection Staff phải giữ đúng dòng người đăng nhập');
assert_same(2, $staffProjection[0]['rank'], 'Hạng Staff phải được giữ từ cohort trước projection');
assert_same(4, $staffProjection[0]['total_ranked_staff'], 'Tổng cohort phải được giữ sau projection');
assert_true(!array_key_exists('email', $staffProjection[0]), 'Projection Staff không được chứa email');
assert_true(!array_key_exists('quote_score', $staffProjection[0]), 'Projection Staff không được chứa điểm thành phần');
assert_true(!array_key_exists('closed_count', $staffProjection[0]), 'Projection Staff không được chứa raw decision counts');
assert_true(!array_key_exists('data_quality_flags', $staffProjection[0]), 'Projection Staff chỉ được nhận cờ provisional tổng hợp');

$adminProjection = $calculator->project_leaderboard($ranking['leaderboard'], true, 3);
assert_same(4, count($adminProjection), 'Projection Admin phải giữ toàn bộ cohort');
assert_true($adminProjection[0]['can_open_details'], 'Admin phải mở được drawer mọi dòng');

$notConfigured = $calculator->calculate_leaderboard($ranking['leaderboard'], array_merge($config, [
    'revenue_target' => '',
]));
assert_same('not_configured', $notConfigured['status'], 'Thiếu target theo kỳ phải trả trạng thái not_configured');
assert_same(null, $notConfigured['leaderboard'][0]['rank'], 'Không được tạo hạng khi cấu hình chưa đầy đủ');

$missingStaffProjection = $calculator->project_leaderboard($ranking['leaderboard'], false, 999);
assert_same([], $missingStaffProjection, 'Projection Staff không được rò rỉ dòng khác khi người dùng không thuộc cohort');

fwrite(STDOUT, "PASS: Performance Score calculator\n");
