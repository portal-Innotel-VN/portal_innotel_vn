<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Estimate_group_reconciliation_policy
{
    /**
     * Decide whether reconciliation has a material repair to persist.
     * Existing non-null decision values are immutable in the normal reconcile path.
     */
    public function should_persist(array $group, $newOutcome, $decisionEstimateId, $decisionValueBase)
    {
        if (($group['outcome'] ?? null) !== $newOutcome) {
            return true;
        }

        if ($newOutcome !== 'pending'
            && (empty($group['decision_at']) || empty($group['decision_estimate_id']))) {
            return true;
        }

        return $newOutcome === 'accepted'
            && ($group['decision_value_base'] ?? null) === null
            && (int) ($group['decision_estimate_id'] ?? 0) === (int) $decisionEstimateId
            && $decisionValueBase !== null;
    }
}
