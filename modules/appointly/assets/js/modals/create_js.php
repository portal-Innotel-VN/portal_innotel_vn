<script>
     $(function() {

          var div_name = $('#div_name');
          var div_email = $('#div_email');
          var div_phone = $('#div_phone');

          init_editor('textarea[name="notes"]', {
               menubar: false,
          });
          initAppointmentScheduledDates();
          $('.modal').on('hidden.bs.modal', function(e) {
               $('.xdsoft_datetimepicker').remove();
               $(this).removeData();
          });

          $('#by_sms, #by_email').on('change', function() {
               var anyChecked = $('#by_sms').prop('checked') || $('#by_email').prop('checked');
               if (anyChecked) {
                    $('.appointment-reminder').removeClass('hide');
               } else {
                    $('.appointment-reminder').addClass('hide');
               }
          })

          appValidateForm($("#appointment-form"), {
               subject: "required",
               description: "required",
               date: "required",
               rel_type: "required",
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
                    $('#div_name input, #div_email input, #div_phone input').val('').attr('disabled', false).attr('required', true);
                    $('#div_phone input').attr('required', false);
                    $('#div_name, #div_email, #div_phone').removeClass('hidden');
                    $('#select_contacts').addClass('hidden');
                    $("#contact_id").attr('required', false);
               } else {
                    $("#contact_id").val('default').selectpicker("refresh");
                    $("#contact_id").attr('required', true)
                    $('#div_name, #div_email, #div_phone').addClass('hidden').attr('required', false);
                    $('#select_contacts').removeClass('hidden');
               }
          });

          $('body').on('change', '#contact_id', function() {

               var contact_id = $("option:selected", this).val();

               if (contact_id == "" && div_name.children('input').is(":visible")) {
                    div_name.children('input').val('');
                    div_email.children('input').val('');
                    div_phone.children('input').val('');
               }

               var url = "<?= admin_url('appointly/appointments/fetch_contact_data'); ?>";

               $.post(url, {
                    contact_id: contact_id
               }).done(function(response) {
                    if (response !== null) {
                         $('#div_name, #div_email, #div_phone').removeClass('hidden')

                         var full_name = response.firstname + ' ' + response.lastname;
                         var email = response.email;
                         var phone = response.phonenumber;

                         div_name.children('input').val(full_name).attr('disabled', true);
                         div_email.children('input').val(email).attr('disabled', true);
                         div_phone.children('input').val(phone).attr('disabled', true);
                    }
               });
          });

          init_selectpicker();

     });
</script>