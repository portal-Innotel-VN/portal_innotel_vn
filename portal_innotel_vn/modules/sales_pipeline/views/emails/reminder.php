<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$severity = $severity ?? 'warning';
$badgeColor = '#0284c7';
$badgeBg = '#f0f9ff';
$badgeBorder = '#bae6fd';
$badgeText = _l('sales_pipeline_reminder_badge_notice');

if ($severity === 'critical') {
    $badgeColor = '#dc2626';
    $badgeBg = '#fef2f2';
    $badgeBorder = '#fecaca';
    $badgeText = _l('sales_pipeline_reminder_badge_critical');
} elseif ($severity === 'warning') {
    $badgeColor = '#d97706';
    $badgeBg = '#fffbeb';
    $badgeBorder = '#fde68a';
    $badgeText = _l('sales_pipeline_reminder_badge_warning');
}

$snapshot = $snapshot ?? [];
$hasSnapshotDetails = !empty($snapshot['customer_name'])
    || !empty($snapshot['deal_name'])
    || !empty($snapshot['estimate_number'])
    || !empty($snapshot['risk_reason'])
    || !empty($snapshot['inactive_days']);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style type="text/css">
    @media only screen and (max-width: 520px) {
        .sp-email-card {
            width: 100% !important;
            border-radius: 8px !important;
        }
        .sp-email-header {
            padding: 16px !important;
        }
        .sp-email-header-table td {
            display: block !important;
            width: 100% !important;
            text-align: left !important;
        }
        .sp-badge-container {
            margin-top: 8px !important;
        }
        .sp-email-body {
            padding: 20px 16px !important;
        }
        .sp-btn-cell {
            display: block !important;
            width: 100% !important;
            box-sizing: border-box !important;
            padding: 0 0 10px 0 !important;
        }
        .sp-btn-spacer {
            display: none !important;
        }
        .sp-btn-action {
            display: block !important;
            width: 100% !important;
            box-sizing: border-box !important;
            text-align: center !important;
        }
        .sp-meta-label {
            width: 40% !important;
        }
        .sp-meta-val {
            width: 60% !important;
        }
    }
