<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Reminder_delivery_operations
{
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function health()
    {
        $counts = ['crm' => [], 'email' => []];
        $rows = $this->CI->db->query('SELECT `channel`,`status`,COUNT(*) AS total FROM `'
            . db_prefix() . 'sales_pipeline_reminder_deliveries` GROUP BY `channel`,`status`')->result_array();
        foreach ($rows as $row) {
            if (isset($counts[$row['channel']])) {
                $counts[$row['channel']][$row['status']] = (int) $row['total'];
            }
        }

        $summary = $this->CI->db->query('SELECT'
            . " MIN(CASE WHEN `channel`='email' AND `status` IN ('pending','failed') THEN `created_at` END) AS oldest_email_pending_at,"
            . " MAX(CASE WHEN `channel`='email' AND `status`='sent' THEN `sent_at` END) AS last_email_sent_at,"
            . " SUM(CASE WHEN `channel`='email' AND `last_error_class`='rate_limited'"
            . ' AND `last_attempt_at`>=DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS rate_limited_24h,'
            . " MAX(CASE WHEN `last_error_class`='rate_limited' THEN `next_retry_at` END) AS cooldown_until"
            . ' FROM `' . db_prefix() . 'sales_pipeline_reminder_deliveries`')->row_array();

        $recent = $this->CI->db->query('SELECT d.`id`,d.`recipient_key`,d.`status`,d.`attempt_count`,'
            . 'd.`last_error_code`,d.`last_error_class`,d.`next_retry_at`,d.`last_attempt_at`,'
            . 'r.`rule_code`,r.`entity_type`,r.`entity_id`'
            . ' FROM `' . db_prefix() . 'sales_pipeline_reminder_deliveries` d'
            . ' JOIN `' . db_prefix() . 'sales_pipeline_reminders_log` r ON r.`id`=d.`reminder_id`'
            . ' WHERE d.`last_error_class` IS NOT NULL'
            . ' ORDER BY d.`last_attempt_at` DESC,d.`id` DESC LIMIT 20')->result_array();
        foreach ($recent as &$row) {
            $row['recipient_masked'] = $this->maskRecipient($row['recipient_key']);
            unset($row['recipient_key']);
        }
        unset($row);

        return [
            'counts' => $counts,
            'summary' => $summary ?: [],
            'recent_errors' => $recent,
            'circuit_state' => (string) get_option('sp_reminder_delivery_email_circuit_state'),
            'circuit_opened_at' => (string) get_option('sp_reminder_delivery_email_circuit_opened_at'),
            'circuit_reason' => (string) get_option('sp_reminder_delivery_email_circuit_reason'),
        ];
    }

    public function retry($deliveryId, $staffId)
    {
        $now = date('Y-m-d H:i:s');
        $delivery = $this->CI->db->select('*')
            ->where('id', (int) $deliveryId)
            ->get(db_prefix() . 'sales_pipeline_reminder_deliveries')
            ->row_array();

        if (!$delivery) {
            return false;
        }

        if (!in_array($delivery['status'], ['failed', 'cancelled'], true)) {
            return false;
        }

        if (!empty($delivery['expires_at']) && $delivery['expires_at'] <= $now) {
            return false;
        }

        $this->CI->load->library('sales_pipeline/Reminder_recipient_resolver');
        if ($this->CI->reminder_recipient_resolver->isV2Enabled()) {
            $reval = $this->CI->reminder_recipient_resolver->revalidateDeliveryRecipient($delivery);
            if (empty($reval['valid'])) {
                log_activity('Sales Pipeline reminder delivery #' . (int) $deliveryId
                    . ' retry rejected: recipient revalidation failed (' . ($reval['reason'] ?? 'unknown') . ')');
                return false;
            }
        }

        $this->CI->db->query('UPDATE `' . db_prefix() . 'sales_pipeline_reminder_deliveries`'
            . " SET `status`='pending',`attempt_count`=0,`next_retry_at`=NULL,`last_error`=NULL,"
            . ' `last_error_code`=NULL,`last_error_class`=NULL,`sent_at`=NULL,`updated_at`=?'
            . " WHERE `id`=? AND `channel`='email' AND `status` IN ('failed','cancelled')"
            . ' AND `expires_at` IS NOT NULL AND `expires_at`>?', [$now, (int) $deliveryId, $now]);
        $updated = $this->CI->db->affected_rows() === 1;
        if ($updated) {
            log_activity('Sales Pipeline reminder delivery #' . (int) $deliveryId
                . ' manually retried by Staff #' . (int) $staffId);
        }

        return $updated;
    }

    public function resumeCircuit($staffId)
    {
        if ((string) get_option('sp_reminder_delivery_email_circuit_state') === 'closed') {
            return false;
        }
        update_option('sp_reminder_delivery_email_circuit_state', 'closed');
        update_option('sp_reminder_delivery_email_circuit_opened_at', '');
        update_option('sp_reminder_delivery_email_circuit_reason', '');
        log_activity('Sales Pipeline reminder email circuit resumed by Staff #' . (int) $staffId);

        return true;
    }

    public function maskRecipient($recipient)
    {
        $recipient = trim((string) $recipient);
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return '[masked]';
        }
        [$local, $domain] = explode('@', $recipient, 2);

        return substr($local, 0, 1) . '***@' . $domain;
    }
}
