<div class="modal right fade" id="search_messages_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="any">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _l('chat_messages_history_title'); ?></h4>
            </div>
            <div class="modal-body">
                <h4 class="mbot20  text-center"><?= _l('chat_search_mutual_messages'); ?> <strong><?= $identifier_name; ?></strong></h4>
                <input type="text" placeholder="<?= _l('chat_messages_search_here'); ?>" id="search_mutual_messages" class="form-control">
                <div class="chat_message_history_view" style="padding-top:10px;">
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function($) {
        "use strict";

        var messages_history = JSON.parse('<?= $messages; ?>');
        var messageHistoryParent = $('.chat_message_history_view');

        if (!$.isEmptyObject(messages_history)) {

            messageHistoryParent.html(showMessagesRecords());

            $('body').on('keydown', '#search_mutual_messages', _debounce(function() {

                var input = $.trim($(this).val());
                var render = [];

                if (input.length > 2) {
                    for (var i = 0; i < messages_history.length; i++) {
                        for (var key in messages_history[i]) {
                            if (messages_history[i].message.matchAll(input) &&
                                !render.includes(messages_history[i])) {
                                render.push(messages_history[i]);
                            }
                        }
                    }

                    appendResultsToModal(render);

                } else {
                    input = '';
                    messageHistoryParent.html('');
                }
            }, 500));
        } else {
            messageHistoryParent.html('<h3 class="text-center">' + "<?= _l('chat_sorry_no_data'); ?>" + '</h3>');
        }

        function appendResultsToModal(data) {
            renderMessagesToView(data);
        };

        function renderMessagesToView(messages) {
            var html = '';
            $.each(messages, function(i, msg) {
                var sender_fullname = msg.sender_fullname ? msg.sender_fullname : msg.contact_fullname;

                if (msg.sender_id === userSessionId || msg.sender_id == 'staff_' + userSessionId) {
                    html += '<div class="panel panel-primary chat_message_history_own" data-toggle="tooltip" title="' + msg.time_sent + '">';
                    html += '<div class="panel-heading"><small>' + moment(msg.time_sent, "YYYYMMDD h:mm:ss").fromNow() + '</small> <h3 class="panel-title">' + sender_fullname + '</h3></div>';
                    html += '<div class="panel-body"><span class="message_content">' + decodeChatMessageHtml(msg.message) + '<span></div> </div>';
                } else {
                    html += '<div class="panel panel-primary chat_message_history_other" data-toggle="tooltip" title="' + msg.time_sent + '">';
                    html += '<div class="panel-heading"><small>' + moment(msg.time_sent, "YYYYMMDD h:mm:ss").fromNow() + '</small> <h3 class="panel-title">' + sender_fullname + '</h3></div>';
                    html += '<div class="panel-body"><span class="message_content">' + decodeChatMessageHtml(msg.message) + '</span></div> </div>';
                }
            });

            if (html === '') {
                messageHistoryParent.html('<h3 class="text-center">' + "<?= _l('chat_sorry_no_data'); ?>" + '</h3>');
                return;
            }

            messageHistoryParent.html($.parseHTML(html));
        }

        function showMessagesRecords() {
            var recordsFound = '';

            recordsFound += '<div class="alert alert-info text-center" role="alert">';
            recordsFound += '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
            recordsFound += '<span aria-hidden="true">&times;</span></button>';
            recordsFound += 'Total mutual messages found: <strong>' + <?= $this->session->userdata('chat_messages_count'); ?> + '</strong></div>';

            return recordsFound;
        }
        $('#search_messages_modal').on('show.bs.modal', function(e) {
            setTimeout(function() {
                $(".modal_container input").focus();
            }, 750);
        });
    })(jQuery);
</script>