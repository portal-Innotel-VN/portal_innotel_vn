<?php

define('BASEPATH', __DIR__);

$moduleRoot = dirname(__DIR__);
require_once $moduleRoot . '/libraries/Reminder_delivery_error_sanitizer.php';
require_once $moduleRoot . '/libraries/Reminder_delivery_error_classifier.php';

$classifier = new Reminder_delivery_error_classifier(new Reminder_delivery_error_sanitizer());
$fixtures = [
    ['535 5.7.8 Authentication credentials invalid for crm@example.test', [], 'authentication_configuration', 'smtp_authentication_failed'],
    ['421 4.7.0 Too many messages, rate limit exceeded', [], 'rate_limited', 'smtp_rate_limited'],
    ['451 4.4.2 Connection timed out', [], 'transient_transport', 'smtp_transient_transport'],
    ['550 5.1.1 Recipient user@example.test does not exist', [], 'permanent_recipient', 'smtp_permanent_recipient'],
    ['552 5.2.2 Mailbox full: archive@example.test', ['archive@example.test'], 'system_bcc_over_quota', 'smtp_system_bcc_over_quota'],
    ['Connection reset after DATA; outcome unknown', [], 'unknown_outcome', 'smtp_unknown_outcome'],
];
foreach ($fixtures as $fixture) {
    $result = $classifier->classify($fixture[0], $fixture[1]);
    if ($result['class'] !== $fixture[2] || $result['code'] !== $fixture[3]) {
        fwrite(STDERR, "FAIL: classifier mismatch for {$fixture[2]}\n");
        exit(1);
    }
    if (strpos($result['safe_error'], '@') !== false || strpos($result['safe_error'], 'crm@example.test') !== false) {
        fwrite(STDERR, "FAIL: sanitized classifier result retained an email address\n");
        exit(1);
    }
}

$secret = "AUTH LOGIN\r\ndXNlckBleGFtcGxlLnRlc3Q=\r\nc2VjcmV0LXZhbHVl\r\n"
    . "Password: secret-value Token=abc123 smtp://user:pass@192.0.2.10:587\r\n"
    . "To: customer@example.test\r\nSubject: Confidential quotation\r\n";
$safe = (new Reminder_delivery_error_sanitizer())->sanitize($secret);
foreach (['secret-value', 'abc123', 'dXNlckBleGFtcGxlLnRlc3Q=', 'customer@example.test', '192.0.2.10', 'Confidential quotation'] as $sensitive) {
    if (strpos($safe, $sensitive) !== false) {
        fwrite(STDERR, "FAIL: sanitizer retained sensitive SMTP material\n");
        exit(1);
    }
}
if (strlen($safe) > 500) {
    fwrite(STDERR, "FAIL: sanitized error exceeds storage bound\n");
    exit(1);
}

$engine = file_get_contents($moduleRoot . '/libraries/Reminder_engine.php');
foreach (['openAuthenticationCircuit(', "'open_authentication'", 'deferDueEmails(',
          'reminder_delivery_error_classifier->classify('] as $needle) {
    if (strpos($engine, $needle) === false) {
        fwrite(STDERR, "FAIL: engine error handling missing {$needle}\n");
        exit(1);
    }
}
if (strpos($engine, "get_option('smtp_password')") !== false) {
    fwrite(STDERR, "FAIL: reminder engine must not read SMTP credentials\n");
    exit(1);
}

fwrite(STDOUT, "PASS: SMTP error classification, sanitization and circuit contract\n");
