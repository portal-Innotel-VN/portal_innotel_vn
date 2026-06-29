<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_244 extends CI_Migration
{
    public function __construct()
    {
        parent::__construct();
    }

        public function up()
    {
        $this->dbforge->add_field([
            'userid' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => TRUE,
                'auto_increment' => TRUE
            ],
            'company' => [
                'type'       => 'VARCHAR',
                'constraint' => '191',
            ],
            'phonenumber' => [
                'type'       => 'VARCHAR',
                'constraint' => '30',
                'null'       => TRUE,
            ],
            'address' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => TRUE,
            ],
            'active' => [
                'type'    => 'INT',
                'default' => 1,
            ],
            'datecreated' => [
                'type' => 'DATETIME',
            ],
        ]);

        // Đặt userid làm khóa chính
        $this->dbforge->add_key('userid', TRUE);

        // Tạo bảng my_customers
        $this->dbforge->create_table('my_customers', TRUE);
    }

    // 
        public function down()
    {
        $this->dbforge->drop_table('my_customers', TRUE);
    }

}
