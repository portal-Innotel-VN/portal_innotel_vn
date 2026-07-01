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
                        
                        <?php echo form_open(admin_url('tech_reports/report/' . ($report ? $report['id'] : '')), ['id' => 'tech_report_form']); ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo render_select('staff_id', $staff_members, ['staffid', ['firstname', 'lastname']], 'tech_report_staff', $report ? $report['staff_id'] : get_staff_user_id(), ['required' => true], [], '', '', false); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo render_date_input('report_date', 'tech_report_date', $report ? $report['report_date'] : date('Y-m-d'), ['required' => true]); ?>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <?php echo render_textarea('task_description', 'tech_report_task', $report ? $report['task_description'] : '', ['rows' => 4, 'required' => true]); ?>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="task_category"><?php echo _l('tech_report_category'); ?></label>
                                    <select name="task_category" id="task_category" class="selectpicker" data-width="100%">
                                        <option value=""><?php echo _l('select'); ?></option>
                                        <?php foreach ($task_categories as $key => $category) { ?>
                                            <option value="<?php echo $key; ?>" <?php echo ($report && $report['task_category'] == $key) ? 'selected' : ''; ?>>
                                                <?php echo $category; ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <?php echo render_input('hours_spent', 'tech_report_hours', $report ? $report['hours_spent'] : '', 'number', ['step' => '0.5', 'min' => '0']); ?>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="status"><?php echo _l('tech_report_status'); ?></label>
                                    <select name="status" id="status" class="selectpicker" data-width="100%">
                                        <option value="not_started" <?php echo ($report && $report['status'] == 'not_started') ? 'selected' : ''; ?>><?php echo _l('tech_status_not_started'); ?></option>
                                        <option value="in_progress" <?php echo (!$report || $report['status'] == 'in_progress') ? 'selected' : ''; ?>><?php echo _l('tech_status_in_progress'); ?></option>
                                        <option value="completed" <?php echo ($report && $report['status'] == 'completed') ? 'selected' : ''; ?>><?php echo _l('tech_status_completed'); ?></option>
                                        <option value="blocked" <?php echo ($report && $report['status'] == 'blocked') ? 'selected' : ''; ?>><?php echo _l('tech_status_blocked'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <?php echo render_select('assigned_by', $staff_members, ['staffid', ['firstname', 'lastname']], 'tech_report_assigned_by', $report ? $report['assigned_by'] : '', [], [], '', '', false); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo render_input('related_task_id', 'tech_report_related_task', $report ? $report['related_task_id'] : '', 'number'); ?>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="followers[]"><?php echo _l('tech_report_followers'); ?></label>
                                    <select name="followers[]" id="followers" class="selectpicker" data-width="100%" multiple data-live-search="true">
                                        <?php 
                                        $current_followers = [];
                                        if ($report) {
                                            $this->db->where('report_id', $report['id']);
                                            $followers_result = $this->db->get(db_prefix() . 'tech_report_followers')->result_array();
                                            foreach ($followers_result as $f) {
                                                $current_followers[] = $f['staff_id'];
                                            }
                                        }
                                        foreach ($staff_members as $staff) { 
                                        ?>
                                            <option value="<?php echo $staff['staffid']; ?>" <?php echo in_array($staff['staffid'], $current_followers) ? 'selected' : ''; ?>>
                                                <?php echo $staff['firstname'] . ' ' . $staff['lastname']; ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <?php echo render_textarea('notes', 'tech_report_notes', $report ? $report['notes'] : '', ['rows' => 3]); ?>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary pull-right">
                                    <?php echo $report ? _l('submit') : _l('tech_report_add'); ?>
                                </button>
                                <a href="<?php echo admin_url('tech_reports'); ?>" class="btn btn-default pull-right mright5">
                                    <?php echo _l('cancel'); ?>
                                </a>
                            </div>
                        </div>

                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
    $(function() {
        appValidateForm($('#tech_report_form'), {
            staff_id: 'required',
            report_date: 'required',
            task_description: 'required'
        });
    });
</script>
