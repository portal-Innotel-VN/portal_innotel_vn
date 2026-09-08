<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Performance Score Dispatcher
 *
 * Resolves the appropriate calculator implementation (V1 legacy vs V2)
 * based on period type, period boundaries, and cutover rules.
 *
 * Cutover Matrix (Timezone: Asia/Ho_Chi_Minh):
 * - Month:   < 2026-09-01 -> V1, >= 2026-09-01 -> V2
 * - Week:    < 2026-09-07 -> V1, >= 2026-09-07 -> V2 (week 31/08-06/09 starts in Aug -> V1)
 * - Quarter: < 2026-10-01 -> V1, >= 2026-10-01 -> V2 (Q3 2026 -> V1, Q4 2026 -> V2)
 * - Year:    < 2027-01-01 -> V1, >= 2027-01-01 -> V2 (2026 -> V1, 2027 -> V2)
 */
class Performance_score_dispatcher
{
    const CUTOVER_DATE = '2026-09-01';

    /**
     * Resolve formula version string.
     *
     * @param string $periodType 'month', 'week', 'quarter', 'year', 'custom'
     * @param string $periodStart 'YYYY-MM-DD'
     * @param string $periodEnd 'YYYY-MM-DD'
     * @return string 'performance_score_v1' or 'performance_score_v2'
     */
    public function resolve_formula_version($periodType, $periodStart, $periodEnd)
    {
        $startDate = substr((string) $periodStart, 0, 10);
        $periodType = strtolower((string) $periodType);
        $periodAliases = [
            'this_week'    => 'week',
            'this_month'   => 'month',
            'this_quarter' => 'quarter',
            'this_year'    => 'year',
        ];
        $periodType = $periodAliases[$periodType] ?? $periodType;

        switch ($periodType) {
            case 'week':
                // Week starting on or after 2026-09-07 uses V2
                return $startDate >= '2026-09-07' ? 'performance_score_v2' : 'performance_score_v1';

            case 'quarter':
                // Quarter starting on or after 2026-10-01 (Q4 2026) uses V2
                return $startDate >= '2026-10-01' ? 'performance_score_v2' : 'performance_score_v1';

            case 'year':
                // Year starting on or after 2027-01-01 uses V2
                return $startDate >= '2027-01-01' ? 'performance_score_v2' : 'performance_score_v1';

            case 'month':
            default:
                // Month and custom periods starting on or after 2026-09-01 use V2
                return $startDate >= self::CUTOVER_DATE ? 'performance_score_v2' : 'performance_score_v1';
        }
    }

    /**
     * Factory method to get calculator instance.
     *
     * @param string $formulaVersion
     * @return object
     */
    public function get_calculator($formulaVersion)
    {
        if ($formulaVersion === 'performance_score_v2') {
            require_once __DIR__ . '/Performance_score_calculator_v2.php';
            return new Performance_score_calculator_v2();
        }

        require_once __DIR__ . '/Performance_score_calculator_v1.php';
        return new Performance_score_calculator_v1();
    }
}
