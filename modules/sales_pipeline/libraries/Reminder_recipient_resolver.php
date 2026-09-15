<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Reminder_recipient_resolver
 *
 * Centralized resolution and validation of reminder notification recipients.
 * Decouples data access (global view permissions) from alert responsibility
 * (business managers vs technical admins vs staff owners).
 */
class Reminder_recipient_resolver
{
    /** @var object CodeIgniter super-object */
    private $CI;

    /** @var array|null In-request cache for explicit view staff IDs */
    private $explicitViewCache = null;

    /** @var array|null In-request cache for validated selected staff IDs */
    private $selectedStaffCache = null;

    /** @var array|null In-request cache for business managers */
    private $businessManagersCache = null;

    /** @var array|null In-request cache for technical admins */
    private $technicalAdminsCache = null;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Clear all in-request caches at the beginning of a dispatch/retry pass or test.
     *
     * @return void
     */
    public function clearCache()
    {
        $this->explicitViewCache = null;
        $this->selectedStaffCache = null;
        $this->businessManagersCache = null;
        $this->technicalAdminsCache = null;
    }

    /**
     * Check whether Policy V2 is enabled.
     *
     * @return bool
     */
    public function isV2Enabled()
    {
        return (string) get_option('sp_reminder_recipient_policy_v2_enabled') === '1';
    }

    /**
     * Get the active policy source ('explicit_view' or 'selected_staff').
     *
     * @return string
     */
    public function getPolicySource()
    {
        $source = (string) get_option('sp_reminder_manager_recipient_source');
        return in_array($source, ['explicit_view', 'selected_staff'], true) ? $source : 'explicit_view';
    }

    /**
     * Resolve staff IDs who have an explicit `sales_pipeline:view` row in `tblstaff_permissions`.
     * Bypasses `is_admin()` and `has_permission()` shortcuts.
     * Only active staff are returned.
     *
     * @return int[]
     */
    public function explicitViewStaffIds()
    {
        if ($this->explicitViewCache !== null) {
            return $this->explicitViewCache;
        }

        $rows = $this->CI->db->query(
            'SELECT DISTINCT p.staff_id '
            . 'FROM `' . db_prefix() . 'staff_permissions` p '
            . 'JOIN `' . db_prefix() . 'staff` s ON s.staffid = p.staff_id '
            . "WHERE p.feature = 'sales_pipeline' "
            . "  AND p.capability = 'view' "
            . '  AND s.active = 1'
        )->result_array();

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['staff_id'];
        }

