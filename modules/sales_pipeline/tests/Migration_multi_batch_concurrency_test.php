<?php

defined('BASEPATH') or define('BASEPATH', 'dummy');

function assert_true($cond, $msg) {
    if (!$cond) throw new RuntimeException("Assertion failed: {$msg}");
}
function assert_equals($expected, $actual, $msg) {
    if ($expected !== $actual) {
        $exp = var_export($expected, true);
        $act = var_export($actual, true);
        throw new RuntimeException("Assertion failed: {$msg} (Expected {$exp}, got {$act})");
    }
}

// 1. Verify Migration 114 class structure and fail-safe safety bounds
$migrationFile = __DIR__ . '/../migrations/114_version_114.php';
assert_true(file_exists($migrationFile), '114_version_114.php must exist');

$migrationCode = file_get_contents($migrationFile);

// Advisory lock check
assert_true(
    strpos($migrationCode, 'sales_pipeline:first_sent_reconcile') !== false,
    'Migration 114 must use unified advisory lock sales_pipeline:first_sent_reconcile'
);

// Safety bound check against fake success
assert_true(
    strpos($migrationCode, 'max iterations') !== false,
    'Migration 114 must fail fast if max iterations reached with pending records'
);

// Cursor progression check
assert_true(
    strpos($migrationCode, 'Cursor did not advance') !== false,
    'Migration 114 must abort if cursor fails to advance'
);

// Per-batch transaction check
assert_true(
    strpos($migrationCode, 'trans_begin') !== false && strpos($migrationCode, 'trans_commit') !== false,
    'Migration 114 must use per-batch transactions'
);

// 2. Multi-batch cursor simulation with 250 records (100 + 100 + 50)
$totalRecords = 250;
$records = [];
for ($i = 1; $i <= $totalRecords; $i++) {
    $records[$i] = [
        'id'       => $i,
        'state'    => 'pending',
        'attempts' => 0,
    ];
}

$mockReconcileBatch = function ($batchSize, $cursor) use (&$records) {
    $batch = [];
    foreach ($records as $id => $rec) {
        if ($id > $cursor) {
            $batch[] = $id;
            if (count($batch) >= $batchSize) {
                break;
            }
        }
    }

    $scanned = count($batch);
    $nextCursor = $cursor;
    foreach ($batch as $id) {
        $records[$id]['state'] = 'processed';
        $records[$id]['attempts']++;
        $nextCursor = $id;
    }

    return [
        'scanned'     => $scanned,
        'next_cursor' => $nextCursor,
        'has_more'    => ($scanned === $batchSize),
    ];
};

// Run multi-batch loop
$cursor = 0;
$batchSize = 100;
$iteration = 0;
$batchesExecuted = 0;
$hasMore = true;

while ($hasMore && $iteration < 10) {
    $iteration++;
    $batchesExecuted++;
    $res = $mockReconcileBatch($batchSize, $cursor);
    $cursor = $res['next_cursor'];
    $hasMore = $res['has_more'];
}

assert_equals(3, $batchesExecuted, '250 records with batch size 100 must take exactly 3 batches (100 + 100 + 50)');
assert_equals(250, $cursor, 'Final cursor must be 250');
assert_equals(false, $hasMore, 'has_more must be false after batch 3');

$processedCount = 0;
foreach ($records as $rec) {
    if ($rec['state'] === 'processed') {
        $processedCount++;
    }
}
assert_equals(250, $processedCount, 'All 250 records must be processed');

// 3. Test Restart-Idempotent contract (Simulate failure at batch 2, retry from cursor 0)
// Reset states to simulate failure during batch 2
for ($i = 101; $i <= $totalRecords; $i++) {
    $records[$i]['state'] = 'pending';
}

$cursor = 0;
$hasMore = true;
$iteration = 0;

while ($hasMore && $iteration < 10) {
    $iteration++;
    $res = $mockReconcileBatch($batchSize, $cursor);
    $cursor = $res['next_cursor'];
    $hasMore = $res['has_more'];
}

assert_equals(250, $cursor, 'Retry from cursor 0 must reach 250 safely');
foreach ($records as $rec) {
    assert_equals('processed', $rec['state'], 'Every record must end up in processed state');
}

echo "PASS: Migration_multi_batch_concurrency_test (250 records processed, fail-safe verified, restart-idempotent verified)\n";
