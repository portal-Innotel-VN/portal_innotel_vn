<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <i class="fa fa-cogs"></i> <?php echo $title; ?>
                            <a href="<?php echo admin_url('sales_pipeline'); ?>" class="btn btn-default pull-right">
                                <i class="fa fa-arrow-left"></i> <?php echo _l('sales_pipeline_back'); ?>
                            </a>
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <div class="horizontal-scrollable-tabs">
                            <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
                            <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
                            <div class="horizontal-tabs">
                                <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
                                    <li role="presentation" class="active">
                                        <a href="#statuses" aria-controls="statuses" role="tab" data-toggle="tab">
                                            <?php echo _l('sales_pipeline_settings_statuses'); ?>
                                        </a>
                                    </li>
                                    <li role="presentation">
                                        <a href="#sources" aria-controls="sources" role="tab" data-toggle="tab">
                                            <?php echo _l('sales_pipeline_settings_sources'); ?>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="tab-content mtop15">
                            <!-- TAB TRẠNG THÁI -->
                            <div role="tabpanel" class="tab-pane active" id="statuses">
                                <a href="#" class="btn btn-info mbot15" data-toggle="modal" data-target="#status_modal" onclick="reset_status_modal(); return false;">
                                    <i class="fa fa-plus"></i> <?php echo _l('sales_pipeline_add_status'); ?>
                                </a>
                                <table class="table table-bordered dt-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th><?php echo _l('sales_pipeline_status_name'); ?></th>
                                            <th><?php echo _l('sales_pipeline_status_color'); ?></th>
                                            <th><?php echo _l('sales_pipeline_status_order'); ?></th>
                                            <th><?php echo _l('sales_pipeline_status_is_won'); ?></th>
                                            <th><?php echo _l('sales_pipeline_status_is_lost'); ?></th>
                                            <th><?php echo _l('sales_pipeline_options'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($statuses as $status) { ?>
                                        <tr>
                                            <td><?php echo $status['id']; ?></td>
                                            <td><span class="label" style="background:<?php echo $status['color']; ?>"><?php echo html_escape($status['name']); ?></span></td>
                                            <td><?php echo $status['color']; ?></td>
                                            <td><?php echo $status['order']; ?></td>
                                            <td><?php echo $status['is_won'] ? '<i class="fa fa-check text-success"></i>' : ''; ?></td>
                                            <td><?php echo $status['is_lost'] ? '<i class="fa fa-check text-success"></i>' : ''; ?></td>
                                            <td>
                                                <a href="#" class="btn btn-default btn-icon" onclick="edit_status(this, <?php echo $status['id']; ?>); return false;" data-name="<?php echo html_escape($status['name']); ?>" data-color="<?php echo $status['color']; ?>" data-order="<?php echo $status['order']; ?>" data-is-won="<?php echo $status['is_won']; ?>" data-is-lost="<?php echo $status['is_lost']; ?>"><i class="fa fa-pencil"></i></a>
                                                <a href="<?php echo admin_url('sales_pipeline/delete_setting/status/' . $status['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- TAB NGUỒN KHÁCH HÀNG -->
                            <div role="tabpanel" class="tab-pane" id="sources">
                                <a href="#" class="btn btn-info mbot15" data-toggle="modal" data-target="#source_modal" onclick="reset_source_modal(); return false;">
                                    <i class="fa fa-plus"></i> <?php echo _l('sales_pipeline_add_source'); ?>
                                </a>
                                <table class="table table-bordered dt-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th><?php echo _l('sales_pipeline_source_name'); ?></th>
                                            <th><?php echo _l('sales_pipeline_options'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sources as $source) { ?>
                                        <tr>
                                            <td><?php echo $source['id']; ?></td>
                                            <td><?php echo html_escape($source['name']); ?></td>
                                            <td>
                                                <a href="#" class="btn btn-default btn-icon" onclick="edit_source(this, <?php echo $source['id']; ?>); return false;" data-name="<?php echo html_escape($source['name']); ?>"><i class="fa fa-pencil"></i></a>
                                                <a href="<?php echo admin_url('sales_pipeline/delete_setting/source/' . $source['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>
                                            </td>
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

<!-- Modal Trạng Thái -->
<div class="modal fade" id="status_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?php echo form_open(admin_url('sales_pipeline/settings')); ?>
        <input type="hidden" name="setting_type" value="status">
        <input type="hidden" name="id" id="status_id" value="">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo html_escape(_l('sales_pipeline_close')); ?>"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="status_modal_title"><?php echo _l('sales_pipeline_add_status'); ?></h4>
            </div>
            <div class="modal-body">
                <?php echo render_input('name', _l('sales_pipeline_status_name'), '', 'text', ['required' => 'true']); ?>
                <?php echo render_color_picker('color', _l('sales_pipeline_status_color')); ?>
                <?php echo render_input('order', _l('sales_pipeline_display_order'), '0', 'number'); ?>
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" name="is_won" id="is_won" value="1">
                    <label for="is_won"><?php echo _l('sales_pipeline_is_won_status'); ?></label>
                </div>
                <div class="checkbox checkbox-danger">
                    <input type="checkbox" name="is_lost" id="is_lost" value="1">
                    <label for="is_lost"><?php echo _l('sales_pipeline_is_lost_status'); ?></label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="submit" class="btn btn-info"><?php echo _l('submit'); ?></button>
            </div>
        </div>
        <?php echo form_close(); ?>
    </div>
</div>

<!-- Modal Nguồn Khách Hàng -->
<div class="modal fade" id="source_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?php echo form_open(admin_url('sales_pipeline/settings')); ?>
        <input type="hidden" name="setting_type" value="source">
        <input type="hidden" name="id" id="source_id_input" value="">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo html_escape(_l('sales_pipeline_close')); ?>"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="source_modal_title"><?php echo _l('sales_pipeline_add_source_title'); ?></h4>
            </div>
            <div class="modal-body">
                <?php echo render_input('name', _l('sales_pipeline_source_name'), '', 'text', ['required' => 'true']); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="submit" class="btn btn-info"><?php echo _l('submit'); ?></button>
            </div>
        </div>
        <?php echo form_close(); ?>
    </div>
</div>

<?php init_tail(); ?>
<script>
    $(function(){
        initDataTable('.dt-table');
    });

    function reset_status_modal() {
        $('#status_modal_title').text('<?php echo _l('sales_pipeline_add_status'); ?>');
        $('#status_modal input[name="id"]').val('');
        $('#status_modal input[name="name"]').val('');
        $('#status_modal input[name="color"]').val('#333333');
        $('#status_modal input[name="color"]').next('.input-group-addon').find('i').css('background-color', '#333333');
        $('#status_modal input[name="order"]').val('0');
        $('#status_modal input[name="is_won"]').prop('checked', false);
        $('#status_modal input[name="is_lost"]').prop('checked', false);
    }

    function edit_status(invoker, id) {
        var name = $(invoker).data('name');
        var color = $(invoker).data('color');
        var order = $(invoker).data('order');
        var is_won = $(invoker).data('is-won');
        var is_lost = $(invoker).data('is-lost');

        $('#status_modal_title').text('<?php echo _l('sales_pipeline_edit_status'); ?>');
        $('#status_modal input[name="id"]').val(id);
        $('#status_modal input[name="name"]').val(name);
        $('#status_modal input[name="color"]').val(color);
        $('#status_modal input[name="color"]').next('.input-group-addon').find('i').css('background-color', color);
        $('#status_modal input[name="order"]').val(order);
        $('#status_modal input[name="is_won"]').prop('checked', is_won == 1);
        $('#status_modal input[name="is_lost"]').prop('checked', is_lost == 1);
        $('#status_modal').modal('show');
    }

    function reset_source_modal() {
        $('#source_modal_title').text('<?php echo _l('sales_pipeline_add_source_title'); ?>');
        $('#source_modal input[name="id"]').val('');
        $('#source_modal input[name="name"]').val('');
    }

    function edit_source(invoker, id) {
        var name = $(invoker).data('name');
        $('#source_modal_title').text('<?php echo _l('sales_pipeline_edit_source_title'); ?>');
        $('#source_modal input[name="id"]').val(id);
        $('#source_modal input[name="name"]').val(name);
        $('#source_modal').modal('show');
    }
</script>
</body>
</html>
