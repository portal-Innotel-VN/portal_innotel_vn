<?php

defined('BASEPATH') or exit('No direct script access allowed');

// 1. Các cột hiển thị (phải khớp thứ tự với thẻ <th> ở View)
$aColumns = [
    db_prefix() . 'my_customers.userid as userid',
    db_prefix() . 'my_customers.company as company',
    db_prefix() . 'my_customers.phonenumber as phonenumber',
    db_prefix() . 'my_customers.address as address',
    db_prefix() . 'my_customers.active as active',
    'CONCAT(firstname, " ", lastname) as assigned_staff_name',
    db_prefix() . 'my_customers.datecreated as datecreated'
];

$sIndexColumn = 'userid';
$sTable       = db_prefix() . 'my_customers';
$join         = [
    'LEFT JOIN ' . db_prefix() . 'staff ON ' . db_prefix() . 'staff.staffid = ' . db_prefix() . 'my_customers.assigned_staff'
];
$where        = [];

// 2. Xử lý Bộ lọc động (Filters) gửi từ Client
// Nhận tham số POST an toàn thông qua thư viện đầu vào của CodeIgniter
$region = $this->ci->input->post('region');
$active = $this->ci->input->post('active');

// Lọc theo Vùng miền: Tìm kiếm chuỗi tương đối trong cột địa chỉ (hoặc cột vùng miền của bạn)
if (!empty($region)) {
    // Sử dụng escape_str để phòng tránh SQL Injection
    $escaped_region = $this->ci->db->escape_str($region);
    array_push($where, "AND (" . db_prefix() . "my_customers.address LIKE '%" . $escaped_region . "%')");
}

// Lọc theo Trạng thái (1: Hoạt động, 0: Ngừng hoạt động)
if ($active !== '' && $active !== null) {
    $escaped_active = intval($active); // Cast kiểu INT để tuyệt đối an toàn
    array_push($where, "AND " . db_prefix() . "my_customers.active = " . $escaped_active);
}

// 3. Khởi tạo datatable thông qua helper hệ thống
$result  = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, []);
$output  = $result['output'];
$rResult = $result['rResult'];

// 4. Định dạng dữ liệu đầu ra cho từng dòng
foreach ($rResult as $aRow) {
    $row = [];

    // Cột # ID
    $row[] = $aRow['userid'];

    // Cột Tên công ty kèm Link chi tiết (Chỉ hiện Link nếu có quyền Edit)
    if (has_permission('my_customers', '', 'edit')) {
        $companyUrl = admin_url('my_customers/customer/' . $aRow['userid']);
        $row[] = '<a href="' . $companyUrl . '"><strong>' . html_escape($aRow['company']) . '</strong></a>';
    } else {
        $row[] = '<strong>' . html_escape($aRow['company']) . '</strong>';
    }

    // Cột Số điện thoại
    $row[] = html_escape($aRow['phonenumber']);

    // Cột Địa chỉ
    $row[] = html_escape($aRow['address']);

    // Cột Trạng thái (Active / Inactive)
    if ($aRow['active'] == 1) {
        $row[] = '<span class="label label-success">Hoạt động</span>';
    } else {
        $row[] = '<span class="label label-danger">Ngừng</span>';
    }

    // Cột Nhân viên phụ trách
    $row[] = $aRow['assigned_staff_name'] ? html_escape($aRow['assigned_staff_name']) : '<em>Chưa phân công</em>';

    // Cột Ngày tạo
    $row[] = _dt($aRow['datecreated']); // Dùng helper định dạng ngày của Perfex CRM

    // Cột Thao tác (Hành động sửa/xóa - check quyền tương ứng)
    $options = '';
    if (has_permission('my_customers', '', 'edit')) {
        $companyUrl = admin_url('my_customers/customer/' . $aRow['userid']);
        $options .= '<a href="' . $companyUrl . '" class="btn btn-default btn-icon" title="Sửa"><i class="fa fa-pencil-square-o"></i></a>';
    }
    if (has_permission('my_customers', '', 'delete')) {
        $options .= '<a href="' . admin_url('my_customers/delete/' . $aRow['userid']) . '" class="btn btn-danger btn-icon _delete" title="Xóa"><i class="fa fa-remove"></i></a>';
    }
    $row[] = $options;

    $row['DT_RowClass'] = 'has-row-options';
    $output['aaData'][] = $row;
}
