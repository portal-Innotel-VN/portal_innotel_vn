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

require_once __DIR__ . '/../libraries/Quote_first_sent_service.php';

$service = new Quote_first_sent_service(null);

// 1. Test Source Ranks
assert_equals(4, $service->get_source_rank('activity_email_sent'), 'activity_email_sent must be Rank 4');
assert_equals(3, $service->get_source_rank('estimate_datesend'), 'estimate_datesend must be Rank 3');
assert_equals(2, $service->get_source_rank('activity_status_sent'), 'activity_status_sent must be Rank 2');
assert_equals(1, $service->get_source_rank('inferred_estimate_date'), 'inferred_estimate_date must be Rank 1');
assert_equals(1, $service->get_source_rank('legacy_inferred_estimate_date'), 'legacy_inferred_estimate_date must be Rank 1');
assert_equals(0, $service->get_source_rank(null), 'null source must be Rank 0');
assert_equals(0, $service->get_source_rank('unknown_source'), 'unknown source must be Rank 0');

// 2. Test Evidence-Rank Decision Matrix
// Rule 1: Current is NULL (Rank 0) -> Any candidate Rank >= 1 replaces
assert_true(
    $service->evaluate_evidence_transition(null, null, 'estimate_datesend', '2026-09-03 12:13:40'),
    'Null current must be replaced by real evidence'
);
assert_true(
    $service->evaluate_evidence_transition(null, null, 'inferred_estimate_date', '2026-09-03 09:00:00'),
    'Null current can be replaced by inferred evidence'
);
assert_equals(
    false,
    $service->evaluate_evidence_transition(null, null, null, null),
    'Null candidate cannot replace Null current'
);

// Rule 2: Current is Inferred (Rank 1) -> Real evidence (Rank >= 2) trumps inferred even if timestamp is later
assert_true(
    $service->evaluate_evidence_transition('inferred_estimate_date', '2026-09-03 09:00:00', 'estimate_datesend', '2026-09-03 12:13:40'),
    'Real datesend (12:13:40) must replace inferred timestamp (09:00:00)'
);
assert_true(
    $service->evaluate_evidence_transition('inferred_estimate_date', '2026-09-03 09:00:00', 'activity_email_sent', '2026-09-03 15:00:00'),
    'Real email activity must replace inferred timestamp'
);
assert_true(
    $service->evaluate_evidence_transition('inferred_estimate_date', '2026-09-03 09:00:00', 'activity_status_sent', '2026-09-03 11:00:00'),
    'Status 2 activity must replace inferred timestamp'
);
// Two inferred: keep earliest
assert_true(
    $service->evaluate_evidence_transition('inferred_estimate_date', '2026-09-05 09:00:00', 'inferred_estimate_date', '2026-09-02 09:00:00'),
    'Earlier inferred can replace later inferred'
);
assert_equals(
    false,
    $service->evaluate_evidence_transition('inferred_estimate_date', '2026-09-02 09:00:00', 'inferred_estimate_date', '2026-09-05 09:00:00'),
    'Later inferred cannot replace earlier inferred'
);

// Rule 3: Current has Real Evidence (Rank >= 2)
// 3a. Stronger evidence replaces if timestamp is valid (earlier or equal)
assert_true(
    $service->evaluate_evidence_transition('activity_status_sent', '2026-09-03 12:13:40', 'activity_email_sent', '2026-09-03 12:13:40'),
    'Stronger email sent activity replaces status sent activity at same timestamp'
);
// 3b. Same rank: keep earliest timestamp
assert_true(
    $service->evaluate_evidence_transition('estimate_datesend', '2026-09-05 10:00:00', 'estimate_datesend', '2026-09-03 12:13:40'),
    'Earlier datesend replaces later datesend'
);
assert_equals(
    false,
    $service->evaluate_evidence_transition('estimate_datesend', '2026-09-03 12:13:40', 'estimate_datesend', '2026-09-05 10:00:00'),
    'Later datesend cannot replace earlier datesend'
);
assert_equals(
    false,
    $service->evaluate_evidence_transition('estimate_datesend', '2026-09-03 12:13:40', 'estimate_datesend', '2026-09-03 12:13:40'),
    'Identical datesend does not replace (idempotent)'
);
// 3c. Weaker evidence cannot replace stronger evidence
assert_equals(
    false,
    $service->evaluate_evidence_transition('estimate_datesend', '2026-09-03 12:13:40', 'inferred_estimate_date', '2026-09-01 09:00:00'),
    'Inferred evidence cannot replace real datesend even if timestamp is earlier'
);
assert_equals(
    false,
    $service->evaluate_evidence_transition('activity_email_sent', '2026-09-03 12:13:40', 'activity_status_sent', '2026-09-03 10:00:00'),
    'Status activity cannot replace email sent activity'
);

// 3. Test Safe Activity Parsing
$serializedFormStatus2 = serialize([
    '<old_status>1</old_status>',
    '<new_status>2</new_status>'
]);
$parsedStatus = $service->parse_activity_status_evidence('not_estimate_status_updated', $serializedFormStatus2);
assert_equals(2, $parsedStatus, 'Should safely parse new_status 2 from form update');

$serializedAdminMarked2 = serialize([
    '<status>2</status>'
]);
$parsedMarkedStatus = $service->parse_activity_status_evidence('estimate_activity_marked', $serializedAdminMarked2);
assert_equals(2, $parsedMarkedStatus, 'Should safely parse status 2 from admin marked activity');

$serializedStatus3 = serialize([
    '<old_status>2</old_status>',
    '<new_status>3</new_status>'
]);
$parsedOther = $service->parse_activity_status_evidence('not_estimate_status_updated', $serializedStatus3);
assert_equals(null, $parsedOther, 'Should return null for non-status-2 transitions');

// Test resilience against malformed or object payloads
$malformedPayload = 'O:8:"stdClass":0:{}';
$parsedObject = $service->parse_activity_status_evidence('not_estimate_status_updated', $malformedPayload);
assert_equals(null, $parsedObject, 'Must safely reject object injection');

// 4. Test Finalized Outcomes (estimate_accepted, invoiceid, draft-only)
assert_equals(2, $service->get_source_rank('estimate_accepted'), 'estimate_accepted must be Rank 2');
assert_equals(2, $service->get_source_rank('estimate_declined'), 'estimate_declined must be Rank 2');
assert_equals(2, $service->get_source_rank('estimate_expired'), 'estimate_expired must be Rank 2');

// Estimate with invoice converted
$estInvoiced = ['id' => 124, 'status' => 4, 'sent' => 0, 'datesend' => null, 'invoiceid' => 40, 'datecreated' => '2026-09-03 15:16:17'];
$evInvoiced = $service->extract_estimate_evidence($estInvoiced, []);
assert_equals('estimate_accepted', $evInvoiced['source'], 'Converted estimate must produce estimate_accepted evidence');
assert_equals('2026-09-03 15:16:17', $evInvoiced['occurred_at'], 'Occurred at should match datecreated or invoiced_date');

// Draft-only estimate (status 1, sent 0, datesend null)
$estDraft = ['id' => 121, 'status' => 1, 'sent' => 0, 'datesend' => null, 'invoiceid' => null, 'datecreated' => '2026-09-03 11:56:00'];
$evDraft = $service->extract_estimate_evidence($estDraft, []);
assert_equals(null, $evDraft['source'], 'Draft-only estimate must yield null source');
assert_equals(null, $evDraft['occurred_at'], 'Draft-only estimate must yield null occurred_at');

echo "PASS: Quote_first_sent_service unit test\n";
