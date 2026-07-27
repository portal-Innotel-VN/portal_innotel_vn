/**
 * Sales Pipeline Module - Custom Javascript Utilities
 * 1. Chống Spam Toast Notification (sp_alert)
 * 2. Khóa nút bấm chống Spam AJAX (btnLoading / btnReset)
 */
(function (window, $) {
    'use strict';

    window.SalesPipeline = window.SalesPipeline || {};

    var lastAlert = {
        type: '',
        message: '',
        timestamp: 0
    };

    /**
     * Hàm bọc thông báo Toast chuyên nghiệp
     * @param {string} type 'success' | 'danger' | 'warning' | 'info'
     * @param {string} message Nội dung thông báo
     * @param {number} timeout Thời gian tự đóng (ms), mặc định 3500ms
     * @param {boolean} clearPrevious Có dọn dẹp các toast cũ không (mặc định true)
     */
    SalesPipeline.alert = function (type, message, timeout, clearPrevious) {
        if (typeof alert_float !== 'function') return;

        type = type || 'info';
        timeout = timeout || 3500;
        if (clearPrevious === undefined) clearPrevious = true;

        var now = Date.now();

        // 1. Chống lặp nội dung trùng trong vòng 1.5 giây
        if (lastAlert.type === type && lastAlert.message === message && (now - lastAlert.timestamp < 1500)) {
            return;
        }

        lastAlert = {
            type: type,
            message: message,
            timestamp: now
        };

        // 2. Dọn dẹp các toast cũ của module nếu clearPrevious = true
        if (clearPrevious) {
            $('.float-alert.sp-toast').remove();
        }

        // 3. Giới hạn tối đa 2 toast cùng lúc
        var activeToasts = $('.float-alert');
        if (activeToasts.length >= 2) {
            activeToasts.first().fadeOut(150, function () {
                $(this).remove();
            });
        }

        // 4. Bật alert_float của Perfex CRM
        alert_float(type, message, timeout);

        // Đánh dấu class sp-toast
        setTimeout(function () {
            $('.float-alert').not('.sp-toast').last().addClass('sp-toast');
        }, 50);
    };

    // Alias ngắn gọn toàn cục
    window.sp_alert = SalesPipeline.alert;

    /**
     * Khóa nút bấm và hiển thị icon xoay loading
     * @param {jQuery|string} btn Element hoặc Selector của nút bấm
     * @param {string} text Văn bản hiển thị kèm icon (mặc định: 'Đang xử lý...')
     */
    SalesPipeline.btnLoading = function (btn, text) {
        var $btn = $(btn);
        if ($btn.length === 0) return;
        text = text || (typeof app !== 'undefined' && app.lang ? app.lang.please_wait : 'Processing...');

        if (!$btn.data('sp-original-html')) {
            $btn.data('sp-original-html', $btn.html());
        }

        $btn.prop('disabled', true).addClass('disabled')
            .html('<i class="fa fa-spinner fa-spin"></i> ' + text);
    };

    /**
     * Khôi phục nút bấm về trạng thái ban đầu
     * @param {jQuery|string} btn Element hoặc Selector của nút bấm
     */
    SalesPipeline.btnReset = function (btn) {
        var $btn = $(btn);
        if ($btn.length === 0) return;

        var originalHtml = $btn.data('sp-original-html');
        if (originalHtml) {
            $btn.prop('disabled', false).removeClass('disabled').html(originalHtml);
        } else {
            $btn.prop('disabled', false).removeClass('disabled');
        }
    };

    // Tự động bảo vệ các Form có class .disable-on-submit trong module
    $(function () {
        $(document).on('submit', 'form.disable-on-submit', function () {
            var $form = $(this);
            if ($form.valid && !$form.valid()) {
                return false;
            }
            var $btn = $form.find('[type="submit"]');
            if ($btn.length > 0) {
                SalesPipeline.btnLoading($btn);
            }
        });
    });

})(window, jQuery);