        $this->explicitViewCache = $ids;
        return $this->explicitViewCache;
    }

    /**
     * Resolve active technical admins for infrastructure alerts.
     *
     * @return array
     */
    public function technicalAdmins()
    {
        if ($this->technicalAdminsCache !== null) {
            return $this->technicalAdminsCache;
        }

        $this->technicalAdminsCache = $this->CI->db->select('staffid, firstname, lastname, email, phonenumber')
            ->where('admin', 1)
            ->where('active', 1)
            ->get(db_prefix() . 'staff')
            ->result();

        return $this->technicalAdminsCache;
    }

    /**
     * Resolve validated selected staff IDs from the configured JSON option.
     * Each staff must be active = 1 AND possess global view (either admin = 1 or explicit view).
     * Staff with only view_own are strictly excluded.
     *
     * @param array|null $overrideIds Optional array of IDs to validate (e.g. for preview or settings validation)
     * @return int[]
     */
    public function selectedStaffIds(?array $overrideIds = null)
    {
        if ($overrideIds === null && $this->selectedStaffCache !== null) {
            return $this->selectedStaffCache;
        }

        if ($overrideIds !== null) {
            $rawIds = $overrideIds;
        } else {
            $rawJson = (string) get_option('sp_reminder_manager_recipient_staff_ids');
            $decoded = json_decode($rawJson, true);
            $rawIds = is_array($decoded) ? $decoded : [];
        }

        $candidateIds = array_values(array_unique(array_filter(array_map('intval', $rawIds), function ($id) {
            return $id > 0;
        })));

        if (empty($candidateIds)) {
            if ($overrideIds === null) {
                $this->selectedStaffCache = [];
            }
            return [];
        }

        $explicitIds = $this->explicitViewStaffIds();

        // Query active candidate staff
        $staffRows = $this->CI->db->select('staffid, admin, active')
            ->where_in('staffid', $candidateIds)
            ->where('active', 1)
            ->get(db_prefix() . 'staff')
            ->result_array();

        $validIds = [];
        foreach ($staffRows as $staff) {
            $sid = (int) $staff['staffid'];
            $isAdmin = (int) $staff['admin'] === 1;
            $hasExplicitView = in_array($sid, $explicitIds, true);

            // Must have global view (admin or explicit sales_pipeline:view)
            if ($isAdmin || $hasExplicitView) {
                $validIds[] = $sid;
            }
        }

        if ($overrideIds === null) {
            $this->selectedStaffCache = $validIds;
        }

        return $validIds;
    }

    /**
     * Resolve list of business managers based on active policy mode.
     * If V2 is disabled, returns legacy managers (admin = 1 OR has_permission view).
     *
     * @return array Array of staff objects (staffid, firstname, lastname, email, phonenumber)
     */
    public function businessManagers()
    {
        if ($this->businessManagersCache !== null) {
            return $this->businessManagersCache;
        }

        if (!$this->isV2Enabled()) {
            return $this->legacyActiveManagers();
        }

        $source = $this->getPolicySource();

        if ($source === 'selected_staff') {
            $validIds = $this->selectedStaffIds();
        } else {
            $validIds = $this->explicitViewStaffIds();
        }

        if (empty($validIds)) {
            $this->businessManagersCache = [];
            return [];
        }

        $managers = $this->CI->db->select('staffid, firstname, lastname, email, phonenumber')
            ->where_in('staffid', $validIds)
            ->where('active', 1)
            ->get(db_prefix() . 'staff')
            ->result();

        $this->businessManagersCache = $managers;
        return $this->businessManagersCache;
    }

    /**
     * Resolve CC or direct email addresses for business managers.
     *
     * V2 Policy:
     * - Only active business managers resolved via businessManagers() are eligible.
     * - The staff owner ($ownerStaffId) is excluded to avoid duplicate emails.
     * - If the manager list is empty, returns [] (NO fallback to external email, NO fallback to IT admins).
     *
     * @param int|null $ownerStaffId
     * @param string $severity
     * @param bool|null $eventAllowsCC
     * @return string[]
     */
    public function resolveManagerEmails($ownerStaffId = null, $severity = 'warning', $eventAllowsCC = null)
    {
        if ($eventAllowsCC === false) {
            return [];
        }
        if ((string) get_option('sp_reminder_email_cc_manager_enabled') !== '1') {
            return [];
        }
        $scope = (string) get_option('sp_reminder_email_cc_scope') ?: 'all';
        if ($scope === 'critical_only' && $severity !== 'critical') {
            return [];
        }

        $ownerEmail = '';
        if ($ownerStaffId !== null && (int) $ownerStaffId > 0) {
            $ownerStaff = $this->CI->db->select('email')->where('staffid', (int) $ownerStaffId)->get(db_prefix() . 'staff')->row();
            if ($ownerStaff && !empty($ownerStaff->email)) {
                $ownerEmail = strtolower(trim((string) $ownerStaff->email));
            }
        }

        if (!$this->isV2Enabled()) {
            return $this->legacyResolveManagerCCEmails((int) $ownerStaffId, $ownerEmail);
        }

        $managers = $this->businessManagers();
        if (empty($managers)) {
            return [];
        }

        $cleanEmails = [];
        foreach ($managers as $manager) {
            if ($ownerStaffId !== null && (int) $manager->staffid === (int) $ownerStaffId) {
                continue;
            }
            $email = strtolower(trim((string) $manager->email));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if ($ownerEmail !== '' && $email === $ownerEmail) {
                continue;
            }
            $cleanEmails[] = $email;
        }

        return array_values(array_unique($cleanEmails));
    }

    /**
     * Resolve personal WhatsApp JIDs of business managers (excluding the staff owner).
     *
     * @param int|null $ownerStaffId
     * @return array Array of ['staff_id' => int, 'jid' => string, 'name' => string]
     */
    public function resolveManagerWhatsAppRecipients($ownerStaffId = null)
    {
        $managers = $this->businessManagers();
        if (empty($managers)) {
            return [];
        }

        if (!isset($this->CI->reminder_whatsapp_formatter)) {
            if (isset($this->CI->load)) {
                $this->CI->load->library('sales_pipeline/Reminder_whatsapp_formatter');
            } else {
                require_once __DIR__ . '/Reminder_whatsapp_formatter.php';
                $this->CI->reminder_whatsapp_formatter = new Reminder_whatsapp_formatter();
            }
        }

        $recipients = [];
        $seenJids = [];

        foreach ($managers as $manager) {
            if ($ownerStaffId !== null && (int) $manager->staffid === (int) $ownerStaffId) {
                continue;
            }
            if (empty($manager->phonenumber)) {
                continue;
            }
            $jid = $this->CI->reminder_whatsapp_formatter->normalizePhoneToJid($manager->phonenumber);
            if ($jid !== null && !isset($seenJids[$jid])) {
                $seenJids[$jid] = true;
                $recipients[] = [
                    'staff_id' => (int) $manager->staffid,
                    'jid'      => $jid,
                    'name'     => trim($manager->firstname . ' ' . $manager->lastname),
                ];
            }
        }

        return $recipients;
    }

    /**
     * Helper to format a phone number to WhatsApp JID.
     *
     * @param string|null $phone
     * @return string|null
     */
    public function formatWhatsappRecipientKey($phone)
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }

        if (class_exists('Reminder_whatsapp_formatter', false)) {
            return Reminder_whatsapp_formatter::formatPhoneNumberToJid($raw);
        }
        $file = __DIR__ . '/Reminder_whatsapp_formatter.php';
        if (file_exists($file)) {
            require_once $file;
            if (class_exists('Reminder_whatsapp_formatter', false)) {
                return Reminder_whatsapp_formatter::formatPhoneNumberToJid($raw);
            }
        }

        if (strpos($raw, '@g.us') !== false || strpos($raw, '@s.whatsapp.net') !== false) {
            return $raw;
        }
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return null;
        }
        if (substr($digits, 0, 1) === '0') {
            $digits = '84' . substr($digits, 1);
        }
        if (strlen($digits) < 9 || strlen($digits) > 15) {
            return null;
        }
        return $digits . '@s.whatsapp.net';
    }

    /**
     * Revalidate a pending delivery recipient's eligibility immediately before dispatch or retry.
     *
     * Rules:
     * - Staff delivery: checks active staff member with access (view or view_own) and matches channel destination.
     * - Manager direct (email or whatsapp with staff ID): checks active business manager status, manager mode, and destination match.
     * - Manager group (whatsapp group): checks group JID validity and WhatsApp group enabled.
     * - Immutability: Terminal/uncertain deliveries are never modified.
     *
     * @param array $deliveryRow
     * @return array ['valid' => bool, 'reason' => string|null]
     */
    public function revalidateDeliveryRecipient(array $deliveryRow)
    {
        $status = $deliveryRow['status'] ?? 'pending';
        // Immutability: deliveries already sent, failed, uncertain, or unverified must not be modified or retargeted
        if (in_array($status, ['sent', 'uncertain', 'unverified'], true)) {
            return ['valid' => true, 'reason' => 'immutable_status'];
        }

        $recipientType = $deliveryRow['recipient_type'] ?? 'staff';
        $channel = $deliveryRow['channel'] ?? '';
        $recipientStaffId = !empty($deliveryRow['recipient_staff_id']) ? (int) $deliveryRow['recipient_staff_id'] : null;

        // Branch 1: Staff Owner
        if ($recipientType === 'staff') {
            $staffId = $recipientStaffId ?: (int) ($deliveryRow['staff_id'] ?? 0);
            if ($staffId <= 0) {
                return ['valid' => false, 'reason' => 'invalid_staff_id'];
            }
            $staff = $this->CI->db->select('staffid, active, admin, email, phonenumber')->where('staffid', $staffId)->get(db_prefix() . 'staff')->row();
            if (!$staff || (int) $staff->active !== 1) {
                return ['valid' => false, 'reason' => 'staff_inactive'];
            }
            $hasAccess = ((int) $staff->admin === 1)
                || staff_can('view', 'sales_pipeline', $staffId)
                || staff_can('view_own', 'sales_pipeline', $staffId);

            if (!$hasAccess) {
                return ['valid' => false, 'reason' => 'access_revoked'];
            }

            // Channel-specific destination validation:
            $actualKey = trim((string) ($deliveryRow['recipient_key'] ?? ''));
            if ($channel === 'whatsapp') {
                if ((string) get_option('sp_reminder_whatsapp_enabled') !== '1') {
                    return ['valid' => false, 'reason' => 'whatsapp_disabled'];
                }
                $currentPhone = trim((string) ($staff->phonenumber ?? ''));
                if ($currentPhone === '') {
                    return ['valid' => false, 'reason' => 'staff_phone_missing'];
                }
                $expectedJid = $this->formatWhatsappRecipientKey($currentPhone);
                if ($expectedJid === null) {
                    return ['valid' => false, 'reason' => 'invalid_whatsapp_jid'];
                }
                if ($actualKey === '' || $actualKey !== $expectedJid) {
                    return ['valid' => false, 'reason' => 'recipient_destination_mismatch'];
                }
            } elseif ($channel === 'email') {
                $currentEmail = strtolower(trim((string) ($staff->email ?? '')));
                if ($currentEmail === '' || !filter_var($currentEmail, FILTER_VALIDATE_EMAIL)) {
                    return ['valid' => false, 'reason' => 'staff_email_missing'];
                }
                if ($actualKey === '' || strtolower($actualKey) !== $currentEmail) {
                    return ['valid' => false, 'reason' => 'recipient_destination_mismatch'];
                }
            } elseif ($channel === 'crm') {
                // CRM Bell uses staff id as recipient_key
                if ($actualKey !== '' && (int) $actualKey !== $staffId) {
                    return ['valid' => false, 'reason' => 'recipient_destination_mismatch'];
                }
            }

            return ['valid' => true, 'reason' => null];
        }

        // Branch 2: Manager Group WhatsApp
        if ($recipientType === 'manager' && $channel === 'whatsapp' && $recipientStaffId === null) {
            if ((string) get_option('sp_reminder_whatsapp_enabled') !== '1') {
                return ['valid' => false, 'reason' => 'whatsapp_disabled'];
            }
            $mode = (string) get_option('sp_reminder_whatsapp_manager_mode') ?: 'group_only';
            if ($mode !== 'group_only' && $mode !== 'both') {
                return ['valid' => false, 'reason' => 'whatsapp_group_mode_disabled'];
            }
            $groupJid = trim((string) get_option('sp_reminder_whatsapp_group_jid'));
            $rowJid = trim((string) ($deliveryRow['recipient_key'] ?? ''));
            if ($groupJid === '' || $rowJid === '' || $rowJid !== $groupJid) {
                return ['valid' => false, 'reason' => 'whatsapp_group_jid_mismatch'];
            }
            return ['valid' => true, 'reason' => null];
        }

        // Branch 3: Manager Direct (WhatsApp or Email)
        if ($recipientType === 'manager' && in_array($channel, ['whatsapp', 'email'], true)) {
            if (!$recipientStaffId) {
                return ['valid' => false, 'reason' => 'missing_manager_staff_id'];
            }

            $targetManager = null;
            if ($this->isV2Enabled()) {
                $managers = $this->businessManagers();
                foreach ($managers as $m) {
                    if ((int) $m->staffid === $recipientStaffId) {
                        $targetManager = $m;
                        break;
                    }
                }

                if (!$targetManager) {
                    return ['valid' => false, 'reason' => 'recipient_revoked'];
                }
            } else {
                $staff = $this->CI->db->select('staffid, active, admin, email, phonenumber')->where('staffid', $recipientStaffId)->get(db_prefix() . 'staff')->row();
                if (!$staff || (int) $staff->active !== 1) {
                    return ['valid' => false, 'reason' => 'staff_inactive'];
                }
                $isLegacyManager = ((int) $staff->admin === 1) || staff_can('view', 'sales_pipeline', $recipientStaffId);
                if (!$isLegacyManager) {
                    return ['valid' => false, 'reason' => 'recipient_revoked'];
                }
                $targetManager = $staff;
            }

            // Channel-specific destination validation & mode checks:
            $actualKey = trim((string) ($deliveryRow['recipient_key'] ?? ''));
            if ($channel === 'whatsapp') {
                if ((string) get_option('sp_reminder_whatsapp_enabled') !== '1') {
                    return ['valid' => false, 'reason' => 'whatsapp_disabled'];
                }
                $mode = (string) get_option('sp_reminder_whatsapp_manager_mode') ?: 'group_only';
                if ($mode !== 'direct_only' && $mode !== 'both') {
                    return ['valid' => false, 'reason' => 'whatsapp_direct_mode_disabled'];
                }
                $currentPhone = trim((string) ($targetManager->phonenumber ?? ''));
                if ($currentPhone === '') {
                    return ['valid' => false, 'reason' => 'manager_phone_missing'];
                }
                $expectedJid = $this->formatWhatsappRecipientKey($currentPhone);
                if ($expectedJid === null) {
                    return ['valid' => false, 'reason' => 'invalid_whatsapp_jid'];
                }
                if ($actualKey === '' || $actualKey !== $expectedJid) {
                    return ['valid' => false, 'reason' => 'recipient_destination_mismatch'];
                }
            } elseif ($channel === 'email') {
                $currentEmail = strtolower(trim((string) ($targetManager->email ?? '')));
                if ($currentEmail === '' || !filter_var($currentEmail, FILTER_VALIDATE_EMAIL)) {
                    return ['valid' => false, 'reason' => 'manager_email_missing'];
                }
                if ($actualKey === '' || strtolower($actualKey) !== $currentEmail) {
                    return ['valid' => false, 'reason' => 'recipient_destination_mismatch'];
                }
            }

            return ['valid' => true, 'reason' => null];
        }

        // Branch 4: Manager CRM Bell
        if ($recipientType === 'manager' && $channel === 'crm') {
            if ($this->isV2Enabled()) {
                // In V2 target model, CRM Bell is strictly for the staff owner
                return ['valid' => false, 'reason' => 'manager_crm_bell_disallowed'];
            }
            return ['valid' => true, 'reason' => null];
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * Generate preview data for the settings modal without dispatching notifications.
     *
     * @param array $overrides Optional overrides: ['v2_enabled' => '0'|'1', 'source' => 'explicit_view'|'selected_staff', 'selected_staff_ids' => []]
     * @return array
     */
    public function previewManagerRecipients(array $overrides = [])
    {
        $v2Enabled = isset($overrides['v2_enabled']) ? ((string) $overrides['v2_enabled'] === '1') : $this->isV2Enabled();
        $source = isset($overrides['source']) ? (string) $overrides['source'] : $this->getPolicySource();
        $selectedIdsOverride = isset($overrides['selected_staff_ids']) ? (array) $overrides['selected_staff_ids'] : null;

        $explicitIds = $this->explicitViewStaffIds();
        $validatedSelectedIds = $this->selectedStaffIds($selectedIdsOverride);

        $activeStaff = $this->CI->db->select('staffid, firstname, lastname, email, phonenumber, admin, active')
            ->where('active', 1)
            ->get(db_prefix() . 'staff')
            ->result();

        $includedManagers = [];
        $excludedCandidates = [];

        foreach ($activeStaff as $staff) {
            $sid = (int) $staff->staffid;
            $isAdmin = (int) $staff->admin === 1;
            $hasExplicitView = in_array($sid, $explicitIds, true);
            $isSelected = in_array($sid, $validatedSelectedIds, true);
            $fullName = trim($staff->firstname . ' ' . $staff->lastname);

            $isIncluded = false;
            $reason = '';

            if (!$v2Enabled) {
                if ($isAdmin || staff_can('view', 'sales_pipeline', $sid)) {
                    $isIncluded = true;
                    $reason = $isAdmin ? 'admin_core_shortcut' : 'core_view_permission';
                }
            } else {
                if ($source === 'selected_staff') {
                    if ($isSelected) {
                        $isIncluded = true;
                        $reason = $isAdmin ? 'selected_admin_executive' : 'selected_manager';
                    } elseif ($isAdmin) {
                        $excludedCandidates[] = [
                            'staff_id' => $sid,
                            'name'     => $fullName,
                            'reason'   => 'admin_not_in_selected_list',
                        ];
                    } elseif ($hasExplicitView) {
                        $excludedCandidates[] = [
                            'staff_id' => $sid,
                            'name'     => $fullName,
                            'reason'   => 'explicit_view_not_in_selected_list',
                        ];
                    }
                } else {
                    // explicit_view mode
                    if ($hasExplicitView) {
                        $isIncluded = true;
                        $reason = 'explicit_view_granted';
                    } elseif ($isAdmin) {
                        $excludedCandidates[] = [
                            'staff_id' => $sid,
                            'name'     => $fullName,
                            'reason'   => 'technical_admin_no_explicit_view',
                        ];
                    }
                }
            }

            if ($isIncluded) {
                $includedManagers[] = [
                    'staff_id'     => $sid,
                    'name'         => $fullName,
                    'email_masked' => $this->maskEmail($staff->email),
                    'phone_masked' => $this->maskPhone($staff->phonenumber),
                    'reason'       => $reason,
                ];
            }
        }

        // Technical admins list
        $techAdmins = [];
        foreach ($this->technicalAdmins() as $admin) {
            $techAdmins[] = [
                'staff_id'     => (int) $admin->staffid,
                'name'         => trim($admin->firstname . ' ' . $admin->lastname),
                'email_masked' => $this->maskEmail($admin->email),
            ];
        }

        $groupJid = (string) get_option('sp_reminder_whatsapp_group_jid');

        return [
            'v2_enabled'          => $v2Enabled,
            'source'              => $source,
            'included_managers'   => $includedManagers,
            'excluded_candidates' => $excludedCandidates,
            'technical_admins'    => $techAdmins,
            'whatsapp_group_jid'  => $groupJid !== '' ? $groupJid : null,
            'empty_warning'       => empty($includedManagers),
            'no_fallback_note'    => 'V2 Policy: If manager list is empty, no manager alerts are dispatched (zero external fallback, zero IT admin broadcast).',
        ];
    }

    public function maskEmail($email)
    {
        $email = trim((string) $email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '[masked]';
        }
        [$local, $domain] = explode('@', $email, 2);
        $len = strlen($local);
        $visible = min(2, $len);
        return substr($local, 0, $visible) . '***@' . $domain;
    }

    public function maskPhone($phone)
    {
        $phone = preg_replace('/[^\d+]/', '', trim((string) $phone));
        if (strlen($phone) < 6) {
            return '[masked]';
        }
        return substr($phone, 0, 3) . '***' . substr($phone, -3);
    }

    private function legacyActiveManagers()
    {
        $activeStaff = $this->CI->db->where('active', 1)->get(db_prefix() . 'staff')->result();
        $managers = [];
        foreach ($activeStaff as $staff) {
            $sid = (int) $staff->staffid;
            if ((int) $staff->admin === 1 || staff_can('view', 'sales_pipeline', $sid)) {
                $managers[] = $staff;
            }
        }
        return $managers;
    }

    private function legacyResolveManagerCCEmails($ownerStaffId, $ownerEmail)
    {
        $rawEmails = [];
        foreach ($this->legacyActiveManagers() as $manager) {
            if ((int) $manager->staffid !== $ownerStaffId) {
                $rawEmails[] = trim((string) $manager->email);
            }
        }

        if (empty($rawEmails)) {
            $fallback = (string) get_option('sp_reminder_manager_fallback_emails');
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
            if ($ownerEmail !== '' && strtolower($email) === $ownerEmail) {
                continue;
            }
            $cleanEmails[] = $email;
        }

        return array_values(array_unique($cleanEmails));
    }
}
