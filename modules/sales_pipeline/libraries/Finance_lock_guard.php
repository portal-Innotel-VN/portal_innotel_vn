<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Finance Lock Guard
 *
 * Enforces financial integrity and audit locks.
 * Prevents write operations on entities that have been finalized/locked by Finance:
 * - Version exchange rate / base_total refresh
 * - Estimate Group decision estimate / value changes
 * - Deal value / status sync via bridge
 * - Reconcile overrides
 * - Sanitizer revaluation
 * - Manual editing
 */
class Finance_lock_guard
{
    /**
     * Determine if entity can be modified.
     *
     * @param array|object $entity
     * @return bool
     */
    public function can_modify($entity)
    {
        $result = $this->check_can_modify($entity);
        return $result['allowed'];
    }

    /**
     * Check if entity can be modified with explicit reason code.
     *
     * @param array|object $entity
     * @return array ['allowed' => bool, 'reason_code' => string]
     */
    public function check_can_modify($entity)
    {
        if (is_object($entity)) {
            $isLocked = !empty($entity->is_finance_locked);
            $lockedAt = $entity->finance_locked_at ?? null;
            $ref = $entity->finance_approval_reference ?? null;
        } else {
            $isLocked = !empty($entity['is_finance_locked']);
            $lockedAt = $entity['finance_locked_at'] ?? null;
            $ref = $entity['finance_approval_reference'] ?? null;
        }

        if ($isLocked) {
            return [
                'allowed'     => false,
                'reason_code' => 'finance_locked',
                'locked_at'   => $lockedAt,
                'reference'   => $ref,
            ];
        }

        return [
            'allowed'     => true,
            'reason_code' => 'unlocked',
        ];
    }
}
