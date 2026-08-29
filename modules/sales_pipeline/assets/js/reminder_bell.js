/**
 * Sales Pipeline - Reminder Bell Inbox JS
 * Seamlessly integrates Pending Reminders into Perfex CRM Notification Bell.
 */
(function ($) {
    'use strict';

    var FEED_URL = admin_url + 'sales_pipeline/reminder_bell_feed';
    var ACK_URL_PREFIX = admin_url + 'sales_pipeline/reminder_bell_acknowledge/';
    var i18n = window.salesPipelineReminderBellI18n || null;

    var state = {
        isLoading: false,
        pendingCount: 0,
        items: [],
        hasFetchedOnce: false,
        lastFetchTime: 0,
        lastFetchSucceeded: false,
        error: false
    };

    var observers = [];
    var debounceTimer = null;
    var activeRequest = null;
    var requestSerial = 0;

    /**
     * Escape HTML helper to prevent XSS.
     */
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Get the Core Notification Bell wrapper element.
     */
    function getWrapper() {
        return $('li.notifications-wrapper.header-notifications');
    }

    /**
     * Get the dropdown menu container.
     */
    function getDropdown() {
        var $wrapper = getWrapper();
        return $wrapper.find('.dropdown-menu.notifications, ul.notifications');
    }

    /**
     * Update Pulse Dot and badge count on header wrapper.
     */
    function updatePendingState(count) {
        state.pendingCount = parseInt(count, 10) || 0;
        var $wrapper = getWrapper();
        if ($wrapper.length) {
            $wrapper
                .toggleClass('has-sp-reminder-pending', state.pendingCount > 0)
                .attr('data-sp-reminder-count', state.pendingCount);
        }

    }

    /**
     * Build HTML for a single reminder item card.
     */
    function renderReminderItem(item) {
        var isActionable = (parseInt(item.response_required, 10) === 1);
        var severity = String(item.severity || 'warning').toLowerCase();
        if (severity === 'danger') {
            severity = 'critical';
        }
        if ($.inArray(severity, ['critical', 'warning']) === -1) {
            severity = 'warning';
        }
        var severityClass = 'sp-severity-' + severity;
        var severityLabel = i18n.severity && i18n.severity[severity]
            ? i18n.severity[severity]
            : severity;

        var html = '<li class="sp-reminder-item ' + severityClass + '" data-reminder-id="' + item.id + '">';

        // Header
        html += '<div class="sp-reminder-item-header">';
        html += '<div class="sp-reminder-item-title">';
        html += '<span class="sr-only sp-reminder-severity-label">' + escapeHtml(severityLabel) + ': </span>';
        if (isActionable && item.quick_response_url) {
            html += '<a href="' + escapeHtml(item.quick_response_url) + '">' + escapeHtml(item.title) + '</a>';
        } else if (item.entity_url) {
            html += '<a href="' + escapeHtml(item.entity_url) + '">' + escapeHtml(item.title) + '</a>';
        } else {
            html += escapeHtml(item.title);
        }
        html += '</div>';

        // Informational Dismiss Button (✕)
        if (!isActionable) {
            html += '<button type="button" class="sp-bell-ack-btn" data-id="' + item.id + '" title="' + escapeHtml(i18n.ackTooltip) + '" aria-label="' + escapeHtml(i18n.ackTooltip) + '">&times;</button>';
        }
        html += '</div>';

        // Message
        if (item.message) {
            html += '<div class="sp-reminder-item-message">' + escapeHtml(item.message) + '</div>';
        }

        // Footer
        html += '<div class="sp-reminder-item-footer">';
        html += '<small class="text-muted sp-reminder-item-time">' + escapeHtml(item.time_ago || '') + '</small>';

        html += '<div class="sp-reminder-actions">';
        if (item.entity_url) {
            html += '<a href="' + escapeHtml(item.entity_url) + '" class="sp-reminder-btn sp-reminder-btn-default">' + escapeHtml(i18n.actionView) + '</a>';
        }
        if (isActionable && item.quick_response_url) {
            html += '<a href="' + escapeHtml(item.quick_response_url) + '" class="sp-reminder-btn sp-reminder-btn-primary">' + escapeHtml(i18n.actionRespond) + '</a>';
        }
        html += '</div>';

        html += '</div>'; // end footer
        html += '</li>';
        return html;
    }

    /**
     * Render the Reminder Inbox section inside dropdown.
     */
    function renderInbox() {
        var $dropdown = getDropdown();
        if (!$dropdown.length) return;

        // The Module Inbox is an enhancement of Core Bell, not a second panel.
        // Loading, empty and error states therefore stay silent and preserve
        // the Core notification list as the only visible fallback.
        if (state.error || !state.items || state.items.length === 0) {
            unmountInbox();
            updatePendingState(0);
            return;
        }

        var $inbox = $dropdown.find('.sp-reminder-bell-inbox');
        if (!$inbox.length) {
            var inboxSkeleton = '<li class="sp-reminder-bell-inbox">' +
                '<ul class="sp-reminder-inbox-scroll" aria-live="polite" aria-busy="false"></ul>' +
                '</li>';

            // Prepend before Core notifications
            $dropdown.prepend(inboxSkeleton);
            $inbox = $dropdown.find('.sp-reminder-bell-inbox');
        }

        var $scroll = $inbox.find('.sp-reminder-inbox-scroll');
        $scroll.attr('aria-busy', state.isLoading ? 'true' : 'false');

        var itemsHtml = '';
        for (var i = 0; i < state.items.length; i++) {
            itemsHtml += renderReminderItem(state.items[i]);
        }
        $scroll.html(itemsHtml);
        updatePendingState(state.pendingCount);

        if (state.lastFetchSucceeded) {
            dedupeLegacyItems();
        }
    }

    function unmountInbox() {
        getDropdown().find('.sp-reminder-bell-inbox').remove();
    }

    /** Restore every Core item hidden by this module before applying a new feed. */
    function restoreLegacyItems() {
        getDropdown()
            .find('li.notification-wrapper[data-sp-reminder-hidden="1"]')
            .removeAttr('data-sp-reminder-hidden')
            .show();
    }

    /**
     * Hide legacy Core notification items that duplicate active Reminder items.
     */
    function dedupeLegacyItems() {
        var $dropdown = getDropdown();
        if (!$dropdown.length) return;

        restoreLegacyItems();
        if (!state.lastFetchSucceeded || !state.items || state.items.length === 0) return;

        var reminderIds = {};
        for (var i = 0; i < state.items.length; i++) {
            reminderIds[String(parseInt(state.items[i].id, 10))] = true;
        }

        $dropdown.find('li.notification-wrapper a.notification-link').each(function () {
            var href = String($(this).attr('href') || '');
            var match = href.match(/sales_pipeline\/reminder_response\/(\d+)(?:[\/?#]|$)/);
            if (match && reminderIds[match[1]]) {
                $(this).closest('li.notification-wrapper')
                    .attr('data-sp-reminder-hidden', '1')
                    .hide();
            }
        });
    }

    function handleFeedFailure() {
        state.isLoading = false;
        state.hasFetchedOnce = true;
        state.lastFetchSucceeded = false;
        state.error = true;
        state.items = [];
        state.pendingCount = 0;
        restoreLegacyItems();
        renderInbox();
    }

    /**
     * Fetch latest feed from server.
     */
    function fetchFeed() {
        if (activeRequest && activeRequest.readyState !== 4) {
            activeRequest.abort();
        }

        var requestId = ++requestSerial;
        state.isLoading = true;
        state.error = false;
        renderInbox();

        activeRequest = $.ajax({
            url: FEED_URL,
            type: 'GET',
            dataType: 'json',
            cache: false,
            success: function (res) {
                if (requestId !== requestSerial) return;
                state.isLoading = false;
                state.hasFetchedOnce = true;
                state.lastFetchTime = Date.now();

                if (res && res.status && res.data && Array.isArray(res.data.items)) {
                    state.items = res.data.items;
                    state.pendingCount = parseInt(res.data.pending_count, 10) || 0;
                    state.lastFetchSucceeded = true;
                    state.error = false;
                    renderInbox();
                    return;
                }

                handleFeedFailure();
            },
            error: function (xhr, status, err) {
                if (requestId !== requestSerial || status === 'abort') return;
                handleFeedFailure();
            },
            complete: function () {
                if (requestId === requestSerial) {
                    activeRequest = null;
                }
            }
        });
    }

    /**
     * Handle Informational Acknowledge (✕ click).
     */
    function handleAcknowledge(e) {
        e.preventDefault();
        e.stopPropagation();

        var $btn = $(this);
        var reminderId = parseInt($btn.data('id'), 10);
        if (!reminderId) return;

        var $item = $btn.closest('.sp-reminder-item');
        $item.addClass('sp-item-fading');

        var postData = {};
        if (typeof csrfData !== 'undefined' && csrfData && csrfData.token_name) {
            postData[csrfData.token_name] = csrfData.hash;
        }

        $.ajax({
            url: ACK_URL_PREFIX + reminderId,
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function (res) {
                if (res && res.status) {
                    setTimeout(function () {
                        $item.remove();
                        // Remove from local state
                        state.items = state.items.filter(function (it) {
                            return it.id !== reminderId;
                        });
                        updatePendingState(Math.max(0, state.pendingCount - 1));
                        if (state.items.length === 0) {
                            renderInbox();
                        }
                    }, 250);
                } else {
                    $item.removeClass('sp-item-fading');
                    alert_float('danger', (res && res.message) ? res.message : i18n.inboxError);
                }
            },
            error: function (xhr) {
                $item.removeClass('sp-item-fading');
                var msg = i18n.inboxError;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                alert_float('danger', msg);
            }
        });
    }

    /**
     * Setup MutationObserver to watch for Core Perfex DOM re-renders.
     */
    function mutationOnlyTouchesModule(mutation) {
        if ($(mutation.target).closest('.sp-reminder-bell-inbox').length > 0) {
            return true;
        }

        var nodes = Array.prototype.slice.call(mutation.addedNodes || [])
            .concat(Array.prototype.slice.call(mutation.removedNodes || []));
        if (nodes.length === 0) return false;

        return nodes.every(function (node) {
            return node.nodeType === 1
                && ($(node).hasClass('sp-reminder-bell-inbox')
                    || $(node).closest('.sp-reminder-bell-inbox').length > 0);
        });
    }

    function setupMutationObserver() {
        var $wrapper = getWrapper();
        if (!$wrapper.length || typeof MutationObserver === 'undefined') return;

        observers.forEach(function (observer) { observer.disconnect(); });
        observers = [];

        $wrapper.each(function () {
            var wrapperElement = this;
            var observer = new MutationObserver(function (mutations) {
                var coreChanged = false;
                for (var i = 0; i < mutations.length; i++) {
                    if (mutations[i].type === 'childList' && !mutationOnlyTouchesModule(mutations[i])) {
                        coreChanged = true;
                        break;
                    }
                }

                if (!coreChanged) return;
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    if ($(wrapperElement).hasClass('open')) {
                        fetchFeed();
                    } else {
                        renderInbox();
                    }
                }, 100);
            });

            observer.observe(wrapperElement, {
                childList: true,
                subtree: true
            });
            observers.push(observer);
        });
    }

    /**
     * Initialize on DOM Ready.
     */
    $(function () {
        if (!i18n) return;

        var $wrapper = getWrapper();
        if (!$wrapper.length) return;

        // 1. Initial fetch renders a real loading state before the request.
        fetchFeed();

        // 2. Setup MutationObserver for Core re-renders
        setupMutationObserver();

        // 3. Bind Dropdown Open Event
        $wrapper.off('show.bs.dropdown.spReminderBell').on('show.bs.dropdown.spReminderBell', function () {
            // Fetch if last fetch was > 15s ago
            if (Date.now() - state.lastFetchTime > 15000) {
                fetchFeed();
            } else {
                renderInbox();
            }
        });

        // 4. Delegate Acknowledge Click Event
        $(document).on('click', '.sp-bell-ack-btn', handleAcknowledge);
    });

})(jQuery);
