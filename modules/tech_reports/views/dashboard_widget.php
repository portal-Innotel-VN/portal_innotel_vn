<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="widget" id="tech_reports_widget">
    <div class="panel_s">
        <div class="panel-body">
            <div class="widget-dragger"></div>
            <h4 class="no-margin">
                <i class="fa fa-file-text-o"></i> <?php echo _l('tech_widget_title'); ?>
            </h4>
            <hr class="hr-10" />
            
            <!-- Today's Reports Summary -->
            <div class="row">
                <div class="col-md-6">
                    <p class="text-muted"><?php echo _l('tech_dashboard_today_reports'); ?></p>
                    <h3 class="text-primary"><?php echo count($today_reports); ?></h3>
                </div>
                <div class="col-md-6">
                    <p class="text-muted"><?php echo _l('tech_dashboard_my_reports'); ?></p>
                    <h3 class="text-success"><?php echo count($my_reports); ?></h3>
                </div>
            </div>

            <hr class="hr-10" />

            <!-- My Reports Today -->
            <?php if (!empty($my_reports)) { ?>
                <div class="mbot10">
                    <strong><?php echo _l('tech_dashboard_my_reports'); ?>:</strong>
                    <ul class="list-unstyled">
                        <?php foreach ($my_reports as $report) { ?>
                            <li class="mbot5">
                                <i class="fa fa-check-circle text-success"></i>
                                <?php echo substr($report['task_description'], 0, 50) . (strlen($report['task_description']) > 50 ? '...' : ''); ?>
                                <span class="pull-right text-muted"><?php echo $report['hours_spent']; ?>h</span>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } else { ?>
                <div class="alert alert-warning">
                    <i class="fa fa-exclamation-triangle"></i>
                    <?php echo _l('tech_widget_no_reports'); ?>
                </div>
            <?php } ?>

            <!-- Pending Compliance Issues -->
            <?php if (!empty($pending_compliance)) { ?>
                <hr class="hr-10" />
                <div class="alert alert-danger">
                    <strong><?php echo _l('tech_dashboard_pending_compliance'); ?>:</strong>
                    <ul class="list-unstyled mbot0 mtop5">
                        <?php foreach (array_slice($pending_compliance, 0, 3) as $comp) { ?>
                            <li>
                                <i class="fa fa-user"></i> <?php echo $comp['staff_name']; ?>
                                <span class="pull-right"><?php echo $comp['compliance_rate']; ?>%</span>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } ?>

            <!-- Action Buttons -->
            <div class="row">
                <div class="col-md-6">
                    <a href="<?php echo admin_url('tech_reports/report'); ?>" class="btn btn-primary btn-block btn-sm">
                        <i class="fa fa-plus"></i> <?php echo _l('tech_widget_add_report'); ?>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="<?php echo admin_url('tech_reports'); ?>" class="btn btn-default btn-block btn-sm">
                        <i class="fa fa-list"></i> <?php echo _l('tech_widget_view_all'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
