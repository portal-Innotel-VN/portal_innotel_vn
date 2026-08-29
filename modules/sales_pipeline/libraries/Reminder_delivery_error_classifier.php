<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Reminder_delivery_error_classifier
{
    private $sanitizer;

    public function __construct(?Reminder_delivery_error_sanitizer $sanitizer = null)
    {
        $this->sanitizer = $sanitizer ?: new Reminder_delivery_error_sanitizer();
    }

    public function classify($rawError, array $systemBccRecipients = [])
    {
        $rawError = (string) $rawError;
        $normalized = strtolower($rawError);
        $class = 'transient_transport';
        $code = 'smtp_transient_transport';
        $retryable = true;
        $breakLoop = false;

        if (preg_match('/(?:\b535\b|5\.7\.8|authentication (?:failed|credentials)|invalid credentials)/i', $rawError)) {
            $class = 'authentication_configuration';
            $code = 'smtp_authentication_failed';
            $retryable = false;
            $breakLoop = true;
        } elseif ($this->isSystemBccOverQuota($normalized, $systemBccRecipients)) {
            $class = 'system_bcc_over_quota';
            $code = 'smtp_system_bcc_over_quota';
            $retryable = false;
        } elseif (preg_match('/(?:rate[ -]?limit|too many (?:messages|connections|recipients)|throttl|sending quota|4\.7\.0)/i', $rawError)) {
            $class = 'rate_limited';
            $code = 'smtp_rate_limited';
            $retryable = true;
            $breakLoop = true;
        } elseif (preg_match('/(?:after DATA|during DATA|outcome unknown|connection reset after|timed out after message)/i', $rawError)) {
            $class = 'unknown_outcome';
            $code = 'smtp_unknown_outcome';
            $retryable = true;
        } elseif (preg_match('/(?:\b550\b|\b551\b|\b553\b|5\.1\.1|user unknown|recipient.*(?:invalid|rejected|not exist))/i', $rawError)) {
            $class = 'permanent_recipient';
            $code = 'smtp_permanent_recipient';
            $retryable = false;
        } elseif (!preg_match('/(?:\b4\d\d\b|timeout|timed out|connection|temporar|network|unavailable)/i', $rawError)) {
            $class = 'unknown_outcome';
            $code = 'smtp_unknown_outcome';
        }

        return [
            'class' => $class,
            'code' => $code,
            'retryable' => $retryable,
            'break_loop' => $breakLoop,
            'safe_error' => $this->sanitizer->sanitize($rawError),
        ];
    }

    private function isSystemBccOverQuota($normalizedError, array $systemBccRecipients)
    {
        if (!preg_match('/(?:\b552\b|5\.2\.2|mailbox (?:is )?full|over quota)/i', $normalizedError)) {
            return false;
        }
        foreach ($systemBccRecipients as $recipient) {
            $recipient = strtolower(trim((string) $recipient));
            if ($recipient !== '' && strpos($normalizedError, $recipient) !== false) {
                return true;
            }
        }

        return false;
    }
}
