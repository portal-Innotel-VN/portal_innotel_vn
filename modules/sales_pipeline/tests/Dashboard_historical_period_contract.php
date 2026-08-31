<?php

define('BASEPATH', __DIR__);

require_once dirname(__DIR__) . '/includes/dashboard_period.php';

$today = '2026-08-31';
$cases = [
    ['this_week', '2026-05-13', '2026-05-11', '2026-05-17'],
    ['this_month', '2024-02-12', '2024-02-01', '2024-02-29'],
    ['this_quarter', '2025-11-08', '2025-10-01', '2025-12-31'],
    ['this_year', '2023-06-10', '2023-01-01', '2023-12-31'],
];

foreach ($cases as $case) {
    list($period, $anchor, $expectedStart, $expectedEnd) = $case;
    $resolved = sales_pipeline_resolve_dashboard_period($period, $anchor, $today);
    if ($resolved['key'] !== $period
        || $resolved['anchor'] !== $anchor
        || $resolved['start'] !== $expectedStart
        || $resolved['end'] !== $expectedEnd) {
        fwrite(STDERR, "FAIL: Historical range mismatch for {$period}\n");
        exit(1);
    }
}

$future = sales_pipeline_resolve_dashboard_period('this_month', '2027-01-01', $today);
if ($future['anchor'] !== $today || $future['start'] !== '2026-08-01' || $future['end'] !== '2026-08-31') {
    fwrite(STDERR, "FAIL: Future period anchors must fall back to today\n");
    exit(1);
}

$invalid = sales_pipeline_resolve_dashboard_period('unexpected', 'not-a-date', $today);
if ($invalid['key'] !== 'this_month' || $invalid['anchor'] !== $today) {
    fwrite(STDERR, "FAIL: Invalid period input fallback mismatch\n");
    exit(1);
}

fwrite(STDOUT, "PASS: Dashboard historical period resolver\n");
