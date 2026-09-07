<script>
    var url = "<?= $appointment['url']; ?>";
    var hash = "<?= $this->input->get('hash'); ?>";

    $('#cancelAppointmentForm').on('click', function(e) {
        e.preventDefault();

        var notes = $('#notes').val();

        if ($.trim(notes) == '') {
            $('#alert').addClass('alert-warning').html("<?= _l('appointment_describe_reason_for_cancel'); ?>");
            return;
        }
        $('#alert').hide();

        $.get(url, {
            notes: notes,
            hash: hash
        }).done(function(r) {
            if (r !== '') {

                if (r.response.success == true) {
                    $('#alert').addClass('alert-success').removeClass('alert-warning').text(r.response.message).show();
                    $('#cancelAppointmentForm').attr('disabled', true);
                } else {
                    $('#alert').addClass('alert-warning').removeClass('alert-success').text(r.response.message).show();
                }

                if (r.response.success == true) {
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                }
            }
        })
    });
</script>