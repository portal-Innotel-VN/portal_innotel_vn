<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Reminder_delivery_rate_limiter
{
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function reserve($recipientCount, DateTimeImmutable $now, array $limits)
    {
        $recipientCount = max(1, (int) $recipientCount);
        if ($recipientCount > (int) $limits['max_recipients_per_message']) {
            return $this->denied('recipient_count_exceeds_limit', false, null);
        }

        $this->CI->db->trans_begin();
        $hour = $this->usageSince($now->modify('-60 minutes'));
        $day = $this->usageSince($now->modify('-24 hours'));
        $denied = $this->quotaDecision($hour, 1, $recipientCount,
            (int) $limits['hourly_messages'], (int) $limits['hourly_recipients'], 'hourly', $now, 60);
        if ($denied === null) {
            $denied = $this->quotaDecision($day, 1, $recipientCount,
                (int) $limits['daily_messages'], (int) $limits['daily_recipients'], 'daily', $now, 1440);
        }
        if ($denied !== null) {
            $this->CI->db->trans_rollback();
            return $denied;
        }

        $bucket = $now->setTime((int) $now->format('H'), (int) $now->format('i'), 0)->format('Y-m-d H:i:s');
        $this->CI->db->query('INSERT INTO `' . db_prefix() . 'sales_pipeline_reminder_delivery_rate_buckets`'
            . ' (`scope_key`,`bucket_minute`,`message_attempts`,`recipient_attempts`,`updated_at`)'
            . ' VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE'
            . ' `message_attempts`=`message_attempts`+VALUES(`message_attempts`),'
            . ' `recipient_attempts`=`recipient_attempts`+VALUES(`recipient_attempts`), `updated_at`=VALUES(`updated_at`)',
            ['default_smtp_account', $bucket, 1, $recipientCount, $now->format('Y-m-d H:i:s')]);
        if (!$this->CI->db->trans_status()) {
            $this->CI->db->trans_rollback();
            return $this->denied('rate_bucket_storage_unavailable', true, $now->modify('+5 minutes'));
        }
        $this->CI->db->trans_commit();

        return ['allowed' => true, 'retryable' => false, 'code' => null, 'next_retry_at' => null];
    }

    private function usageSince(DateTimeImmutable $since)
    {
        $rows = $this->CI->db->query('SELECT `message_attempts`,`recipient_attempts`,`bucket_minute`'
            . ' FROM `' . db_prefix() . 'sales_pipeline_reminder_delivery_rate_buckets`'
            . ' WHERE `scope_key`=? AND `bucket_minute`>? ORDER BY `bucket_minute` ASC FOR UPDATE',
            ['default_smtp_account', $since->format('Y-m-d H:i:s')])->result_array();

        $messages = 0;
        $recipients = 0;
        foreach ($rows as $row) {
            $messages += (int) $row['message_attempts'];
            $recipients += (int) $row['recipient_attempts'];
        }

        return [
            'message_attempts' => $messages,
            'recipient_attempts' => $recipients,
            'oldest_bucket' => $rows ? $rows[0]['bucket_minute'] : null,
        ];
    }

    private function quotaDecision(array $usage, $messages, $recipients, $messageLimit, $recipientLimit,
        $window, DateTimeImmutable $now, $minutes)
    {
        if ($usage['message_attempts'] + $messages > $messageLimit) {
            return $this->denied($window . '_message_quota_exhausted', true,
                $this->nextWindow($usage['oldest_bucket'], $now, $minutes));
        }
        if ($usage['recipient_attempts'] + $recipients > $recipientLimit) {
            return $this->denied($window . '_recipient_quota_exhausted', true,
                $this->nextWindow($usage['oldest_bucket'], $now, $minutes));
        }

        return null;
    }

    private function nextWindow($oldestBucket, DateTimeImmutable $now, $minutes)
    {
        if (!$oldestBucket) {
            return $now->modify('+5 minutes');
        }

        return (new DateTimeImmutable($oldestBucket, $now->getTimezone()))->modify('+' . ((int) $minutes) . ' minutes +1 second');
    }

    private function denied($code, $retryable, ?DateTimeImmutable $nextRetryAt = null)
    {
        return [
            'allowed' => false,
            'retryable' => (bool) $retryable,
            'code' => (string) $code,
            'next_retry_at' => $nextRetryAt ? $nextRetryAt->format('Y-m-d H:i:s') : null,
        ];
    }
}
