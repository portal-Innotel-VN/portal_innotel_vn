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
    private $staffCache = [];
    private $staffEmailCache = [];
    private $activeManagerStaffCache;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('staff_model');
        $this->CI->load->library('sales_pipeline/Deal_reminder_rule_evaluator');
        $this->CI->load->library('sales_pipeline/Reminder_delivery_policy');
        $this->CI->load->library('sales_pipeline/Reminder_delivery_selector');
        $this->CI->load->library('sales_pipeline/Reminder_delivery_throttle');
        $this->CI->load->library('sales_pipeline/Reminder_delivery_rate_limiter');
        $this->CI->load->library('sales_pipeline/Reminder_delivery_lock');
        $this->CI->load->library('sales_pipeline/Reminder_delivery_error_sanitizer');
        $this->CI->load->library('sales_pipeline/Reminder_delivery_error_classifier');
        $this->CI->load->library('sales_pipeline/Reminder_delivery_backoff');
        $this->CI->load->library('sales_pipeline/Reminder_recipient_resolver');
    }

    public function process()
    {
        $this->CI->reminder_recipient_resolver->clearCache();

        if ($this->option('sp_reminder_global_enabled') !== '1') {
            return;
        }

        $now = new DateTimeImmutable('now');
        if ($this->isWorkingDay($now)) {
            $this->processEstimatePeriods();
            $this->processDealPipelineMinimum($now);
            $this->processDealStaleFollowUps($now);
        }
        $this->processEstimateLifecycle();
        $this->dispatchPendingDeliveries();
    }

    private function processDealPipelineMinimum(DateTimeImmutable $now)
    {
        if ($this->option('sp_reminder_deal_pipeline_enabled') !== '1') {
            return;
        }

        $statuses = $this->openDealStatusIds();
        if (!$statuses) {
            return;
        }

        // Start from the active Staff roster so salespeople with zero Deals are
        // evaluated too. Starting from the Deal table would silently omit them.
        $staffRows = $this->CI->db->select('staffid')->where('active', 1)->where('admin', 0)
            ->get(db_prefix() . 'staff')->result_array();
        $summaryRows = $this->CI->db
            ->select('staff_id, COUNT(*) open_deal_count, COALESCE(SUM(deal_value),0) open_pipeline_value', false)
            ->where_in('status', $statuses)
            ->group_by('staff_id')
            ->get(db_prefix() . 'sales_pipeline')->result_array();
        $summariesByStaff = [];
        foreach ($summaryRows as $summaryRow) {
            $summariesByStaff[(int) $summaryRow['staff_id']] = $summaryRow;
        }
        foreach ($staffRows as $staffRow) {
            $staffId = (int) $staffRow['staffid'];
            if (!staff_can('view', 'sales_pipeline', $staffId) && !staff_can('view_own', 'sales_pipeline', $staffId)) {
                continue;
            }
            $summary = $summariesByStaff[$staffId] ?? [];
            $openDealCount = (int) ($summary['open_deal_count'] ?? 0);

            // Quota cohort rule: pure supervisory managers without sales activity are excluded from deal quota reminders
            if ($this->isPureSupervisoryManager($staffId) && $openDealCount === 0) {
                continue;
            }

            $event = $this->CI->deal_reminder_rule_evaluator->evaluatePipelineMinimum([
                'staff_id' => $staffId,
                'open_deal_count' => $openDealCount,
                'open_pipeline_value' => (float) ($summary['open_pipeline_value'] ?? 0),
            ], $now, [
                'enabled' => true,
                'minimum_count' => (int) $this->option('sp_reminder_deal_pipeline_min_count'),
                'check_time' => $this->option('sp_reminder_deal_pipeline_check_time'),
                'channels' => $this->parseChannels('sp_reminder_deal_pipeline_channels'),
            ]);
            if ($event) {
                $this->run($event);
            }
        }
    }

    private function processDealStaleFollowUps(DateTimeImmutable $now)
    {
        if ($this->option('sp_reminder_deal_stale_enabled') !== '1') {
            return;
        }
        $statuses = $this->openDealStatusIds();
        if (!$statuses) {
            return;
        }

        $activityTable = db_prefix() . 'sales_pipeline_activity';
        $dealTable = db_prefix() . 'sales_pipeline';
        $statusTable = db_prefix() . 'sales_pipeline_statuses';
        $lastActivitySql = '(SELECT MAX(a.datecreated) FROM `' . $activityTable . '` a'
            . ' WHERE a.pipeline_id=p.id'
            . " AND a.description NOT LIKE 'Phản hồi nhắc nhở:%'"
            . " AND a.description NOT LIKE 'Reminder response:%')";
        $lastMeaningfulSql = 'GREATEST(COALESCE(' . $lastActivitySql . ", '1000-01-01 00:00:00'),"
            . " COALESCE(p.datemodified, '1000-01-01 00:00:00'), COALESCE(p.datecreated, '1000-01-01 00:00:00'))";
        $rows = $this->CI->db->query(
            'SELECT p.*, ss.name status_name, ' . $lastMeaningfulSql . ' last_meaningful_activity_at'
            . ' FROM `' . $dealTable . '` p LEFT JOIN `' . $statusTable . '` ss ON ss.id=p.status'
            . ' WHERE p.reminder_enabled=1 AND p.status IN (' . implode(',', array_map('intval', $statuses)) . ')'
        )->result_array();

        $eligibleDeals = [];
        foreach ($rows as $deal) {
            if (!$this->activeStaff((int) $deal['staff_id'])) {
                continue;
            }
            $eligibleDeals[] = $deal;
        }
        $events = $this->CI->deal_reminder_rule_evaluator->evaluateStaleFollowUps($eligibleDeals, $now, [
            'enabled' => true,
            'channels' => $this->parseChannels('sp_reminder_deal_stale_channels'),
            'cutoff_days' => (int) $this->option('sp_reminder_deal_stale_cutoff_days'),
            'max_per_run' => (int) $this->option('sp_reminder_deal_stale_max_per_run'),
        ]);
        foreach ($events as $event) {
            if ($event) {
                $this->run($event);
            }
        }
    }

    private function openDealStatusIds()
    {
        $statuses = $this->CI->db->select('id')->where('is_won', 0)->where('is_lost', 0)
            ->get(db_prefix() . 'sales_pipeline_statuses')->result_array();
        return array_map('intval', array_column($statuses, 'id'));
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
            // Quota cohort rule: pure supervisory managers without estimate activity in the period are excluded
            if ($this->isPureSupervisoryManager($staffId) && !$this->hasStaffEstimateActivity($staffId, $now)) {
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
        $responseRequired = (int) ($event['response_required'] ?? 1);
        $slaHours = $responseRequired === 1 ? max(1, min(720, (int) $this->option('sp_reminder_sla_hours', '24'))) : null;
        $data = [
            'pipeline_id' => $event['pipeline_id'], 'staff_id' => $event['staff_id'], 'reminder_type' => 'multi',
            'rule_code' => $event['rule_code'], 'entity_type' => $event['entity_type'], 'entity_id' => $event['entity_id'],
            'period_key' => $event['period_key'], 'checkpoint' => $event['checkpoint'], 'severity' => $event['severity'],
            'response_required' => $responseRequired, 'response_sla_hours' => $slaHours,
            'title' => $content['title'], 'message' => $content['message'],
            'snapshot_json' => json_encode($event['snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'dedupe_key' => $event['dedupe_key'], 'created_at' => date('Y-m-d H:i:s'), 'sent_at' => null, 'response_due_at' => null,
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
        $createdAt = new DateTimeImmutable('now');
        $createdAtValue = $createdAt->format('Y-m-d H:i:s');
        $maxValidAgeHours = (int) $this->option('sp_reminder_delivery_default_max_valid_age_hours');
        $recipients = [['type' => 'staff', 'id' => $event['staff_id']]];
        if (in_array('manager', $event['recipients'], true)) {
            $managers = $this->isV2Enabled()
                ? $this->CI->reminder_recipient_resolver->businessManagers()
                : $this->activeManagerStaff();

            foreach ($managers as $manager) {
                if ((int) $manager->staffid !== (int) $event['staff_id']) {
                    $recipients[] = ['type' => 'manager', 'id' => (int) $manager->staffid];
                }
            }
        }
        $isCCApplicable = $this->isManagerCCApplicable($event['severity'] ?? 'warning');
        $deliveryRows = [];
        foreach ($recipients as $recipient) {
            $staff = $this->activeStaff($recipient['id']);
            if (!$staff) { continue; }
            foreach ($event['channels'] as $channel) {
                if ($channel === 'whatsapp') {
                    // WhatsApp is materialized dedicatedly for managers per configured mode
                    continue;
                }
                if ($this->isV2Enabled() && $recipient['type'] === 'manager' && $channel === 'crm') {
                    // In V2 target model, CRM Bell is strictly for the staff owner; omit manager Bell
                    continue;
                }
                if ($recipient['type'] === 'manager' && $channel === 'email' && $isCCApplicable) {
                    continue;
                }
                $key = $channel === 'email' ? trim((string) $staff->email) : (string) $recipient['id'];
                if ($key === '') { continue; }
                $expiresAt = $channel === 'email'
                    ? $this->CI->reminder_delivery_policy->expiresAt($createdAt, $maxValidAgeHours)->format('Y-m-d H:i:s')
                    : null;
                $deliveryRows[] = [$reminderId, $channel, $recipient['type'], $recipient['id'], $key, 'pending', $expiresAt, $createdAtValue];
            }
        }
        if (in_array('whatsapp', $event['channels'], true)) {
            $this->materializeWhatsAppDeliveries($reminderId, $event, $createdAtValue, $deliveryRows);
        }
        $this->insertDeliveryBatch($deliveryRows);
    }

    private function materializeWhatsAppDeliveries($reminderId, array $event, $createdAtValue, array &$deliveryRows)
    {
        if ($this->option('sp_reminder_whatsapp_enabled') !== '1') {
            return;
        }
        $mode = $this->option('sp_reminder_whatsapp_manager_mode') ?: 'group_only';
        $groupJid = trim((string) $this->option('sp_reminder_whatsapp_group_jid'));

        if (($mode === 'group_only' || $mode === 'both') && $groupJid !== '') {
            $deliveryRows[] = [
                $reminderId,
                'whatsapp',
                'manager',
                null,
                $groupJid,
                'pending',
                null,
                $createdAtValue,
            ];
        }

        if ($mode === 'direct_only' || $mode === 'both') {
            $this->CI->load->library('sales_pipeline/Reminder_whatsapp_formatter');
            $managers = $this->isV2Enabled()
                ? $this->CI->reminder_recipient_resolver->businessManagers()
                : $this->activeManagerStaff();

            foreach ($managers as $manager) {
                if ($this->isV2Enabled() && (int) $manager->staffid === (int) $event['staff_id']) {
                    continue;
                }
                $staffObj = $this->activeStaff($manager->staffid);
                if (!$staffObj || empty($staffObj->phonenumber)) {
                    continue;
                }
                $jid = $this->CI->reminder_whatsapp_formatter->normalizePhoneToJid($staffObj->phonenumber);
                if ($jid !== null) {
                    $deliveryRows[] = [
                        $reminderId,
                        'whatsapp',
                        'manager',
                        (int) $manager->staffid,
                        $jid,
                        'pending',
                        null,
                        $createdAtValue,
                    ];
                }
            }
        }
    }

    private function insertDeliveryBatch(array $rows)
    {
        if (!$rows) {
            return;
        }
        $valuesSql = implode(',', array_fill(0, count($rows), '(?,?,?,?,?,?,?,?)'));
        $params = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                $params[] = $value;
            }
        }
        $this->CI->db->query('INSERT IGNORE INTO `' . db_prefix() . 'sales_pipeline_reminder_deliveries`'
            . ' (`reminder_id`,`channel`,`recipient_type`,`recipient_staff_id`,`recipient_key`,`status`,`expires_at`,`created_at`)'
            . ' VALUES ' . $valuesSql, $params);
    }

    private function isManagerCCApplicable($severity = 'warning', $eventAllowsCC = null)
    {
        if ($eventAllowsCC === false) {
            return false;
        }
        if ($this->option('sp_reminder_email_cc_manager_enabled') !== '1') {
            return false;
        }
        $scope = $this->option('sp_reminder_email_cc_scope') ?: 'all';
        if ($scope === 'critical_only' && $severity !== 'critical') {
            return false;
        }
        return true;
    }

    private function resolveManagerCCEmails($staffId, $severity = 'warning', $eventAllowsCC = null)
    {
        if ($this->isV2Enabled()) {
            return $this->CI->reminder_recipient_resolver->resolveManagerEmails($staffId, $severity, $eventAllowsCC);
        }

        if (!$this->isManagerCCApplicable($severity, $eventAllowsCC)) {
            return [];
        }

        $staff = $this->staffById($staffId);
        $staffEmail = $staff && !empty($staff->email) ? strtolower(trim((string) $staff->email)) : '';

        $rawEmails = [];

        foreach ($this->activeManagerStaff() as $manager) {
            if ((int) $manager->staffid !== (int) $staffId) {
                $rawEmails[] = trim((string) $manager->email);
            }
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
            'DEAL_PIPELINE_MIN_COUNT' => 'sales_pipeline_deal_pipeline_min_title',
            'DEAL_STALE_FOLLOW_UP' => 'sales_pipeline_deal_stale_title',
            'DEAL_STALE_BACKLOG' => 'sales_pipeline_deal_stale_backlog_title',
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
        } elseif ($event['rule_code'] === 'DEAL_PIPELINE_MIN_COUNT') {
            $message = _l('sales_pipeline_deal_pipeline_min_message', [$s['actual_count'], $s['required_count']]);
        } elseif ($event['rule_code'] === 'DEAL_STALE_BACKLOG') {
            $message = _l('sales_pipeline_deal_stale_backlog_message', [
                $s['total_attention_required'], $s['individual_sent'], $s['stale_overflow_count'],
                $s['long_stale_count'], $s['cutoff_days'],
            ]);
        } elseif ($event['rule_code'] === 'DEAL_STALE_FOLLOW_UP') {
            $message = _l('sales_pipeline_deal_stale_message', [$s['deal_name'], $s['customer_name'], $s['inactive_days']]);
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
        $maxAttempts = max(1, (int) $this->option('sp_reminder_delivery_max_attempts'));
        $this->CI->reminder_delivery_selector->expireStaleEmails($now);
        if (!$this->isWorkingDay($now) || $this->isQuietHours($now)) {
            $this->CI->db->query('UPDATE `' . db_prefix() . 'sales_pipeline_reminder_deliveries` SET `next_retry_at` = ?'
                . " WHERE `status` IN ('pending','failed') AND `attempt_count` < ?"
                . ' AND (`next_retry_at` IS NULL OR `next_retry_at` <= NOW())',
                [$this->nextAllowedDeliveryAt($now)->format('Y-m-d H:i:s'), $maxAttempts]);
            return 0;
        }
        $staleRows = $this->CI->db->query('SELECT `id`,`attempt_count` FROM `'
            . db_prefix() . 'sales_pipeline_reminder_deliveries`'
            . " WHERE `status`='processing' AND `attempt_count`<?"
            . ' AND `updated_at` < DATE_SUB(NOW(), INTERVAL 15 MINUTE) ORDER BY `id` ASC LIMIT 100',
            [$maxAttempts])->result_array();
        foreach ($staleRows as $staleRow) {
            $attemptCount = (int) $staleRow['attempt_count'] + 1;
            $nextRetryAt = $this->CI->reminder_delivery_backoff->nextRetryAt($attemptCount, $now)->format('Y-m-d H:i:s');
            $this->CI->db->query('UPDATE `' . db_prefix() . 'sales_pipeline_reminder_deliveries`'
                . " SET `status`='failed', `attempt_count`=?, `last_attempt_at`=?, `next_retry_at`=?,"
                . " `last_error_code`='stale_processing_recovered', `last_error_class`='transient_transport',"
                . " `last_error`='Stuck delivery was recovered for retry', `updated_at`=?"
                . " WHERE `id`=? AND `status`='processing' AND `attempt_count`=?",
                [$attemptCount, $now->format('Y-m-d H:i:s'), $nextRetryAt, $now->format('Y-m-d H:i:s'),
                    (int) $staleRow['id'], (int) $staleRow['attempt_count']]);
        }

        $crmRows = $this->CI->reminder_delivery_selector->due('crm', 100, $maxAttempts, $now);
        $anySent = false;
        $anySent = $this->dispatchDeliveryRows($crmRows, $maxAttempts) || $anySent;

        $anySent = $this->dispatchWhatsAppDeliveries($now, $maxAttempts) || $anySent;

        if ($this->option('sp_reminder_delivery_email_circuit_state') !== 'closed'
            || !$this->CI->reminder_delivery_lock->acquire()) {
            return $anySent;
        }
        try {
            $emailBatchSize = max(1, min(100, (int) $this->option('sp_reminder_delivery_email_batch_size')));
            $emailRows = $this->CI->reminder_delivery_selector->due('email', $emailBatchSize, $maxAttempts, $now);
            $anySent = $this->dispatchDeliveryRows($emailRows, $maxAttempts) || $anySent;
        } finally {
            $this->CI->reminder_delivery_lock->release();
        }

        return $anySent;
    }

    private function dispatchWhatsAppDeliveries(DateTimeImmutable $now, $maxAttempts)
    {
        if ($this->option('sp_reminder_whatsapp_enabled') !== '1') {
            return false;
        }

        // MySQL Advisory Lock dedicated to WhatsApp dispatcher
        $lockKey = db_prefix() . ':sales_pipeline:whatsapp:dispatcher';
        $lockAcquired = $this->CI->db->query('SELECT GET_LOCK(?, 0) AS lck', [$lockKey])->row_array();
        if (empty($lockAcquired['lck'])) {
            return false;
        }

        $anySent = false;
        $batchStart = microtime(true);
        $timeBudgetOpt = $this->option('sp_reminder_whatsapp_time_budget');
        $timeBudget = is_numeric($timeBudgetOpt) && (float) $timeBudgetOpt > 0 ? (float) $timeBudgetOpt : 15.0;
        $minSafety = 5.0;

        $this->CI->load->library('sales_pipeline/channels/Reminder_delivery_whatsapp_adapter');
        $this->CI->load->library('sales_pipeline/Reminder_whatsapp_formatter');

        try {
            // Reconcile Pass: Resolve in-flight uncertain deliveries
            $uncertainRows = $this->CI->reminder_delivery_selector->dueUncertainWhatsApp(10);
            foreach ($uncertainRows as $row) {
                $elapsed = microtime(true) - $batchStart;
                if (($timeBudget - $elapsed) < 3.0) {
                    break;
                }
                $statusResult = $this->CI->reminder_delivery_whatsapp_adapter->checkStatus((int) $row['id'], 3.0);
                if ($statusResult['status'] === 'sent') {
                    $completedAt = date('Y-m-d H:i:s');
                    $this->CI->db->where('id', (int) $row['id'])->update(db_prefix() . 'sales_pipeline_reminder_deliveries', [
                        'status'              => 'sent',
                        'sent_at'             => $completedAt,
                        'provider_message_id' => $statusResult['message_id'] ?? null,
                        'last_error'          => null,
                        'last_error_code'     => null,
                        'last_error_class'    => null,
                        'next_retry_at'       => null,
                        'updated_at'          => $completedAt,
                    ]);
                    $anySent = true;
                } elseif ($statusResult['status'] === 'failed') {
                    $rawRetrySafe = $statusResult['retry_safe'] ?? ($statusResult['raw']['retry_safe'] ?? null);

                    if ($rawRetrySafe === true) {
                        $attemptCount = (int) $row['attempt_count'] + 1;
                        $nextRetryAt = $this->CI->reminder_delivery_backoff->nextRetryAt(
                            $attemptCount, new DateTimeImmutable('now')
                        )->format('Y-m-d H:i:s');

                        $this->CI->db->where('id', (int) $row['id'])->update(db_prefix() . 'sales_pipeline_reminder_deliveries', [
                            'status'           => 'failed',
                            'last_error'       => $statusResult['error'] ?? 'Gateway reported message delivery failure during reconciliation (retry_safe=true)',
                            'last_error_code'  => 'gateway_reported_failed',
                            'last_error_class' => 'transient',
                            'next_retry_at'    => $nextRetryAt,
                            'updated_at'       => date('Y-m-d H:i:s'),
                        ]);
                    } else {
                        $this->CI->db->where('id', (int) $row['id'])->update(db_prefix() . 'sales_pipeline_reminder_deliveries', [
                            'last_error'       => $statusResult['error'] ?? 'Delivery result unverified or retry unsafe on Gateway (retry_safe != true); manual verification required',
                            'last_error_code'  => 'whatsapp_reconcile_unverified',
                            'last_error_class' => 'unverified',
                            'next_retry_at'    => null,
                            'updated_at'       => date('Y-m-d H:i:s'),
                        ]);
                    }
                } elseif ($statusResult['status'] === 'not_found') {
                    // Gateway has no record of this delivery after timeout; isolate permanently to prevent duplicates
                    $this->CI->db->where('id', (int) $row['id'])->update(db_prefix() . 'sales_pipeline_reminder_deliveries', [
                        'last_error'       => 'Delivery result unverified on Gateway; manual verification required',
                        'last_error_code'  => 'whatsapp_reconcile_unverified',
                        'last_error_class' => 'unverified',
                        'next_retry_at'    => null,
                        'updated_at'       => date('Y-m-d H:i:s'),
                    ]);
                } elseif (in_array($statusResult['status'], ['in_progress', 'uncertain'], true)) {
                    $this->CI->db->where('id', (int) $row['id'])->update(db_prefix() . 'sales_pipeline_reminder_deliveries', [
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            // Normal Dispatch Pass
            $whatsappRows = $this->CI->reminder_delivery_selector->due('whatsapp', 20, $maxAttempts, $now);
            $hourlyLimit = max(1, (int) $this->option('sp_reminder_delivery_whatsapp_hourly_limit', 60));

            foreach ($whatsappRows as $row) {
                $elapsed = microtime(true) - $batchStart;
                if (($timeBudget - $elapsed) < $minSafety) {
                    break;
                }

                $reservation = $this->CI->reminder_delivery_rate_limiter->reserve(
                    1,
                    new DateTimeImmutable('now'),
                    [
                        'hourly_messages'            => $hourlyLimit,
                        'hourly_recipients'          => $hourlyLimit,
                        'daily_messages'             => $hourlyLimit * 24,
                        'daily_recipients'           => $hourlyLimit * 24,
                        'max_recipients_per_message' => 1,
                    ],
                    'whatsapp_baileys_gateway'
                );

                if (empty($reservation['allowed'])) {
                    if (!empty($reservation['retryable'])) {
                        $this->CI->reminder_delivery_selector->deferDueWhatsApp(
                            $reservation['next_retry_at'], $reservation['code'], 'WhatsApp hourly rate limit reached'
                        );
                        break;
                    } else {
                        $this->CI->reminder_delivery_selector->cancel(
                            (int) $row['id'], $reservation['code'], 'WhatsApp recipient limit exceeded'
                        );
                        continue;
                    }
                }

                if (!$this->CI->reminder_delivery_selector->claim((int) $row['id'], 'whatsapp', $maxAttempts, new DateTimeImmutable('now'))) {
                    continue;
                }

                if ($this->isV2Enabled()) {
                    $reval = $this->CI->reminder_recipient_resolver->revalidateDeliveryRecipient($row);
                    if (empty($reval['valid'])) {
                        $this->CI->reminder_delivery_selector->cancel(
                            (int) $row['id'],
                            $reval['reason'] ?: 'recipient_revoked',
                            'Recipient eligibility revalidation failed before dispatch'
                        );
                        continue;
                    }
                }

                $text = $this->CI->reminder_whatsapp_formatter->format($row);
                $remainingTimeout = max(1.0, min(5.0, $timeBudget - (microtime(true) - $batchStart)));
                $result = $this->CI->reminder_delivery_whatsapp_adapter->send(
                    $row['recipient_key'],
                    $text,
                    (int) $row['id'],
                    ['timeout' => $remainingTimeout]
                );

                $completedAt = date('Y-m-d H:i:s');
                $attemptCount = (int) $row['attempt_count'] + 1;

                if ($result['success']) {
                    $this->CI->db->where('id', (int) $row['id'])->update(db_prefix() . 'sales_pipeline_reminder_deliveries', [
                        'status'              => 'sent',
                        'attempt_count'       => $attemptCount,
                        'last_attempt_at'     => $completedAt,
                        'sent_at'             => $completedAt,
                        'provider_message_id' => $result['provider_message_id'] ?? null,
                        'last_error'          => null,
                        'last_error_code'     => null,
                        'last_error_class'    => null,
                        'next_retry_at'       => null,
                        'updated_at'          => $completedAt,
                    ]);
                    $anySent = true;
                } elseif (!empty($result['uncertain'])) {
                    // Result uncertain: isolate from regular due() to avoid double sends
                    $this->CI->db->where('id', (int) $row['id'])->update(db_prefix() . 'sales_pipeline_reminder_deliveries', [
                        'status'           => 'failed',
                        'attempt_count'    => $attemptCount,
                        'last_attempt_at'  => $completedAt,
                        'last_error'       => $result['last_error'] ?? 'WhatsApp delivery uncertain',
                        'last_error_code'  => 'whatsapp_delivery_uncertain',
                        'last_error_class' => 'uncertain',
                        'next_retry_at'    => null,
                        'updated_at'       => $completedAt,
                    ]);
                } else {
                    $isPermanent = ($result['last_error_class'] === 'permanent');
                    $status = $isPermanent ? 'cancelled' : 'failed';
                    $nextRetryAt = $isPermanent ? null : $this->CI->reminder_delivery_backoff->nextRetryAt(
                        $attemptCount, new DateTimeImmutable($completedAt)
                    )->format('Y-m-d H:i:s');

                    $this->CI->db->where('id', (int) $row['id'])->update(db_prefix() . 'sales_pipeline_reminder_deliveries', [
                        'status'           => $status,
                        'attempt_count'    => $attemptCount,
                        'last_attempt_at'  => $completedAt,
                        'last_error'       => $result['last_error'] ?? 'WhatsApp delivery failed',
                        'last_error_code'  => $result['last_error_code'] ?? 'whatsapp_delivery_failed',
                        'last_error_class' => $result['last_error_class'] ?? 'transient',
                        'next_retry_at'    => $nextRetryAt,
                        'updated_at'       => $completedAt,
                    ]);
                }
            }
        } finally {
            $this->CI->db->query('SELECT RELEASE_LOCK(?)', [$lockKey]);
        }

        return $anySent;
    }

    private function dispatchDeliveryRows(array $rows, $maxAttempts)
    {
        $anySent = false;
        $storedLastAttempt = $this->option('sp_reminder_delivery_email_last_attempt_started_at');
        $lastEmailAttemptStartedAt = is_numeric($storedLastAttempt) ? (float) $storedLastAttempt : null;
        foreach ($rows as $row) {
            $claimAt = new DateTimeImmutable('now');
            if (!$this->CI->reminder_delivery_selector->claim(
                (int) $row['id'], (string) $row['channel'], $maxAttempts, $claimAt
            )) {
                continue;
            }

            if ($this->isV2Enabled()) {
                $reval = $this->CI->reminder_recipient_resolver->revalidateDeliveryRecipient($row);
                if (empty($reval['valid'])) {
                    $this->CI->reminder_delivery_selector->cancel(
                        (int) $row['id'],
                        $reval['reason'] ?: 'recipient_revoked',
                        'Recipient eligibility revalidation failed before dispatch'
                    );
                    continue;
                }
            }

            $success = false; $error = null; $classification = null;
            if ($row['channel'] === 'crm') {
                $inboxEnabled = (int) $this->option('sp_reminder_crm_inbox_enabled', '0');
                if ($inboxEnabled === 1) {
                    $recipientStaffId = !empty($row['recipient_staff_id']) ? (int) $row['recipient_staff_id'] : (int) $row['recipient_key'];
                    $staff = $recipientStaffId > 0 ? $this->staffById($recipientStaffId) : null;
                    $canUseInbox = $staff
                        && (int) $staff->active === 1
                        && (is_admin($recipientStaffId)
                            || has_permission('sales_pipeline', (string) $recipientStaffId, 'view')
                            || has_permission('sales_pipeline', (string) $recipientStaffId, 'view_own'));
                    if ($canUseInbox) {
                        $success = true;
                    } else {
                        $success = false;
                        $error = _l('sales_pipeline_reminder_inbox_recipient_unavailable');
                        $classification = 'permanent';
                    }
                } else {
                    $success = (bool) add_notification([
                        'description' => 'sales_pipeline_rule_reminder', 'touserid' => (int) $row['recipient_key'],
                        'fromuserid' => null, 'link' => 'sales_pipeline/reminder_response/' . $row['reminder_id'],
                        'additional_data' => serialize([$row['title'], $row['message']]),
                    ]);
                }
            } elseif ($row['channel'] === 'email') {
                $this->CI->load->model('emails_model');
                $recipient = !empty($row['recipient_staff_id'])
                    ? $this->staffById((int) $row['recipient_staff_id'])
                    : $this->staffByEmail((string) $row['recipient_key']);
                $staffId = !empty($row['staff_id']) ? (int) $row['staff_id'] : ($recipient ? (int) $recipient->staffid : 0);
                $severity = !empty($row['severity']) ? (string) $row['severity'] : 'warning';
                $body = $this->CI->load->view('sales_pipeline/emails/reminder', [
                    'staff_name'        => $recipient ? trim($recipient->firstname . ' ' . $recipient->lastname) : '',
                    'title'             => $row['title'],
                    'message'           => $row['message'],
                    'response_url'      => admin_url('sales_pipeline/reminder_response/' . $row['reminder_id']),
                    'entity_url'        => $this->entityUrl($row['entity_type'], $row['entity_id']),
                    'severity'          => $severity,
                    'rule_code'         => $row['rule_code'] ?? '',
                    'entity_type'       => $row['entity_type'] ?? '',
                    'checkpoint'        => $row['checkpoint'] ?? '',
                    'response_required' => (int) ($row['response_required'] ?? 1),
                    'snapshot'          => !empty($row['snapshot_json']) ? json_decode((string) $row['snapshot_json'], true) : [],
                    'recipient_type'    => $row['recipient_type'] ?? 'staff',
                ], true);
                $ccList = [];
                if ($row['recipient_type'] === 'staff') {
                    $eventAllowsCC = null;
                    if ($row['rule_code'] === 'DEAL_PIPELINE_MIN_COUNT') {
                        $deliverySnapshot = json_decode((string) ($row['snapshot_json'] ?? ''), true);
                        $eventAllowsCC = !empty($deliverySnapshot['manager_cc']);
                    }
                    $ccList = $this->resolveManagerCCEmails($staffId, $severity, $eventAllowsCC);
                }
                $ccString = !empty($ccList) ? implode(', ', $ccList) : null;
                $this->CI->db->where('id', (int) $row['id'])->update(db_prefix() . 'sales_pipeline_reminder_deliveries', [
                    'cc_recipients' => $ccString,
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
                if (strlen($body) > max(1, (int) $this->option('sp_reminder_delivery_email_max_rendered_bytes'))) {
                    $this->CI->reminder_delivery_selector->cancel(
                        (int) $row['id'], 'rendered_message_exceeds_limit', 'Rendered email exceeds the configured size limit'
                    );
                    continue;
                }
                $reservation = $this->CI->reminder_delivery_rate_limiter->reserve(
                    $this->emailRecipientCount((string) $row['recipient_key'], $ccList),
                    new DateTimeImmutable('now'),
                    [
                        'hourly_messages' => max(1, (int) $this->option('sp_reminder_delivery_email_hourly_message_limit')),
                        'hourly_recipients' => max(1, (int) $this->option('sp_reminder_delivery_email_hourly_recipient_limit')),
                        'daily_messages' => max(1, (int) $this->option('sp_reminder_delivery_email_daily_message_limit')),
                        'daily_recipients' => max(1, (int) $this->option('sp_reminder_delivery_email_daily_recipient_limit')),
                        'max_recipients_per_message' => max(1, (int) $this->option('sp_reminder_delivery_email_max_recipients_per_message')),
                    ]
                );
                if (empty($reservation['allowed'])) {
                    if (!empty($reservation['retryable'])) {
                        $this->CI->reminder_delivery_selector->defer(
                            (int) $row['id'], $reservation['next_retry_at'], $reservation['code']
                        );
                    } else {
                        $this->CI->reminder_delivery_selector->cancel(
                            (int) $row['id'], $reservation['code'], 'Email recipient count exceeds the configured limit'
                        );
                    }
                    continue;
                }
                $lastEmailAttemptStartedAt = $this->CI->reminder_delivery_throttle->waitUntilAllowed(
                    $lastEmailAttemptStartedAt,
                    max(0, (int) $this->option('sp_reminder_delivery_email_min_interval_ms'))
                );
                update_option('sp_reminder_delivery_email_last_attempt_started_at',
                    number_format($lastEmailAttemptStartedAt, 6, '.', ''));
                $rawError = null;
                try {
                    self::$currentEmailCC = $ccString;
                    $success = (bool) $this->CI->emails_model->send_simple_email($row['recipient_key'], $row['title'], $body);
                } catch (Throwable $exception) {
                    $success = false;
                    $rawError = $exception->getMessage();
                } finally {
                    self::$currentEmailCC = null;
                }
                if (!$success) {
                    if ($rawError === null && isset($this->CI->email) && method_exists($this->CI->email, 'print_debugger')) {
                        $rawError = (string) $this->CI->email->print_debugger();
                    }
                    if ($rawError === null || $rawError === '') {
                        $rawError = 'SMTP delivery failed without diagnostic details';
                    }
                    $classification = $this->CI->reminder_delivery_error_classifier->classify(
                        $rawError, $this->systemBccRecipients()
                    );
                    $error = $classification['safe_error'];
                }
            } else {
                $error = 'Channel adapter is not enabled';
            }
            $completedAt = date('Y-m-d H:i:s');
            $completedDate = new DateTimeImmutable($completedAt);
            $completedAttemptCount = (int) $row['attempt_count'] + 1;
            $status = $success ? 'sent' : 'failed';
            $nextRetryAt = $success ? null : $this->CI->reminder_delivery_backoff->nextRetryAt(
                $completedAttemptCount, $completedDate
            )->format('Y-m-d H:i:s');
            $lastErrorCode = null;
            $lastErrorClass = null;
            if ($classification) {
                $lastErrorCode = $classification['code'];
                $lastErrorClass = $classification['class'];
                if (empty($classification['retryable']) && $classification['class'] !== 'authentication_configuration') {
                    $status = 'cancelled';
                    $nextRetryAt = null;
                } elseif ($classification['class'] === 'authentication_configuration') {
                    $nextRetryAt = null;
                } elseif ($classification['class'] === 'rate_limited') {
                    $cooldown = max(60, (int) $this->option('sp_reminder_delivery_rate_limit_cooldown_seconds'));
                    $nextRetryAt = $completedDate->modify('+'
                        . ($cooldown + random_int(0, min(900, (int) floor($cooldown * 0.25)))) . ' seconds')->format('Y-m-d H:i:s');
                }
            }
            $this->CI->db->trans_begin();

            $this->CI->db->where('id', $row['id'])->update(db_prefix() . 'sales_pipeline_reminder_deliveries', [
                'status' => $status,
                'attempt_count' => (!$success && $classification && empty($classification['retryable']))
                    ? $maxAttempts : $completedAttemptCount,
                'last_attempt_at' => $completedAt,
                'last_error' => $success ? null : ($error ?: 'Delivery provider returned false'),
                'last_error_code' => $lastErrorCode,
                'last_error_class' => $lastErrorClass,
                'sent_at' => $success ? $completedAt : null, 'updated_at' => $completedAt,
                'next_retry_at' => $nextRetryAt,
            ]);

            $isStaffRecipient = ($row['recipient_type'] === 'staff')
                && ((int) ($row['recipient_staff_id'] ?? 0) === (int) $row['staff_id']);

            if ($success && $isStaffRecipient) {
                // Conditional update on reminders_log: only the first successful staff delivery triggers SLA deadline
                $this->CI->db->query(
                    'UPDATE `' . db_prefix() . 'sales_pipeline_reminders_log` '
                    . 'SET `sent_at` = ?, `response_due_at` = DATE_ADD(?, INTERVAL `response_sla_hours` HOUR) '
                    . 'WHERE `id` = ? AND `response_required` = 1 AND `response_sla_hours` IS NOT NULL AND `sent_at` IS NULL AND `response_due_at` IS NULL',
                    [$completedAt, $completedAt, (int) $row['reminder_id']]
                );

                if ($row['entity_type'] === 'deal' && !empty($row['pipeline_id'])) {
                    $this->CI->db->where('id', (int) $row['pipeline_id'])
                        ->update(db_prefix() . 'sales_pipeline', ['last_reminder_sent' => $completedAt]);
                }
            } elseif ($success && empty($row['reminder_sent_at']) && empty($row['response_required'])) {
                $this->CI->db->where('id', (int) $row['reminder_id'])->where('sent_at IS NULL', null, false)
                    ->update(db_prefix() . 'sales_pipeline_reminders_log', ['sent_at' => $completedAt]);
            }

            if ($this->CI->db->trans_status() === false) {
                $this->CI->db->trans_rollback();
            } else {
                $committed = (bool) $this->CI->db->trans_commit();
                if ($committed) {
                    if ($success) {
                        $anySent = true;
                    }
                } else {
                    $this->CI->db->trans_rollback();
                }
            }

            if ($classification && $classification['class'] === 'rate_limited') {
                $this->CI->reminder_delivery_selector->deferDueEmails($nextRetryAt, $classification['code']);
                break;
            }
            if ($classification && $classification['class'] === 'authentication_configuration') {
                $this->openAuthenticationCircuit($classification['code']);
                break;
            }
            if ($classification && $classification['class'] === 'system_bcc_over_quota') {
                $this->alertSystemBccIncident();
            }
        }
        return $anySent;
    }

    /**
     * Self-healing reconciliation for any actionable reminders with delivered staff
     * messages that missed their response_due_at calculation.
     *
     * @param int $limit
     * @return int Number of reconciled reminders
     */
    public function reconcile_missing_response_due_at($limit = 100)
    {
        $this->CI->load->library('sales_pipeline/Reminder_sla_reconcile_lock');
        if (!$this->CI->reminder_sla_reconcile_lock->acquire(0)) {
            return 0;
        }

        $limit = max(1, min(500, (int) $limit));
        $reconciledCount = 0;

        try {
            $sql = 'SELECT rl.id, rl.response_sla_hours, MIN(d.sent_at) as first_staff_sent_at '
                . 'FROM `' . db_prefix() . 'sales_pipeline_reminders_log` rl '
                . 'JOIN `' . db_prefix() . 'sales_pipeline_reminder_deliveries` d '
                . '  ON d.reminder_id = rl.id '
                . " AND d.recipient_type = 'staff' "
                . ' AND d.recipient_staff_id = rl.staff_id '
                . " AND d.status = 'sent' "
                . ' AND d.sent_at IS NOT NULL '
                . 'WHERE rl.response_required = 1 '
                . '  AND rl.response_sla_hours IS NOT NULL '
                . '  AND rl.response_due_at IS NULL '
                . 'GROUP BY rl.id, rl.response_sla_hours '
                . 'LIMIT ' . $limit;

            $candidates = $this->CI->db->query($sql)->result_array();
            if (empty($candidates)) {
                $this->CI->reminder_sla_reconcile_lock->release();
                return 0;
            }

            foreach ($candidates as $cand) {
                $firstSentAt = $cand['first_staff_sent_at'];
                if (empty($firstSentAt)) {
                    continue;
                }

                $this->CI->db->query(
                    'UPDATE `' . db_prefix() . 'sales_pipeline_reminders_log` '
                    . 'SET `sent_at` = COALESCE(`sent_at`, ?), '
                    . '    `response_due_at` = DATE_ADD(?, INTERVAL `response_sla_hours` HOUR) '
                    . 'WHERE `id` = ? '
                    . '  AND `response_required` = 1 '
                    . '  AND `response_sla_hours` IS NOT NULL '
                    . '  AND `response_due_at` IS NULL',
                    [$firstSentAt, $firstSentAt, (int) $cand['id']]
                );

                if ($this->CI->db->affected_rows() > 0) {
                    $reconciledCount++;
                }
            }
        } finally {
            $this->CI->reminder_sla_reconcile_lock->release();
        }

        if ($reconciledCount > 0) {
            log_activity('Reminder SLA response deadlines reconciled [count: ' . $reconciledCount . ']');
        }

        return $reconciledCount;
    }

    private function emailRecipientCount($to, array $ccList)
    {
        $recipients = array_merge([$to], $ccList, $this->systemBccRecipients());
        $count = 0;
        foreach ($recipients as $recipient) {
            $recipient = strtolower(trim((string) $recipient));
            if ($recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $count++;
            }
        }

        return max(1, $count);
    }

    private function systemBccRecipients()
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) get_option('bcc_emails')))));
    }

    private function openAuthenticationCircuit($reasonCode)
    {
        if ($this->option('sp_reminder_delivery_email_circuit_state') === 'open_authentication') {
            return;
        }
        $openedAt = date('Y-m-d H:i:s');
        update_option('sp_reminder_delivery_email_circuit_state', 'open_authentication');
        update_option('sp_reminder_delivery_email_circuit_opened_at', $openedAt);
        update_option('sp_reminder_delivery_email_circuit_reason', (string) $reasonCode);

        $admins = $this->isV2Enabled()
            ? $this->CI->reminder_recipient_resolver->technicalAdmins()
            : $this->CI->db->select('staffid')->where('admin', 1)->where('active', 1)->get(db_prefix() . 'staff')->result();
        foreach ($admins as $admin) {
            $adminStaffId = is_object($admin) ? (int) $admin->staffid : (int) $admin['staffid'];
            add_notification([
                'description' => 'sales_pipeline_reminder_email_authentication_alert',
                'touserid' => $adminStaffId,
                'fromuserid' => null,
                'link' => 'sales_pipeline/settings#reminders',
                'additional_data' => serialize([]),
            ]);
        }
    }

    private function alertSystemBccIncident()
    {
        $lastAlertedAt = (string) $this->option('sp_reminder_delivery_bcc_incident_alerted_at');
        if ($lastAlertedAt !== '' && strtotime($lastAlertedAt) >= strtotime('-24 hours')) {
            return;
        }
        update_option('sp_reminder_delivery_bcc_incident_alerted_at', date('Y-m-d H:i:s'));
        $admins = $this->isV2Enabled()
            ? $this->CI->reminder_recipient_resolver->technicalAdmins()
            : $this->CI->db->select('staffid')->where('admin', 1)->where('active', 1)->get(db_prefix() . 'staff')->result();
        foreach ($admins as $admin) {
            $adminStaffId = is_object($admin) ? (int) $admin->staffid : (int) $admin['staffid'];
            add_notification([
                'description' => 'sales_pipeline_reminder_email_bcc_quota_alert',
                'touserid' => $adminStaffId,
                'fromuserid' => null,
                'link' => 'sales_pipeline/settings#reminders',
                'additional_data' => serialize([]),
            ]);
        }
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
            return in_array($channel, ['crm', 'email', 'whatsapp'], true);
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
        $staff = $this->staffById($staffId);
        return $staff && (int) $staff->active === 1 ? $staff : null;
    }

    private function staffById($staffId)
    {
        $staffId = (int) $staffId;
        if ($staffId <= 0) {
            return null;
        }
        if (!array_key_exists($staffId, $this->staffCache)) {
            $this->staffCache[$staffId] = $this->CI->staff_model->get($staffId) ?: null;
        }
        return $this->staffCache[$staffId];
    }

    private function staffByEmail($email)
    {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return null;
        }
        if (!array_key_exists($email, $this->staffEmailCache)) {
            $staff = $this->CI->db->where('email', $email)->get(db_prefix() . 'staff')->row();
            $this->staffEmailCache[$email] = $staff ?: null;
            if ($staff && !empty($staff->staffid)) {
                $this->staffCache[(int) $staff->staffid] = $staff;
            }
        }
        return $this->staffEmailCache[$email];
    }

    /** Business recipients use the same global-view capability as the dashboard. */
    private function activeManagerStaff()
    {
        if ($this->activeManagerStaffCache === null) {
            $activeStaff = $this->CI->db->where('active', 1)
                ->get(db_prefix() . 'staff')->result();
            $this->activeManagerStaffCache = [];
            foreach ($activeStaff as $staff) {
                $staffId = (int) $staff->staffid;
                if ((int) $staff->admin === 1
                    || has_permission('sales_pipeline', (string) $staffId, 'view')) {
                    $this->activeManagerStaffCache[] = $staff;
                    $this->staffCache[$staffId] = $staff;
                }
            }
        }
        return $this->activeManagerStaffCache;
    }

    private function isV2Enabled()
    {
        if (isset($this->CI->reminder_recipient_resolver)) {
            return $this->CI->reminder_recipient_resolver->isV2Enabled();
        }
        return (string) $this->option('sp_reminder_recipient_policy_v2_enabled') === '1';
    }

    private function isPureSupervisoryManager($staffId)
    {
        $staffId = (int) $staffId;
        if ($staffId <= 0) {
            return false;
        }
        $staff = $this->staffById($staffId);
        if (!$staff) {
            return false;
        }
        if ((int) $staff->admin === 1) {
            return true;
        }
        if ($this->isV2Enabled() && isset($this->CI->reminder_recipient_resolver)) {
            $managers = $this->CI->reminder_recipient_resolver->businessManagers();
            foreach ($managers as $m) {
                if ((int) $m->staffid === $staffId) {
                    return true;
                }
            }
            return in_array($staffId, $this->CI->reminder_recipient_resolver->explicitViewStaffIds(), true);
        }
        return has_permission('sales_pipeline', (string) $staffId, 'view');
    }

    private function hasStaffEstimateActivity($staffId, DateTimeImmutable $now)
    {
        $monthStart = $now->modify('first day of this month')->format('Y-m-d 00:00:00');
        $monthEnd = $now->modify('first day of next month')->format('Y-m-d 00:00:00');
        $count = $this->validEstimateCount($staffId, $monthStart, $monthEnd);
        if ($count > 0) {
            return true;
        }
        $revenue = $this->acceptedRevenue($staffId, $monthStart, $monthEnd);
        return $revenue > 0;
    }
}
