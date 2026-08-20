<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<p><?php echo _l('sales_pipeline_reminder_email_greeting', ['<b>' . html_escape($staff_name) . '</b>']); ?></p>
<h3><?php echo html_escape($title); ?></h3>
<p><?php echo nl2br(html_escape($message)); ?></p>
<p style="margin:24px 0">
    <a href="<?php echo html_escape($response_url); ?>" style="background:#03a9f4;color:#fff;padding:10px 18px;text-decoration:none;border-radius:4px;display:inline-block">
        <b><?php echo _l('sales_pipeline_reminder_email_quick_response'); ?></b>
    </a>
</p>
<p><a href="<?php echo html_escape($entity_url); ?>"><?php echo _l('sales_pipeline_reminder_email_view_entity'); ?></a></p>