</style>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9;">
<div style="background-color: #f1f5f9; padding: 24px 10px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #1e293b; line-height: 1.5;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="sp-email-card" style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06);">
        <!-- Header -->
        <tr>
            <td class="sp-email-header" style="background: #0f172a; padding: 18px 24px; border-bottom: 3px solid #0284c7;">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="sp-email-header-table">
                    <tr>
                        <td align="left" style="vertical-align: middle;">
                            <span style="font-size: 14px; font-weight: 700; color: #ffffff; letter-spacing: 0.5px; text-transform: uppercase;">
                                <?php echo html_escape(get_option('companyname') ?: 'PORTAL 18'); ?>
                            </span>
                        </td>
                        <td align="right" class="sp-badge-container" style="vertical-align: middle;">
                            <span style="display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: <?php echo $badgeBg; ?>; color: <?php echo $badgeColor; ?>; border: 1px solid <?php echo $badgeBorder; ?>; letter-spacing: 0.3px;">
                                <?php echo html_escape($badgeText); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Main Body -->
        <tr>
            <td class="sp-email-body" style="padding: 28px 24px 20px;">
                <!-- Greeting -->
                <p style="margin: 0 0 12px; font-size: 15px; color: #475569;">
                    <?php echo _l('sales_pipeline_reminder_email_greeting', ['<strong style="color: #0f172a;">' . html_escape($staff_name ?: _l('sales_pipeline_reminder_email_recipient_fallback')) . '</strong>']); ?>
                </p>

                <!-- Title -->
                <h2 style="margin: 0 0 16px; font-size: 19px; font-weight: 700; color: #0f172a; line-height: 1.35;">
                    <?php echo html_escape($title); ?>
                </h2>

                <!-- Highlight Box / Message -->
                <div style="margin: 0 0 20px; padding: 16px 18px; background: #f8fafc; border-left: 4px solid <?php echo $badgeColor; ?>; border-radius: 0 8px 8px 0; border-top: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                    <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #334155;">
                        <?php echo nl2br(html_escape($message)); ?>
                    </p>
                </div>

                <!-- Structured Snapshot Details (If Available) -->
                <?php if ($hasSnapshotDetails) { ?>
                    <div style="margin: 0 0 24px; padding: 14px 18px; background: #ffffff; border: 1px dashed #cbd5e1; border-radius: 8px;">
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="font-size: 13px; color: #475569;">
                            <?php if (!empty($snapshot['customer_name'])) { ?>
                                <tr>
                                    <td class="sp-meta-label" width="35%" style="padding: 4px 0; color: #64748b; font-weight: 500;"><?php echo _l('sales_pipeline_customer'); ?>:</td>
                                    <td class="sp-meta-val" width="65%" style="padding: 4px 0; color: #0f172a; font-weight: 600;"><?php echo html_escape($snapshot['customer_name']); ?></td>
                                </tr>
                            <?php } ?>
                            <?php if (!empty($snapshot['deal_name'])) { ?>
                                <tr>
                                    <td class="sp-meta-label" style="padding: 4px 0; color: #64748b; font-weight: 500;"><?php echo _l('sales_pipeline_deal'); ?>:</td>
                                    <td class="sp-meta-val" style="padding: 4px 0; color: #0f172a; font-weight: 600;"><?php echo html_escape($snapshot['deal_name']); ?></td>
                                </tr>
                            <?php } ?>
                            <?php if (!empty($snapshot['estimate_number'])) { ?>
                                <tr>
                                    <td class="sp-meta-label" style="padding: 4px 0; color: #64748b; font-weight: 500;"><?php echo _l('estimate'); ?>:</td>
                                    <td class="sp-meta-val" style="padding: 4px 0; color: #0f172a; font-weight: 600;"><?php echo html_escape($snapshot['estimate_number']); ?></td>
                                </tr>
                            <?php } ?>
                            <?php if (!empty($snapshot['estimate_total']) || !empty($snapshot['deal_value'])) { 
                                $val = !empty($snapshot['estimate_total']) ? (float)$snapshot['estimate_total'] : (float)$snapshot['deal_value'];
                            ?>
                                <tr>
                                    <td class="sp-meta-label" style="padding: 4px 0; color: #64748b; font-weight: 500;"><?php echo _l('sales_pipeline_value'); ?>:</td>
                                    <td class="sp-meta-val" style="padding: 4px 0; color: #0284c7; font-weight: 700;"><?php echo app_format_money($val, get_base_currency()); ?></td>
                                </tr>
                            <?php } ?>
                            <?php if (!empty($snapshot['risk_reason'])) { ?>
                                <tr>
                                    <td class="sp-meta-label" style="padding: 4px 0; color: #64748b; font-weight: 500;"><?php echo _l('sales_pipeline_reminder_reason'); ?>:</td>
                                    <td class="sp-meta-val" style="padding: 4px 0; color: #e11d48; font-weight: 600;"><?php echo html_escape($snapshot['risk_reason']); ?></td>
                                </tr>
                            <?php } ?>
                            <?php if (!empty($snapshot['inactive_days'])) { ?>
                                <tr>
                                    <td class="sp-meta-label" style="padding: 4px 0; color: #64748b; font-weight: 500;"><?php echo _l('sales_pipeline_inactive_days'); ?>:</td>
                                    <td class="sp-meta-val" style="padding: 4px 0; color: #d97706; font-weight: 600;"><?php echo (int) $snapshot['inactive_days']; ?> <?php echo _l('days'); ?></td>
                                </tr>
                            <?php } ?>
                        </table>
                    </div>
                <?php } ?>

                <!-- Call to Action Buttons -->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 24px 0 16px;">
                    <tr>
                        <?php if (!empty($response_url) && !empty($entity_url)) { ?>
                            <td width="48%" align="center" class="sp-btn-cell" style="vertical-align: middle;">
                                <a href="<?php echo html_escape($response_url); ?>" target="_blank" class="sp-btn-action" style="display: block; width: 100%; box-sizing: border-box; padding: 13px 12px; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px; background: #0284c7; border: 1px solid #0284c7; text-align: center; line-height: 1.3;">
                                    <?php echo _l('sales_pipeline_reminder_email_quick_response'); ?>
                                </a>
                            </td>
                            <td width="4%" class="sp-btn-spacer" style="width: 4%; font-size: 1px; line-height: 1px;">&nbsp;</td>
                            <td width="48%" align="center" class="sp-btn-cell" style="vertical-align: middle;">
                                <a href="<?php echo html_escape($entity_url); ?>" target="_blank" class="sp-btn-action" style="display: block; width: 100%; box-sizing: border-box; padding: 13px 12px; font-size: 14px; font-weight: 600; color: #334155; text-decoration: none; border-radius: 6px; background: #ffffff; border: 1px solid #cbd5e1; text-align: center; line-height: 1.3;">
                                    <?php echo _l('sales_pipeline_reminder_email_view_entity'); ?>
                                </a>
                            </td>
                        <?php } elseif (!empty($response_url)) { ?>
                            <td width="100%" align="center" class="sp-btn-cell">
                                <a href="<?php echo html_escape($response_url); ?>" target="_blank" class="sp-btn-action" style="display: block; width: 100%; box-sizing: border-box; padding: 13px 12px; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px; background: #0284c7; border: 1px solid #0284c7; text-align: center; line-height: 1.3;">
                                    <?php echo _l('sales_pipeline_reminder_email_quick_response'); ?>
                                </a>
                            </td>
                        <?php } elseif (!empty($entity_url)) { ?>
                            <td width="100%" align="center" class="sp-btn-cell">
                                <a href="<?php echo html_escape($entity_url); ?>" target="_blank" class="sp-btn-action" style="display: block; width: 100%; box-sizing: border-box; padding: 13px 12px; font-size: 14px; font-weight: 600; color: #334155; text-decoration: none; border-radius: 6px; background: #ffffff; border: 1px solid #cbd5e1; text-align: center; line-height: 1.3;">
                                    <?php echo _l('sales_pipeline_reminder_email_view_entity'); ?>
                                </a>
                            </td>
                        <?php } ?>
                    </tr>
                </table>

                <!-- Helper Note -->
                <div style="margin-top: 20px; padding: 12px 16px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 12px; color: #64748b; line-height: 1.5;">
                    <strong><?php echo _l('note'); ?>:</strong> <?php echo _l('sales_pipeline_reminder_email_footer_tip'); ?>
                </div>
            </td>
        </tr>

        <!-- Footer -->
        <tr>
            <td style="background: #f8fafc; padding: 16px 24px; border-top: 1px solid #e2e8f0; text-align: center;">
                <p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5;">
                    <?php echo _l('sales_pipeline_reminder_email_auto_generated'); ?>
                </p>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
