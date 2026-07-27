<?php defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$CI->load->model('sales_pipeline/sales_pipeline_model');

$current_quarter = ceil(date('n') / 3);
$summary = $CI->sales_pipeline_model->get_summary($current_quarter, date('Y'));
?>

<div class="panel_s">
    <div class="panel-body padding-10">
        <p class="no-margin font-bold">
            <i class="fa fa-line-chart text-info"></i>
            <?php echo _l('sales_pipeline'); ?> — <?php echo _l('sales_pipeline_quarter'); ?> <?php echo $current_quarter; ?>/<?php echo date('Y'); ?>
            <a href="<?php echo admin_url('sales_pipeline'); ?>" class="pull-right" style="font-weight: normal; font-size: 12px;">
                <?php echo _l('sales_pipeline_view_all'); ?> →
            </a>
        </p>
        <hr class="hr-panel-heading" />
        <div class="row text-center">
            <div class="col-xs-4">
                <h4 class="no-margin text-primary font-bold"><?php echo $summary['total_deals']; ?></h4>
                <small class="text-muted"><?php echo _l('sales_pipeline_total_deals'); ?></small>
            </div>
            <div class="col-xs-4">
                <h4 class="no-margin text-success font-bold"><?php echo $summary['won_deals']; ?></h4>
                <small class="text-muted"> <?php echo _l('sales_pipeline_won'); ?></small>
            </div>
            <div class="col-xs-4">
                <h4 class="no-margin text-danger font-bold"><?php echo $summary['lost_deals']; ?></h4>
                <small class="text-muted"> <?php echo _l('sales_pipeline_lost'); ?></small>
            </div>
        </div>
        <div class="mtop10 text-center">
            <span class="font-bold" style="font-size: 18px; color: #2c3e50;">
                <?php echo number_format($summary['total_value']); ?> <?php echo _l('sales_pipeline_vnd'); ?>
            </span>
            <br><small class="text-muted"><?php echo _l('sales_pipeline_total_value'); ?></small>
        </div>
    </div>
</div>
