<?php

defined('BASEPATH') or define('BASEPATH', 'dummy');

function assert_true($condition, $message) {
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

function assert_equals($expected, $actual, $message) {
    if ($expected !== $actual) {
        $expStr = var_export($expected, true);
        $actStr = var_export($actual, true);
        throw new RuntimeException("Assertion failed: {$message} (Expected {$expStr}, got {$actStr})");
    }
}

$lockFile = __DIR__ . '/../libraries/Finance_lock_guard.php';
$rateFile = __DIR__ . '/../libraries/Authoritative_exchange_rate_provider.php';

assert_true(file_exists($lockFile), 'Finance_lock_guard.php must exist');
assert_true(file_exists($rateFile), 'Authoritative_exchange_rate_provider.php must exist');

require_once $lockFile;
require_once $rateFile;

assert_true(class_exists('Finance_lock_guard'), 'Finance_lock_guard class must exist');
assert_true(class_exists('Authoritative_exchange_rate_provider'), 'Authoritative_exchange_rate_provider class must exist');

// 1. Finance Lock Guard tests
$lockGuard = new Finance_lock_guard();

$unlockedEntity = ['id' => 10, 'is_finance_locked' => 0];
$lockedEntity = [
    'id' => 11,
    'is_finance_locked' => 1,
    'finance_locked_at' => '2026-09-01 10:00:00',
    'finance_locked_by' => 1,
    'finance_approval_reference' => 'FIN-LOCK-001',
    'finance_lock_reason' => 'Quarterly books closed',
];

assert_true($lockGuard->can_modify($unlockedEntity), 'Unlocked entity can be modified');
assert_true(!$lockGuard->can_modify($lockedEntity), 'Locked entity cannot be modified');

$checkResult = $lockGuard->check_can_modify($lockedEntity);
assert_equals(false, $checkResult['allowed'], 'Locked entity check must return allowed = false');
assert_equals('finance_locked', $checkResult['reason_code'], 'Reason code must be finance_locked');

// 2. Authoritative Exchange Rate Provider tests
$provider = new Authoritative_exchange_rate_provider();

// 2a. Base currency to base currency is always 1.0 without DB query
$baseRate = $provider->resolve_rate([
    'source_currency_id' => 1,
    'base_currency_id'   => 1,
    'target_date'        => '2026-09-02',
]);
assert_equals(1.0, (float) $baseRate['rate'], 'Base currency rate must be strictly 1.0');
assert_equals('base_identity', $baseRate['source'], 'Base rate source must be base_identity');

// 2b. In-memory approved rate lookup: latest effective_date <= target_date
$approvedRates = [
    [
        'source_currency_id' => 2, // USD
        'base_currency_id'   => 1, // VND
        'effective_date'     => '2026-08-01',
        'exchange_rate_to_base' => 25000.0,
        'rate_unit'          => 'base_currency_per_source_currency',
        'is_active'          => 1,
        'approved_at'        => '2026-08-01 08:00:00',
    ],
    [
        'source_currency_id' => 2,
        'base_currency_id'   => 1,
        'effective_date'     => '2026-09-01',
        'exchange_rate_to_base' => 25500.0,
        'rate_unit'          => 'base_currency_per_source_currency',
        'is_active'          => 1,
        'approved_at'        => '2026-09-01 08:00:00',
    ],
];

// Target date 2026-08-15 -> must pick 2026-08-01 rate (25000)
$rateAug = $provider->find_rate_in_dataset($approvedRates, 2, 1, '2026-08-15');
assert_equals(25000.0, (float) $rateAug['rate'], 'Must pick rate from 2026-08-01');

// Target date 2026-09-02 -> must pick 2026-09-01 rate (25500)
$rateSep = $provider->find_rate_in_dataset($approvedRates, 2, 1, '2026-09-02');
assert_equals(25500.0, (float) $rateSep['rate'], 'Must pick rate from 2026-09-01');

// Target date 2026-07-15 -> prior to any rate -> must return NULL (never fallback to 1.0)
$rateJul = $provider->find_rate_in_dataset($approvedRates, 2, 1, '2026-07-15');
assert_true($rateJul === null || $rateJul['rate'] === null, 'Prior date with no approved rate must return NULL');

echo "PASS: Finance lock and authoritative exchange rate provider contract\n";
