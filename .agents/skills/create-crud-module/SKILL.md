---
name: create-crud-module
description: Tạo bộ Controller, Model, và View hoàn chỉnh cho một chức năng CRUD mới trong admin panel Perfex CRM. Kích hoạt khi người dùng yêu cầu tạo module mới, thêm chức năng quản lý mới, tạo trang admin mới, hoặc scaffold CRUD.
---

# Hướng dẫn tạo Module CRUD mới cho Admin Panel

## Tổng quan các file cần tạo

Khi tạo một chức năng CRUD mới (ví dụ: `products`), cần tạo **5 file**:

```
application/
├── controllers/admin/{ModuleName}.php     # Controller
├── models/{ModuleName}_model.php          # Model
└── views/admin/{module_name}/
    ├── manage.php                          # View danh sách (list)
    ├── {singular_name}.php                 # View form (thêm/sửa)
    └── (views/admin/tables/{module_name}.php)  # DataTable server-side (nếu cần)
```

## Quy tắc đặt tên

| Thành phần | Quy tắc | Ví dụ |
|---|---|---|
| Controller file | `{ModuleName}.php` (PascalCase) | `Products.php` |
| Controller class | `class {ModuleName} extends AdminController` | `class Products extends AdminController` |
| Model file | `{ModuleName}_model.php` | `Products_model.php` |
| Model class | `class {ModuleName}_model extends App_Model` | `class Products_model extends App_Model` |
| View folder | `views/admin/{module_name}/` (snake_case) | `views/admin/products/` |
| Table name | `{module_name}` (snake_case, KHÔNG có `tbl`) | Table: `tblproducts` |

---

## Bước 1: Tạo Model

**File**: `application/models/{ModuleName}_model.php`

```php
<?php
defined('BASEPATH') or exit('No direct script access allowed');

class {ModuleName}_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Lấy danh sách hoặc 1 bản ghi theo ID
     * @param string|int $id  Nếu rỗng → trả tất cả, nếu có ID → trả 1 row
     */
    public function get($id = '')
    {
        if ($id != '') {
            $this->db->where('id', $id);
            return $this->db->get(db_prefix() . '{module_name}')->row_array();
        }
        return $this->db->get(db_prefix() . '{module_name}')->result_array();
    }

    /**
     * Thêm bản ghi mới
     */
    public function add($data)
    {
        $data['datecreated'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . '{module_name}', $data);
        return $this->db->insert_id();
    }

    /**
     * Cập nhật bản ghi
     */
    public function update($data, $id)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . '{module_name}', $data);
        return $this->db->affected_rows() > 0;
    }

    /**
     * Xóa bản ghi
     */
    public function delete($id)
    {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . '{module_name}');
        return $this->db->affected_rows() > 0;
    }
}
```

**Lưu ý**: Luôn dùng `db_prefix() . '{module_name}'` — KHÔNG BAO GIỜ hardcode `tbl`.

---

## Bước 2: Tạo Controller

**File**: `application/controllers/admin/{ModuleName}.php`

```php
<?php
defined('BASEPATH') or exit('No direct script access allowed');

class {ModuleName} extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('{module_name}_model');
        $this->load->library('form_validation');
    }

    /**
     * Trang danh sách (index)
     * URL: admin/{module_name}
     */
    public function index()
    {
        if (!has_permission('{module_name}', '', 'view')) {
            access_denied('{module_name}');
        }

        $data['items'] = $this->{module_name}_model->get();
        $data['title'] = '{Tên tiếng Việt}';

        $this->load->view('admin/{module_name}/manage', $data);
    }

    /**
     * API endpoint cho DataTable server-side
     * URL: admin/{module_name}/table
     */
    public function table()
    {
        if (!has_permission('{module_name}', '', 'view')) {
            ajax_access_denied();
        }
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }
        $this->app->get_table_data('{module_name}');
    }

    /**
     * Form thêm/sửa
     * URL: admin/{module_name}/{singular} hoặc admin/{module_name}/{singular}/{id}
     */
    public function {singular_name}($id = '')
    {
        if ($id == '') {
            if (!has_permission('{module_name}', '', 'create')) {
                access_denied('{module_name}');
            }
        } else {
            if (!has_permission('{module_name}', '', 'edit')) {
                access_denied('{module_name}');
            }
        }

        // Validation rules
        $this->form_validation->set_rules('field_name', 'Label', 'trim|required');

        if ($this->input->post()) {
            if ($this->form_validation->run() !== false) {
                $post_data = [
                    'field_name' => $this->input->post('field_name'),
                    // ... thêm các field khác
                ];

                if ($id == '') {
                    $insert_id = $this->{module_name}_model->add($post_data);
                    if ($insert_id) {
                        set_alert('success', 'Thêm thành công!');
                    }
                } else {
                    $success = $this->{module_name}_model->update($post_data, $id);
                    if ($success) {
                        set_alert('success', 'Cập nhật thành công!');
                    }
                }
                redirect(admin_url('{module_name}'));
            }
        }

        if ($id != '') {
            $data['item'] = $this->{module_name}_model->get($id);
        }

        $data['title'] = ($id == '') ? 'Thêm {Tên}' : 'Sửa {Tên}';
        $this->load->view('admin/{module_name}/{singular_name}', $data);
    }

    /**
     * Xóa bản ghi
     * URL: admin/{module_name}/delete/{id}
     */
    public function delete($id)
    {
        if (!has_permission('{module_name}', '', 'delete')) {
            access_denied('{module_name}');
        }

        $success = $this->{module_name}_model->delete($id);
        if ($success) {
            set_alert('success', 'Xóa thành công!');
        }
        redirect(admin_url('{module_name}'));
    }
}
```

