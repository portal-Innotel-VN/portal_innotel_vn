<?php

defined('BASEPATH') or define('BASEPATH', dirname(__DIR__));

require_once dirname(__DIR__) . '/libraries/Deal_bridge_calculator.php';

function assert_bridge_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, 'FAILED: ' . $message . ' expected=' . var_export($expected, true)
            . ' actual=' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$calculator = new Deal_bridge_calculator();

$locked = $calculator->calculate([], null, true);
assert_bridge_same('preserve', $locked['action'], 'manual lock preserves Deal');

$missingAccepted = $calculator->calculate([
    ['outcome' => 'accepted', 'decision_value_base' => '100.00'],
    ['outcome' => 'accepted', 'decision_value_base' => null],
], null, false);
assert_bridge_same('fail', $missingAccepted['action'], 'one missing accepted value blocks the whole sync');
assert_bridge_same('accepted_value_missing', $missingAccepted['reason'], 'missing accepted reason');

$accepted = $calculator->calculate([
    ['outcome' => 'accepted', 'decision_value_base' => '0.10'],
    ['outcome' => 'accepted', 'decision_value_base' => '0.20'],
    ['outcome' => 'pending', 'decision_value_base' => null],
], null, false);
assert_bridge_same('apply', $accepted['action'], 'accepted values can be applied');
assert_bridge_same('0.30', $accepted['deal_value'], 'accepted values use decimal addition');
assert_bridge_same('won', $accepted['status_intent'], 'accepted values make Deal won');

$pending = $calculator->calculate([
    ['outcome' => 'pending', 'is_primary' => 1, 'current_estimate_id' => 21],
], '260000000.00', false);
assert_bridge_same('260000000.00', $pending['deal_value'], 'pending Deal uses version base_total');
assert_bridge_same(21, $pending['estimate_id'], 'pending Deal links current estimate');

$pendingMissing = $calculator->calculate([
    ['outcome' => 'pending', 'is_primary' => 1, 'current_estimate_id' => 22],
], null, false);
assert_bridge_same('preserve', $pendingMissing['action'], 'missing pending rate preserves Deal');
assert_bridge_same('pending_base_total_missing', $pendingMissing['reason'], 'missing pending rate reason');

$overflow = $calculator->calculate([
    ['outcome' => 'accepted', 'decision_value_base' => '9999999999999.99'],
    ['outcome' => 'accepted', 'decision_value_base' => '0.01'],
], null, false);
assert_bridge_same('fail', $overflow['action'], 'DECIMAL(15,2) overflow fails');
assert_bridge_same('deal_value_overflow', $overflow['reason'], 'overflow reason');

$declined = $calculator->calculate([
    ['outcome' => 'declined', 'decision_value_base' => null],
], null, false);
assert_bridge_same('apply', $declined['action'], 'all declined can be applied');
assert_bridge_same('lost', $declined['status_intent'], 'all declined makes Deal lost');

$serviceSource = file_get_contents(dirname(__DIR__) . '/libraries/Estimate_revision_service.php');
$syncStart = strpos($serviceSource, 'public function sync_deal($dealId)');
$syncEnd = strpos($serviceSource, 'public function record_event(', $syncStart);
$syncSource = substr($serviceSource, $syncStart, $syncEnd - $syncStart);
assert_bridge_same(true, strpos($syncSource, "select('base_total')") !== false, 'sync_deal reads Version base_total');
assert_bridge_same(false, strpos($syncSource, "select('total')") !== false, 'sync_deal never reads raw Estimate total');
assert_bridge_same(true, strpos($syncSource, 'Deal_bridge_calculator') !== false, 'sync_deal delegates to the guarded calculator');

fwrite(STDOUT, "PASS: Deal bridge calculator\n");
