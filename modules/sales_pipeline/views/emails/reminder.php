<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<p><?php echo _l('sales_pipeline_reminder_email_greeting', ['<b>' . $staff_name . '</b>']); ?></p>
<p><?php echo _l('sales_pipeline_reminder_email_intro'); ?></p>
<ul>
    <li><b><?php echo _l('sales_pipeline_reminder_email_type'); ?>:</b> <?php echo $entity_label; ?></li>
    <li><b><?php echo $entity_label; ?>:</b> <?php echo $entity_name; ?></li>
    <li><b><?php echo _l('sales_pipeline_customer_name'); ?>:</b> <?php echo $customer_name; ?></li>
    <li><b><?php echo _l('sales_pipeline_deal_name'); ?>:</b> <?php echo $deal_name; ?></li>
    <li><b><?php echo _l('sales_pipeline_status'); ?>:</b> <?php echo $status_name; ?></li>
    <li><b><?php echo _l('sales_pipeline_deal_value'); ?>:</b> <?php echo $deal_value; ?> <?php echo _l('sales_pipeline_vnd'); ?></li>
    <li><b><?php echo _l('sales_pipeline_expected_date'); ?>:</b> <?php echo $deal_date; ?></li>
</ul>
<p><?php echo _l('sales_pipeline_reminder_email_instruction', [
    '<b>' . _l('sales_pipeline_reminder_email_quick_response') . '</b>',
    $entity_label,
]); ?></p>
<p style="margin:24px 0">
    <a href="<?php echo $response_url; ?>" style="background:#03a9f4;color:#fff;padding:10px 18px;text-decoration:none;border-radius:4px;display:inline-block">
        <b><?php echo _l('sales_pipeline_reminder_email_quick_response'); ?></b>
    </a>
</p>
<p><a href="<?php echo $deal_url; ?>"><?php echo _l('sales_pipeline_reminder_email_view_deal'); ?></a></p>
