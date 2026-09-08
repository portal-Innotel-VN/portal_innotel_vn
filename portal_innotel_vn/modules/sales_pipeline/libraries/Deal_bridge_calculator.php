<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pure decision engine for the Estimate Group -> Deal bridge.
 *
 * Money is converted to integer cents before addition so binary floating point
 * never decides a persisted DECIMAL(15,2) value.
 */
class Deal_bridge_calculator
{
    const MAX_DEAL_VALUE_CENTS = 999999999999999;

    public function calculate(array $groups, $primaryBaseTotal, $manualLocked = false, $financeLocked = false)
    {
        if ($financeLocked) {
            return ['action' => 'preserve', 'reason' => 'finance_locked'];
        }

        if ($manualLocked) {
            return ['action' => 'preserve', 'reason' => 'manual_lock'];
        }

        if (empty($groups)) {
            return ['action' => 'apply', 'estimate_id' => null, 'status_intent' => null];
        }

        $accepted = [];
        $declinedCount = 0;
        foreach ($groups as $group) {
            if (($group['outcome'] ?? null) === 'accepted') {
                $accepted[] = $group;
            }
            if (($group['outcome'] ?? null) === 'declined') {
                $declinedCount++;
            }
        }

        if (!empty($accepted)) {
            $totalCents = 0;
            foreach ($accepted as $group) {
                if (!array_key_exists('decision_value_base', $group)
                    || $group['decision_value_base'] === null
                    || $group['decision_value_base'] === '') {
                    return ['action' => 'fail', 'reason' => 'accepted_value_missing'];
                }

                $cents = $this->toCents($group['decision_value_base']);
                if ($cents === null) {
                    return ['action' => 'fail', 'reason' => 'accepted_value_invalid'];
                }
                if ($totalCents > self::MAX_DEAL_VALUE_CENTS - $cents) {
                    return ['action' => 'fail', 'reason' => 'deal_value_overflow'];
                }
                $totalCents += $cents;
            }

            return [
                'action'        => 'apply',
                'deal_value'    => $this->formatCents($totalCents),
                'status_intent' => 'won',
            ];
        }

        if ($declinedCount === count($groups)) {
            return ['action' => 'apply', 'status_intent' => 'lost'];
        }

        if ($primaryBaseTotal === null || $primaryBaseTotal === '') {
            return ['action' => 'preserve', 'reason' => 'pending_base_total_missing'];
        }

        $cents = $this->toCents($primaryBaseTotal);
        if ($cents === null) {
            return ['action' => 'fail', 'reason' => 'pending_base_total_invalid'];
        }
        if ($cents > self::MAX_DEAL_VALUE_CENTS) {
            return ['action' => 'fail', 'reason' => 'deal_value_overflow'];
        }

        $primary = null;
        foreach ($groups as $group) {
            if ((int) ($group['is_primary'] ?? 0) === 1) {
                $primary = $group;
                break;
            }
        }
        $primary = $primary ?: $groups[0];

        return [
            'action'        => 'apply',
            'deal_value'    => $this->formatCents($cents),
            'estimate_id'   => (int) ($primary['current_estimate_id'] ?? 0),
            'status_intent' => null,
        ];
    }

    private function toCents($value)
    {
        $raw = trim((string) $value);
        if (!preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,2}))?$/', $raw, $matches)) {
            return null;
        }

        $fraction = str_pad($matches[2] ?? '', 2, '0');
        $integer = ltrim($matches[1], '0');
        $digits = ($integer === '' ? '0' : $integer) . $fraction;
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return 0;
        }
        if (strlen($digits) > 15) {
            return self::MAX_DEAL_VALUE_CENTS + 1;
        }

        return (int) $digits;
    }

    private function formatCents($cents)
    {
        $raw = str_pad((string) $cents, 3, '0', STR_PAD_LEFT);
        return substr($raw, 0, -2) . '.' . substr($raw, -2);
    }
}
