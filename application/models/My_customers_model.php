<?php

defined('BASEPATH') or exit('No direct script access allowed');

class My_customers_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // Lấy danh sách khách hàng (hoặc 1 khách hàng theo ID)
    public function get($id = '')
    {
        if ($id != '') {
            $this->db->where('userid', $id);
            return $this->db->get(db_prefix() . 'my_customers')->row_array();
        }

        return $this->db->get(db_prefix() . 'my_customers')->result_array();
    }

    // Thêm khách hàng mới
    public function add($data)
    {
        $data['datecreated'] = date('Y-m-d H:i:s');

        $this->db->insert(db_prefix() . 'my_customers', $data);
        return $this->db->insert_id();
    }

    // Cập nhật khách hàng
    public function update($data, $id)
    {
        $this->db->where('userid', $id);
        $this->db->update(db_prefix() . 'my_customers', $data);

        return $this->db->affected_rows() > 0;
    }

    // Xóa khách hàng
    public function delete($id)
    {
        $this->db->where('userid', $id);
        $this->db->delete(db_prefix() . 'my_customers');

        return $this->db->affected_rows() > 0;
    }
}
