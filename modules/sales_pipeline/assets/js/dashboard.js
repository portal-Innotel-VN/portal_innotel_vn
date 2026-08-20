(function ($) {
    'use strict';

    $(function () {
        var $dashboard = $('[data-sales-pipeline-dashboard]');
        if (!$dashboard.length) {
            return;
        }

        var $drawer = $('#sp-dashboard-drawer');
        var $drawerPanel = $drawer.find('.sp-dashboard-drawer__panel');
        var $drawerContent = $drawer.find('[data-dashboard-drawer-content]');
        var $contentWrapper = $dashboard.find('[data-dashboard-content-wrapper]');
        var staffUrl = String($dashboard.data('staff-url') || '').replace(/\/$/, '');
        var dashboardUrl = String($dashboard.data('dashboard-url') || '');
        var loadingMessage = String($dashboard.data('loading-message') || 'Loading...');
        var errorMessage = String($dashboard.data('error-message') || 'Unable to load data.');
        var activeRequest = null;
        var activeDashboardRequest = null;
        var lastTrigger = null;
        var activeDashboardTab = String(
            $contentWrapper.find('[data-dashboard-tab][aria-selected="true"]').data('dashboard-tab') || 'deals'
        );

        function updateDashboardUrl(period, tab) {
            var urlParams = new URLSearchParams(window.location.search);
            if (period && period !== 'this_month') {
                urlParams.set('period', period);
            } else {
                urlParams.delete('period');
            }
            if (tab && tab !== 'deals') {
                urlParams.set('dashboard_tab', tab);
            } else {
                urlParams.delete('dashboard_tab');
            }
            var nextQuery = urlParams.toString();
            window.history.replaceState({}, '', window.location.pathname + (nextQuery ? '?' + nextQuery : ''));
        }

        function activateDashboardTab(tab, moveFocus) {
            if (tab !== 'deals' && tab !== 'estimates') {
                tab = 'deals';
            }

            activeDashboardTab = tab;
            var $tabs = $contentWrapper.find('[data-dashboard-tab]');
            var $panels = $contentWrapper.find('[data-dashboard-panel]');
            $tabs.each(function () {
                var isActive = String($(this).data('dashboard-tab')) === tab;
                $(this)
                    .toggleClass('is-active', isActive)
                    .attr('aria-selected', isActive ? 'true' : 'false')
                    .attr('tabindex', isActive ? '0' : '-1');
                if (isActive && moveFocus) {
                    $(this).trigger('focus');
                }
            });
            $panels.each(function () {
                var isActive = String($(this).data('dashboard-panel')) === tab;
                $(this).toggleClass('is-active', isActive).prop('hidden', !isActive);
            });

            updateDashboardUrl($('#sp-dashboard-time-filter').val() || 'this_month', tab);
        }

        function initializeDashboardContent() {
            if ($.fn.tooltip) {
                $contentWrapper.find('[data-toggle="tooltip"]').tooltip();
            }
        }

        function setDashboardLoading(isLoading) {
            $contentWrapper.find('[data-performance-loading]').remove();
            if (!isLoading) {
                $contentWrapper.removeClass('sp-loading').removeAttr('aria-busy');
                return;
            }

            var $loading = $('<div>', {
                class: 'sp-dashboard-loading-state',
                'data-performance-loading': '',
                role: 'status',
                'aria-label': loadingMessage
            });
            for (var rowIndex = 0; rowIndex < 3; rowIndex++) {
                var $row = $('<div>', { class: 'sp-dashboard-loading-state__row' });
                for (var cellIndex = 0; cellIndex < 6; cellIndex++) {
                    $('<span>', {
                        class: 'sp-dashboard-loading-state__cell'
                            + (cellIndex === 1 ? ' sp-dashboard-loading-state__cell--staff' : ''),
                        'aria-hidden': 'true'
                    }).appendTo($row);
                }
                $row.appendTo($loading);
            }
            $contentWrapper.addClass('sp-loading').attr('aria-busy', 'true').append($loading);
        }

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

        function openDrawer(trigger) {
            var $trigger = $(trigger);
            var staffId = $trigger.data('staff-id');

            if (!staffId || !staffUrl) {
                return;
            }

            if (activeRequest) {
                activeRequest.abort();
            }

            lastTrigger = trigger;
            $drawer = $('#sp-dashboard-drawer');
            $drawerPanel = $drawer.find('.sp-dashboard-drawer__panel');
            $drawerContent = $drawer.find('[data-dashboard-drawer-content]');

            var period = $('#sp-dashboard-time-filter').val() || 'this_month';

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
                dataType: 'json',
                data: {
                    dashboard_tab: activeDashboardTab,
                    period: period
                }
            }).done(function (response) {
                if (response && response.success && response.data
                    && Object.prototype.hasOwnProperty.call(response.data, 'html')) {
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
            openDrawer(this);
        });

        $dashboard.on('change', '#sp-dashboard-time-filter', function () {
            var period = $(this).val() || 'this_month';

            if (!dashboardUrl || !$contentWrapper.length) {
                return;
            }

            if (activeDashboardRequest) {
                activeDashboardRequest.abort();
            }

            updateDashboardUrl(period, activeDashboardTab);

            setDashboardLoading(true);

            var dashboardRequest = $.ajax({
                url: dashboardUrl,
                method: 'GET',
                dataType: 'json',
                data: {
                    period: period,
                    dashboard_tab: activeDashboardTab
                }
            }).done(function (response) {
                if (response && response.success && response.data && response.data.html) {
                    $contentWrapper.html(response.data.html);
                    initializeDashboardContent();
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
                if (activeDashboardRequest === dashboardRequest) {
                    activeDashboardRequest = null;
                    setDashboardLoading(false);
                }
            });

            activeDashboardRequest = dashboardRequest;
        });

        $dashboard.on('click', '[data-dashboard-tab]', function (event) {
            if (event.metaKey || event.ctrlKey || event.shiftKey) {
                return;
            }
            event.preventDefault();
            activateDashboardTab(String($(this).data('dashboard-tab')), false);
        });

        $dashboard.on('keydown', '[data-dashboard-tab]', function (event) {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight' && event.key !== 'Home' && event.key !== 'End') {
                return;
            }

            event.preventDefault();
            var nextTab = activeDashboardTab;
            if (event.key === 'Home' || event.key === 'ArrowLeft') {
                nextTab = 'deals';
            } else if (event.key === 'End' || event.key === 'ArrowRight') {
                nextTab = 'estimates';
            }
            activateDashboardTab(nextTab, true);
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
        initializeDashboardContent();
    });
})(jQuery);
