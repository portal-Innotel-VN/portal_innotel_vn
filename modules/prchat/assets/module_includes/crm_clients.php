<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<script>
    (function() {
        "use strict";
        /*---------------* Pusher Trigger accessing channel *---------------*/
        var clientsChannel = pusher.subscribe('presence-clients');
        var customersUlFirst = '';
        var hiddenUnreadMessages = $('.invisibleUnread');
        var own_image_url = $('.icon.header-user-profile a img').prop("src");
        var clientOffsetPush = 0;
        var clientEndOfScroll = false;
        var lang_customer = "<?= _l('chat_lang_customer'); ?>";
        var lang_contact = "<?= _l('chat_lang_contact'); ?>";
        var checkForNewClientUnreadMessages = prchatSettings.getClientUnreadMessages;

        var f = {
            fetchClients: function() {
                var customers = JSON.stringify(<?php get_staff_customers(); ?>);
                return JSON.parse(customers);
            },
            chatAppendCustomers: function(customers) {
                var dfd = new $.Deferred();
                var promise = dfd.promise();

                customers = chatGroupBy(customers, 'client_id');

                if (Object.keys(customers).length === 0) {
                    $('form[name=clientMessagesForm] .message-input').hide();
                    $('.chat_clients_list').append("<li class='text-center m-t-5 cp-5 bg-chat-primary'><h4><?= _l('chat_assigned_contacts'); ?></h4></li>");
                    return false;
                }


                initCustomersAppending(customers);
                dfd.resolve(customers);

                promise.then(function() {
                    customersUlFirst = $('.chat_clients_list ul:first');
                    f.initAfterAppendClients();
                });
            },
            initAfterAppendClients: function() {
                // Append client contacts unread messages
                if (customersUlFirst !== '') {
                    customersUlFirst.find('p.customers_toggler').click();
                }
                // Show button if there are more thaan 16 clients available in database
                if ($('.chat_clients_list').children().length > 16) {
                    $('.chat_clients_list').after('<button type="button" onClick="fetchMoreClients(this)" class="btn btn-primary btn-block chat_load_more_clients"><?= _l('chat_load_more_clients'); ?></button');
                } else {
                    $('#frame #clients_container').css('height', '72vh');
                }

                f.checkForUnreadMessages();
            },
            updateUnreadMessages: function(id) {
                $.post(prchatSettings.updateClientUnread, {
                    id: id
                });
            },
            checkForUnreadMessages: function() {
                $.post(checkForNewClientUnreadMessages).done(function(r) {
                    var contacts = '';
                    r = JSON.parse(r);
                    if (!r.null) {
                        $.each(r, function(i, sender) {
                            var sender_id = sender.sender_id.replace('client_', '');
                            var contactName = $('body').find('.contact_name#' + sender_id);

                            if (contactName.length === 0 && sender.client_data !== null) {
                                var contact_fullname = sender.client_data.firstname + ' ' + sender.client_data.lastname;

                                $('<ul class="list-group company_selector active" id="client_' + sender.client_data.client_id + '"><p class="customers_toggler chevron-default">' + sender.client_data.company + '</p><li class="list-group-item contact_name" style="display:list-item;" id="' + sender.client_data.contact_id + '"><span class="client-unread-notifications" data-badge="' + sender.count_messages + '"></span>' + contact_fullname + '</li></ul>').prependTo('#crm_clients .chat_clients_list');

                                clientsChannel.bind('pusher:subscription_succeeded', function(members) {
                                    if (members.get(sender.sender_id)) {
                                        $('li.contact_name#' + sender.client_data.contact_id).addClass('contactActive');
                                    }
                                });
                            }

                            contactName.append('<span class="client-unread-notifications" data-badge="' + sender.count_messages + '"></span>');
                            contactName.css('display', 'block');
                            contactName.parent().addClass('active');
                            contactName.parent().children('p.customers_toggler').chevronRemoveRightAddDefault();
                            contactName.parent().prependTo('.chat_clients_list');
                        });
                        $('.crm_clients a').addClass('flashit');
                    }
                });
            }
        }


        // Get clients and contacts list
        var customerData = f.fetchClients();
        if (customerData.customers !== 'none') f.chatAppendCustomers(customerData.customers);


        // DOM Functions
        // Div clients_container functionality for order when clicking
        $('body').on('click', 'p.customers_toggler', function(e) {
            e.preventDefault();
            var $this = $(this);
            var parentUl = $this.parent();
            var hasClassActive = parentUl.hasClass('active');

            if (hasClassActive) {
                parentUl.find('li').animate({
                    opacity: "toggle"
                }, {
                    duration: 100,
                    queue: false
                });
                parentUl.removeClass('active');
                $this.removeClass('chevron-default').addClass('chevron-right');
            } else {
                parentUl.find('li').animate({
                    opacity: "toggle"
                }, {
                    duration: 200,
                    queue: false
                });
                parentUl.addClass('active');
                $this.chevronRemoveRightAddDefault();
            }
        });

        /*---------------* Handle Clients DOM *---------------*/
        $('#frame li.crm_clients a').on('click', function() {

            var hasNotifications = $('.contact_name .client-unread-notifications');

            if (customersUlFirst !== '' && customersUlFirst.find('li:first').length > 0) {
                customersUlFirst.find('li:first').click();
            }

            if (customersUlFirst.length > 0 && !customersUlFirst.hasClass('active')) {
                customersUlFirst.find('.customers_toggler').click();
                hasNotifications.parent().show();
                hasNotifications.parents('ul').addClass('active');
                hasNotifications.parents('ul').children('p.chevron-right').removeClass('chevron-right').addClass('chevron-down');

                var id = customersUlFirst.children('li:first').attr('id');
                f.updateUnreadMessages(id);
            }
            $('#frame form[name=groupMessagesForm],#frame form[name=pusherMessagesForm],#frame .content .group_messages, #frame .groupOptions').hide();
            $('#frame .chat_group_options.active').hide().removeClass('active');

            $('#search #search_field').attr('id', 'search_clients_field');
            $('#search #search_clients_field').attr('placeholder', '<?= _l('chat_search_customers'); ?>');

            chat_contact_profile_img.hide();
            chat_contact_profile_img.next().hide();
            chat_contact_profile_img.next().next().hide();
            chat_social_media.hide();
            chat_content_messages.hide();
            chat_client_messages.show();

            $('.group_members_inline').remove();

            // Hide staff chatbox form
            $('#frame #pusherMessagesForm, #sharedFiles').hide();
            $(this).removeClass('flashit');

            $('#frame .content .client_messages, #frame form[name=clientMessagesForm]').show();

        });

        /*---------------* Search customers *---------------*/
        var crmClientsParent = $('#crm_clients ul:not(.dropdown-menu)');
        var crmClientsChildren = $('#crm_clients ul:not(.dropdown-menu) li');

        $("body").on("focusout", '#search_clients_field', function() {
            $(this).val('');
            var id = '';
            setTimeout(function() {
                id = $('.client_data a').attr('id');
                crmClientsParent.show();
                var selector = $('ul.company_selector#client_' + id).addClass('active');
                selector.prependTo('.chat_clients_list')
            }, 800);
        });

        $("body").on("keyup", '#search_clients_field', _debounce(function(e) {

            var value = $.trim($(this).val().toLowerCase());
            var searchInDatabase = false;
            if (value == '') {
                $("#frame #crm_clients ul:not(.dropdown-menu)").show();
                return;
            }
            var key = e.keyCode || e.which;

            // var removeClassActive = $('#crm_clients ul').removeClass('active');

            if (key == 8 || key == 46)
                crmClientsChildren.hide() &&
                crmClientsParent.show();

            if (e.keyCode === 27 || e.which == 27) {
                $('#search_clients_field').val('');
                crmClientsParent.show();
                return false;
            }
            if (value.length >= 3) {
                $("#frame  #crm_clients ul.company_selector").each(function() {
                    var $this = $(this);

                    if ($(this).find('li').prev().text().toLowerCase().indexOf(value) > -1 ||
                        $(this).find('li.contact_name').text().toLowerCase().indexOf(value) > -1
                    ) {
                        $(this).show();
                        $(this).children().show();
                    } else {
                        if ($(this).children('ul').is(':visible')) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    }
                    if (crmClientsParent.find('ul:visible:last').length === 0 ||
                        crmClientsParent.find('ul:visible:first').length === 0) {
                        searchInDatabase = true;
                    }
                });


                if (searchInDatabase) {
                    var csrf = csrfData.formatted.csrf_token_name;
                    // csrf token is automatically included in data (system adjustments)
                    var data = {
                        'search': $("#search_clients_field").val(),
                    };

                    $.ajax({
                        type: "POST",
                        url: "<?php echo site_url('prchat/Prchat_ClientsController/searchClients'); ?>",
                        cache: false,
                        data: data,
                        success: function(response) {
                            var obj = JSON.parse(response);
                            if (obj.length > 0) {
                                try {
                                    var items = [];
                                    $.each(obj, function(i, val) {
                                        var isOnline = '';
                                        var client_id = val.client_id,
                                            contact_id = val.contact_id,
                                            company = val.company,
                                            contact_fullname = val.firstname + ' ' + val.lastname;

                                        if ($('ul#client_' + client_id + '').length === 0) {
                                            if (clientsChannel.members.get('client_' + contact_id)) {
                                                isOnline = 'contactActive';
                                            }
                                            items.push('<ul class="list-group company_selector active" id="client_' + client_id + '"><p class="customers_toggler chevron-down">' + company + '</p><li class="list-group-item contact_name ' + isOnline + '" id="' + contact_id + '" style="display: list-item;">' + contact_fullname + '</li></ul>');
                                        }
                                    });
                                    $('.chat_clients_list').prepend.apply($('.chat_clients_list'), items);

                                } catch (e) {
                                    alert_float('warning', e)
                                }
                            }
                        },
                        error: function(err) {
                            alert_float('danger', err)
                        }
                    });
                    return false;
                }
            }
        }, 500));


        /*--------------------  * send message & typing event to server  * ------------------- */
        $("#frame").on('keypress', 'textarea.client_chatbox', function(e) {
            var form = $('form[name=clientMessagesForm]');
            var imgPath = $('#sidepanel #profile .wrap img').prop('currentSrc');

            if (e.which == 13) {
                e.preventDefault();
                var message = $.trim($(this).val());

                if (message == '' || internetConnectionCheck() === false) {
                    return false;
                }

                message = createTextLinks_(emojify.replace(message));

                $('.client_messages .chat_client_messages ul').append('<li class="sent" id="' + userSessionId + '"><img class="myProfilePic" src="' + imgPath + '"/><p class="you" data-toggle="tooltip" title="' + moment().format('hh:mm A') + '" id="' + userSessionId + '">' + message + '</p></li>');
                $(this).next().next().next().val('false');
                message = escapeHtml(message);
                // send event 
                var formData = form.serializeArray();

                $.post(prchatSettings.clientsMessagesPath, formData);
                $(this).val('').focus();
                scroll_event();
            } else if (!$(this).val() || ($(this).next().next().next().val() == 'null' && $(this).val())) {
                // typing event 
                $(this).next().next().next().val('true');
                $.post(prchatSettings.clientsMessagesPath, form.serialize());
            }
        });

        /*---------------* Check for unread frontend *---------------*/
        $("#frame").on("focus", ".client_chatbox", function() {
            var id = $('.client_messages').attr('id');
            if (id !== '') {
                id.replace('client_', '');
                if (hiddenUnreadMessages.val() > 0) {
                    f.updateUnreadMessages(id);
                    hiddenUnreadMessages.val('0');
                }
            }
        });

        // Init Crm clients messages view 
        $(function() {
            $('body').on('click', '.company_selector > li', function() {
                $('body').find('.company_selector > li.selected').removeClass('selected');
                if (!$(this).parent().hasClass('active')) {
                    $(this).parent().addClass('active');
                }
                $(this).addClass('selected');
            });
        });



        var createChatBoxClientRequest = null;

        function getClientMessages(staff_id, client_id, contact) {
            var notificationDiv = contact.children('.client-unread-notifications');
            var notificationLength = contact.children('.client-unread-notifications');

            if (notificationLength.length > 0) {
                notificationDiv.remove();
            }

            $("#no_messages, .group_members_inline").remove();

            var deferred = $.Deferred();
            var promise = deferred.promise();

            if (createChatBoxClientRequest) {
                createChatBoxClientRequest.abort();
                deferred.resolve([]);
            } else {
                createChatBoxClientRequest = $.get(prchatSettings.getMutualMessages, {
                        reciever_id: 'staff_' + staff_id,
                        sender_id: 'client_' + client_id,
                        offset: 0,
                        limit: 20
                    })
                    .done(function(messages) {
                        clientOffsetPush = 10;
                        clientOffsetPush += 10;
                        deferred.resolve(messages);

                    }).always(function() {
                        if ($("#no_messages").length > 0) {
                            $("#no_messages").remove();
                        }
                        createChatBoxClientRequest = null;
                    });
            }

            /*---------------* After users are fetched from database -> continue with loading *---------------*/
            promise.then(function(messages) {
                var clientUl = $('.client_messages ul');

                $(messages).each(function(key, value) {
                    value.message = emojify.replace(value.message);
                    (value.sender_id == 'staff_' + staff_id) ?
                    clientUl.prepend('<li class="sent"><img class="myProfilePic" src="' + own_image_url + '"/><p class="you" data-toggle="tooltip" data-container="body" data-placement="left" title="' + value.time_sent_formatted + '" id="msg_' + value.id + '">' + value.message + '</p></li>'): clientUl.prepend('<li class="replies"><img class="clientProfilePic" src="' + value.client_image_path + '"/><p  class="client" data-toggle="tooltip" data-container="body" data-placement="right" title="' + value.time_sent_formatted + '">' + value.message + '</p></li>');
                });

                $('.group_members_inline').remove();
            });

            activateLoader(promise, true);

            $.when(promise)
                .then(function() {
                    ($(".client_messages").hasScrollBar())
                    scroll_event();
                    $('.client_chatbox').focus();
                });
            return false;
        }


        /*---------------* Check for messages history and append to main chat window *---------------*/
        $('.client_messages').on('scroll', function() {

            var pos = $(this).scrollTop();
            var client_id = $(this).attr("id");
            var to = $('#clients_container li.contact_name.selected').attr('id');

            var defScroll = $.Deferred();
            var promise = defScroll.promise();

            if (pos == 0 && clientOffsetPush >= 10) {

                $.get(prchatSettings.getMutualMessages, {
                        reciever_id: client_id,
                        sender_id: 'staff_' + userSessionId,
                        offset: clientOffsetPush,
                    })
                    .done(function(messages) {
                        if (Array.isArray(messages) === false) {
                            clientEndOfScroll = true;
                            $('#frame .client_messages').find('.message_loader').hide();
                            if ($('.client_messages').hasScrollBar() && clientEndOfScroll == true) {
                                prchat_setNoMoreMessages();
                            }
                        } else {
                            clientOffsetPush += 10;
                            defScroll.resolve(messages);
                        }

                        $(messages).each(function(key, value) {
                            var clientUl = $('.client_messages ul');
                            value.message = emojify.replace(value.message);
                            (value.sender_id == 'staff_' + userSessionId) ?
                            clientUl.prepend('<li class="sent"><img class="myProfilePic" src="' + own_image_url + '"/><p class="you" data-toggle="tooltip" data-container="body" data-placement="left" title="' + value.time_sent_formatted + '" id="msg_' + value.id + '">' + value.message + '</p></li>'): clientUl.prepend('<li class="replies"><img class="clientProfilePic" src="' + value.client_image_path + '"/><p  class="client" data-toggle="tooltip" data-container="body" data-placement="right" title="' + value.time_sent_formatted + '">' + value.message + '</p></li>');
                        });
                        if (clientEndOfScroll === false) {
                            $('.client_messages').scrollTop(200);
                        }
                    });
                if (!clientEndOfScroll === true) {
                    activateLoader(promise, true);
                }
            }
        });

        // File upload 
        $('#frame').on('click', '.clientFileUpload', function() {
            $('#frame').find('form[name="clientFileForm"] input:first').click();
        });

        // Show All Contacts
        $('#frame').on('click', '#clients_show', function() {
            // Used and duplicated due to evenent delegation
            $('body').find('.chat_clients_list ul.company_selector')
                .children('p')
                .addClass('chevron-default').
            removeClass('chevron-right');
            $('body')
                .find('.chat_clients_list ul.company_selector li')
                .slideDown('400');
        });

        // Hide all contacts
        $('#frame').on('click', '#clients_hide', function() {
            // Used and duplicated due to evenent delegation
            $('body').find('.chat_clients_list ul.company_selector')
                .children('p')
                .removeClass('chevron-default')
                .addClass('chevron-right')
            $('body')
                .find('.chat_clients_list ul.company_selector li')
                .slideUp('400');
        });

        /*---------------* Event that handles when clicked on a specific contact in sidebar *---------------*/
        $('#clients_container').on('click', '.contact_name', function(e) {
            e.preventDefault();

            if ($(this).children('.client-unread-notifications').length > 0)
                f.updateUnreadMessages($(this).attr('id'));

            // Clear messages
            chat_client_messages.html('');
            chat_client_messages.append('<ul></ul>');

            // Reset offset and end of scroll
            clientOffsetPush = 0;
            clientEndOfScroll = false;

            var contactId = $(this).attr('id'),
                clientName = $(this).text(),
                customerName = $(this).parent().find('.customers_toggler').text(),
                contactProfile = $('#frame .contact-profile'),
                clientId = $(this).parent().attr('id').replace('client_', ''),
                clientData = '';

            $('.client_data').remove();
            $('.client_messages').attr('id', 'client_' + contactId);
            $('.client_messages').addClass('isFocused');

            $('form#clientMessagesForm .to').val('client_' + contactId);
            $('form#clientMessagesForm .from').val('staff_' + userSessionId);

            getClientMessages(userSessionId, contactId, $(this));

            clientData += '<div class="client_data">';
            clientData += '<p> ' + lang_customer + ' <a id="' + clientId + '" href="' + site_url + 'admin/clients/client/' + clientId + '" target="_blank"><strong>' + customerName + ' </strong></a></p>';
            clientData += '<span class="contact_lang"> ' + lang_contact + ' <a href="' + site_url + 'admin/clients/client/' + clientId + '?group=contacts&contactid=' + contactId + '" target="_blank""><strong>' + clientName + ' </strong></a></span>';
            clientData += '</div>';
            contactProfile.prepend(clientData);
        });

        /*---------------* Member array for online / offline activity *---------------*/
        var pendingRemoves = [];
        /*---------------* Pusher Trigger user subscribed successfully *---------------*/
        clientsChannel.bind('pusher:subscription_succeeded', function(member) {
            checkIfContactIsActive(member);
            $('#main_loader_init').fadeOut(500);
            ifContactOnlineAddToClientsList(member);
        });

        /*---------------* Pusher Trigger user logout *---------------*/
        clientsChannel.bind('pusher:member_removed', function(member) {
            removeClientMember(member);
        });

        /*---------------* Pusher Trigger user connected *---------------*/
        clientsChannel.bind('pusher:member_added', function(member) {
            var contact = member.info;
            var contactIsLoggedIn = $('ul.company_selector#client_' + contact.client_id);
            if (typeof contact.company !== "undefined") {
                if (contactIsLoggedIn.length === 0) {
                    $('<ul class="list-group company_selector active" id="client_' + contact.client_id + '"><p class="customers_toggler chevron-down">' + contact.company.name + '</p><li class="list-group-item contact_name contactActive" id="' + contact.contact_id + '" style="display: list-item;">' + contact.name + '</li></ul>').prependTo('.chat_clients_list');
                }
            }
            addClientMember(member);
        });

        /*---------------* New staff member activity online / offline  *---------------*/
        function ifContactOnlineAddToClientsList() {
            var c = f.fetchClients();
            for (var i = 0; i < c.customers.length; i++) {
                var contact = clientsChannel.members.get('client_' + c.customers[i].contact_id);
                var cid = c.customers[i].client_id;
                if (contact !== null) {
                    var bodyCid = $('body').find('.company_selector#client_' + cid);
                    if (!$(bodyCid).hasClass('active')) {
                        bodyCid.addClass('active');
                        bodyCid.find('p').chevronRemoveRightAddDefault();
                        bodyCid.children('li').css('display', 'list-item');
                    }
                    $('body').find('.company_selector#client_' + cid + ' .contact_name#' + c.customers[i].contact_id).addClass('contactActive');
                }
            };
        }

        /*---------------* New chat members tracking / removing *---------------*/
        function addClientMember(member) {
            var member = member.id.replace('client_', '');
            var pendingRemoveTimeout = pendingRemoves[member.id];

            var activeContact = $('body').find('.company_selector .contact_name#' + member).addClass('contactActive');

            activeContact.parent().addClass('active');
            activeContact.parent().children('p').chevronRemoveRightAddDefault();
            activeContact.parent().children('li').css('display', 'list-item');

            var c = f.fetchClients();
            for (var i = 0; i < c.customers.length; i++) {
                var contact = clientsChannel.members.get('client_' + c.customers[i].contact_id);
                if (contact !== null) {
                    if (contact.info.justLoggedIn) {
                        sendliveNotification(contact);
                    }
                }
            };
            if (pendingRemoveTimeout) {
                clearTimeout(pendingRemoveTimeout);
            }
        }

        /*---------------* New chat members tracking / removing from channel and UX*---------------*/
        function removeClientMember(member) {
            var member = member.id.replace('client_', '');
            pendingRemoves[member.id] = setTimeout(function() {
                $('body').find('.company_selector .contact_name#' + member).removeClass('contactActive');
            }, 5000);
        }

        /*---------------* Bind the 'send-event' & update the chat box message log *---------------*/
        var invisibleCounter = 1;

        clientsChannel.bind('send-event', function(data) {
            $('#frame .client_messages').find('span.userIsTyping').fadeOut(500);

            var selectedContact = $('.chat_clients_list ul.active li.selected');

            selectedContact = 'client_' + selectedContact.attr('id');

            if (selectedContact == data.from && data.to == 'staff_' + userSessionId) {

                data.message = createTextLinks_(emojify.replace(data.message));

                $('.client_messages#' + data.from + ' .chat_client_messages ul')
                    .append('<li class="replies"><img class="clientProfilePic" src="' + data.client_image_path + '"/><p class="client" data-toggle="tooltip" title="' + moment().format('hh:mm A') + '">' + data.message + '</p></li>');

                parseInt(hiddenUnreadMessages.val(invisibleCounter++));
                scroll_event();

            } else if (data.to == 'staff_' + userSessionId) {
                data.from = data.from.replace('client_', '');
                var contactNotification = $('body').find('.contact_name#' + data.from);
                var contactNotificationParent = $('body').find('.contact_name#' + data.from).parent();
                var contactNotificationClass = '.client-unread-notifications';

                $('.crm_clients:not(.active) a').addClass('flashit');

                var topLi = $('.chat_clients_list ul li').first();

                var isClientLoaded = $('.chat_clients_list ul#client_' + data.client_id);

                if (isClientLoaded.length === 0) {
                    $('<ul class="list-group company_selector active" id="client_' + data.client_id + '"><p class="customers_toggler chevron-default">' + data.company + '</p><li class="list-group-item contact_name contactActive" style="display:list-item;" id="' + data.from + '"><span class="client-unread-notifications" data-badge="1"></span>' + data.contact_full_name + '</li></ul>').prependTo('#crm_clients .chat_clients_list');
                }

                if (topLi.attr('id') !== data.from) {
                    contactNotificationParent.prependTo('.chat_clients_list');
                }

                initClientSound(data);
                if (!contactNotification.find(contactNotificationClass).length) {
                    contactNotification.append('<span class="client-unread-notifications" data-badge="1"></span>');
                    contactNotification.parent('ul').addClass('active');
                    contactNotification.show();
                } else {
                    var currentNotifications = parseInt(contactNotification.find(contactNotificationClass).attr('data-badge'));
                    contactNotification.find(contactNotificationClass).attr('data-badge', currentNotifications + 1);
                }
            }
        });
        /*---------------* Detect when a user is typing a message *---------------*/
        clientsChannel.bind('typing-event', function(data) {
            var clearTypingInterval = 2500; // 2.2 seconds
            var clearTypingTimerId;
            var clientMessages = $('#frame .client_messages');
            var selectedContact = $('.chat_clients_list ul.active li.selected');
            selectedContact = 'client_' + selectedContact.attr('id');

            if (clientMessages.hasClass('isFocused') &&
                data.from === selectedContact &&
                data.to == 'staff_' + userSessionId &&
                data.message == 'true') {
                clientMessages.find('.userIsTyping').fadeIn(500);
                clearTimeout(clearTypingTimerId);
                clearTypingTimerId = setTimeout(function() {
                    clientMessages.find('.userIsTyping').fadeOut(500);
                }, clearTypingInterval);
                clientMessages.find('span.userIsTyping').fadeIn(500);
            } else if (
                clientMessages.hasClass('isFocused') &&
                data.from === selectedContact &&
                data.to == 'staff_' + userSessionId &&
                data.message == 'true') {
                clientMessages.find('span.userIsTyping').fadeOut(500);
            }
        });

        $('body').on('click', '.prchat_convertedImage', function() {
            if ($('body').find('.lity-opened')) {
                $('body').find('.lity-opened').remove();
            }
        });

        function checkIfContactIsActive(member) {
            member.each(function(m) {
                var contact = m.info;
                var contactIsLoggedIn = $('body').find('ul.company_selector#client_' + contact.client_id);
                if (typeof contact.company !== "undefined") {
                    if (contactIsLoggedIn.length === 0) {
                        $('<ul class="list-group company_selector active" id="client_' + contact.client_id + '"><p class="customers_toggler chevron-down">' + contact.company.name + '</p><li class="list-group-item contact_name contactActive" id="' + contact.contact_id + '" style="display: list-item;">' + contact.name + '</li></ul>').prependTo('.chat_clients_list');
                    }
                }
            });
        }

        function sendliveNotification(contact) {
            return $.notify('', {
                'title': app.lang.new_notification,
                'body': lang_contact + ' ' + contact.info.name + ' ' + prchatSettings.hasComeOnlineText,
                'requireInteraction': true,
                'tag': 'contact-join-' + contact.id,
                'closeTime': 5000,
            });
        }

        var onlineToggled = false;
        var mainClientsContainer = $('.chat_clients_list');

        $('body').on('click', '#resetContacts', function(e) {
            e.stopPropagation();
            $.each(mainClientsContainer.children('ul'), function(i, el) {
                el = $(el);
                if (el.is(':hidden')) {
                    el.show();
                }
            });
            if (onlineToggled === false) {
                alert_float('warning', 'You are already seeing all clients');
            }
            onlineToggled = false;

        });
        $('body').on('click', '#showOnlineContacts', function(e) {
            e.stopPropagation();
            if (!onlineToggled) {
                onlineToggled = true;
                $.each(mainClientsContainer.children('ul'), function(i, el) {
                    el = $(el);
                    if (!el.children('li').hasClass('contactActive')) {
                        el.hide();
                        if (el.length) {
                            el.appendTo('.chat_clients_list');
                        }
                    } else {
                        el.attr('data-toggle', 'tooltip');
                        el.attr('title', "<?= _l('chat_clients_click_to_see_info'); ?>");
                        el.children('li:not(.contactActive)').hide();
                    }
                });

                clientsChannel.members.each(function(m) {
                    var contact = m.info;
                    var contactIsLoggedIn = $('body').find('ul.company_selector#client_' + contact.client_id);
                    if (typeof contact.company !== "undefined") {
                        if (contactIsLoggedIn.length === 0) {
                            $('<ul class="list-group company_selector active" id="client_' + contact.client_id + '"><p class="customers_toggler chevron-down">' + contact.company.name + '</p><li class="list-group-item contact_name contactActive" id="' + contact.contact_id + '" style="display: list-item;">' + contact.name + '</li></ul>').prependTo('.chat_clients_list');
                        }
                    }
                });
            }
        });
    }());

    function initCustomersAppending(customers) {
        for (var index in customers) {
            var client_id = client_id = customers[index][0].client_id,
                company = customers[index][0].company,
                companyExists = $('ul#client_' + client_id);

            if (!companyExists.length)
                $('#crm_clients .chat_clients_list')
                .append('<ul class="list-group company_selector" id="client_' + client_id + '"><p class="customers_toggler chevron-right">' + company + '</p></ul>');

            customers[index].forEach(function(contact) {
                var firstname = contact.firstname,
                    lastname = contact.lastname,
                    contact_id = contact.contact_id;

                $('#crm_clients .chat_clients_list ul#client_' + client_id)
                    .append('<li class="list-group-item contact_name" id="' + contact_id + '">' + firstname + ' ' + lastname + '</li>');
            });
        }
    }
    // Handles client file form upload 
    function uploadClientFileForm(form) {
        var formData = new FormData();
        var fileForm = $(form).children('input[type=file]')[0].files[0];
        var sentTo = $('.company_selector.active').attr('id');
        var token_name = $(form).children('input[name=csrf_token_name]').val();
        var formId = $(form).attr('id');

        formData.append('userfile', fileForm);
        formData.append('send_to', sentTo);
        formData.append('send_from', 'staff_' + userSessionId);
        formData.append('csrf_token_name', token_name);

        $.ajax({
            type: 'POST',
            url: prchatSettings.uploadMethod,
            data: formData,
            dataType: 'json',
            processData: false,
            contentType: false,
            beforeSend: function() {
                if ($('.chat-module-loader').length == 0) {
                    $('.content').prepend('<div class="chat-module-loader"><div></div><div></div><div></div></div>');
                } else {
                    $('.content .chat-module-loader').fadeIn();
                }
                var Regex = new RegExp('\[~%:\()@]');
                if (Regex.test(fileForm.name)) {
                    alert_float('warning', '<?php echo _l('chat_permitted_files') ?>');
                    $('.content .chat-module-loader').remove();
                    return false;
                }
            },
            success: function(r) {
                if (!r.error) {
                    var uploadSend = $.Event("keypress", {
                        which: 13
                    });
                    var basePath = "<?php echo base_url('modules/prchat/uploads/'); ?>";
                    $('#frame textarea.client_chatbox').val(basePath + r.upload_data.file_name);
                    setTimeout(function() {
                        if ($('#frame textarea.client_chatbox ').trigger(uploadSend)) {
                            alert_float('info', 'File ' + r.upload_data.file_name + ' sent.');
                            $('.content .chat-module-loader').fadeOut();
                        }
                    }, 100);
                } else {
                    $('.content .chat-module-loader').fadeOut();
                    alert_float('danger', r.error);
                }
            }
        });
        $('form#' + formId).trigger("reset");
    }

    /*!
     * Group items from an array together by some criteria or value.
     * @param  {Array}           arr      The array to group items from
     * @param  {String|Function} criteria The criteria to group by
     * @return {Object}                   The grouped object
     */

    function chatGroupBy(arr, criteria) {
        return arr.reduce(function(obj, item) {
            // Check if the criteria is a function to run on the item or a property of it
            var key = typeof criteria === 'function' ? criteria(item) : item[criteria];

            // If the key doesn't exist yet, create it
            if (!obj.hasOwnProperty(key)) {
                obj[key] = [];
            }

            // Push the value to the object
            obj[key].push(item);

            // Return the object to the next item in the loop
            return obj;

        }, {});
    };

    var clientsOffset = 0;

    function fetchMoreClients(el) {
        clientsOffset += 50;
        $(el).html('<div class="fa-1x chat_loading_more_clients"><?= _l('chat_load_more_clients'); ?><i class="fa fa-spinner fa-pulse"></i><i class="fa fa-stroopwafel fa-spin"></i></div>');
        $.getJSON('<?php echo site_url('prchat/Prchat_ClientsController/loadMoreClients'); ?>', {
                offset: clientsOffset
            })
            .done(function(clients /** json format **/ ) {
                customers = chatGroupBy(clients.customers, 'client_id');

                if (Object.keys(customers).length === 0) {
                    var content = "<li class='text-center m-t-5 cp-5 no_more_clients bg-chat-primary'><h4><?= _l('chat_no_more_contacts_found'); ?></h4></li>";
                    $(el).html("<?= _l('chat_load_more_clients'); ?>");
                    if ($('.chat_clients_list li.no_more_clients').length) {
                        $('.chat_clients_list li.no_more_clients').html(content);
                    } else {
                        $('.chat_clients_list').append(content);
                    }
                    return false;
                }


                initCustomersAppending(customers);

                $('#clients_container').scrollTop($('#clients_container')[0].scrollHeight);
                $(el).html("<?= _l('chat_load_more_clients'); ?>");
            });
    }

    $('#frame .message-input .wrap .search_messages').on('click', function() {
        var table = 'chatmessages';
        var id = $('.tab-pane.fade.active.in .contact.active a').attr('id');
        $('.modal_container').load(prchatSettings.searchMessagesView, {
                id: id,
                table: table
            },
            function(res) {
                $('#search_messages_modal').modal({
                    show: true
                });
            });
    });

    $('#frame .message-input .wrap .search_client_messages').on('click', function() {
        var table = 'chatclientmessages';
        var id = $('.tab-pane.fade.active.in .company_selector.active .contact_name.selected').attr('id');
        $('.modal_container').load(prchatSettings.searchMessagesView, {
                id: 'client_' + id,
                table: table
            },
            function(res) {
                $('#search_messages_modal').modal({
                    show: true
                });
            });
    });



    jQuery.fn.extend({
        chevronRemoveRightAddDefault: function() {
            return $(this).removeClass('chevron-right').addClass('chevron-default');
        }
    });
</script>