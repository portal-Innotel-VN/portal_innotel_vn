<?php defined('BASEPATH') or exit('No direct script access allowed');
$CI =& get_instance();
$CI->load->model('sales_pipeline/sales_pipeline_model');

foreach ($statuses as $status) {
    $total_pages = ceil($CI->sales_pipeline_model->do_kanban_query($status['id'], $search, 1, [
        'quarter' => isset($quarter) ? $quarter : '',
        'year' => isset($year) ? $year : '',
        'staff_id' => isset($staff_id) ? $staff_id : '',
        'contract_signed' => isset($contract_signed) ? $contract_signed : '',
        'invoice_issued' => isset($invoice_issued) ? $invoice_issued : ''
    ], true) / 10);
    
    $status_color = '';
    if (!empty($status["color"])) {
        $status_color = 'style="border-top: 3px solid ' . $status["color"] . '"';
    }
    
    $deals = $CI->sales_pipeline_model->do_kanban_query($status['id'], $search, 1, [
        'sort_by' => $sort_by,
        'sort' => $sort_type,
        'quarter' => isset($quarter) ? $quarter : '',
        'year' => isset($year) ? $year : '',
        'staff_id' => isset($staff_id) ? $staff_id : '',
        'contract_signed' => isset($contract_signed) ? $contract_signed : '',
        'invoice_issued' => isset($invoice_issued) ? $invoice_issued : ''
    ]);
    $total_deals = count($deals);
    ?>
    <div class="pipeline-kan-ban-col" data-status-id="<?php echo $status['id']; ?>" data-total-pages="<?php echo $total_pages; ?>">
        <div class="panel_s" <?php echo $status_color; ?>>
            <div class="panel-heading kanban-status-header">
                <span class="kanban-status-name"><?php echo html_escape($status['name']); ?></span>
                <span class="kanban-status-count"><?php echo $total_deals; ?></span>
            </div>
            <div class="panel-body">
                <ul>
                    <?php
                    foreach ($deals as $deal) {
                        $CI->load->view('sales_pipeline/_kanban_card', [
                            'deal' => $deal, 
                            'status' => $status, 
                            'query_string' => isset($query_string) ? $query_string : ''
                        ]);
                    }
                    ?>
                </ul>
                <?php if ($total_pages > 1) { ?>
                <div class="kanban-load-more">
                    <button class="btn btn-default btn-sm" 
                       onclick="pipeline_load_more(<?php echo $status['id']; ?>, 2, this); return false;">
                        <i class="fa fa-angle-down"></i> <?php echo _l('load_more'); ?>
                    </button>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
<?php } ?>
