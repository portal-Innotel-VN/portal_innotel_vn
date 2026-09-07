<script>
     $(document).ready(function() {

          var div_name = $('#div_name');
          var div_email = $('#div_email');
          var div_phone = $('#div_phone');

          init_editor('textarea[name="notes"]', {
               menubar: false,
          });

          init_selectpicker();
          initAppointmentScheduledDates();

          $('#by_sms, #by_email').on('change', function() {
               var anyChecked = $('#by_sms').prop('checked') || $('#by_email').prop('checked');
               if (anyChecked) {
                    $('.appointment-reminder').removeClass('hide');
               } else {
                    $('.appointment-reminder').addClass('hide');
               }
          })

          $('.modal').on('hidden.bs.modal', function(e) {
               $('.xdsoft_datetimepicker').remove();
          });

          appValidateForm($("#appointment-form"), {
               subject: "required",
               description: "required",
               date: "required",
               name: "required",
               email: "required",
               'attendees[]': {
                    required: true,
                    minlength: 1
               }
          }, function(form) {
               $('button[type="submit"], button.close_btn').prop('disabled', true);
               $('button[type="submit"]').html('<i class="fa fa-refresh fa-spin fa-fw"></i>');
               form.submit();
          }, {
               'attendees[]': "Please select at least 1 staff member"
          });

          $("body").on('change', '#rel_type', function() {
               var optionSelected = $("option:selected", this).attr('id');
               if (optionSelected == 'external') {
                    $('#select_contacts').addClass('hidden');
                    $("#contact_id").attr('required', false);
               } else {
                    $("#contact_id").attr('required', true)
                    $('#div_name, #div_email, #div_phone').addClass('hidden').attr('required', false);
                    $('#select_contacts').removeClass('hidden');
               }
          });

          $('body').on('change', '#contact_id', function() {

               var contact_id = $("option:selected", this).val();
               var url = "<?= admin_url('appointly/appointments/fetch_contact_data'); ?>";

               $.post(url, {
                    contact_id: contact_id
               }).done(function(response) {
                    $('#div_name, #div_email, #div_phone').removeClass('hidden');

                    var full_name = response.firstname + ' ' + response.lastname;
                    var email = response.email;
                    var phone = response.phone;

                    div_name.children('input').val(full_name).attr('disabled', true);
                    div_email.children('input').val(email).attr('disabled', true);
                    div_phone.children('input').val(phone).attr('disabled', true);
               });
          });
     });

     function addEventToGoogleCalendar(button) {

          var form = $('#appointment-form').serialize();
          var url = "<?= admin_url('appointly/appointments/addEventToGoogleCalendar'); ?>";

          $.ajax({
               url: url,
               type: "POST",
               data: form,
               beforeSend: function() {
                    $(button).attr('disabled', true);
                    $('.modal .btn').attr('disabled', true);
                    $(button).html('' + appointly_please_wait + '<i class="fa fa-refresh fa-spin fa-fw"></i>');
               },
               success: function(r) {
                    if (r.result == 'success') {
                         alert_float('success', r.message);
                         $('.modal').modal('hide');
                         $('.table-appointments').DataTable().ajax.reload();
                    } else if (r.result == 'error') {
                         alert_float('danger', r.message);
                    }
               }
          });
     }
</script>