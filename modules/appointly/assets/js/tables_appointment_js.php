<script>
    var appointly_lang_finished = "<?= _l('appointment_marked_as_finished'); ?>";
    var appointly_lang_cancelled = "<?= _l('appointment_is_cancelled'); ?>";
    var appointly_mark_as_ongoing = "<?= _l('appointment_marked_as_ongoing'); ?>";
    var appointment_are_you_sure_mark_as_ongoing = "<?= _l('appointment_are_you_sure_to_mark_as_ongoing') ?>";
    var appointly_are_you_sure_mark_as_cancelled = "<?= _l('appointment_are_you_sure_to_cancel') ?>";
    var appointly_are_you_early_reminders = "<?= _l('appointly_are_you_early_reminders') ?>";
    var appointly_reminders_sent = "<?= _l('appointly_reminders_sent') ?>";
    var appointly_please_wait = "<?= _l('appointment_please_wait'); ?>";

    $(function() {
        $('body').addClass('single_view_board');
    });

    function markAppointmentAsFinished() {
        var url = window.location.search;
        var id = url.split("=")[1];
        $.post('finished', {
            id: id
        }).done(function(r) {
            if (r.success == true) {
                alert_float('success', appointly_lang_finished);
                reloadlocation(800);
            }
        });
    }

    function cancelAppointment() {
        var url = window.location.search;
        var id = url.split("=")[1];
        var button = $('#cancelAppointment');
        if (confirm(appointly_are_you_sure_mark_as_cancelled)) {
            $.post('cancel_appointment', {
                id: id,
                beforeSend: function() {
                    button.attr('disabled', true);
                    $('#markAsFinished').attr('disabled', true);
                    $('#confirmDelete').attr('disabled', true);
                    button.html('' + appointly_please_wait + '<i class="fa fa-refresh fa-spin fa-fw"></i>');
                },
            }).done(function(r) {
                if (r.success == true) {
                    alert_float('success', appointly_lang_cancelled);
                    reloadlocation(800);
                }
            });
        }
    }

    function markAppointmentAsOngoing() {
        var url = window.location.search;
        var id = url.split("=")[1];

        var button = $('#markAppointmentAsOngoing');

        if (confirm(appointment_are_you_sure_mark_as_ongoing)) {
            $.post('mark_as_ongoing_appointment', {
                id: id,
                beforeSend: function() {
                    button.attr('disabled', true);
                    $('#markAsFinished').attr('disabled', true);
                    $('#confirmDelete').attr('disabled', true);
                    button.html('' + appointly_please_wait + '<i class="fa fa-refresh fa-spin fa-fw"></i>');
                },
            }).done(function(r) {
                if (r.success == true) {
                    alert_float('success', appointly_mark_as_ongoing);
                    reloadlocation(800);
                }
            });
        }
    }

    function sendAppointmentReminders() {
        var url = window.location.search;
        var id = url.split("=")[1];

        var button = $('#sendAppointmentReminders');

        if (confirm(appointly_are_you_early_reminders)) {
            $.post('send_appointment_early_reminders', {
                id: id,
                beforeSend: function() {
                    button.attr('disabled', true);
                    $('#markAsFinished').attr('disabled', true);
                    $('#confirmDelete').attr('disabled', true);
                    $('#cancelAppointment').attr('disabled', true);
                    $('.btn-primary-google').attr('disabled', true);
                    button.html('' + appointly_please_wait + '<i class="fa fa-refresh fa-spin fa-fw"></i>');
                },
            }).done(function(r) {
                r = JSON.parse(r);
                if (r.success == true) {
                    alert_float('success', appointly_reminders_sent);
                    reloadlocation(2000);
                }
            });
        }
    }

    function disableButtonsAfterDelete() {
        $('button').attr('disabled', true);
        $('a').addClass('disabled');
        return false;
    }

    function reloadlocation(timer) {
        setTimeout(function() {
            location.reload();
        }, timer);
    }
</script>