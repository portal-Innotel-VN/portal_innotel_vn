<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Reminder_delivery_lock
{
    private $CI;
    private $acquired = false;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function acquire()
    {
        if (!empty($this->CI->db->pconnect)) {
            return false;
        }

        $row = $this->CI->db->query('SELECT GET_LOCK(?, 0) AS lock_result', [$this->lockName()])->row_array();
        $this->acquired = isset($row['lock_result']) && (int) $row['lock_result'] === 1;

        return $this->acquired;
    }

    public function release()
    {
        if (!$this->acquired) {
            return true;
        }

        $row = $this->CI->db->query('SELECT RELEASE_LOCK(?) AS lock_result', [$this->lockName()])->row_array();
        $this->acquired = false;

        return isset($row['lock_result']) && (int) $row['lock_result'] === 1;
    }

    private function lockName()
    {
        return db_prefix() . ':sales_pipeline:reminder:email';
    }
}
