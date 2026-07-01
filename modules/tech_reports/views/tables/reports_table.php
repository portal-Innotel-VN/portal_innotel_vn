<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'id',
    'staff_id',
    'report_date',
    'task_description',
    'task_category',
    'hours_spent',
    'status',
    'id', // for actions
];

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'tech_daily_reports';

$join = [
    'LEFT JOIN ' . db_prefix() . 'staff as staff ON staff.staffid = ' . $sTable . '.staff_id',
];

$additionalSelect = [
    'CONCAT(staff.firstname, " ", staff.lastname) as staff_name',
];

$where = [];

// Filter by staff
if ($this->ci->input->post('filter_staff')) {
    array_push($where, 'AND ' . $sTable . '.staff_id = ' . $this->ci->input->post('filter_staff'));
}

// Filter by category
if ($this->ci->input->post('filter_category')) {
    array_push($where, 'AND ' . $sTable . '.task_category = "' . $this->ci->db->escape_str($this->ci->input->post('filter_category')) . '"');
}

// Filter by date range
if ($this->ci->input->post('filter_date_from')) {
    array_push($where, 'AND ' . $sTable . '.report_date >= "' . $this->ci->db->escape_str($this->ci->input->post('filter_date_from')) . '"');
}

if ($this->ci->input->post('filter_date_to')) {
    array_push($where, 'AND ' . $sTable . '.report_date <= "' . $this->ci->db->escape_str($this->ci->input->post('filter_date_to')) . '"');
}

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, $additionalSelect);

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    // ID
    $row[] = $aRow['id'];

    // Staff
    $row[] = '<a href="' . admin_url('staff/member/' . $aRow['staff_id']) . '">' . $aRow['staff_name'] . '</a>';

    // Date
    $row[] = _d($aRow['report_date']);

    // Task Description
    $task_desc = strlen($aRow['task_description']) > 100 
        ? substr($aRow['task_description'], 0, 100) . '...' 
        : $aRow['task_description'];
    $row[] = '<span title="' . htmlspecialchars($aRow['task_description']) . '">' . $task_desc . '</span>';

    // Category
    if (!empty($aRow['task_category'])) {
        $category_label = _l('tech_category_' . $aRow['task_category']);
        $row[] = '<span class="label label-default">' . $category_label . '</span>';
    } else {
        $row[] = '-';
    }

    // Hours
    $row[] = number_format($aRow['hours_spent'], 1) . 'h';

    // Status
    $status_class = '';
    switch ($aRow['status']) {
        case 'completed':
            $status_class = 'success';
            break;
        case 'in_progress':
            $status_class = 'info';
            break;
        case 'blocked':
            $status_class = 'danger';
            break;
        case 'not_started':
            $status_class = 'default';
            break;
    }
    $status_label = _l('tech_status_' . $aRow['status']);
    $row[] = '<span class="label label-' . $status_class . '">' . $status_label . '</span>';

    // Actions
    $options = '';
    if (has_permission('tech_reports', '', 'edit')) {
        $options .= '<a href="' . admin_url('tech_reports/report/' . $aRow['id']) . '" class="btn btn-default btn-icon btn-sm" title="' . _l('edit') . '">
            <i class="fa fa-pencil-square-o"></i>
        </a> ';
    }
    if (has_permission('tech_reports', '', 'delete')) {
        $options .= '<a href="' . admin_url('tech_reports/delete/' . $aRow['id']) . '" class="btn btn-danger btn-icon btn-sm _delete" title="' . _l('delete') . '">
            <i class="fa fa-remove"></i>
        </a>';
    }
    $row[] = $options;

    $output['aaData'][] = $row;
}