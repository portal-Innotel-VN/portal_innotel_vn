<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Reminder_whatsapp_formatter
{
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Format a reminder delivery row into a concise, scannable WhatsApp Markdown message.
     *
     * @param array $row Delivery row with joined reminder data
     * @return string
     */
    public function format(array $row)
    {
        $severity = !empty($row['severity']) ? (string) $row['severity'] : 'warning';
        $icon = '🟡';
        $badgeText = _l('sales_pipeline_reminder_badge_warning');
        if ($severity === 'critical') {
            $icon = '🔴';
            $badgeText = _l('sales_pipeline_reminder_badge_critical');
        } elseif ($severity === 'info' || $severity === 'notice') {
            $icon = 'ℹ️';
            $badgeText = _l('sales_pipeline_reminder_badge_notice');
        }

        $title = !empty($row['title']) ? trim((string) $row['title']) : _l('sales_pipeline_reminder');
        $message = !empty($row['message']) ? trim((string) $row['message']) : '';
        $snapshot = !empty($row['snapshot_json']) ? json_decode((string) $row['snapshot_json'], true) : [];

        $lines = [];
        $lines[] = "{$icon} *[" . mb_strtoupper($badgeText, 'UTF-8') . " - SALES PIPELINE]*";
        $lines[] = "*{$title}*";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━━━";

        // Staff in charge
        $staffName = $this->resolveStaffName((int) ($row['staff_id'] ?? 0));
        if ($staffName !== '') {
            $lines[] = "👤 *" . _l('sp_reminder_whatsapp_msg_staff') . ":* {$staffName}";
        }

        // Entity details from snapshot
        if (!empty($snapshot['customer_name'])) {
            $lines[] = "🏢 *" . _l('sp_reminder_whatsapp_msg_customer') . ":* " . trim((string) $snapshot['customer_name']);
        }

        if (!empty($snapshot['deal_name'])) {
            $dealValStr = '';
            if (isset($snapshot['deal_value']) && is_numeric($snapshot['deal_value'])) {
                $dealValStr = ' (' . number_format((float) $snapshot['deal_value'], 0, ',', '.') . ' đ)';
            }
            $lines[] = "💼 *" . _l('sp_reminder_whatsapp_msg_deal') . ":* " . trim((string) $snapshot['deal_name']) . $dealValStr;
        } elseif (!empty($snapshot['estimate_number'])) {
            $estValStr = '';
            if (isset($snapshot['estimate_total']) && is_numeric($snapshot['estimate_total'])) {
                $estValStr = ' (' . number_format((float) $snapshot['estimate_total'], 0, ',', '.') . ' đ)';
            }
            $lines[] = "📄 *" . _l('sp_reminder_whatsapp_msg_estimate') . ":* #" . trim((string) $snapshot['estimate_number']) . $estValStr;
        }

        if (!empty($snapshot['risk_reason'])) {
            $lines[] = "⚠️ *" . _l('sp_reminder_whatsapp_msg_issue') . ":* " . trim((string) $snapshot['risk_reason']);
        } elseif ($message !== '') {
            $cleanMsg = strip_tags($message);
            if (mb_strlen($cleanMsg) > 200) {
                $cleanMsg = mb_substr($cleanMsg, 0, 197) . '...';
            }
            $lines[] = "⚠️ *" . _l('sp_reminder_whatsapp_msg_content') . ":* {$cleanMsg}";
        }

        if (!empty($snapshot['inactive_days'])) {
            $lines[] = "⏳ *" . _l('sp_reminder_whatsapp_msg_inactive_time') . ":* " . (int) $snapshot['inactive_days'] . " " . _l('sp_reminder_whatsapp_msg_days');
        }

        // Action links with custom base URL if configured
        $lines[] = "";
        $lines[] = "👇 *" . _l('sp_reminder_whatsapp_msg_quick_action') . ":*";

        $staffId = !empty($row['staff_id']) ? (int) $row['staff_id'] : 0;
        $dealId = !empty($row['pipeline_id']) ? (int) $row['pipeline_id'] : (int) ($row['entity_id'] ?? 0);
        if ($row['entity_type'] === 'deal' && $dealId > 0) {
            $dealUrl = $this->buildMobileUrl('sales_pipeline/deal/' . $dealId);
            $lines[] = "👉 *" . _l('sp_reminder_whatsapp_msg_view_deal') . ":* {$dealUrl}";
        } elseif ($row['entity_type'] === 'estimate' && !empty($row['entity_id'])) {
            $estUrl = $this->buildMobileUrl('estimates/list_estimates/' . (int) $row['entity_id']);
            $lines[] = "👉 *" . _l('sp_reminder_whatsapp_msg_view_estimate') . ":* {$estUrl}";
        }

        // Deeplink to Estimates Revenue Chart (filtered by staff for Management review)
        if ($staffId > 0) {
            $chartUrl = $this->buildMobileUrl('sales_pipeline/dashboard?dashboard_tab=estimates&staff_id=' . $staffId);
            $chartLabel = $staffName !== ''
                ? sprintf(_l('sp_reminder_whatsapp_msg_view_staff_revenue_chart'), $staffName)
                : _l('sp_reminder_whatsapp_msg_view_revenue_chart');
            $lines[] = "📊 *{$chartLabel}:* {$chartUrl}";
        } elseif ($row['entity_type'] === 'estimate') {
            $chartUrl = $this->buildMobileUrl('sales_pipeline/dashboard?dashboard_tab=estimates');
            $lines[] = "📊 *" . _l('sp_reminder_whatsapp_msg_view_revenue_chart') . ":* {$chartUrl}";
        }

        if (!empty($row['reminder_id'])) {
            $respUrl = $this->buildMobileUrl('sales_pipeline/reminder_response/' . (int) $row['reminder_id'] . '?src=wa');
            $lines[] = "👉 *" . _l('sp_reminder_whatsapp_msg_view_reminder') . ":* {$respUrl}";
        }

        return implode("\n", $lines);
    }

    /**
     * Build URL accessible from mobile devices based on sp_reminder_whatsapp_base_url.
     */
    public function buildMobileUrl($path)
    {
        $customBase = trim((string) get_option('sp_reminder_whatsapp_base_url'));
        if ($customBase !== '') {
            return rtrim($customBase, '/') . '/admin/' . ltrim($path, '/');
        }
        return admin_url($path);
    }

    /**
     * Normalize raw phone number into a WhatsApp JID (e.g. 84901234567@s.whatsapp.net).
     * Supports both static and instance calls.
     *
     * @param string $phone
     * @return string|null
     */
    public static function formatPhoneNumberToJid($phone)
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }

        // If it's already a group or user JID
        if (strpos($raw, '@g.us') !== false || strpos($raw, '@s.whatsapp.net') !== false) {
            return $raw;
        }

        // Strip non-numeric characters
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return null;
        }

        // Vietnam local prefix conversion: 090... -> 8490...
        if (substr($digits, 0, 1) === '0') {
            $digits = '84' . substr($digits, 1);
        }

        // Minimal length check for international phone numbers
        if (strlen($digits) < 9 || strlen($digits) > 15) {
            return null;
        }

        return $digits . '@s.whatsapp.net';
    }

    public function normalizePhoneToJid($phone)
    {
        return self::formatPhoneNumberToJid($phone);
    }

    private function resolveStaffName($staffId)
    {
        if ($staffId <= 0) {
            return '';
        }
        $staff = $this->CI->db->select('firstname, lastname')->where('staffid', $staffId)->get(db_prefix() . 'staff')->row();
        if ($staff) {
            return trim($staff->firstname . ' ' . $staff->lastname);
        }
        return '#' . $staffId;
    }
}
