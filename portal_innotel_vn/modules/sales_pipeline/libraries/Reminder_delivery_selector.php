<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Reminder_delivery_selector
{
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function expireStaleEmails(DateTimeImmutable $now)
    {
        $timestamp = $now->format('Y-m-d H:i:s');
        $this->CI->db->query('UPDATE `' . db_prefix() . 'sales_pipeline_reminder_deliveries`'
            . " SET `status`='expired', `expired_at`=?, `next_retry_at`=NULL,"
            . " `last_error_code`='reminder_validity_window_elapsed', `last_error_class`='expired',"
            . " `last_error`='Reminder validity window elapsed', `updated_at`=?"
            . " WHERE `channel`='email' AND `status` IN ('pending','failed')"
            . ' AND `expires_at` IS NOT NULL AND `expires_at`<=?',
            [$timestamp, $timestamp, $timestamp]);

        return (int) $this->CI->db->affected_rows();
    }

    public function due($channel, $limit, $maxAttempts, DateTimeImmutable $now)
    {
        if (!in_array($channel, ['crm', 'email', 'whatsapp'], true)) {
            return [];
        }
        $limit = max(1, min(100, (int) $limit));
        $maxAttempts = max(1, (int) $maxAttempts);
        $timestamp = $now->format('Y-m-d H:i:s');
        $expirationSql = $channel === 'email'
            ? ' AND d.expires_at IS NOT NULL AND d.expires_at>?' : '';
        $params = [$channel, $maxAttempts, $timestamp];
        if ($channel === 'email') {
            $params[] = $timestamp;
        }

        return $this->CI->db->query('SELECT d.*, r.title, r.message, r.entity_type, r.entity_id,'
            . ' r.pipeline_id, r.rule_code, r.staff_id, r.severity, r.checkpoint, r.response_required,'
            . ' r.snapshot_json, r.sent_at AS reminder_sent_at'
            . ' FROM `' . db_prefix() . 'sales_pipeline_reminder_deliveries` d'
            . ' JOIN `' . db_prefix() . 'sales_pipeline_reminders_log` r ON r.id=d.reminder_id'
            . " WHERE d.channel=? AND d.status IN ('pending','failed') AND d.attempt_count<?"
            . ' AND (d.next_retry_at IS NULL OR d.next_retry_at<=?)'
            . " AND (d.last_error_code IS NULL OR d.last_error_code NOT IN ('whatsapp_delivery_uncertain', 'whatsapp_reconcile_unverified'))"
            . $expirationSql
            . " ORDER BY CASE WHEN d.status='pending' THEN 0 ELSE 1 END,"
            . ' d.next_retry_at ASC, d.id ASC LIMIT ' . $limit,
            $params)->result_array();
    }

    public function claim($deliveryId, $channel, $maxAttempts, DateTimeImmutable $now)
    {
        if (!in_array($channel, ['crm', 'email', 'whatsapp'], true)) {
            return false;
        }
        $expirationSql = $channel === 'email'
            ? ' AND `expires_at` IS NOT NULL AND `expires_at`>?' : '';
        $params = [$now->format('Y-m-d H:i:s'), (int) $deliveryId, $channel, max(1, (int) $maxAttempts)];
        if ($channel === 'email') {
            $params[] = $now->format('Y-m-d H:i:s');
        }
        $this->CI->db->query('UPDATE `' . db_prefix() . 'sales_pipeline_reminder_deliveries`'
            . " SET `status`='processing', `updated_at`=?"
            . " WHERE `id`=? AND `channel`=? AND `status` IN ('pending','failed')"
            . ' AND `attempt_count`<?'
            . " AND (`last_error_code` IS NULL OR `last_error_code` NOT IN ('whatsapp_delivery_uncertain', 'whatsapp_reconcile_unverified'))"
            . $expirationSql, $params);

        return $this->CI->db->affected_rows() === 1;
    }

    public function dueUncertainWhatsApp($limit = 20)
    {
        $limit = max(1, min(100, (int) $limit));
        return $this->CI->db->query('SELECT d.*, r.title, r.message, r.entity_type, r.entity_id,'
            . ' r.pipeline_id, r.rule_code, r.staff_id, r.severity, r.checkpoint, r.response_required,'
            . ' r.snapshot_json, r.sent_at AS reminder_sent_at'
            . ' FROM `' . db_prefix() . 'sales_pipeline_reminder_deliveries` d'
            . ' JOIN `' . db_prefix() . 'sales_pipeline_reminders_log` r ON r.id=d.reminder_id'
            . " WHERE d.channel='whatsapp' AND d.status='failed' AND d.last_error_code='whatsapp_delivery_uncertain'"
            . ' ORDER BY COALESCE(d.updated_at, d.created_at) ASC, d.id ASC LIMIT ' . $limit)->result_array();
    }

    public function defer($deliveryId, $nextRetryAt, $code, $message = null)
    {
        $this->CI->db->where('id', (int) $deliveryId)->where('status', 'processing')
            ->update(db_prefix() . 'sales_pipeline_reminder_deliveries', [
                'status' => 'pending',
                'next_retry_at' => $nextRetryAt,
                'last_error_code' => (string) $code,
                'last_error_class' => 'delivery_budget',
                'last_error' => $message !== null ? (string) $message : 'Delivery budget is temporarily unavailable',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function cancel($deliveryId, $code, $message)
    {
        $this->CI->db->where('id', (int) $deliveryId)->where_in('status', ['processing', 'pending', 'failed'])
            ->update(db_prefix() . 'sales_pipeline_reminder_deliveries', [
                'status' => 'cancelled',
                'next_retry_at' => null,
                'last_error_code' => (string) $code,
                'last_error_class' => 'delivery_policy',
                'last_error' => (string) $message,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function deferDueEmails($nextRetryAt, $code)
    {
        $this->CI->db->query('UPDATE `' . db_prefix() . 'sales_pipeline_reminder_deliveries`'
            . ' SET `next_retry_at`=?, `last_error_code`=?, `last_error_class`=?, `last_error`=?, `updated_at`=NOW()'
            . " WHERE `channel`='email' AND `status` IN ('pending','failed')"
            . ' AND (`next_retry_at` IS NULL OR `next_retry_at`<?)', [
                $nextRetryAt,
                (string) $code,
                'rate_limited',
                'Email delivery is paused by the SMTP cooldown',
                $nextRetryAt,
            ]);
    }

    public function deferDueWhatsApp($nextRetryAt, $code, $message = null)
    {
        $this->CI->db->query('UPDATE `' . db_prefix() . 'sales_pipeline_reminder_deliveries`'
            . ' SET `next_retry_at`=?, `last_error_code`=?, `last_error_class`=?, `last_error`=?, `updated_at`=NOW()'
            . " WHERE `channel`='whatsapp' AND `status` IN ('pending','failed')"
            . " AND (last_error_code IS NULL OR last_error_code NOT IN ('whatsapp_delivery_uncertain', 'whatsapp_reconcile_unverified'))"
            . ' AND (`next_retry_at` IS NULL OR `next_retry_at`<?)', [
                $nextRetryAt,
                (string) $code,
                'rate_limited',
                $message !== null ? (string) $message : 'WhatsApp delivery is paused by rate limit quota',
                $nextRetryAt,
            ]);
    }
}
