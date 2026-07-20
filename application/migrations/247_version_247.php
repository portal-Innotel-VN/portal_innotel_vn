<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_247 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // 1. Tạo bảng tblsales_pipeline_sources
        if (!$CI->db->table_exists(db_prefix() . 'sales_pipeline_sources')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . "sales_pipeline_sources` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(100) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');

            // Insert default sources
            $sources = ['Giới thiệu', 'Cold Call', 'SEO', 'Quảng cáo', 'Khác'];
            foreach ($sources as $source) {
                $CI->db->insert(db_prefix() . 'sales_pipeline_sources', [
                    'name' => $source
                ]);
            }
        }

        // 2. Thêm cột source_id vào tblsales_pipeline (nếu chưa có)
        if (!$CI->db->field_exists('source_id', db_prefix() . 'sales_pipeline')) {
            $CI->dbforge->add_column('sales_pipeline', [
                'source_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                    'after' => 'contact_email'
                ]
            ]);

            // Migrate data: map old string `source` to new `source_id`
            if ($CI->db->field_exists('source', db_prefix() . 'sales_pipeline')) {
                $all_deals = $CI->db->get(db_prefix() . 'sales_pipeline')->result_array();
                foreach ($all_deals as $deal) {
                    if (!empty($deal['source'])) {
                        // Find source_id by name
                        $src_row = $CI->db->where('name', $deal['source'])->get(db_prefix() . 'sales_pipeline_sources')->row();
                        if ($src_row) {
                            $CI->db->where('id', $deal['id'])->update(db_prefix() . 'sales_pipeline', ['source_id' => $src_row->id]);
                        } else {
                            // If source doesn't exist in our table, insert it to keep data integrity
                            $CI->db->insert(db_prefix() . 'sales_pipeline_sources', ['name' => $deal['source']]);
                            $new_id = $CI->db->insert_id();
                            $CI->db->where('id', $deal['id'])->update(db_prefix() . 'sales_pipeline', ['source_id' => $new_id]);
                        }
                    }
                }
                
                // Drop the old source column
                $CI->dbforge->drop_column('sales_pipeline', 'source');
            }
        }

        // 3. Xóa bảng my_customers (Chức năng test CRUD)
        if ($CI->db->table_exists(db_prefix() . 'my_customers')) {
            $CI->dbforge->drop_table('my_customers');
        }
    }

    public function down()
    {
        $CI = &get_instance();

        // Rollback: revert source_id back to source string
        if ($CI->db->field_exists('source_id', db_prefix() . 'sales_pipeline')) {
            $CI->dbforge->add_column('sales_pipeline', [
                'source' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                    'after' => 'contact_email'
                ]
            ]);

            if ($CI->db->table_exists(db_prefix() . 'sales_pipeline_sources')) {
                // Map back ID to String
                $all_deals = $CI->db->get(db_prefix() . 'sales_pipeline')->result_array();
                foreach ($all_deals as $deal) {
                    if (!empty($deal['source_id'])) {
                        $src_row = $CI->db->where('id', $deal['source_id'])->get(db_prefix() . 'sales_pipeline_sources')->row();
                        if ($src_row) {
                            $CI->db->where('id', $deal['id'])->update(db_prefix() . 'sales_pipeline', ['source' => $src_row->name]);
                        }
                    }
                }
            }

            $CI->dbforge->drop_column('sales_pipeline', 'source_id');
        }

        if ($CI->db->table_exists(db_prefix() . 'sales_pipeline_sources')) {
            $CI->dbforge->drop_table('sales_pipeline_sources', true);
        }
    }
}
