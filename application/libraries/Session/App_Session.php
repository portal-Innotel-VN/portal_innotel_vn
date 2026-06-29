<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * File này được tạo ra để ghi đè (override) thư viện Session lõi của CodeIgniter 3.
 * LÝ DO: Bản lõi CodeIgniter 3 (trước 3.1.13) có một bug khi chạy trên PHP 8.
 * Cụ thể trong hàm _ci_init_vars(), phép so sánh `elseif ($value < $current_time)`
 * với $value = 'old' sẽ trả về FALSE trên PHP 8 (vì chữ 'o' lớn hơn chữ '1' của timestamp).
 * Việc trả về FALSE khiến Flashdata không bao giờ được xóa và gây ra lỗi "Dính Flashdata".
 *
 * Tạo file ở thư mục application/ giúp bản vá này sống sót qua các lần cập nhật (Update)
 * của Perfex CRM mà không bị ghi đè, tuân thủ nguyên tắc không can thiệp thư mục system/.
 */
class App_Session extends CI_Session
{
    public function __construct(array $params = array())
    {
        parent::__construct($params);
    }

    /**
     * Handle temporary variables
     *
     * Clears old "flash" data, marks the new one for deletion and handles
     * "temp" data deletion.
     *
     * @return void
     */
    protected function _ci_init_vars()
    {
        if (!empty($_SESSION['__ci_vars'])) {
            $current_time = time();

            foreach ($_SESSION['__ci_vars'] as $key => &$value) {
                if ($value === 'new') {
                    $_SESSION['__ci_vars'][$key] = 'old';
                }
                // Bản vá lỗi PHP 8: So sánh tường minh chuỗi 'old' để xóa Flashdata thành công
                // DO NOT move this above the 'new' check!
                elseif ($value === 'old' || $value < $current_time) {
                    unset($_SESSION[$key], $_SESSION['__ci_vars'][$key]);
                }
            }

            if (empty($_SESSION['__ci_vars'])) {
                unset($_SESSION['__ci_vars']);
            }
        }

        $this->userdata();
    }
}
