<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * KISS rule evaluator + shared Reminder workflow.
 * Rules return plain arrays and never deliver directly.
 */
class Reminder_engine
{
    public static $currentEmailCC = null;

    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('staff_model');
    }

    public function process()
    {
        if ($this->option('sp_reminder_global_enabled') !== '1') {
            return;
        }

        $now = new DateTimeImmutable('now');
        if ($this->isWorkingDay($now)) {
            $this->processEstimatePeriods();
        }
        $this->processDeals();
        $this->processEstimateLifecycle();
        $this->dispatchPendingDeliveries();
    }

    private function processDeals()
    {
        if ($this->option('sp_reminder_deal_frequency_enabled') !== '1') {
            return;
        }
        $statuses = $this->CI->db->select('id')->where('is_won', 0)->where('is_lost', 0)
            ->get(db_prefix() . 'sales_pipeline_statuses')->result_array();
        $statusIds = array_map('intval', array_column($statuses, 'id'));
        if (!$statusIds) {
            return;
        }
        $this->CI->db->where('reminder_enabled', 1)->where_in('status', $statusIds)->group_start()
            ->where('last_reminder_sent IS NULL', null, false)
            ->or_where('DATEDIFF(NOW(), last_reminder_sent) >= reminder_frequency', null, false)->group_end();
        foreach ($this->CI->db->get(db_prefix() . 'sales_pipeline')->result_array() as $deal) {
            $staff = $this->activeStaff((int) $deal['staff_id']);
            if (!$staff) {
                continue;
            }
            $status = $this->CI->db->select('name')->where('id', (int) $deal['status'])
                ->get(db_prefix() . 'sales_pipeline_statuses')->row_array();
            $event = [
                'rule_code' => 'DEAL_FREQUENCY_REMINDER', 'entity_type' => 'deal',
                'entity_id' => (int) $deal['id'], 'pipeline_id' => (int) $deal['id'],
                'staff_id' => (int) $deal['staff_id'], 'period_key' => date('Y-m-d'),
                'checkpoint' => 'FREQUENCY', 'severity' => 'warning', 'response_required' => 1,
                'recipients' => ['staff'], 'channels' => $this->parseChannels('sp_reminder_deal_frequency_channels'),
                'dedupe_key' => 'DEAL_FREQUENCY_REMINDER:' . (int) $deal['staff_id'] . ':' . (int) $deal['id'] . ':' . date('Y-m-d'),
                'snapshot' => [
                    'deal_name' => $deal['deal_name'], 'customer_name' => $deal['customer_name'],
                    'deal_value' => (float) $deal['deal_value'], 'deal_date' => $deal['deal_date'],
                    'status_name' => $status['name'] ?? '', 'evaluated_at' => date('Y-m-d H:i:s'),
                ],
            ];
            if ($event['channels']) {
                $this->run($event);
            }
        }
    }

    private function processEstimatePeriods()
    {
        $now = new DateTimeImmutable('now');
        $staffRows = $this->CI->db->select('staffid')->where('active', 1)->get(db_prefix() . 'staff')->result_array();
        foreach ($staffRows as $row) {
            $staffId = (int) $row['staffid'];
            if (!staff_can('view', 'sales_pipeline', $staffId) && !staff_can('view_own', 'sales_pipeline', $staffId)) {
                continue;
            }
            $monthly = $this->monthlyEvent($staffId, $now);
            if ($monthly) {
                $this->run($monthly);
            } else {
                $daily = $this->dailyEvent($staffId, $now);
                if ($daily) {
                    $this->run($daily);
                }
            }
            $weekly = $this->weeklyEvent($staffId, $now);
            if ($weekly) {
                $this->run($weekly);
            }
        }
    }

    private function dailyEvent($staffId, DateTimeImmutable $now)
    {
        if ($this->option('sp_reminder_est_daily_enabled') !== '1'
            || $now->format('H:i') < $this->option('sp_reminder_est_daily_time')) {
            return null;
        }
        $actual = $this->validEstimateCount($staffId, $now->format('Y-m-d 00:00:00'), $now->modify('+1 day')->format('Y-m-d 00:00:00'));
        $required = (int) $this->option('sp_reminder_est_daily_threshold');
        return $actual < $required ? $this->periodEvent('ESTIMATE_DAILY_MIN_COUNT', $staffId, $now->format('Y-m-d'), 'FINAL', 'warning', [
            'actual_count' => $actual, 'required_count' => $required,
        ], ['staff'], $this->parseChannels('sp_reminder_est_daily_channels')) : null;
    }

    private function monthlyEvent($staffId, DateTimeImmutable $now)
    {
        if ($this->option('sp_reminder_est_monthly_enabled') !== '1'
            || $now->format('H:i') < $this->option('sp_reminder_est_monthly_time')) {
            return null;
        }
        $checkpoint = null; $required = 0;
        if ($this->checkpointDue($now, 10)) {
            $checkpoint = 'D10'; $required = (int) $this->option('sp_reminder_est_monthly_d10');
        } elseif ($this->checkpointDue($now, 20)) {
            $checkpoint = 'D20'; $required = (int) $this->option('sp_reminder_est_monthly_d20');
        } elseif ($this->lastWorkingDay($now)) {
            $checkpoint = 'FINAL'; $required = (int) $this->option('sp_reminder_est_monthly_final');
        }
        if (!$checkpoint) {
            return null;
        }
        $actual = $this->validEstimateCount($staffId, $now->modify('first day of this month')->format('Y-m-d 00:00:00'), $now->modify('first day of next month')->format('Y-m-d 00:00:00'));
        if ($actual >= $required) {
            return null;
        }
        return $this->periodEvent('ESTIMATE_MONTHLY_MIN_COUNT', $staffId, $now->format('Y-m'), $checkpoint, $checkpoint === 'FINAL' ? 'critical' : 'warning', [
            'actual_count' => $actual, 'required_count' => $required,
            'today_count' => $this->validEstimateCount($staffId, $now->format('Y-m-d 00:00:00'), $now->modify('+1 day')->format('Y-m-d 00:00:00')),
        ], ['staff'], $this->parseChannels('sp_reminder_est_monthly_channels'));
    }

    private function weeklyEvent($staffId, DateTimeImmutable $now)
    {
        $day = (int) $now->format('N');
        $time = $day === 3 ? $this->option('sp_reminder_est_weekly_midweek_time') : $this->option('sp_reminder_est_weekly_final_time');
        if ($this->option('sp_reminder_est_weekly_enabled') !== '1' || ($day !== 3 && $day !== 5) || $now->format('H:i') < $time) {
            return null;
        }
        $revenue = $this->acceptedRevenue($staffId, $now->modify('monday this week')->format('Y-m-d 00:00:00'), $now->modify('monday next week')->format('Y-m-d 00:00:00'));
        $target = (float) $this->option('sp_reminder_est_weekly_target');
        if ($revenue >= $target || ($day === 3 && $revenue > 0)) {
            return null;
        }
        $checkpoint = $day === 3 ? 'MIDWEEK' : 'FINAL';
        return $this->periodEvent('ESTIMATE_WEEKLY_MIN_REVENUE', $staffId, $now->format('o-\WW'), $checkpoint, $checkpoint === 'FINAL' ? 'critical' : 'warning', [
            'accepted_revenue' => $revenue, 'required_revenue' => $target,
        ], $checkpoint === 'FINAL' ? ['staff', 'manager'] : ['staff'], $this->parseChannels('sp_reminder_est_weekly_channels'));
    }

    private function periodEvent($rule, $staffId, $period, $checkpoint, $severity, array $snapshot, array $recipients = ['staff'], array $channels = [])
    {
        $snapshot['evaluated_at'] = date('Y-m-d H:i:s');
        if (!$channels) {
            return null;
        }
        return [
            'rule_code' => $rule, 'entity_type' => 'staff_estimate_period', 'entity_id' => null,
            'pipeline_id' => null, 'staff_id' => $staffId, 'period_key' => $period,
            'checkpoint' => $checkpoint, 'severity' => $severity, 'response_required' => 1,
            'recipients' => $recipients, 'channels' => $channels,
            'dedupe_key' => implode(':', [$rule, $staffId, $period, $checkpoint]), 'snapshot' => $snapshot,
        ];
    }

    private function validEstimateCount($staffId, $start, $end)
    {
        $sql = 'SELECT COUNT(*) total FROM `' . db_prefix() . 'sales_pipeline_estimate_groups` grp'
            . ' WHERE grp.owner_staff_id=? AND grp.datecreated>=? AND grp.datecreated<?'
            . ' AND EXISTS(SELECT 1 FROM `' . db_prefix() . 'sales_pipeline_estimate_versions` ev'
            . ' JOIN `' . db_prefix() . 'estimates` e ON e.id=ev.estimate_id'
            . ' WHERE ev.estimate_group_id=grp.id AND e.status IN (2,3,4,5))';
        $row = $this->CI->db->query($sql, [$staffId, $start, $end])->row_array();
        return (int) ($row['total'] ?? 0);
    }

    private function acceptedRevenue($staffId, $start, $end)
    {
        $row = $this->CI->db->select('COALESCE(SUM(decision_value_base),0) total', false)
            ->where('decision_owner_staff_id', $staffId)->where('outcome', 'accepted')
            ->where('decision_at>=', $start)->where('decision_at<', $end)
            ->where('decision_value_base IS NOT NULL', null, false)
            ->get(db_prefix() . 'sales_pipeline_estimate_groups')->row_array();
        return (float) ($row['total'] ?? 0);
    }

    private function checkpointDue(DateTimeImmutable $now, $day)
    {
        $date = $now->setDate((int) $now->format('Y'), (int) $now->format('m'), $day);
        while (!$this->isWorkingDay($date)) { $date = $date->modify('+1 day'); }
        return $date->format('Y-m-d') === $now->format('Y-m-d');
    }

    private function lastWorkingDay(DateTimeImmutable $now)
    {
        $date = $now->modify('last day of this month');
        while (!$this->isWorkingDay($date)) { $date = $date->modify('-1 day'); }
        return $date->format('Y-m-d') === $now->format('Y-m-d');
    }

    private function processEstimateLifecycle()
    {
        $rows = $this->CI->db->select('e.id,e.clientid,e.sale_agent,e.addedfrom,e.status,e.total,e.datecreated,e.datesend,e.expirydate,e.invoiceid,e.acceptance_date,c.company')
            ->from(db_prefix() . 'estimates e')->join(db_prefix() . 'clients c', 'c.userid=e.clientid', 'left')
            ->where_in('e.status', [1,2,3,4,5])->get()->result_array();
        foreach ($rows as $estimate) {
            $staffId = (int) $estimate['sale_agent'] ?: (int) $estimate['addedfrom'];
            if (!$this->activeStaff($staffId)) {
                continue;
            }
            $event = $this->lifecycleEvent($estimate, $staffId);
            if ($event) { $this->run($event); }
        }
    }

    private function lifecycleEvent(array $e, $staffId)
    {
        $today = date('Y-m-d');
        $rule = $severity = $reason = $since = null; $responseRequired = 1;
        if ($this->option('sp_reminder_lc_accepted_enabled') === '1' && (int) $e['status'] === 4 && empty($e['invoiceid'])) {
            $rule = 'ESTIMATE_ACCEPTED_NOT_INVOICED'; $severity = 'critical';
            $reason = _l('sales_pipeline_estimate_risk_accepted_not_invoiced');
            $since = $e['acceptance_date'] ?: $this->outcomeTime($e['id'], 'accepted');
            $since = $since ?: $e['datecreated'];
        } elseif ($this->option('sp_reminder_lc_expired_enabled') === '1' && ((int) $e['status'] === 5 || ((int) $e['status'] === 2 && $e['expirydate'] && $e['expirydate'] < $today))) {
            $rule = 'ESTIMATE_EXPIRED'; $severity = 'critical'; $responseRequired = 0;
            $reason = _l('sales_pipeline_estimate_risk_expired'); $since = $e['expirydate'] ?: $e['datecreated'];
        } elseif ($this->option('sp_reminder_lc_declined_enabled') === '1' && (int) $e['status'] === 3) {
            $declined = $this->outcomeTime($e['id'], 'declined');
            if ($declined && strtotime($declined) >= strtotime('-' . (int) $this->option('sp_reminder_lc_declined_days') . ' days')) {
                $rule = 'ESTIMATE_DECLINED_RECENT'; $severity = 'warning';
                $reason = _l('sales_pipeline_estimate_risk_declined'); $since = $declined;
            }
        } elseif ($this->option('sp_reminder_lc_sent_enabled') === '1' && (int) $e['status'] === 2) {
            $sentOld = $e['datesend'] && strtotime($e['datesend']) <= strtotime('-' . (int) $this->option('sp_reminder_lc_sent_days') . ' days');
            $nearExpiry = $e['expirydate'] && $e['expirydate'] >= $today && $e['expirydate'] <= date('Y-m-d', strtotime('+' . (int) $this->option('sp_reminder_lc_sent_expiry_days') . ' days'));
            if ($sentOld || $nearExpiry) {
                $rule = 'ESTIMATE_SENT_NO_RESPONSE'; $severity = 'warning';
                $reason = _l('sales_pipeline_estimate_risk_sent_no_response'); $since = $sentOld ? $e['datesend'] : $e['expirydate'];
            }
        } elseif ($this->option('sp_reminder_lc_draft_enabled') === '1' && (int) $e['status'] === 1 && strtotime($e['datecreated']) <= strtotime('-' . (int) $this->option('sp_reminder_lc_draft_days') . ' days')) {
            $rule = 'ESTIMATE_DRAFT_TOO_LONG'; $severity = 'warning'; $responseRequired = 0;
            $reason = _l('sales_pipeline_estimate_risk_draft_too_long'); $since = $e['datecreated'];
        }
        if (!$rule || !$since) { return null; }

        $deal = $this->CI->db->select('id')->where('estimate_id', (int) $e['id'])
            ->get(db_prefix() . 'sales_pipeline')->row_array();
        $snapshot = [
            'estimate_id' => (int) $e['id'], 'estimate_number' => format_estimate_number($e['id']),
            'customer_id' => (int) $e['clientid'], 'customer_name' => (string) $e['company'],
            'estimate_status' => (int) $e['status'], 'status_label' => format_estimate_status($e['status'], '', false),
            'risk_reason' => $reason, 'condition_since' => $since, 'datecreated' => $e['datecreated'],
            'datesend' => $e['datesend'], 'expirydate' => $e['expirydate'], 'invoiceid' => $e['invoiceid'],
            'estimate_total' => (float) $e['total'], 'evaluated_at' => date('Y-m-d H:i:s'),
        ];
        $channels = $this->lifecycleChannels($rule);
        if (!$channels) {
            return null;
        }
        return [
            'rule_code' => $rule, 'entity_type' => 'estimate', 'entity_id' => (int) $e['id'],
            'pipeline_id' => $deal ? (int) $deal['id'] : null, 'staff_id' => $staffId,
            'period_key' => substr($since, 0, 10), 'checkpoint' => 'LIFECYCLE',
            'severity' => $severity, 'response_required' => $responseRequired,
            'recipients' => ['staff'], 'channels' => $channels,
            'dedupe_key' => implode(':', [$rule, $staffId, 'estimate', (int) $e['id'], preg_replace('/[^0-9]/', '', $since)]),
            'snapshot' => $snapshot,
        ];
    }

    private function outcomeTime($estimateId, $outcome)
    {
        $history = $this->CI->db->select('effective_at')->where('estimate_id', (int) $estimateId)
            ->where('new_outcome', $outcome)->order_by('effective_at', 'DESC')
            ->get(db_prefix() . 'sales_pipeline_estimate_outcome_history')->row_array();
        if (!empty($history['effective_at'])) { return $history['effective_at']; }
        $descriptions = $outcome === 'declined' ? ['estimate_activity_client_declined']
            : ['estimate_activity_client_accepted', 'estimate_activity_client_accepted_and_converted'];
        $activity = $this->CI->db->select('date')->where('rel_type', 'estimate')->where('rel_id', (int) $estimateId)
            ->where_in('description', $descriptions)->order_by('date', 'DESC')
            ->get(db_prefix() . 'sales_activity')->row_array();
        return $activity['date'] ?? null;
    }

    private function run(array $event)
    {
        // Persist rendered content before pending deliveries are committed. This
        // prevents a concurrent worker from ever sending a blank notification.
        return $this->createRecord($event, $this->render($event));
    }

    private function createRecord(array $event, array $content)
    {
        $data = [
            'pipeline_id' => $event['pipeline_id'], 'staff_id' => $event['staff_id'], 'reminder_type' => 'multi',
            'rule_code' => $event['rule_code'], 'entity_type' => $event['entity_type'], 'entity_id' => $event['entity_id'],
            'period_key' => $event['period_key'], 'checkpoint' => $event['checkpoint'], 'severity' => $event['severity'],
            'response_required' => $event['response_required'], 'title' => $content['title'], 'message' => $content['message'],
            'snapshot_json' => json_encode($event['snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'dedupe_key' => $event['dedupe_key'], 'created_at' => date('Y-m-d H:i:s'), 'sent_at' => null,
        ];
        $columns = array_map(function ($v) { return '`' . $v . '`'; }, array_keys($data));
        $this->CI->db->trans_begin();
        $this->CI->db->query('INSERT IGNORE INTO `' . db_prefix() . 'sales_pipeline_reminders_log` ('
            . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')', array_values($data));
        $isNewRecord = $this->CI->db->affected_rows() > 0;
        $record = $this->CI->db->where('dedupe_key', $event['dedupe_key'])
            ->get(db_prefix() . 'sales_pipeline_reminders_log')->row_array();
        if (!$record) { $this->CI->db->trans_rollback(); return null; }
        if ($isNewRecord) {
            $this->materializeDeliveries((int) $record['id'], $event);
        }
        if ($this->CI->db->trans_status() === false || !$this->CI->db->trans_commit()) {
            $this->CI->db->trans_rollback(); return null;
        }
        return $isNewRecord;
    }

    private function materializeDeliveries($reminderId, array $event)
    {
        $recipients = [['type' => 'staff', 'id' => $event['staff_id']]];
        if (in_array('manager', $event['recipients'], true)) {
            foreach ($this->CI->db->select('staffid')->where('active', 1)->where('admin', 1)
                ->where('staffid !=', $event['staff_id'])->get(db_prefix() . 'staff')->result_array() as $admin) {
                $recipients[] = ['type' => 'manager', 'id' => (int) $admin['staffid']];
            }
        }
        $isCCApplicable = $this->isManagerCCApplicable($event['severity'] ?? 'warning');
        foreach ($recipients as $recipient) {
            $staff = $this->activeStaff($recipient['id']);
            if (!$staff) { continue; }
            foreach ($event['channels'] as $channel) {
                if ($recipient['type'] === 'manager' && $channel === 'email' && $isCCApplicable) {
                    continue;
                }
                $key = $channel === 'email' ? trim((string) $staff->email) : (string) $recipient['id'];
                if ($key === '') { continue; }
                $this->CI->db->query('INSERT IGNORE INTO `' . db_prefix() . 'sales_pipeline_reminder_deliveries`'
                    . ' (`reminder_id`,`channel`,`recipient_type`,`recipient_staff_id`,`recipient_key`,`status`,`created_at`) VALUES (?,?,?,?,?,?,?)',
                    [$reminderId, $channel, $recipient['type'], $recipient['id'], $key, 'pending', date('Y-m-d H:i:s')]);
            }
        }
    }

    private function isManagerCCApplicable($severity = 'warning')
    {
        if ($this->option('sp_reminder_email_cc_manager_enabled') !== '1') {
            return false;
        }
        $scope = $this->option('sp_reminder_email_cc_scope') ?: 'all';
        if ($scope === 'critical_only' && $severity !== 'critical') {
            return false;
        }
        return true;
    }

    private function resolveManagerCCEmails($staffId, $severity = 'warning')
    {
        if (!$this->isManagerCCApplicable($severity)) {
            return [];
        }

        $staffObj = $this->CI->db->select('email')->where('staffid', (int) $staffId)->get(db_prefix() . 'staff')->row_array();
        $staffEmail = !empty($staffObj['email']) ? strtolower(trim((string) $staffObj['email'])) : '';

        $rawEmails = [];

        $admins = $this->CI->db->select('email')
            ->from(db_prefix() . 'staff')
            ->where('active', 1)
            ->where('admin', 1)
            ->where('staffid !=', (int) $staffId)
            ->get()->result_array();

        foreach ($admins as $admin) {
            $rawEmails[] = trim((string) $admin['email']);
        }

        if (empty($rawEmails)) {
            $fallback = (string) $this->option('sp_reminder_manager_fallback_emails');
            if ($fallback !== '') {
                foreach (explode(',', $fallback) as $raw) {
                    $rawEmails[] = trim($raw);
                }
            }
        }

        $cleanEmails = [];
        foreach ($rawEmails as $email) {
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if ($staffEmail !== '' && strtolower($email) === $staffEmail) {
                continue;
            }
            $cleanEmails[] = $email;
        }

        return array_values(array_unique($cleanEmails));
    }

    private function render(array $event)
    {
        $titles = [
            'DEAL_FREQUENCY_REMINDER' => 'sales_pipeline_reminder_deal',
            'ESTIMATE_DAILY_MIN_COUNT' => 'sales_pipeline_estimate_reminder_daily_title',
            'ESTIMATE_MONTHLY_MIN_COUNT' => 'sales_pipeline_estimate_reminder_monthly_title',
            'ESTIMATE_WEEKLY_MIN_REVENUE' => 'sales_pipeline_estimate_reminder_weekly_title',
            'ESTIMATE_DRAFT_TOO_LONG' => 'sales_pipeline_estimate_lifecycle_draft_title',
            'ESTIMATE_SENT_NO_RESPONSE' => 'sales_pipeline_estimate_lifecycle_sent_title',
            'ESTIMATE_DECLINED_RECENT' => 'sales_pipeline_estimate_lifecycle_declined_title',
            'ESTIMATE_EXPIRED' => 'sales_pipeline_estimate_lifecycle_expired_title',
            'ESTIMATE_ACCEPTED_NOT_INVOICED' => 'sales_pipeline_estimate_lifecycle_accepted_title',
        ];
        $s = $event['snapshot'];
        if ($event['entity_type'] === 'estimate') {
            $message = _l('sales_pipeline_estimate_lifecycle_message', [$s['estimate_number'], $s['customer_name'], $s['risk_reason']]);
        } elseif ($event['entity_type'] === 'deal') {
            $message = _l('sales_pipeline_deal_frequency_message', [$s['deal_name'], $s['customer_name']]);
        } elseif ($event['rule_code'] === 'ESTIMATE_WEEKLY_MIN_REVENUE') {
            $message = _l('sales_pipeline_estimate_weekly_message', [number_format($s['accepted_revenue'], 0, ',', '.'), number_format($s['required_revenue'], 0, ',', '.')]);
        } else {
            $message = _l('sales_pipeline_estimate_count_message', [$s['actual_count'], $s['required_count']]);
        }
        return ['title' => _l($titles[$event['rule_code']] ?? 'sales_pipeline_reminder'), 'message' => $message];
    }

    /**
     * Delivery is intentionally at-least-once. Claiming prevents concurrent
     * workers from sending the same row; a provider success followed by a
     * process crash can still be retried because CRM/email lacks an idempotency key.
     */
    private function dispatchPendingDeliveries()
    {
        $now = new DateTimeImmutable('now');
        if (!$this->isWorkingDay($now) || $this->isQuietHours($now)) {
            $this->CI->db->query('UPDATE `' . db_prefix() . 'sales_pipeline_reminder_deliveries` SET `next_retry_at` = ?'
                . " WHERE `status` IN ('pending','failed') AND `attempt_count` < 3"
                . ' AND (`next_retry_at` IS NULL OR `next_retry_at` <= NOW())', [$this->nextAllowedDeliveryAt($now)->format('Y-m-d H:i:s')]);
            return 0;
        }
        $this->CI->db->query('UPDATE `' . db_prefix() . 'sales_pipeline_reminder_deliveries`'
            . " SET `status`='failed', `last_error`='Stuck in processing state', `updated_at`=NOW()"
            . " WHERE `status`='processing' AND `updated_at` < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        /*
         * Keep the CRM notification path ahead of the slower email path.
         *
         * A single FIFO order by id causes head-of-line blocking: old failed
         * email rows can consume the whole batch and leave a newly-created CRM
         * row pending, so the staff bell never gets populated in that cron run.
         * The priority is deliberately explicit:
         *   1. new CRM notifications
         *   2. retrying CRM notifications
         *   3. new emails
         *   4. retrying emails
         *
         * This preserves "pending before failed" for each channel while making
         * CRM delivery independent from an email provider/outbox backlog.
         */
        $rows = $this->CI->db->query('SELECT d.*, r.title, r.message, r.entity_type, r.entity_id, r.pipeline_id, r.rule_code, r.staff_id, r.severity, r.sent_at AS reminder_sent_at'
            . ' FROM `' . db_prefix() . 'sales_pipeline_reminder_deliveries` d JOIN `' . db_prefix() . 'sales_pipeline_reminders_log` r ON r.id=d.reminder_id'
            . " WHERE d.status IN ('pending','failed') AND d.attempt_count<3 AND (d.next_retry_at IS NULL OR d.next_retry_at<=NOW())"
            . " ORDER BY CASE"
            . " WHEN d.channel='crm' AND d.status='pending' THEN 0"
            . " WHEN d.channel='crm' THEN 1"
            . " WHEN d.status='pending' THEN 2"
            . " ELSE 3 END, d.id ASC LIMIT 100")->result_array();
        $anySent = false;
        foreach ($rows as $row) {
            $this->CI->db->query('UPDATE `' . db_prefix() . 'sales_pipeline_reminder_deliveries` SET `status` = ?, `updated_at` = NOW()'
                . " WHERE `id` = ? AND `status` IN ('pending','failed') AND `attempt_count` < 3", ['processing', $row['id']]);
            if ($this->CI->db->affected_rows() !== 1) { continue; }
            $success = false; $error = null;
            if ($row['channel'] === 'crm') {
                $success = (bool) add_notification([
                    'description' => 'sales_pipeline_rule_reminder', 'touserid' => (int) $row['recipient_key'],
                    'fromuserid' => null, 'link' => 'sales_pipeline/reminder_response/' . $row['reminder_id'],
                    'additional_data' => serialize([$row['title'], $row['message']]),
                ]);
            } elseif ($row['channel'] === 'email') {
                $this->CI->load->model('emails_model');
                $recipient = !empty($row['recipient_staff_id'])
                    ? $this->CI->db->where('staffid', (int) $row['recipient_staff_id'])->get(db_prefix() . 'staff')->row()
                    : $this->CI->db->where('email', $row['recipient_key'])->get(db_prefix() . 'staff')->row();
                $staffId = !empty($row['staff_id']) ? (int) $row['staff_id'] : ($recipient ? (int) $recipient->staffid : 0);
                $severity = !empty($row['severity']) ? (string) $row['severity'] : 'warning';
                $body = $this->CI->load->view('sales_pipeline/emails/reminder', [
                    'staff_name' => $recipient ? trim($recipient->firstname . ' ' . $recipient->lastname) : '',
                    'title' => $row['title'], 'message' => $row['message'],
                    'response_url' => admin_url('sales_pipeline/reminder_response/' . $row['reminder_id']),
                    'entity_url' => $this->entityUrl($row['entity_type'], $row['entity_id']),
                ], true);
                $ccList = [];
                if ($row['recipient_type'] === 'staff') {
                    $ccList = $this->resolveManagerCCEmails($staffId, $severity);
                }
                $ccString = !empty($ccList) ? implode(', ', $ccList) : null;
                $this->CI->db->where('id', (int) $row['id'])->update(db_prefix() . 'sales_pipeline_reminder_deliveries', [
                    'cc_recipients' => $ccString,
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
                try {
                    self::$currentEmailCC = $ccString;
                    $success = (bool) $this->CI->emails_model->send_simple_email($row['recipient_key'], $row['title'], $body);
                } finally {
                    self::$currentEmailCC = null;
                }
            } else {
                $error = 'Channel adapter is not enabled';
            }
            $now = date('Y-m-d H:i:s');
            $this->CI->db->where('id', $row['id'])->update(db_prefix() . 'sales_pipeline_reminder_deliveries', [
                'status' => $success ? 'sent' : 'failed', 'attempt_count' => (int) $row['attempt_count'] + 1,
                'last_error' => $success ? null : ($error ?: 'Delivery provider returned false'),
                'sent_at' => $success ? $now : null, 'updated_at' => $now,
                'next_retry_at' => $success ? null : date('Y-m-d H:i:s', strtotime('+1 hour')),
            ]);
            $anySent = $anySent || $success;
            if ($success && empty($row['reminder_sent_at'])) {
                $this->CI->db->where('id', $row['reminder_id'])->where('sent_at IS NULL', null, false)
                    ->update(db_prefix() . 'sales_pipeline_reminders_log', ['sent_at' => $now]);
                if ($row['entity_type'] === 'deal' && !empty($row['pipeline_id'])) {
                    $this->CI->db->where('id', (int) $row['pipeline_id'])
                        ->update(db_prefix() . 'sales_pipeline', ['last_reminder_sent' => $now]);
                }
            }
        }
        return $anySent;
    }

    private function entityUrl($type, $id)
    {
        if ($type === 'estimate' && $id) { return admin_url('estimates/list_estimates/' . $id . '#' . $id); }
        if ($type === 'deal' && $id) { return admin_url('sales_pipeline/deal/' . $id); }
        return admin_url('sales_pipeline/dashboard');
    }

    private function option($key)
    {
        $value = get_option($key);
        if ($value !== false && $value !== null) {
            return (string) $value;
        }
        $defaults = function_exists('sales_pipeline_reminder_rule_default_options')
            ? sales_pipeline_reminder_rule_default_options() : [];
        return isset($defaults[$key]) ? (string) $defaults[$key] : '';
    }

    private function parseChannels($optionKey)
    {
        $channels = array_filter(array_map('trim', explode(',', $this->option($optionKey))), function ($channel) {
            return in_array($channel, ['crm', 'email'], true);
        });
        return array_values(array_unique($channels));
    }

    private function lifecycleChannels($rule)
    {
        $keys = [
            'ESTIMATE_DRAFT_TOO_LONG' => 'sp_reminder_lc_draft_channels',
            'ESTIMATE_SENT_NO_RESPONSE' => 'sp_reminder_lc_sent_channels',
            'ESTIMATE_DECLINED_RECENT' => 'sp_reminder_lc_declined_channels',
            'ESTIMATE_EXPIRED' => 'sp_reminder_lc_expired_channels',
            'ESTIMATE_ACCEPTED_NOT_INVOICED' => 'sp_reminder_lc_accepted_channels',
        ];
        return isset($keys[$rule]) ? $this->parseChannels($keys[$rule]) : [];
    }

    private function isWorkingDay(DateTimeImmutable $date)
    {
        if ($this->option('sp_reminder_skip_weekends') === '1' && (int) $date->format('N') > 5) {
            return false;
        }
        $holidays = preg_split('/\R/', trim($this->option('sp_reminder_holiday_dates')));
        return !in_array($date->format('Y-m-d'), $holidays ?: [], true);
    }

    private function isQuietHours(DateTimeImmutable $now)
    {
        $start = $this->option('sp_reminder_quiet_hours_start');
        $end = $this->option('sp_reminder_quiet_hours_end');
        if ($start === '' || $end === '' || $start === $end) { return false; }
        $current = $now->format('H:i');
        return $start < $end ? ($current >= $start && $current < $end) : ($current >= $start || $current < $end);
    }

    private function nextAllowedDeliveryAt(DateTimeImmutable $now)
    {
        $candidate = $now;
        $start = $this->option('sp_reminder_quiet_hours_start');
        $end = $this->option('sp_reminder_quiet_hours_end');
        for ($i = 0; $i < 370; $i++) {
            if (!$this->isWorkingDay($candidate)) {
                $candidate = $candidate->modify('+1 day')->setTime(0, 0);
                continue;
            }
            if (!$this->isQuietHours($candidate)) { break; }
            [$hour, $minute] = array_map('intval', explode(':', $end));
            $candidate = ($start > $end && $candidate->format('H:i') >= $start)
                ? $candidate->modify('+1 day')->setTime($hour, $minute)
                : $candidate->setTime($hour, $minute);
        }
        return $candidate <= $now ? $now->modify('+1 minute') : $candidate;
    }

    private function activeStaff($staffId)
    {
        $staff = $staffId ? $this->CI->staff_model->get($staffId) : null;
        return $staff && (int) $staff->active === 1 ? $staff : null;
    }
}
