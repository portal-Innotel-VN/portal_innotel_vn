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
    'response_target'   => 90,
    'component_cap'     => 120,
    'min_closed_quotes' => 3,
];

// Test 1: Inactive when eligible_reminders is omitted/null (denominator 85)
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

assert_same(100.0, $perfect['leaderboard'][0]['performance_score'], 'Đạt đúng ba target phải nhận 100 điểm khi reminder inactive');
assert_same(23.5294, $perfect['leaderboard'][0]['effective_weights']['quote_score'], 'Trọng số Báo giá phải được quy đổi trên tổng 85');
assert_same('inactive', $perfect['leaderboard'][0]['component_status']['reminder_response'], 'Điểm phản hồi phải inactive khi không có metric reminder');

// Test 2: not_applicable when eligible_reminders == 0 (denominator 85)
$notApplicable = $calculator->calculate_leaderboard([[
    'staff_id'                  => 10,
    'staff_name'                => 'Not Applicable Staff',
    'email'                     => 'na@example.test',
    'estimate_count'            => 20,
    'accepted_revenue'          => 1000000000,
    'accepted_count'            => 5,
    'declined_count'            => 5,
    'missing_revenue_rate_count'=> 0,
    'eligible_reminders'        => 0,
    'on_time_reminders'         => 0,
]], $config);

assert_same(100.0, $notApplicable['leaderboard'][0]['performance_score'], 'Đạt đúng ba target phải nhận 100 điểm khi reminder not_applicable');
assert_same('not_applicable', $notApplicable['leaderboard'][0]['component_status']['reminder_response'], 'Trạng thái reminder phải là not_applicable khi eligible == 0');
assert_same(null, $notApplicable['leaderboard'][0]['response_score'], 'Response score phải là null khi not_applicable');
assert_true(!in_array('insufficient_reminder_sample', $notApplicable['leaderboard'][0]['data_quality_flags'], true), 'Không gắn cờ insufficient_reminder_sample khi eligible == 0');

// Test 3: Active with 4 components (denominator 100)
$activeFour = $calculator->calculate_leaderboard([[
    'staff_id'                  => 10,
    'staff_name'                => 'Full 4 Component Staff',
    'email'                     => 'full@example.test',
    'estimate_count'            => 20,
    'accepted_revenue'          => 1000000000,
    'accepted_count'            => 5,
    'declined_count'            => 5,
    'missing_revenue_rate_count'=> 0,
    'eligible_reminders'        => 10,
    'on_time_reminders'         => 9, // 90% on-time -> 100 points on response_target = 90
]], $config);

assert_same(100.0, $activeFour['leaderboard'][0]['quote_score'], 'Quote score đạt 100');
assert_same(100.0, $activeFour['leaderboard'][0]['accepted_revenue_score'], 'Revenue score đạt 100');
assert_same(100.0, $activeFour['leaderboard'][0]['acceptance_score'], 'Acceptance score đạt 100');
assert_same(100.0, $activeFour['leaderboard'][0]['response_score'], 'Response score đạt 100');
assert_same(100.0, $activeFour['leaderboard'][0]['performance_score'], 'Tổng điểm 4 thành phần trên mẫu số 100 phải là 100');
assert_same('active', $activeFour['leaderboard'][0]['component_status']['reminder_response'], 'Trạng thái reminder phải active');
assert_same(15.0, $activeFour['leaderboard'][0]['effective_weights']['reminder_response'], 'Trọng số hiệu dụng reminder phải là 15%');

// Test 4: Small sample flag when 0 < eligible_reminders < 3
$smallSample = $calculator->calculate_leaderboard([[
    'staff_id'                  => 10,
    'staff_name'                => 'Small Sample Staff',
    'estimate_count'            => 20,
    'accepted_revenue'          => 1000000000,
    'accepted_count'            => 5,
    'declined_count'            => 5,
    'missing_revenue_rate_count'=> 0,
    'eligible_reminders'        => 2,
    'on_time_reminders'         => 2,
]], $config);

assert_true($smallSample['leaderboard'][0]['is_provisional'], 'Phải đánh dấu provisional khi mẫu nhắc nhở < 3');
assert_true(in_array('insufficient_reminder_sample', $smallSample['leaderboard'][0]['data_quality_flags'], true), 'Phải có cờ insufficient_reminder_sample');

// Test 5: Cap 120 Limit (eligible=10, on_time=10, response_target=80 -> raw=125 -> capped=120)
$customConfigCap = array_merge($config, ['response_target' => 80]);
$cappedResponse = $calculator->calculate_leaderboard([[
    'staff_id'                  => 11,
    'staff_name'                => 'Capped Staff',
    'estimate_count'            => 200,
    'accepted_revenue'          => 10000000000,
    'accepted_count'            => 20,
    'declined_count'            => 0,
    'missing_revenue_rate_count'=> 0,
    'eligible_reminders'        => 10,
    'on_time_reminders'         => 10,
]], $customConfigCap);

assert_same(120.0, $cappedResponse['leaderboard'][0]['response_score'], 'Response score 125 phải bị chặn tại 120');
assert_same(120.0, $cappedResponse['leaderboard'][0]['quote_score'], 'Quote score phải bị chặn tại 120');
assert_same(120.0, $cappedResponse['leaderboard'][0]['performance_score'], 'Tổng điểm không vượt trần khi mọi thành phần vượt target');

