<?php defined('BASEPATH') or exit('No direct script access allowed'); 

$status_color = !empty($deal['status_color']) ? $deal['status_color'] : (!empty($status['color']) ? $status['color'] : '#777');
?>
<li data-deal-id="<?php echo $deal['id']; ?>" class="pipeline-deal-card">
    <div class="panel-body">
        <div class="row">
            <div class="col-md-12">
                <h4 class="bold mtop5 mbot10">
                    <span class="text-dark">
                        <?php echo html_escape($deal['customer_name']); ?>
                    </span>
                </h4>
                
                <p class="text-muted mbot10">
                    <i class="fa fa-briefcase"></i> <?php echo html_escape($deal['deal_name']); ?>
                </p>

                <div class="clearfix mbot10">
                    <span class="label" style="background-color:<?php echo $status_color; ?>;color:#fff;">
                        <?php echo html_escape($deal['status_name']); ?>
                    </span>
                </div>

                <div class="pipeline-card-info">
                    <p class="mbot5">
                        <i class="fa fa-money text-success"></i> 
                        <strong><?php echo number_format($deal['deal_value']); ?> <?php echo _l('sales_pipeline_vnd'); ?></strong>
                    </p>
                    
                    <?php if ($deal['actual_profit'] !== null) { ?>
                    <p class="mbot5">
                        <i class="fa fa-line-chart text-info"></i>
                        <span class="text-success">
                            +<?php echo number_format($deal['actual_profit']); ?> <?php echo _l('sales_pipeline_vnd'); ?> 
                            (<?php echo number_format($deal['profit_percentage'], 1); ?>%)
                        </span>
                    </p>
                    <?php } else { ?>
                    <p class="mbot5">
                        <i class="fa fa-exclamation-triangle text-warning"></i>
                        <span class="text-muted"><?php echo _l('sales_pipeline_no_cost_price_yet'); ?></span>
                    </p>
                    <?php } ?>
                    
                    <p class="mbot5">
                        <i class="fa fa-calendar"></i>
                        <?php echo _d($deal['deal_date']); ?>
                    </p>
                    
                    <p class="mbot5">
                        <i class="fa fa-user"></i>
                        <?php echo html_escape($deal['staff_name']); ?>
                    </p>
                </div>


                <?php if (is_admin() || has_permission('sales_pipeline', '', 'view_deal_details')) { ?>
                <div class="mtop10">
                    <a href="<?php echo admin_url('sales_pipeline/deal/' . $deal['id'] . (isset($query_string) ? $query_string : '')); ?>" 
                       class="btn btn-info btn-xs">
                        <i class="fa fa-eye"></i> <?php echo _l('sales_pipeline_details'); ?>
                    </a>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</li>
