---
name: create-migration
description: Tạo file migration mới cho dự án Perfex CRM. Kích hoạt khi người dùng yêu cầu tạo migration, thêm cột, xóa cột, sửa bảng database, thay đổi cấu trúc CSDL, hoặc tạo bảng mới.
---

# Hướng dẫn tạo Database Migration

## Quy trình bắt buộc

### Bước 1: Xác định version tiếp theo
- Đọc file `application/config/migration.php` để lấy `$config['migration_version']` hiện tại.
- Version mới = version hiện tại + 1.

### Bước 2: Tạo file migration
- **Đường dẫn**: `application/migrations/{version}_version_{version}.php`
- **Ví dụ**: Nếu version hiện tại là 245, tạo file `246_version_246.php`
- **Tên class**: `Migration_Version_{version}` — phải khớp chính xác với số trong tên file.
- **Kế thừa (Core)**: `extends CI_Migration` — cho migrations trong `application/migrations/`
- **Kế thừa (Module)**: `extends App_module_migration` — cho migrations trong `modules/{name}/migrations/`

### Bước 3: Cập nhật config
- Cập nhật `$config['migration_version']` trong `application/config/migration.php` lên version mới.
- Comment dòng version cũ, thêm dòng mới.

## Quy tắc tiền tố bảng (QUAN TRỌNG - DỄ SAI)

| Ngữ cảnh | Tiền tố `tbl`? | Ví dụ đúng |
|---|---|---|
| `$this->dbforge->add_column()` | ❌ KHÔNG | `$this->dbforge->add_column('my_customers', $fields);` |
| `$this->dbforge->drop_column()` | ❌ KHÔNG | `$this->dbforge->drop_column('my_customers', 'col_name');` |
| `$this->dbforge->create_table()` | ❌ KHÔNG | `$this->dbforge->create_table('my_customers', TRUE);` |
| `$this->dbforge->drop_table()` | ❌ KHÔNG | `$this->dbforge->drop_table('my_customers', TRUE);` |
| `$this->db->query()` (SQL thuần) | ✅ CÓ | `ALTER TABLE \`tblmy_customers\` ADD CONSTRAINT...` |

**Ghi nhớ**: dbforge tự thêm prefix → KHÔNG thêm `tbl`. Raw SQL KHÔNG tự thêm → PHẢI thêm `tbl` thủ công.

## Template: Thêm cột mới

```php
<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_{VERSION} extends CI_Migration
{
    public function __construct()
    {
        parent::__construct();
    }

    public function up()
    {
        $fields = [
            'column_name' => [
                'type' => 'VARCHAR',       // INT, VARCHAR, TEXT, DATETIME, DECIMAL...
                'constraint' => 191,       // Độ dài (VARCHAR) hoặc kích thước (INT=11)
                'null' => true,            // Cho phép NULL hay không
                'default' => '',           // Giá trị mặc định (nếu cần)
            ],
        ];
        $this->dbforge->add_column('table_name', $fields);  // KHÔNG có 'tbl'
    }

    public function down()
    {
        $this->dbforge->drop_column('table_name', 'column_name');  // KHÔNG có 'tbl'
    }
}
```

## Template: Thêm cột với Foreign Key

```php
public function up()
{
    // 1. Thêm cột (dbforge = KHÔNG có 'tbl')
    $fields = [
        'new_fk_column' => [
            'type' => 'INT',
            'constraint' => 11,
            'null' => true,
        ],
    ];
    $this->dbforge->add_column('table_name', $fields);

    // 2. Tạo FK constraint (raw SQL = CÓ 'tbl')
    $sql = "ALTER TABLE `tbltable_name` 
            ADD CONSTRAINT `fk_table_column` 
            FOREIGN KEY (`new_fk_column`) 
            REFERENCES `tblref_table`(`ref_column`) 
            ON DELETE SET NULL;";
    $this->db->query($sql);
}

public function down()
{
    // PHẢI xóa FK TRƯỚC KHI xóa cột
    $this->db->query("ALTER TABLE `tbltable_name` DROP FOREIGN KEY `fk_table_column`;");
    $this->dbforge->drop_column('table_name', 'new_fk_column');
}
```

## Template: Tạo bảng mới

```php
public function up()
{
    $this->dbforge->add_field([
        'id' => [
            'type' => 'INT',
            'constraint' => 11,
            'unsigned' => TRUE,
            'auto_increment' => TRUE,
        ],
        'name' => [
            'type' => 'VARCHAR',
            'constraint' => 191,
        ],
        'datecreated' => [
            'type' => 'DATETIME',
        ],
    ]);
    $this->dbforge->add_key('id', TRUE);        // Primary key
    $this->dbforge->create_table('new_table', TRUE);  // KHÔNG có 'tbl'
}

public function down()
{
    $this->dbforge->drop_table('new_table', TRUE);    // KHÔNG có 'tbl'
}
```

## Checklist trước khi hoàn thành
- [ ] File name đúng format: `{N}_version_{N}.php`
- [ ] Class name khớp: `Migration_Version_{N}`
- [ ] Extends `CI_Migration`
- [ ] Có cả `up()` và `down()`
- [ ] dbforge KHÔNG dùng prefix `tbl`
- [ ] Raw SQL CÓ dùng prefix `tbl`
- [ ] FK bị drop TRƯỚC KHI drop column trong `down()`
- [ ] `$config['migration_version']` trong `migration.php` đã được cập nhật