// Test 6: Mixed Cohort (Staff A with reminders on 100, Staff B without on 85)
$mixedCohort = $calculator->calculate_leaderboard([
    [
        'staff_id' => 1, 'staff_name' => 'A',
        'estimate_count' => 20, 'accepted_revenue' => 1000000000, 'accepted_count' => 5, 'declined_count' => 5,
        'missing_revenue_rate_count' => 0,
        'eligible_reminders' => 10, 'on_time_reminders' => 9, // response_score = 100 -> total = 100.0
    ],
    [
        'staff_id' => 2, 'staff_name' => 'B',
        'estimate_count' => 20, 'accepted_revenue' => 1000000000, 'accepted_count' => 5, 'declined_count' => 5,
        'missing_revenue_rate_count' => 0,
        'eligible_reminders' => 0, 'on_time_reminders' => 0, // not_applicable -> total = 100.0
    ],
], $config);

assert_same(100.0, $mixedCohort['leaderboard'][0]['performance_score'], 'Staff A trên mẫu số 100 đạt 100.0');
assert_same(100.0, $mixedCohort['leaderboard'][1]['performance_score'], 'Staff B trên mẫu số 85 đạt 100.0');
assert_same([1, 1], array_column($mixedCohort['leaderboard'], 'rank'), 'Đồng điểm 100 xếp đồng hạng 1');

// Test 7: Standard competition ranking
$ranking = $calculator->calculate_leaderboard([
    ['staff_id' => 1, 'staff_name' => 'A', 'estimate_count' => 24, 'accepted_revenue' => 1200000000, 'accepted_count' => 6, 'declined_count' => 4, 'missing_revenue_rate_count' => 0],
    ['staff_id' => 2, 'staff_name' => 'B', 'estimate_count' => 20, 'accepted_revenue' => 1000000000, 'accepted_count' => 5, 'declined_count' => 5, 'missing_revenue_rate_count' => 0],
    ['staff_id' => 3, 'staff_name' => 'C', 'estimate_count' => 20, 'accepted_revenue' => 1000000000, 'accepted_count' => 5, 'declined_count' => 5, 'missing_revenue_rate_count' => 0],
    ['staff_id' => 4, 'staff_name' => 'D', 'estimate_count' => 10, 'accepted_revenue' => 500000000, 'accepted_count' => 2, 'declined_count' => 3, 'missing_revenue_rate_count' => 0],
], $config);

assert_same([1, 2, 2, 4], array_column($ranking['leaderboard'], 'rank'), 'Đồng điểm phải dùng standard competition ranking 1,2,2,4');
assert_same([4, 4, 4, 4], array_column($ranking['leaderboard'], 'total_ranked_staff'), 'Mỗi dòng phải mang tổng cohort');

// Test 8: Provisional flags
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

// Test 9: Projection
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

// Test 10: Not configured
$notConfigured = $calculator->calculate_leaderboard($ranking['leaderboard'], array_merge($config, [
    'response_target' => '',
]));
assert_same('not_configured', $notConfigured['status'], 'Thiếu target response phải trả trạng thái not_configured');
assert_same(null, $notConfigured['leaderboard'][0]['rank'], 'Không được tạo hạng khi cấu hình chưa đầy đủ');

$missingStaffProjection = $calculator->project_leaderboard($ranking['leaderboard'], false, 999);
assert_same([], $missingStaffProjection, 'Projection Staff không được rò rỉ dòng khác khi người dùng không thuộc cohort');

// Test 11: Dynamic Weight Synchronization from standard_weights
$customWeightsCalc = new Performance_score_calculator();
$customWeightsCalc->set_standard_weights([
    'quote_score'            => 10.0,
    'accepted_revenue_score' => 50.0,
    'acceptance_score'       => 20.0,
    'reminder_response'      => 20.0,
]);
$customWeightResult = $customWeightsCalc->calculate_leaderboard([[
    'staff_id'                  => 10,
    'staff_name'                => 'Custom Weight Staff',
    'estimate_count'            => 20, // 100 points
    'accepted_revenue'          => 1000000000, // 100 points
    'accepted_count'            => 5,
    'declined_count'            => 5, // 100 points
    'missing_revenue_rate_count'=> 0,
    'eligible_reminders'        => 10,
    'on_time_reminders'         => 9, // 100 points
]], $config);

assert_same(10.0, $customWeightResult['leaderboard'][0]['effective_weights']['quote_score'], 'Effective weight quote must be 10%');
assert_same(50.0, $customWeightResult['leaderboard'][0]['effective_weights']['accepted_revenue_score'], 'Effective weight revenue must be 50%');
assert_same(20.0, $customWeightResult['leaderboard'][0]['effective_weights']['acceptance_score'], 'Effective weight acceptance must be 20%');
assert_same(20.0, $customWeightResult['leaderboard'][0]['effective_weights']['reminder_response'], 'Effective weight reminder must be 20%');
assert_same(100.0, $customWeightResult['leaderboard'][0]['performance_score'], 'Calculated score must dynamically follow custom weights');

fwrite(STDOUT, "PASS: Performance Score calculator\n");

