<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo $title; ?></h4>
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
                                        <?php echo _l('tech_report_filter'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Compliance Summary -->
                        <div class="row mbot20">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <strong><?php echo _l('tech_compliance_report'); ?>:</strong>
                                    <?php 
                                    $compliant_count = 0;
                                    $total_staff = count($compliance_report);
                                    foreach ($compliance_report as $comp) {
                                        if ($comp['compliance_rate'] >= 80) {
                                            $compliant_count++;
                                        }
                                    }
                                    $overall_rate = $total_staff > 0 ? round(($compliant_count / $total_staff) * 100, 1) : 0;
                                    ?>
                                    <span class="<?php echo $overall_rate >= 80 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $compliant_count; ?> / <?php echo $total_staff; ?> staff compliant (<?php echo $overall_rate; ?>%)
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Compliance Table -->
                        <table class="table table-striped dt-table">
                            <thead>
                                <tr>
                                    <th><?php echo _l('staff_member'); ?></th>
                                    <th><?php echo _l('tech_staff_role'); ?></th>
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
                                            <td>
                                                <a href="<?php echo admin_url('staff/member/' . $comp['staff_id']); ?>">
                                                    <?php echo $comp['staff_name']; ?>
                                                </a>
                                            </td>
                                            <td><?php echo isset($comp['role']) ? _l('tech_role_' . $comp['role']) : '-'; ?></td>
                                            <td><?php echo $comp['reports_submitted']; ?></td>
                                            <td>
                                                <?php if ($comp['reports_missing'] > 0) { ?>
                                                    <span class="text-danger"><?php echo $comp['reports_missing']; ?></span>
                                                <?php } else { ?>
                                                    <?php echo $comp['reports_missing']; ?>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <div class="progress" style="margin-bottom: 0;">
                                                    <div class="progress-bar <?php echo $comp['compliance_rate'] >= 80 ? 'progress-bar-success' : 'progress-bar-danger'; ?>" 
                                                         style="width: <?php echo $comp['compliance_rate']; ?>%">
                                                        <?php echo $comp['compliance_rate']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($comp['compliance_rate'] >= 80) { ?>
                                                    <span class="label label-success">
                                                        <i class="fa fa-check"></i> <?php echo _l('tech_compliance_compliant'); ?>
                                                    </span>
                                                <?php } else { ?>
                                                    <span class="label label-danger">
                                                        <i class="fa fa-times"></i> <?php echo _l('tech_compliance_non_compliant'); ?>
                                                    </span>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                <?php } else { ?>
                                    <tr>
                                        <td colspan="6" class="text-center"><?php echo _l('tech_info_no_compliance_issues'); ?></td>
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

<?php init_tail(); ?>
<script>
    $(function() {
        $('.dt-table').DataTable({
            order: [[4, 'asc']], // Sort by compliance rate ascending
            pageLength: 25
        });

        $('#btn_filter').on('click', function() {
            var start_date = $('#start_date').val();
            var end_date = $('#end_date').val();
            window.location.href = '<?php echo admin_url('tech_reports/compliance'); ?>?start_date=' + start_date + '&end_date=' + end_date;
        });
    });
</script>
