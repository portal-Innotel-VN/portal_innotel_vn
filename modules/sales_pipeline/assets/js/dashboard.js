(function ($) {
    'use strict';

    $(function () {
        var $dashboard = $('[data-sales-pipeline-dashboard]');
        if (!$dashboard.length) {
            return;
        }

        var $drawer = $dashboard.find('[data-dashboard-drawer]');
        var $drawerPanel = $drawer.find('.sp-dashboard-drawer__panel');
        var $drawerContent = $drawer.find('[data-dashboard-drawer-content]');
        var $leaderboardWrapper = $dashboard.find('[data-dashboard-leaderboard-wrapper]');
        var staffUrl = String($dashboard.data('staff-url') || '').replace(/\/$/, '');
        var leaderboardUrl = String($dashboard.data('leaderboard-url') || '');
        var loadingMessage = String($dashboard.data('loading-message') || 'Loading...');
        var errorMessage = String($dashboard.data('error-message') || 'Unable to load data.');
        var activeRequest = null;
        var activeLeaderboardRequest = null;
        var lastTrigger = null;

        function createState(iconClass, message, isError) {
            var $state = $('<div>', {
                class: 'sp-drawer-state' + (isError ? ' sp-drawer-state--error' : '')
            });
            $('<i>', {
                class: 'fa ' + iconClass,
                'aria-hidden': 'true'
            }).appendTo($state);
            $('<span>').text(message).appendTo($state);
            return $state;
        }

        function setDrawerState(iconClass, message, isError) {
            $drawerContent.empty().append(createState(iconClass, message, isError));
        }

        function openDrawer(staffId, trigger) {
            if (!staffId || !staffUrl) {
                return;
            }

            if (activeRequest) {
                activeRequest.abort();
            }

            lastTrigger = trigger || null;
            $drawer.addClass('is-open').attr('aria-hidden', 'false');
            $drawerPanel.attr('aria-busy', 'true');
            $('body').addClass('sp-dashboard-drawer-open');
            setDrawerState('fa-circle-o-notch fa-spin', loadingMessage, false);
            window.setTimeout(function () {
                $drawerPanel.trigger('focus');
            }, 30);

            activeRequest = $.ajax({
                url: staffUrl + '/' + encodeURIComponent(staffId),
                method: 'GET',
                dataType: 'json'
            }).done(function (response) {
                if (response && response.success && response.data && response.data.html) {
                    $drawerContent.html(response.data.html);
                    if ($.fn.tooltip) {
                        $drawerContent.find('[data-toggle="tooltip"]').tooltip();
                    }
                    return;
                }

                var responseMessage = response && response.message ? response.message : errorMessage;
                setDrawerState('fa-exclamation-circle', responseMessage, true);
            }).fail(function (xhr, status) {
                if (status === 'abort') {
                    return;
                }

                var responseMessage = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : errorMessage;
                setDrawerState('fa-exclamation-circle', responseMessage, true);
            }).always(function () {
                activeRequest = null;
                $drawerPanel.removeAttr('aria-busy');
            });
        }

        function closeDrawer() {
            if (!$drawer.hasClass('is-open')) {
                return;
            }

            if (activeRequest) {
                activeRequest.abort();
                activeRequest = null;
            }

            $drawer.removeClass('is-open').attr('aria-hidden', 'true');
            $('body').removeClass('sp-dashboard-drawer-open');

            if (lastTrigger) {
                $(lastTrigger).trigger('focus');
                lastTrigger = null;
            }
        }

        $dashboard.on('click', '.js-sp-open-staff', function () {
            openDrawer($(this).data('staff-id'), this);
        });

        $dashboard.on('change', '#sp-dashboard-time-filter', function () {
            var period = $(this).val() || 'this_month';

            if (!leaderboardUrl || !$leaderboardWrapper.length) {
                return;
            }

            if (activeLeaderboardRequest) {
                activeLeaderboardRequest.abort();
            }

            var urlParams = new URLSearchParams(window.location.search);
            if (period && period !== 'this_month') {
                urlParams.set('period', period);
            } else {
                urlParams.delete('period');
            }
            var nextQuery = urlParams.toString();
            window.history.replaceState({}, '', window.location.pathname + (nextQuery ? '?' + nextQuery : ''));

            $leaderboardWrapper.addClass('sp-loading').attr('aria-busy', 'true');

            activeLeaderboardRequest = $.ajax({
                url: leaderboardUrl,
                method: 'GET',
                dataType: 'json',
                data: {
                    period: period
                }
            }).done(function (response) {
                if (response && response.success && response.data && response.data.html) {
                    $leaderboardWrapper.html(response.data.html);
                    if (typeof init_selectpicker === 'function') {
                        init_selectpicker();
                    }
                    if ($.fn.tooltip) {
                        $leaderboardWrapper.find('[data-toggle="tooltip"]').tooltip();
                    }
                    return;
                }

                var responseMessage = response && response.message ? response.message : errorMessage;
                if (typeof sp_alert === 'function') {
                    sp_alert('danger', responseMessage);
                }
            }).fail(function (xhr, status) {
                if (status === 'abort') {
                    return;
                }

                var responseMessage = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : errorMessage;
                if (typeof sp_alert === 'function') {
                    sp_alert('danger', responseMessage);
                }
            }).always(function () {
                activeLeaderboardRequest = null;
                $leaderboardWrapper.removeClass('sp-loading').removeAttr('aria-busy');
            });
        });

        $drawer.on('click', '.js-sp-close-drawer', closeDrawer);

        $dashboard.on('click', '.js-sp-dashboard-refresh', function () {
            window.location.reload();
        });

        $(document).on('keydown.salesPipelineDashboard', function (event) {
            if (event.key === 'Escape') {
                closeDrawer();
            }
        });

        if ($.fn.tooltip) {
            $dashboard.find('[data-toggle="tooltip"]').tooltip();
        }
    });
})(jQuery);
