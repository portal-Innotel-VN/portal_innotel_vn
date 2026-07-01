<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo _l('tech_dashboard_summary'); ?></h4>
                        <hr class="hr-panel-heading" />
                        
                        <!-- Date Range Filter -->
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="start_date"><?php echo _l('date_from'); ?></label>
                                    <input type="date" id="start_date" class="form-control" value="<?php echo $start_date; ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="end_date"><?php echo _l('date_to'); ?></label>
                                    <input type="date" id="end_date" class="form-control" value="<?php echo $end_date; ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="button" id="btn_filter" class="btn btn-info btn-block">
                                        <?php echo _l('tech_dashboard_filter'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Cards -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="panel_s">
                                    <div class="panel-body text-center" style="padding: 30px;">
                                        <h1 class="text-primary no-margin"><?php echo $total_reports; ?></h1>
                                        <p class="text-muted"><?php echo _l('tech_dashboard_total_reports'); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="panel_s">
                                    <div class="panel-body text-center" style="padding: 30px;">
                                        <h1 class="text-success no-margin"><?php echo number_format($total_hours, 1); ?></h1>
                                        <p class="text-muted"><?php echo _l('tech_dashboard_total_hours'); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="panel_s">
                                    <div class="panel-body text-center" style="padding: 30px;">
                                        <h1 class="<?php echo $compliance_rate >= 80 ? 'text-success' : 'text-danger'; ?> no-margin">
                                            <?php echo $compliance_rate; ?>%
                                        </h1>
                                        <p class="text-muted"><?php echo _l('tech_dashboard_compliance_rate'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Daily Summary Chart -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="panel_s">
                                    <div class="panel-body">
                                        <h4><?php echo _l('tech_dashboard_total_hours'); ?> - <?php echo _l('by_date'); ?></h4>
                                        <canvas id="hoursChart" height="80"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Compliance Report Table -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="panel_s">
                                    <div class="panel-body">
                                        <h4><?php echo _l('tech_compliance_report'); ?></h4>
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th><?php echo _l('staff_member'); ?></th>
                                                    <th><?php echo _l('tech_compliance_submitted'); ?></th>
                                                    <th><?php echo _l('tech_compliance_missing'); ?></th>
                                                    <th><?php echo _l('tech_compliance_rate'); ?></th>
                                                    <th><?php echo _l('tech_compliance_status'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($compliance_report)) { ?>
                                                    <?php foreach ($compliance_report as $comp) { ?>
                                                        <tr>
                                                            <td><?php echo $comp['staff_name']; ?></td>
                                                            <td><?php echo $comp['reports_submitted']; ?></td>
                                                            <td><?php echo $comp['reports_missing']; ?></td>
                                                            <td>
                                                                <span class="<?php echo $comp['compliance_rate'] >= 80 ? 'text-success' : 'text-danger'; ?>">
                                                                    <?php echo $comp['compliance_rate']; ?>%
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if ($comp['compliance_rate'] >= 80) { ?>
                                                                    <span class="label label-success"><?php echo _l('tech_compliance_compliant'); ?></span>
                                                                <?php } else { ?>
                                                                    <span class="label label-danger"><?php echo _l('tech_compliance_non_compliant'); ?></span>
                                                                <?php } ?>
                                                            </td>
                                                        </tr>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center"><?php echo _l('tech_info_no_compliance_issues'); ?></td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script src="<?php echo base_url('assets/plugins/chart.js/Chart.min.js'); ?>"></script>
<script>
    $(function() {
        // Filter button
        $('#btn_filter').on('click', function() {
            var start_date = $('#start_date').val();
            var end_date = $('#end_date').val();
            window.location.href = '<?php echo admin_url('tech_reports/dashboard'); ?>?start_date=' + start_date + '&end_date=' + end_date;
        });

        // Hours chart
        var ctx = document.getElementById('hoursChart').getContext('2d');
        var chartData = {
            labels: [
                <?php foreach ($daily_summary as $summary) { ?>
                    '<?php echo $summary['report_date']; ?>',
                <?php } ?>
            ],
            datasets: [{
                label: '<?php echo _l('tech_report_hours'); ?>',
                data: [
                    <?php foreach ($daily_summary as $summary) { ?>
                        <?php echo $summary['total_hours']; ?>,
                    <?php } ?>
                ],
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2,
                fill: true
            }]
        };

        new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
</script>
