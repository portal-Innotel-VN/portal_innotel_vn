<?php

/**
 * SQL EXPLAIN and Execution Plan Analysis
 *
 * Runs EXPLAIN on the 4 canonical production queries on the local database:
 * 1. Canonical Quote Count (Quote_count_repository)
 * 2. Authoritative Rate lookup (Authoritative_exchange_rate_provider)
 * 3. Performance snapshot read (Performance_score_service)
 * 4. Reminder SLA aggregation (Sales_pipeline_model)
 */

define('BASEPATH', 'dummy');
require_once __DIR__ . '/../../../application/config/app-config.php';

function run_explain_analysis()
{
    $mysqli = new mysqli('127.0.0.1', APP_DB_USERNAME, APP_DB_PASSWORD, APP_DB_NAME, 3306);
    if ($mysqli->connect_error) {
        throw new RuntimeException("DB connect failed: " . $mysqli->connect_error);
    }

    echo "==================== SQL EXPLAIN EXECUTION PLAN REPORT ====================\n";

    // 1. Canonical Quote Count Query
    echo "\n--- 1. CANONICAL QUOTE COUNT QUERY ---\n";
    $sql1 = "
        EXPLAIN FORMAT=TRADITIONAL SELECT grp.owner_staff_id, COUNT(DISTINCT grp.id) as quote_count
        FROM tblsales_pipeline_estimate_groups grp
        JOIN tblsales_pipeline_estimate_versions v ON v.estimate_group_id = grp.id
        JOIN tblestimates e ON e.id = v.estimate_id
        WHERE grp.owner_staff_id IN (1, 2, 5)
          AND grp.first_sent_at >= '2026-09-01 00:00:00'
          AND grp.first_sent_at < '2026-10-01 00:00:00'
          AND e.status IN (2, 3, 4, 5)
        GROUP BY grp.owner_staff_id
    ";
    print_explain($mysqli, $sql1);

    // 2. Authoritative Rate Lookup Query
    echo "\n--- 2. AUTHORITATIVE EXCHANGE RATE LOOKUP QUERY ---\n";
    $sql2 = "
        EXPLAIN FORMAT=TRADITIONAL SELECT *
        FROM tblsales_pipeline_exchange_rates
        WHERE source_currency_id = 2
          AND base_currency_id = 1
          AND rate_unit = 'base_currency_per_source_currency'
          AND is_active = 1
          AND approved_at IS NOT NULL
          AND effective_date <= '2026-09-02'
        ORDER BY effective_date DESC, rate_version DESC
        LIMIT 1
    ";
    print_explain($mysqli, $sql2);

    // 3. Performance Snapshot Read Query
    echo "\n--- 3. PERFORMANCE SNAPSHOT READ QUERY ---\n";
    $sql3 = "
        EXPLAIN FORMAT=TRADITIONAL SELECT *
        FROM tblsales_pipeline_score_snapshots
        WHERE period_type = 'month'
          AND period_start = '2026-09-01'
          AND period_end = '2026-09-30'
          AND formula_version = 'performance_score_v2'
    ";
    print_explain($mysqli, $sql3);

    // 4. Reminder SLA Aggregation Query
    echo "\n--- 4. REMINDER SLA AGGREGATION QUERY ---\n";
    $sql4 = "
        EXPLAIN FORMAT=TRADITIONAL SELECT staff_id,
               COUNT(id) as eligible_reminders,
               SUM(CASE WHEN responded_at IS NOT NULL AND responded_at <= response_due_at THEN 1 ELSE 0 END) as on_time_reminders
        FROM tblsales_pipeline_reminders_log
        WHERE staff_id IN (1, 2, 5)
          AND entity_type IN ('estimate', 'staff_estimate_period')
          AND response_required = 1
          AND response_due_at IS NOT NULL
          AND response_due_at >= '2026-09-01 00:00:00'
          AND response_due_at < '2026-10-01 00:00:00'
          AND response_due_at <= '2026-09-03 11:00:00'
          AND COALESCE(data_quality_status, 'verified') != 'legacy_unverified'
        GROUP BY staff_id
    ";
    print_explain($mysqli, $sql4);

    $mysqli->close();
    echo "\n==================== END OF SQL EXPLAIN REPORT ====================\n";
}

function print_explain($mysqli, $sql)
{
    $res = $mysqli->query($sql);
    if (!$res) {
        echo "Query error: " . $mysqli->error . "\n";
        return;
    }
    printf("%-5s | %-12s | %-35s | %-10s | %-30s | %-6s | %s\n", "id", "select_type", "table", "type", "key", "rows", "Extra");
    echo str_repeat("-", 120) . "\n";
    while ($row = $res->fetch_assoc()) {
        printf(
            "%-5s | %-12s | %-35s | %-10s | %-30s | %-6s | %s\n",
            $row['id'] ?? '',
            $row['select_type'] ?? '',
            $row['table'] ?? '',
            $row['type'] ?? '',
            $row['key'] ?? 'NULL',
            $row['rows'] ?? '',
            $row['Extra'] ?? ''
        );
    }
}

run_explain_analysis();
