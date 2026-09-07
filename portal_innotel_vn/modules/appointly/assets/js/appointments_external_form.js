var form_id = '#appointments-form';

$(function() {

    var allowedHours = [
        '07:00', '08:00', '09:00', '10:00', '11:00',
        '12:00', '13:00', '14:00', '15:00', '16:00',
        '17:00', '18:00', '19:00', '20:00', '21:00'
    ];

    $('.datetimepicker').datetimepicker({
        defaultTime: "09:00",
        minDate: "1",
        allowTimes: allowedHours,
        closeOnDateSelect: 0,
        closeOnTimeSelect: 0,
        onGenerate: function(ct) {
            $(this).find('.xdsoft_date.xdsoft_weekend')
                .addClass('xdsoft_disabled');
        },
    });

    $(form_id).appFormValidator({
        rules: {
            subject: 'required',
            name: 'required',
            email: 'required',
            phone: 'required',
            description: 'required',
            date: 'required'
        },
        onSubmit: function(form) {

            var formURL = $(form).attr("action");
            var formData = new FormData($(form)[0]);
            $('button[type="submit"]').prop('disabled', true);

            $.ajax({
                type: $(form).attr('method'),
                data: formData,
                mimeType: $(form).attr('enctype'),
                contentType: false,
                cache: false,
                processData: false,
                url: formURL
            }).always(function() {

                $('#form_submit').prop('disabled', false);

            }).done(function(response) {
                response = JSON.parse(response);
                if (response.success == true) {
                    $header = $('.appointment-header');
                    $(form_id).remove();
                    $('#response').html($header);
                    $('#response').append('<div class="alert alert-success text-center" style="margin:0 auto;">' + response.message + '</div>');
                    setTimeout(function() {
                        location.reload();
                    }, 2000)
                } else {
                    $('#response').html('<div class="alert alert-danger">Something went wrong...</div>');
                }
            }).fail(function(data) {
                if (data.status == 422) {
                    $('#response').html('<div class="alert alert-danger">Some fields that are required are not filled properly.</div>');
                } else {
                    $('#response').html(data.responseText);
                }
            });
            return false;
        }
    });
});