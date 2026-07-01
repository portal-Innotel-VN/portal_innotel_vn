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
                        
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i>
                            Manage which staff members are part of the tech department and require daily report submission.
                        </div>

                        <?php echo form_open(admin_url('tech_reports/manage_staff'), ['id' => 'staff_form']); ?>
                        
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th width="5%">
                                        <input type="checkbox" id="select_all">
                                    </th>
                                    <th><?php echo _l('staff_member'); ?></th>
                                    <th><?php echo _l('staff_dt_email'); ?></th>
                                    <th><?php echo _l('tech_staff_role'); ?></th>
                                    <th><?php echo _l('tech_staff_active'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Create lookup for existing tech staff
                                $tech_staff_lookup = [];
                                foreach ($tech_staff as $ts) {
                                    $tech_staff_lookup[$ts['staff_id']] = $ts;
                                }
                                
                                foreach ($all_staff as $staff) { 
                                    $is_tech = isset($tech_staff_lookup[$staff['staffid']]);
                                    $tech_data = $is_tech ? $tech_staff_lookup[$staff['staffid']] : null;
                                ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="staff_ids[]" value="<?php echo $staff['staffid']; ?>" 
                                                   <?php echo $is_tech ? 'checked' : ''; ?> class="staff-checkbox">
                                        </td>
                                        <td>
                                            <a href="<?php echo admin_url('staff/member/' . $staff['staffid']); ?>">
                                                <?php echo staff_profile_image($staff['staffid'], ['img', 'img-circle', 'staff-profile-image-small']); ?>
                                                <?php echo $staff['firstname'] . ' ' . $staff['lastname']; ?>
                                            </a>
                                        </td>
                                        <td><?php echo $staff['email']; ?></td>
                                        <td>
                                            <select name="roles[<?php echo $staff['staffid']; ?>]" class="form-control input-sm">
                                                <option value="member" <?php echo ($tech_data && $tech_data['role'] == 'member') ? 'selected' : ''; ?>><?php echo _l('tech_role_member'); ?></option>
                                                <option value="senior" <?php echo ($tech_data && $tech_data['role'] == 'senior') ? 'selected' : ''; ?>><?php echo _l('tech_role_senior'); ?></option>
                                                <option value="lead" <?php echo ($tech_data && $tech_data['role'] == 'lead') ? 'selected' : ''; ?>><?php echo _l('tech_role_lead'); ?></option>
                                                <option value="manager" <?php echo ($tech_data && $tech_data['role'] == 'manager') ? 'selected' : ''; ?>><?php echo _l('tech_role_manager'); ?></option>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="onoffswitch">
                                                <input type="checkbox" name="is_active[<?php echo $staff['staffid']; ?>]" 
                                                       class="onoffswitch-checkbox" id="active_<?php echo $staff['staffid']; ?>" 
                                                       value="1" <?php echo ($tech_data && $tech_data['is_active']) ? 'checked' : ''; ?>>
                                                <label class="onoffswitch-label" for="active_<?php echo $staff['staffid']; ?>"></label>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>

                        <div class="row">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary pull-right">
                                    <i class="fa fa-save"></i>
                                    <?php echo _l('submit'); ?>
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
        // Select all checkbox
        $('#select_all').on('change', function() {
            $('.staff-checkbox').prop('checked', $(this).prop('checked'));
        });

        // Update select all checkbox when individual checkboxes change
        $('.staff-checkbox').on('change', function() {
            var all_checked = $('.staff-checkbox:checked').length === $('.staff-checkbox').length;
            $('#select_all').prop('checked', all_checked);
        });
    });
</script>
