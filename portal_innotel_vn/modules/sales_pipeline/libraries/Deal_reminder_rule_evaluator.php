<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pure evaluator for Deal reminder rules.
 *
 * Database reads and delivery remain owned by Reminder_engine. Keeping this
 * class free of CodeIgniter dependencies makes checkpoint and stale-age rules
 * deterministic and directly testable.
 */
class Deal_reminder_rule_evaluator
{
    public function evaluatePipelineMinimum(array $staff, DateTimeImmutable $now, array $config)
    {
        if (empty($config['enabled']) || empty($config['channels'])) {
            return null;
        }

        $day = (int) $now->format('N');
        $checkpoints = [1 => 'START_WEEK', 3 => 'MIDWEEK', 5 => 'FINAL'];
        if (!isset($checkpoints[$day]) || $now->format('H:i') < (string) $config['check_time']) {
            return null;
        }

        $actual = (int) ($staff['open_deal_count'] ?? 0);
        $required = max(1, (int) ($config['minimum_count'] ?? 1));
        if ($actual >= $required) {
            return null;
        }

        $checkpoint = $checkpoints[$day];
        $managerCC = $checkpoint === 'FINAL';
        $staffId = (int) $staff['staff_id'];
        $period = $now->format('o-\WW');

        return [
            'rule_code' => 'DEAL_PIPELINE_MIN_COUNT',
            'entity_type' => 'staff_deal_period',
            'entity_id' => null,
            'pipeline_id' => null,
            'staff_id' => $staffId,
            'period_key' => $period,
            'checkpoint' => $checkpoint,
            'severity' => $managerCC ? 'critical' : 'warning',
            'response_required' => 1,
            'recipients' => ['staff'],
            'channels' => array_values($config['channels']),
            'manager_cc' => $managerCC,
            'dedupe_key' => implode(':', ['DEAL_PIPELINE_MIN_COUNT', $staffId, $period, $checkpoint]),
            'snapshot' => [
                'actual_count' => $actual,
                'required_count' => $required,
                'open_pipeline_value' => (float) ($staff['open_pipeline_value'] ?? 0),
                'manager_cc' => $managerCC,
                'evaluated_at' => $now->format('Y-m-d H:i:s'),
            ],
        ];
    }

