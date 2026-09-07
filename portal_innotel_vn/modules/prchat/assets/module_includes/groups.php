<script>
    $(document).ready(function() {
        "use strict";

        /*---------------* Add new user to chat group that already exists  *---------------*/
        $('#addNewUserForm').submit(function(e) {
            var action = prchatSettings.addChatGroup;
            var selected_user = $('#usersSelect').val();

            e.preventDefault();
            $.ajax({
                type: "POST",
                url: action,
                data: {
                    users: selected_user,
                    group_name: chname_modal,
                    group_id: $(this).data('group-id'),
                },
                success: function(response) {
                    if (response !== '') {
                        response = JSON.parse(response);
                        $('.ul_' + response.data.group_name.replace('-', '_')).append('<li id="' + response.data.user_id + '">' + response.data.user_full_name + '<a href="#" onClick="removeUser(this)" id="' + response.data.user_id + '"></li><i class="fa fa-times"></i></a>');
                        getInactiveChatUsers();
                    }
                },
                error: function(msg) {}
            });
        });

        /*---------------* Pusher bind group send event  *---------------*/
        pusher.bind('group-send-event', function(data) {
            if (data.from !== userSessionId) {
                var sender_first_name = data.from_name.split(' ')[0];
                $('.user_is_typing').remove();
                var message = createTextLinks_(emojify.replace(data.message));
                message = isAudioMessage(message);
                $('#frame .group_messages .chat_group_messages#' + data.to_group + ' ul').append('<li class="from_other" id="' + userSessionId + '"><img data-toggle="tooltip" data-container="body" data-placement="top" data-html="true" title="' + data.from_name + '<br>' + moment().format('hh:mm A') + '" class="myProfilePic" src="' + fetchUserAvatar(data.from, data.sender_image) + '"/><span class="member_name_other">' + sender_first_name + '</span><p class="from_other" id="' + userSessionId + '">' + message + '</p></li>');
            }
            scroll_event();
        });

        /*---------------* Pusher bind group notify event  *---------------*/
        pusher.bind('group-notify-event', function(data) {
            if (data.from !== userSessionId) {
                <?php if ($chat_desktop_messages_notifications) : ?>
                    if (user_chat_status != 'busy' && user_chat_status != 'offline') {
                        if (!data.message.includes('data-mention-id')) {
                            data.message = "<?= _l('chat_new_audio_group_message'); ?>";
                            $.notify('', {
                                'title': 'Group: ' + strCapitalize(data.group_name.replace('presence-', '')),
                                'body': data.from_name + ': ' + data.message,
                                'requireInteraction': false,
                                'icon': fetchUserAvatar(data.from, data.sender_image),
                                'tag': 'group-message-' + data.from + data.group_id,
                                'closeTime': app.options.dismiss_desktop_not_after != "0" ? app.options.dismiss_desktop_not_after * 1000 : null
                            });
                        }
                    }
                <?php endif; ?>
            }


            if (data.from !== userSessionId) {
                var group_selector = $('#frame .chat_groups_list .group_selector#' + data.to_group);

                if (!group_selector.hasClass('active')) {
                    group_selector.find('.g_new_msg').addClass('flashit').show();
                }

                if (!$('li.groups').hasClass('active')) {
                    $('#frame #sidepanel li.groups').addClass('flashit');
                }
            }

        });

        groupChannels.bind('pusher:subscription_succeeded', function() {
            <?php if (!isClientsEnabled()) { ?>
                $('#main_loader_init').fadeOut(500);
            <?php } ?>
        });

        /*---------------* Groupchannels event if member leaves a channel/group  *---------------*/
        groupChannels.bind('member-left-channel', function(data) {
            var group_name = data.group_name.replace('presence-', '');
            pusher.unsubscribe(data.group_name);

            if (data.member_id == userSessionId) {
                var selector = $('#frame .chat_groups_list li#' + data.group_id);
                if (selector.remove()) {
                    $('.group_messages').hide()

                    setTimeout(function() {
                        $('#frame .group_members_inline').remove();
                    }, 1000)
                    alert_float('info', '<?php echo _l("chat_group_text"); ?>' + group_name + '<?php echo _l("chat_group_left_text"); ?>');
                }
            } else if (data.member_id !== userSessionId || isAdmin) {
                alert_float('info', 'User ' + data.user_full_name + ' has left the group ' + group_name);
                $('#frame .chat_group_options .group_members p#member_' + data.member_id).fadeOut().remove();
            }
        });

        /*---------------* Pusher bind if member is added to new group/channel  *---------------*/
        pusher.bind('added-to-channel', function(data) {
            var group_name = fixChatGroupName(data.group_name);
            if (data.result == 'success') {
                $.each(data.user_ids, function(i, id) {
                    if (id == userSessionId) {
                        pusher.subscribe(data.group_name);
                        appendNewChatGroup(data);
                        alert_float('info', '<?php echo _l("chat_added_to_group");  ?>' + group_name);
                    } else {
                        pusher.subscribe(data.group_name);
                    }
                });
                $('#frame .group_selector.active a').click();
                $('.message-input.group_msg_input').show();
            }
        });


        /*---------------* Pusher bind if member is removed from group/channel  *---------------*/
        pusher.bind('removed-from-channel', function(data) {
            if (data.created_by_me == userSessionId) {
                return false;
            }

            var group_name = fixChatGroupName(data.group_name);

            var selector = $('#frame .chat_groups_list li#' + data.group_id);
            if (data.user_id == userSessionId) {

                if (selector.hasClass('active')) {
                    $('#frame #sidepanel .nav.nav-tabs .staff a').click();
                    $('.group_messages').hide();
                }
                selector.remove();
                pusher.unsubscribe(data.group_name);

                group_name = group_name.replace('presence-', '');

                alert_float('info', '<?php echo _l("chat_removed_from_group");  ?>' + group_name);
            }
        });


        /*---------------* Pusher bind event if group is deleted/closed channel  *---------------*/
        pusher.bind('group-deleted', function(data) {
            if (data.result == 'true') {
                data.group_name = data.group_name.replace('-', '_');
                if ($('#frame #sidepanel li.group_selector#' + data.group_id).remove()) {
                    if ($('#frame #sidepanel li.group_selector:first').length !== 0) {
                        $('#frame #sidepanel li.group_selector:first').click();
                    } else {
                        $('#frame li.staff a').click();
                    }
                }
            }
        });


        /*---------------* Pusher event when new group/channel is created *---------------*/
        pusher.bind('group-chat', function(data) {
            $(data.members).each(function(index, user_id) {
                if (user_id == userSessionId) {
                    setTimeout(function() {
                        alert_float('success', '<?php echo _l("chat_added_to_group");  ?>' + fixChatGroupName(data.group_name) + '');
                    }, 1500);
                    pusher.subscribe(data.group_name);
                    appendNewChatGroup(data);
                    $('.message-input.group_msg_input').show();
                }
            })
        });

        var clearTypingInterval = 2200; // 2.2 seconds
        var clearTypingTimerId;
        pusher.bind('group-typing-event', function(data) {
            var group_name = data.group_name;
            var to_group = data.to_group;
            if (data.from !== userSessionId && data.message == 'true') {
                if ($('#frame .group_messages#' + to_group + ' .user_is_typing').length == 0) {
                    $('#frame .group_messages#' + to_group).append('<span class="user_is_typing"><?php echo _l("chat_someone_is_typing"); ?></span>').fadeIn(500);
                    scroll_event();
                    clearTimeout(clearTypingTimerId);
                    clearTypingTimerId = setTimeout(function() {
                        $('#frame .group_messages#' + to_group + ' .user_is_typing').fadeOut().remove();
                    }, clearTypingInterval);
                }
            }
        });


        /*---------------* Window on load get and render to view all associated groups with logged in user  *---------------*/
        var inChannels = [],
            members, data, result, channels, resp;
        var myGroups = $.post(prchatSettings.getMyGroups).done(function(r) {
            if (r.noChannels) {
                return false;
            } else {
                if (r !== '') {
                    resp = JSON.parse(r);
                    $.each(resp.groups, function(key, data) {
                        if ($('.chat_groups_list li a#' + data.id).length == 0) {
                            appendCurrentGroups(data);
                        }
                    });
                }
            }
        });


        /*---------------* Click event for adding new member to group  *---------------*/
        $('body').on('click', '#frame .add_chat_member', function() {
            var group_id = $('#frame .chat_groups_list li.active').attr('id');
            $('.modal_container').load(prchatSettings.addNewChatGroupMembersModal, {
                group_id: group_id
            }, function(res) {
                if ($('.modal-backdrop.fade').hasClass('in')) {
                    $('.modal-backdrop.fade').remove();
                }
                if ($('#add_members_modal').is(':hidden')) {
                    $('#add_members_modal').modal({
                        show: true
                    });
                }
            });
        });
    });


    /*---------------* Check for messages history and append to main chat window in group messages *---------------*/
    function loadGroupMessages(el) {
        var pos = $(el).scrollTop();
        var messagesScrollbar = $(el).find('.chat_group_messages');
        var to_group = $(el).find('.chat_group_messages').attr('id');
        var messages;
        var from = userSessionId;

        $('#frame .group_messages').find('.message_group_loader').show();
        if ($(messagesScrollbar).children().length !== 0) {
            if (pos == 0 && groupOffsetPush >= 10) {

                $.get(prchatSettings.getGroupMessagesHistory, {
                        group_id: to_group,
                        offset: groupOffsetPush,
                    })
                    .done(function(message) {
                        messages = JSON.parse(message);
                        if (Array.isArray(messages) == false) {
                            groupEndOfScroll = true;

                            $('#frame .group_messages').find('.message_group_loader').hide();
                            if ($('.group_messages').hasScrollBar() && groupEndOfScroll == true) {
                                prchat_setNoMoreGroupMessages();
                            }
                        } else {
                            groupOffsetPush += 10;
                        }

                        $(messages).each(function(i, value) {

                            var previous_time = moment(messages[i].time_sent).format('YYYY-MM-DD HH');

                            if (messages[i + 1] !== undefined) {
                                var current_time = moment(messages[i + 1].time_sent).format('YYYY-MM-DD HH');
                            }

                            value.message = emojify.replace(value.message);
                            var element = $('.chat_group_messages#' + to_group + ' ul');
                            if (value.is_deleted == 1) {
                                value.message = '<span>' + prchatSettings.messageIsDeleted + '</span>';
                            } else {
                                value.message = emojify.replace(value.message);
                            }

                            var member_first_name = value.sender_fullname.split(' ')[0];

                            value.message = isUserMentioned(value.message);

                            if (value.sender_id !== userSessionId) {
                                element.prepend('<li class="from_other"><img data-toggle="tooltip" data-html="true" data-container="body" data-placement="right" title="' + value.time_sent_formatted + '<br>' + value.sender_fullname + '" class="friendProfilePic" src="' + fetchUserAvatar(value.sender_id, value.user_image) + '"/><span class="member_name_other">' + member_first_name + '</span><p  class="from_other">' + value.message + '</p></li>');
                            } else {
                                element.prepend('<li class="own_group_message_li" id="' + value.id + '"><img data-html="true" data-toggle="tooltip" data-container="body" data-placement="left" title="' + value.time_sent_formatted + '<br>' + value.sender_fullname + '" class="myProfilePic" src="' + fetchUserAvatar(userSessionId, value.user_image) + '"/><span class="member_name_me">' + member_first_name + '</span><p class="own_group_message" id="gmsg_' + value.id + '">' + value.message + '</p></li>');

                                if (message[i + 1] !== undefined) {
                                    if (previous_time !== current_time) {
                                        $('<span class="middleDateTime">' + moment(value.time_sent).format('llll') + '</span>').prependTo($('.chat_group_messages ul li#' + value.id).parents('ul'));
                                    }
                                }

                                <?php if ($chat_delete_option == '1' || is_admin()) :  ?>
                                    if (value.is_deleted == 0) {
                                        $('#gmsg_' + value.id).tooltipster(tooltipserContent(value.id, 'group'));
                                    }
                                <?php endif; ?>
                            }
                        });
                        if (groupEndOfScroll == false) {
                            $(el).scrollTop(200);
                        }
                    });
                activateLoader();
            }
        }
    }

    /*---------------* Main function that renders the messages and created view for group chat messages  *---------------*/
    function renderChatGroupMessages(data) {
        groupEndOfScroll = false;
        groupOffsetPush = 0;
        var group_id = $(data).attr('id');
        var group_name = $(data).attr('data-channel');
        chat_group_messages.html('<ul></ul>');
        chat_group_messages.attr('id', group_id);
        $('#frame .content .group_messages').attr('id', group_id);
        $('#frame .content .messages, #frame .content a[target=_blank], #frame .content .staff-profile-image-small').hide();
        $(chat_social_media).hide();
        $(chat_contact_profile_img).hide();
        $('#frame .content p').text('');
        var group_created_by = $(data).data('created-by');
        $('.leave_chat_group').remove();

        $('#frame .content .group_messages#' + group_id).show();

        if ($('#frame #sidepanel .nav.nav-tabs .groups').hasClass('active')) {
            getGroupMessages(group_id);
        }
    }

    /*---------------* Creates options sidebar for group chat *---------------*/
    function appendOptionsBar() {
        var options = '';
        var hasActiveGroupsId = $('#frame ul.chat_groups_list li.active').attr('id');

        options += '<div class="groupOptions">';

        options += '<svg data-toggle="tooltip" data-container="body" onClick="showGroupChatOptions()" data-placement="left" class="group_options_icon" id="groupOptionsIcon" data-original-title="<?php echo _l("chat_group_settings_bar_text"); ?>" viewBox="0 0 24 24"><path d="M13.5,8H12V13L16.28,15.54L17,14.33L13.5,12.25V8M13,3A9,9 0 0,0 4,12H1L4.96,16.03L9,12H6A7,7 0 0,1 13,5A7,7 0 0,1 20,12A7,7 0 0,1 13,19C11.07,19 9.32,18.21 8.06,16.94L6.64,18.36C8.27,20 10.5,21 13,21A9,9 0 0,0 22,12A9,9 0 0,0 13,3" /></svg>';
        options += '</div';

        if ($('#frame .content .contact-profile svg#groupOptionsIcon').length == 0 && hasActiveGroupsId != 'undefined') {
            $('#frame .content .contact-profile').append(options);
        }
    }


    /*---------------* Main function for fetching messages for specific group and appends into view  *---------------*/
    var createGrooupBoxRequest = null;

    function getGroupMessages(group_id) {

        var groupMessages;
        chat_group_messages.html('');
        chat_group_messages.append('<ul></ul>');
        var dfd = $.Deferred();
        var groupMessagesPromises = dfd.promise();

        if (createGrooupBoxRequest !== null) {
            createGrooupBoxRequest.abort();
        }

        createGrooupBoxRequest = $.get(prchatSettings.getGroupMessages, {
                group_id: group_id,
                offset: 0,
                limit: 20
            })
            .done(function(r) {
                groupOffsetPush = 10;

                r = JSON.parse(r);

                groupMessages = r;

                groupOffsetPush += 10;
                dfd.resolve(groupMessages);

            }).always(function() {
                if ($("#no_messages").length) {
                    $("#no_messages").remove();
                }
                createGrooupBoxRequest = null;
            });


        /*---------------* After users are fetched from database -> continue with loading *---------------*/
        groupMessagesPromises.then(function(data) {

            if (!window.matchMedia("only screen and (max-width: 735px)").matches) {
                if (!Array.isArray(data.users)) {
                    $('.message-input.group_msg_input').hide();
                    return false;
                }
            }

            getGroupUsers(data.separete_group_id, data.separete_group_name, data.users);

            $(data.messages).each(function(i, obj) {

                var previous_time = moment(data.messages[i].time_sent).format('YYYY-MM-DD HH');

                if (data.messages[i + 1] !== undefined) {
                    var current_time = moment(data.messages[i + 1].time_sent).format('YYYY-MM-DD HH');
                }

                $(obj).each(function(i, value) {
                    if (value.is_deleted == 1) {
                        value.message = '<span>' + prchatSettings.messageIsDeleted + '</span>';
                    } else {
                        value.message = emojify.replace(value.message);
                    }

                    var member_first_name = value.sender_fullname.split(' ')[0];

                    value.message = isUserMentioned(value.message);

                    /** 
                     * Check if it is audio message and decode html
                     * Delete function is not working
                     */
                    // if (value.message.match('type="audio/ogg"&gt;&lt;/audio&gt')) {
                    //     value.message = renderHtmlForAudio(value.message);
                    // }
                    value.message = isAudioMessage(value.message);


                    if (value.created_by_id == userSessionId && value.sender_id === userSessionId || value.sender_id == userSessionId && value.created_by_id !== userSessionId) {
                        $('#frame .group_messages .chat_group_messages#' + value.group_id + ' ul').prepend('<li class="own_group_message_li" id="' + value.id + '"><img data-toggle="tooltip" data-container="body" data-html="true" data-placement="left" title="' + value.time_sent_formatted + '<br>' + value.sender_fullname + '" class="myProfilePic" src="' + fetchUserAvatar(userSessionId, value.user_image) + '"/><span class="member_name_me">' + member_first_name + '</span><p class="own_group_message" id="gmsg_' + value.id + '">' + value.message + '</p></li>');
                        <?php if ($chat_delete_option == '1' || is_admin()) :  ?>
                            if (value.is_deleted == 0) {
                                $('#gmsg_' + value.id).tooltipster(tooltipserContent(value.id, 'group'));
                            }
                        <?php endif; ?>
                    } else {
                        $('#frame .group_messages .chat_group_messages#' + value.group_id + ' ul').prepend('<li class="from_other"><img data-toggle="tooltip" data-container="body" data-placement="right" data-html="true" title="' + value.time_sent_formatted + '<br>' + value.sender_fullname + '" class="friendProfilePic" src="' + fetchUserAvatar(value.sender_id, value.user_image) + '"/><span class="member_name_other">' + member_first_name + '</span><p  class="from_other">' + value.message + '</p></li>');
                    }
                    if (data.messages[i + 1] !== undefined) {
                        if (previous_time !== current_time) {
                            $('<span class="middleDateTime">' + moment(value.time_sent).format('llll') + '</span>').prependTo($('.chat_group_messages ul'));
                        }
                    }
                });

            });
        });

        activateGroupLoader(groupMessagesPromises);

        $.when(groupMessagesPromises.then())
            .then(function() {
                if ($('.group_messages').hasScrollBar() && $(window).width() > 733) {
                    scroll_event();
                    $('.message-input.group_msg_input textarea.group_chatbox').focus();
                } else if ($(window).width() < 733) {
                    // Due to mobile devices bug and loading time
                    scroll_event();
                    scroll_event();
                } else {
                    // One last check for mobile devices
                    scroll_event();
                }
            });

        return false;
    }


    /*---------------* Functions that handles member events after someone have created group chat  *---------------*/
    function appendCurrentGroups(data) {
        appendChatGroup(data);
    }

    function appendChatGroup(data) {
        var group_name = data.group_name;
        var group_id = data.group_id;

        $(data.members).each(function(index, member_data) {
            if (member_data.member_id == userSessionId) {
                pusher.subscribe(group_name);
                appendNewChatGroup(data);
            }
        })
    }

    /*---------------* Fixes channel (group name) for UI purposes *---------------*/
    function fixChatGroupName(name) {
        if (name.includes('presence-')) {
            return name.replace('presence-', '');
        }
    }

    /*---------------* Renders the new chat group in sidebar  *---------------*/
    function appendNewChatGroup(data) {
        var data_group_id = '';
        if (data.group_id) {
            data_group_id = data.group_id;
        } else {
            data_group_id = data.id;
        }

        var group_name = fixChatGroupName(data.group_name);
        var main_selector = $('#frame #sidepanel .tab-content #groups .chat_groups_list');
        var group = '';

        group += '<li class="group_selector" data-created-by="' + data.created_by_id + '" id="' + data_group_id + '" data-channel="' + data.group_name + '" onClick="renderChatGroupMessages(this)">';
        group += '<div class="group_wrapper">';
        group += '<img data-toggle="tooltip" title="' + strCapitalize(group_name) + '" class="groups_image" src="<?= module_dir_url('prchat', 'assets/chat_implements/icons/groups.png'); ?>" alt="Group"/>';
        group += '<a href="#" class="group-list">' + strCapitalize(group_name) + '</a>';
        group += '<small class="g_new_msg"><i class="fa fa-comments"></i></small>';
        group += '</div>';

        if (main_selector.prepend(group)) {
            if (!window.matchMedia("only screen and (max-width: 735px)").matches) {
                $('#frame #sidepanel #groups .chat_groups_list li#' + data_group_id).click();
            }
        }
    }

    /*---------------* Handles sidebar groups click also handles active classes and notifications  *---------------*/
    $('body').on('click', '#frame .group_selector', function() {
        animateContent();
        mentioned_users = [];
        $('.group_chatbox').val('');
        var groupOptions = $('#frame .groupOptions');
        if (groupOptions.is(':hidden')) {
            groupOptions.show();
        }
        if ($(this).children('.group_wrapper').find('.g_new_msg').hasClass('flashit')) {
            $(this).children('.group_wrapper').find('.g_new_msg').removeClass('flashit').hide();
        }
        $(this).parent().find('li.group_selector.active').removeClass('active');
        $(this).addClass('active');
    });

    /*---------------* Function that gets all users connected with a specifix group chat and renders to view  *---------------*/
    function getGroupUsers(group_id, group_name, users) {

        var active_members = '';

        appendGroupOptions();

        if ($('#frame ul li.groups').hasClass('active')) {

            $('#frame .contact-profile div.group_members_inline').remove();
            $('#frame .contact-profile').append('<div class="group_members_inline"><img class="groups_image" src="<?= module_dir_url('prchat', 'assets/chat_implements/icons/groups.png'); ?>" alt="Group"/><p class="active_group_members"></p></div>');
        }
        var group_selector_options = '.chat_group_options #group_options';
        $.each(users, function(i, user) {
            active_members += user.firstname + ' ' + user.lastname + ', ';

            if (user.created_by_id == userSessionId || isAdmin) {
                if ($('.chat_group_options #group_options').length == 0) {
                    $('#frame .chat_group_options').prepend('<div class="panel-group" id="group_options">');

                }
                if ($('#frame .chat_group_options  .member_identifier_' + group_id).length == 0) {
                    $(group_selector_options).append('<div class="add_member member_identifier_' + group_id + '"><a data-toggle="tooltip" data-placement="left" title="<?php echo _l('chat_add_new_member'); ?>" class="add_chat_member" href="#"><svg class="svg_add_chat_member" viewBox="0 0 24 24"><path d="M15,14C12.33,14 7,15.33 7,18V20H23V18C23,15.33 17.67,14 15,14M6,10V7H4V10H1V12H4V15H6V12H9V10M15,12A4,4 0 0,0 19,8A4,4 0 0,0 15,4A4,4 0 0,0 11,8A4,4 0 0,0 15,12Z"/></svg><?php echo _l('chat_add_members'); ?></a></div>');
                }
                if ($('#frame .chat_group_options').find('.dismiss_chat_group').length == 0) {
                    $(group_selector_options).append('<div data-group-id="' + group_id + '" data-group-name="' + group_name + '" class="dismiss_chat_group btn btn-sm" onClick="deleteGroup(this)"><?php echo _l('chat_group_delete'); ?></div>');
                }
            } else {
                if ($('.chat_group_options #group_options').length == 0) {
                    $('#frame .chat_group_options').prepend('<div class="panel-group" id="group_options">');
                }
                $(group_selector_options).hide();
            }

            if (user.created_by_id !== userSessionId && $('#frame .chat_groups_list li.active .leave_chat_group').length == 0) {
                $('#frame .chat_groups_list li.active').append('<button data-toggle="tooltip" title="<?php echo _l('chat_group_leave'); ?>" onClick="leaveGroup(' + group_id + ')" class="leave_chat_group btn btn-sm btn-info pull-right"><i class="fa fa-sign-out leave_icon" aria-hidden="true"></i></button>');
            }

            if (user.member_id !== userSessionId &&
                $('#frame .chat_group_options #member_' + user.member_id + '').length == 0) {
                $('#frame .chat_group_options .group_members').append('<p class="members_list" id="member_' + user.member_id + '"><a target="_blank" href="' + site_url + 'admin/profile/' + user.member_id + '">' + user.firstname + ' ' + user.lastname + '</a></p>');
            }
            if (user.member_id !== userSessionId &&
                user.created_by_id == userSessionId &&
                $('#frame .chat_group_options #member_' + user.member_id + ' i#' + user.member_id).length == 0 ||
                isAdmin) {
                $('#frame .chat_group_options #group_members #member_' + user.member_id + '').append('<i id="' + user.member_id + '" data-group="' + group_name + '" data-group-id="' + user.group_id + '" class="fa fa-times" data-toggle="tooltip" data-placement="left" title="<?php echo _l('chat_group_remove_member'); ?>" onClick="removeChatGroupUser(this)""></i>');
            }

        });
        active_members = active_members.substring(0, active_members.length - 2);
        var own_name = "<?php echo trim(get_staff_full_name()); ?>";

        if ($('#frame ul li.groups').hasClass('active')) {
            if (active_members === own_name) {
                $('#frame #group_members .group_members').append('<strong><?php echo _l('chat_group_empty'); ?></strong>');
            } else {
                $('#frame .contact-profile p.active_group_members').append('<strong>' + active_members + '</strong>');
            }
        }
        getGroupSharedFiles(group_id);
    }


    /*---------------* Function that handles users if wants to leave group on its own  *---------------*/
    function leaveGroup(group_id) {
        var member_id = userSessionId;
        $.post(prchatSettings.chatMemberLeaveGroup, {
            group_id: group_id,
            member_id: member_id
        }).done(function(r) {
            if (r) {
                r = JSON.parse(r);
                if (r.message == 'deleted') {
                    if ($('#frame .chat_groups_list').children().length == 0) {
                        $('#frame .staff a').click();
                    } else {
                        $('#frame .chat_groups_list li:first a').click();
                    }
                }
            }
        });
    }


    /*---------------* Function that handles removing a member from group chat  *---------------*/
    function removeChatGroupUser(user) {
        var group_name = $(user).attr('data-group');
        var group_id = $(user).attr('data-group-id');
        var member_id = $(user).attr('id');
        member_id = member_id.replace('member_', '');

        if (member_id !== '') {
            $.post(prchatSettings.removeChatGroupUser, {
                id: member_id,
                group_id: group_id,
                group_name: group_name
            }).done(function(r) {
                r = JSON.parse(r);
                if (r.response == 'success') {
                    getGroupMessages(group_id);
                }
            });
        }
    }


    /*---------------* Function that handles closing/deleting an existing group  *---------------*/
    function deleteGroup(el) {

        var group_name = $(el).data('group-name');
        var group_id = $(el).data('group-id');

        if (confirm("<?php echo _l('chat_are_you_sure_delete_group'); ?>")) {
            $.post(prchatSettings.deleteGroup, {
                'group_name': group_name,
                'group_id': group_id
            }).done(function(r) {

                if (r !== '') {
                    r = JSON.parse(r)
                }

                if (r.error == 'nomore') {
                    alert_float('warning', "<?php echo _l('chat_no_more_groups_to_delete'); ?>");
                    $('#frame .chat_group_options, #frame .groupOptions, #frame .group_members_inline').remove();
                    return false;
                }

                if (r.result == 'success') {
                    if ($('#frame #sidepanel li.group_selector#' + group_id).remove()) {

                        if ($('#frame #sidepanel li.group_selector:first').length > 0) {
                            $('#frame #sidepanel li.group_selector:first').click();
                        } else {
                            if (isUserMobile) {
                                $('.chat_nav li.staff a').trigger('click');
                                $('#frame #sidepanel').show();
                                $('#frame .content').hide();
                            } else {
                                $('#contacts ul li.contact:first a').click();
                            }
                        }
                    }
                    alert_float('success', "<?php echo _l('chat_group_deleted'); ?>");
                }

            });
        } else {
            return false;
        }
    }

    /*---------------* Init current group chat loader synchronized with messages append *---------------*/
    function activateGroupLoader(groupMessagesPromises = null) {
        if (groupMessagesPromises !== null) {
            var initLoader = $('#frame .group_messages');
            initLoader.find('.message_group_loader').show(function() {
                groupMessagesPromises.then(function(res) {
                    if (res.users.length) {
                        initLoader.find('.message_group_loader').hide();
                    }
                });
            })
        }
    }

    var optionsSelector = $('#frame .content .chat_group_options');

    /*---------------* Toggle Settings sidebar for group chat  *---------------*/
    function showGroupChatOptions() {
        var g_messages = $('#frame .group_messages');
        if (g_messages.is(':hidden')) {
            g_messages.show();
        }
        if (!optionsSelector.hasClass('active')) {
            optionsSelector.show().animate({
                'right': '0',
                'width': (isUserMobile) ? '100%' : '360px'
            }, 10, 'linear', function() {
                $(this).addClass('active');
            });
        } else if (!optionsSelector.is(':hidden')) {
            optionsSelector.css({
                'right': '-100%',
                'width': '360px'
            }, 10, 'linear').hide(function() {
                $(this).removeClass('active');
            });
        }
    }

    /*---------------* Get groups shared items id -> mixed and append to group option settings *---------------*/
    function getGroupSharedFiles(group_id) {
        var $mainDivSharedFiles = $('.main_div_shared_files');

        $.post(prchatSettings.getGroupSharedFiles, {
            group_id: group_id
        }).done(function(data) {
            if (data) {
                data = JSON.parse(data);
                $mainDivSharedFiles.html('');
                $mainDivSharedFiles.html(data);
            }
        });
    }

    /*---------------* Delete group own messages function *---------------*/
    function delete_group_chat_message(grp_msg_id) {
        grp_msg_id = $(grp_msg_id).attr('id');
        var group_id = $('.chat_group_messages').attr('id');
        var paragraph = "<p class='own_group_message message_was_deleted'>";

        $.post(prchatSettings.deleteMessage, {
            id: grp_msg_id,
            group_id: group_id
        }).done(function(response) {
            if (response == 'true') {
                $('.tooltipster-base').hide();
                $("li.own_group_message_li p#gmsg_" + grp_msg_id).remove();
                $('body').find("li.own_group_message_li#" + grp_msg_id).append(paragraph);
                $('body').find("li.own_group_message_li#" + grp_msg_id + ' p.own_group_message.message_was_deleted')
                    .html(prchatSettings.messageIsDeleted)
                    .removeClass('tooltipstered');
                getGroupSharedFiles(group_id);
            } else {
                alert_float('danger', '<?php echo _l('chat_error_float'); ?>');
            }
        });
    }

    /*--------------------  * send group message & typing event to server  * ------------------- */
    $("#frame").on('keypress', 'textarea.group_chatbox', function(e) {

        var form = $(this).parents('form');
        var group_id = $('#frame .group_selector.active').attr('id');
        var isUserTyping = $(this).parents('.wrap').find('input.typing');

        $(this).parents('.wrap').find('input.from').val(userSessionId);

        var message = $.trim($(this).val());
        if (e.which == 13 || e.keyCode == 13) {

            var ownImagePath = $('#sidepanel #profile .wrap img').prop('currentSrc');
            var member_full_name = $('#frame #sidepanel #profile .wrap p').text();
            var member_first_name = $.trim(member_full_name);

            member_first_name = member_first_name.split(' ')[0];

            e.preventDefault();
            if (message == '' || internetConnectionCheck() === false) {
                return false;
            }

            message = createTextLinks_(emojify.replace(message));


            var notify_users = [];

            if (mentioned_users.length > 0) {

                $.each(mentioned_users, function(index, user) {
                    if (!message.includes('data-mention-id="' + user.id + '"')) {
                        notify_users.push({
                            user_id: user.id,
                            name: user.name
                        });
                        message = message.replace('@' + user.name, '<a href="' + site_url + 'admin/profile/' + user.id + '" class="user_mentioned" data-chatmentioned="true" data-toggle="tooltip" title="' + user.name + '" target="_blank" data-mention-id="' + user.id + '">@' + user.name + '</a>');
                    }
                });

                var group_name = $('body').find('.group_selector.active .group_wrapper a.group-list').text();

                sendMentionNotifications(userSessionId, group_name, notify_users);

                $(this).val(message);
                mentioned_users = [];
            }

            $('.group_messages .chat_group_messages ul').append('<li class="own_group_message_li" id="' + userSessionId + '"><img data-toggle="tooltip" data-container="body" data-placement="left" data-html="true" title="' + moment().format('hh:mm A') + '<br>' + member_full_name + '" class="myProfilePic" src="' + ownImagePath + '"/><span class="member_name_me">' + member_first_name + '</span><p class="own_group_message" id="' + userSessionId + '">' + message + '</p></li>');
            isUserTyping.val('false');
            // send event
            var formData = form.serializeArray();

            formData.push({
                name: "group_id",
                value: group_id
            });

            $.post(prchatSettings.groupMessagePath, formData);
            $(this).val('');
            $(this).focus();
            scroll_event();
        } else if (!$(this).val() || (isUserTyping.val() == 'false')) {
            // typing event
            isUserTyping.val('true');
            var formTyping = form.serializeArray();

            formTyping.push({
                name: "group_id",
                value: group_id
            });

            $.post(prchatSettings.groupMessagePath, formTyping);
        }
    });

    // Handles group file form uploads
    function uploadGroupFileForm(file) {
        var formData = new FormData();
        var fileForm = $(file).children('input[type=file]')[0].files[0];
        var to_group = $('#frame .group_messages .chat_group_messages').attr('id');
        var token_name = $(file).children('input:nth-child(3)').val();

        formData.append('userfile', fileForm);
        formData.append('to_group', to_group);
        formData.append('send_from', userSessionId);
        formData.append('csrf_token_name', token_name);

        $.ajax({
            type: 'POST',
            url: prchatSettings.groupUploadMethod,
            data: formData,
            dataType: 'json',
            processData: false,
            contentType: false,
            beforeSend: function() {
                if (fileForm !== undefined) {
                    if ($('.chat-module-loader').length == 0) {
                        $('.content').prepend('<div class="chat-module-loader"><div></div><div></div><div></div></div>');
                    } else {
                        $('.content .chat-module-loader').fadeIn();
                    }
                    var Regex = new RegExp('\[~%:\()@]');

                    if (Regex.test(fileForm.name)) {
                        alert_float('warning', '<?php echo _l('chat_permitted_files') ?>');
                        $('.content .chat-module-loader').fadeOut();
                        return false;
                    }
                } else {
                    $('.content .chat-module-loader').fadeOut();
                    return false;
                }
            },
            success: function(r) {
                if (!r.error) {
                    var uploadSend = $.Event("keypress", {
                        which: 13
                    });

                    var basePath = "<?php echo base_url('modules/prchat/uploads/groups/'); ?>";
                    $('form#groupFileForm').trigger("reset");
                    $('#frame textarea.group_chatbox').val(basePath + r.upload_data.file_name);
                    setTimeout(function() {
                        if ($('#frame textarea.group_chatbox').trigger(uploadSend)) {
                            alert_float('info', 'File ' + r.upload_data.file_name + ' sent.');
                            $('.content .chat-module-loader').fadeOut();
                        }
                    }, 100);
                    getGroupSharedFiles(to_group);
                } else {
                    $('.content .chat-module-loader').fadeOut();
                    alert_float('danger', r.error);
                }
            }
        });
    }


    function appendGroupOptions() {
        var group_messages_selector = $('.group_messages .chat_group_messages');
        var group_messages_active_selector = $('.content .chat_group_options');
        var accordionMembers = '';
        var accordionSharedFiles = '';

        accordionMembers += '<div class="panel-group" id="group_members">';
        accordionMembers += '<div class="panel panel-default">';
        accordionMembers += '<div class="panel-heading">';
        accordionMembers += '<h4 class="panel-title">';
        accordionMembers += '<a class="accordion-toggle" data-toggle="collapse" data-parent="#group_members" href="#groupOptionsTwo">';
        accordionMembers += '<?php echo _l("chat_group_members_text"); ?>';
        accordionMembers += '</a>';
        accordionMembers += '</h4>';
        accordionMembers += '</div>';
        accordionMembers += '<div id="groupOptionsTwo" class="panel-collapse collapse in">';
        accordionMembers += '<div class="panel-body">';
        accordionMembers += '<div class="group_members"></div>';
        accordionMembers += '</div></div></div>';

        accordionSharedFiles += '<div class="panel-group" id="group_shared_files">';
        accordionSharedFiles += '<div class="panel panel-default">';
        accordionSharedFiles += '<div class="panel-heading">';
        accordionSharedFiles += '<h4 class="panel-title">';
        accordionSharedFiles += '<a class="accordion-toggle" data-toggle="collapse" data-parent="#group_shared_files" href="#groupOptionsThree">';
        accordionSharedFiles += '<?php echo _l("chat_group_shared_items_text"); ?>';
        accordionSharedFiles += '</a>';
        accordionSharedFiles += '</h4>';
        accordionSharedFiles += '</div>';
        accordionSharedFiles += '<div id="groupOptionsThree" class="panel-collapse collapse in">';
        accordionSharedFiles += '<div class="panel-body">';
        accordionSharedFiles += '<div class="main_div_shared_files"></div>';
        accordionSharedFiles += '</div></div></div>';

        group_messages_active_selector.html('');
        group_messages_active_selector.append(accordionMembers);
        group_messages_active_selector.append(accordionSharedFiles);
    }

    function isUserMentioned(message) {
        var result = '';
        if (message.includes('data-mention-id')) {
            var unescaped_msg = unescapeHtml(message);
            var mentionMatch = new RegExp(">[^<]*</", "g");

            var ress = unescaped_msg.match(mentionMatch).map(function(val) {
                return val.replace('>', '').replace('</', '');
            });

            if (ress.length) {
                ress.forEach(function(name) {
                    if (message.includes(name)) {
                        message = message.replace(unescaped_msg, '<bold>' + name + '</bold>');
                    }
                });
                result = unescapeHtml(message);
            }
        } else {
            return message;
        }
        return result;
    }

    function unescapeHtml(unsafe) {
        return unsafe
            .replace(/&amp;/g, "&")
            .replace(/&lt;/g, "<")
            .replace(/&gt;/g, ">")
            .replace(/&quot;/g, "\"")
            .replace(/&#039;/g, "'");
    }

    function sendMentionNotifications(from, channel, users) {
        // Get textarea values and user names
        var chatBoxValues = $('body').find('.group_chatbox').val();

        // go trough users and see if textarea contains users as same in array
        // if no remove user from array also because we want no notification to be sent to those users
        users.forEach(function(user, index, object) {
            if (!chatBoxValues.includes('@' + user.name)) {
                object.splice(index, 1);
            }
        });

        $.post(admin_url + 'prchat/Prchat_Controller/pusherMentionEvent', {
            from: from,
            channel: channel,
            users: users
        })
    }

    $('textarea.mention').mentionsInput({
        onDataRequest: _.debounce(function(mode, query, callback) {
            var group_id = $('.group_messages').attr('id');
            $.getJSON("<?= admin_url('prchat/Prchat_Controller/getUsersInJsonFormat'); ?>", {
                group_id: group_id
            }, function(usersJsonResponseData) {
                usersJsonResponseData = _.filter(usersJsonResponseData, function(item) {
                    return item.name.toLowerCase().indexOf(query.toLowerCase()) > -1
                });
                callback.call(this, usersJsonResponseData);
            }); /**/
        }, 200)
    });
</script>