---

## Bước 3: Tạo View Danh sách (manage.php)

**File**: `application/views/admin/{module_name}/manage.php`

```php
<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <!-- Tiêu đề & Nút thêm mới -->
                        <div class="clearfix mbot15">
                            <h4 class="no-margin font-bold pull-left" style="line-height: 34px;">
                                <i class="fa fa-{icon}"></i> <?php echo $title; ?>
                            </h4>
                            <?php if (has_permission('{module_name}', '', 'create')) { ?>
                            <a href="<?php echo admin_url('{module_name}/{singular_name}'); ?>"
                               class="btn btn-info pull-right">
                                <i class="fa fa-plus"></i> Thêm Mới
                            </a>
                            <?php } ?>
                        </div>
                        <hr class="hr-panel-heading" />

                        <!-- DataTable -->
                        <table class="table table-{module_name} table-striped">
                            <thead>
                                <tr>
                                    <th># ID</th>
                                    <th>Tên</th>
                                    <!-- Thêm các cột khác -->
                                    <th>Ngày Tạo</th>
                                    <th class="text-center">Thao Tác</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
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
    initDataTable('.table-{module_name}', admin_url + '{module_name}/table', undefined, undefined, undefined, [0, 'desc']);
});
</script>
</body>
</html>
```

---

## Bước 4: Tạo View Form (thêm/sửa)

**File**: `application/views/admin/{module_name}/{singular_name}.php`

```php
<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin font-bold">
                            <i class="fa fa-{icon}"></i> <?php echo $title; ?>
                        </h4>
                        <hr class="hr-panel-heading" />

                        <!-- Hiển thị lỗi validation -->
                        <?php if (validation_errors()) { ?>
                            <div class="alert alert-danger">
                                <?php echo validation_errors(); ?>
                            </div>
                        <?php } ?>

                        <!-- Form -->
                        <?php echo form_open($this->uri->uri_string()); ?>

                        <div class="form-group">
                            <label for="field_name" class="control-label">
                                <span class="text-danger">*</span> Label
                            </label>
                            <input type="text" class="form-control" name="field_name" id="field_name"
                                   value="<?php echo isset($item) ? html_escape($item['field_name']) : ''; ?>"
                                   required autocomplete="off">
                        </div>

                        <!-- Thêm các form-group khác tương tự -->

                        <hr />
                        <div class="text-right">
                            <a href="<?php echo admin_url('{module_name}'); ?>" class="btn btn-default mright5">
                                Quay Lại
                            </a>
                            <button type="submit" class="btn btn-info">Lưu Lại</button>
                        </div>

                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
</body>
</html>
```

---

## Bước 5: Tạo DataTable Server-side (tùy chọn)

**File**: `application/views/admin/tables/{module_name}.php`

```php
<?php
defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    db_prefix() . '{module_name}.id as id',
    db_prefix() . '{module_name}.name as name',
    db_prefix() . '{module_name}.datecreated as datecreated',
];

$sIndexColumn = 'id';
$sTable       = db_prefix() . '{module_name}';
$join         = [];
$where        = [];

$result  = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, []);
$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];
    $row[] = $aRow['id'];
    $row[] = '<strong>' . html_escape($aRow['name']) . '</strong>';
    $row[] = _dt($aRow['datecreated']);

    // Cột thao tác
    $options = '';
    if (has_permission('{module_name}', '', 'edit')) {
        $options .= '<a href="' . admin_url('{module_name}/{singular_name}/' . $aRow['id']) . '" class="btn btn-default btn-icon" title="Sửa"><i class="fa fa-pencil-square-o"></i></a>';
    }
    if (has_permission('{module_name}', '', 'delete')) {
        $options .= '<a href="' . admin_url('{module_name}/delete/' . $aRow['id']) . '" class="btn btn-danger btn-icon _delete" title="Xóa"><i class="fa fa-remove"></i></a>';
    }
    $row[] = $options;

    $row['DT_RowClass'] = 'has-row-options';
    $output['aaData'][] = $row;
}
```

---

## Checklist sau khi tạo

- [ ] Model kế thừa `App_Model`, dùng `db_prefix()` cho mọi query
- [ ] Controller kế thừa `AdminController` (nằm trong `controllers/admin/`)
- [ ] Kiểm tra quyền (`has_permission`) ở mọi action
- [ ] View dùng `init_head()` / `init_tail()` đúng format
- [ ] Form dùng `form_open()` / `form_close()` (tự thêm CSRF token)
- [ ] Hiển thị `validation_errors()` trong view form
- [ ] Dùng `set_alert('success', '...')` cho thông báo
- [ ] Dùng `redirect(admin_url('{module_name}'))` sau khi xử lý POST
- [ ] DataTable file nằm trong `views/admin/tables/`
- [ ] Thêm menu sidebar (xem skill `add-admin-menu`)
- [ ] Tạo migration cho bảng mới (xem skill `create-migration`)
