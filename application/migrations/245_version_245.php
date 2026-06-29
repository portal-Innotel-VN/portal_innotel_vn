<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_245 extends CI_Migration
{
    public function __construct()
    {
        parent::__construct();
    }

    public function up()
    {
        // 1. Thêm cột assigned_staff (INT, cho phép NULL)
        $fields = [
            'assigned_staff' => [
                'type' => 'INT',
                'constraint' => 11, // Định dạng độ dài chuẩn của khóa chính staffid
                'null' => true,
            ],
        ];
        $this->dbforge->add_column('my_customers', $fields);

        // 2. Tạo Ràng buộc Khóa ngoại (Foreign Key Constraint) bằng SQL thuần
        $sql = "ALTER TABLE `tblmy_customers` 
                ADD CONSTRAINT `fk_my_customers_staff` 
                FOREIGN KEY (`assigned_staff`) 
                REFERENCES `tblstaff`(`staffid`) 
                ON DELETE SET NULL;"; // Rất quan trọng: Nếu nhân viên bị xóa, cột này sẽ tự trả về NULL thay vì xóa luôn khách hàng

        $this->db->query($sql);
    }

    public function down()
    {
        // Khi Rollback (lùi phiên bản), PHẢI xóa Khóa ngoại trước khi xóa cột
        $this->db->query("ALTER TABLE `tblmy_customers` DROP FOREIGN KEY `fk_my_customers_staff`;");

        // Sau đó mới xóa cột an toàn
        $this->dbforge->drop_column('my_customers', 'assigned_staff');
    }
}