    public function evaluateStaleFollowUp(array $deal, DateTimeImmutable $now, array $config)
    {
        if (empty($config['enabled']) || empty($config['channels'])) {
            return null;
        }

        $lastActivity = trim((string) ($deal['last_meaningful_activity_at'] ?? ''));
        if ($lastActivity === '') {
            return null;
        }
        try {
            $lastActivityAt = new DateTimeImmutable($lastActivity);
        } catch (Exception $e) {
            return null;
        }

        $requiredDays = max(1, (int) ($deal['reminder_frequency'] ?? 1));
        $inactiveDays = (int) $lastActivityAt->setTime(0, 0)->diff($now->setTime(0, 0))->format('%r%a');
        if ($inactiveDays < $requiredDays) {
            return null;
        }
        $cutoffDays = max(1, (int) ($config['cutoff_days'] ?? 30));
        if ($inactiveDays > $cutoffDays) {
            return null;
        }

        $dealId = (int) $deal['id'];
        $staffId = (int) $deal['staff_id'];
        $activityKey = $lastActivityAt->format('Y-m-d\TH:i:s');

        return [
            'rule_code' => 'DEAL_STALE_FOLLOW_UP',
            'entity_type' => 'deal',
            'entity_id' => $dealId,
            'pipeline_id' => $dealId,
            'staff_id' => $staffId,
            'period_key' => $lastActivityAt->format('Y-m-d'),
            'checkpoint' => 'STALE',
            'severity' => 'warning',
            'response_required' => 1,
            'recipients' => ['staff'],
            'channels' => array_values($config['channels']),
            'manager_cc' => false,
            'dedupe_key' => implode(':', ['DEAL_STALE_FOLLOW_UP', $dealId, $activityKey, $requiredDays]),
            'snapshot' => [
                'deal_name' => (string) ($deal['deal_name'] ?? ''),
                'customer_name' => (string) ($deal['customer_name'] ?? ''),
                'deal_value' => (float) ($deal['deal_value'] ?? 0),
                'deal_date' => $deal['deal_date'] ?? null,
                'status_name' => (string) ($deal['status_name'] ?? ''),
                'last_meaningful_activity_at' => $lastActivityAt->format('Y-m-d H:i:s'),
                'inactive_days' => $inactiveDays,
                'required_days' => $requiredDays,
                'manager_cc' => false,
                'evaluated_at' => $now->format('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * Evaluate all stale Deals for one Cron run. Individual reminders are
     * capped deterministically; every remaining item is represented by one
     * staff-level backlog reminder.
     */
    public function evaluateStaleFollowUps(array $deals, DateTimeImmutable $now, array $config)
    {
        if (empty($config['enabled']) || empty($config['channels'])) {
            return [];
        }

        $groups = [];
        foreach ($deals as $deal) {
            $staffId = (int) ($deal['staff_id'] ?? 0);
            if ($staffId > 0) {
                $groups[$staffId][] = $deal;
            }
        }

        $events = [];
        foreach ($groups as $staffId => $staffDeals) {
            $events = array_merge($events, $this->evaluateStaleFollowUpsForStaff(
                (int) $staffId,
                $staffDeals,
                $now,
                $config
            ));
        }
        return $events;
    }

    private function evaluateStaleFollowUpsForStaff($staffId, array $deals, DateTimeImmutable $now, array $config)
    {
        $cutoffDays = max(1, (int) ($config['cutoff_days'] ?? 30));
        $maxPerRun = max(1, (int) ($config['max_per_run'] ?? 5));
        $individualCandidates = [];
        $longStale = [];

        foreach ($deals as $deal) {
            $context = $this->staleContext($deal, $now);
            if (!$context || $context['inactive_days'] < $context['required_days']) {
                continue;
            }
            if ($context['inactive_days'] > $cutoffDays) {
                $longStale[] = [$deal, $context];
            } else {
                $individualCandidates[] = [$deal, $context];
            }
        }

        usort($individualCandidates, function ($left, $right) {
            if ($left[1]['inactive_days'] !== $right[1]['inactive_days']) {
                return $right[1]['inactive_days'] <=> $left[1]['inactive_days'];
            }
            $activityOrder = strcmp($left[1]['last_activity_at']->format('Y-m-d H:i:s'), $right[1]['last_activity_at']->format('Y-m-d H:i:s'));
            return $activityOrder !== 0 ? $activityOrder : ((int) $left[0]['id'] <=> (int) $right[0]['id']);
        });

        $selected = array_slice($individualCandidates, 0, $maxPerRun);
        $events = [];
        foreach ($selected as [$deal]) {
            $event = $this->evaluateStaleFollowUp($deal, $now, $config);
            if ($event) {
                $events[] = $event;
            }
        }

        $overflow = array_slice($individualCandidates, $maxPerRun);
        if (!$overflow && !$longStale) {
            return $events;
        }

        $attention = array_merge($overflow, $longStale);
        usort($attention, function ($left, $right) {
            return $right[1]['inactive_days'] <=> $left[1]['inactive_days'];
        });
        $fingerprintParts = [];
        foreach ($attention as [$deal, $context]) {
            $fingerprintParts[] = (int) $deal['id'] . '@' . $context['last_activity_at']->format('Y-m-d\TH:i:s');
        }
        sort($fingerprintParts, SORT_STRING);
        $fingerprint = hash('sha256', implode('|', $fingerprintParts));
        $totalValue = 0.0;
        foreach ($attention as [$deal]) {
            $totalValue += (float) ($deal['deal_value'] ?? 0);
        }
        $topDeals = [];
        foreach (array_slice($attention, 0, 5) as [$deal, $context]) {
            $topDeals[] = [
                'deal_id' => (int) $deal['id'],
                'deal_name' => (string) ($deal['deal_name'] ?? ''),
                'customer_name' => (string) ($deal['customer_name'] ?? ''),
                'inactive_days' => $context['inactive_days'],
                'deal_value' => (float) ($deal['deal_value'] ?? 0),
            ];
        }
        $events[] = [
            'rule_code' => 'DEAL_STALE_BACKLOG',
            'entity_type' => 'staff_deal_backlog',
            'entity_id' => null,
            'pipeline_id' => null,
            'staff_id' => (int) $staffId,
            'period_key' => $now->format('Y-m-d'),
            'checkpoint' => 'BACKLOG',
            'severity' => 'warning',
            'response_required' => 1,
            'recipients' => ['staff'],
            'channels' => array_values($config['channels']),
            'manager_cc' => false,
            'dedupe_key' => implode(':', ['DEAL_STALE_BACKLOG', (int) $staffId, $fingerprint, $cutoffDays]),
            'snapshot' => [
                'individual_sent' => count($selected),
                'stale_overflow_count' => count($overflow),
                'long_stale_count' => count($longStale),
                'cutoff_days' => $cutoffDays,
                'oldest_inactive_days' => $attention ? $attention[0][1]['inactive_days'] : 0,
                'total_value' => $totalValue,
                'total_attention_required' => count($attention) + count($selected),
                'top_deals' => $topDeals,
                'evaluated_at' => $now->format('Y-m-d H:i:s'),
            ],
        ];
        return $events;
    }

    private function staleContext(array $deal, DateTimeImmutable $now)
    {
        $lastActivity = trim((string) ($deal['last_meaningful_activity_at'] ?? ''));
        if ($lastActivity === '') {
            return null;
        }
        try {
            $lastActivityAt = new DateTimeImmutable($lastActivity);
        } catch (Exception $e) {
            return null;
        }
        return [
            'last_activity_at' => $lastActivityAt,
            'required_days' => max(1, (int) ($deal['reminder_frequency'] ?? 1)),
            'inactive_days' => (int) $lastActivityAt->setTime(0, 0)->diff($now->setTime(0, 0))->format('%r%a'),
        ];
    }
}
