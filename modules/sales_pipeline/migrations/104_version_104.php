<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_104 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        require_once(module_dir_path('sales_pipeline', 'includes/estimate_group_schema.php'));
        sales_pipeline_ensure_estimate_group_schema($CI);
    }
}
