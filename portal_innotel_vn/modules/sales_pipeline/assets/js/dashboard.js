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
        var $historyPicker = $dashboard.find('[data-history-picker]');
        var $historyToggle = $dashboard.find('.js-sp-history-picker-toggle');
        var $periodAnchorInput = $('#sp-dashboard-period-anchor');
        var $periodAnchorDisplay = $('#sp-dashboard-period-anchor-display');
        var staffUrl = String($dashboard.data('staff-url') || '').replace(/\/$/, '');
        var dashboardUrl = String($dashboard.data('dashboard-url') || '');
        var loadingMessage = String($dashboard.data('loading-message') || (window.salesPipelineI18n && window.salesPipelineI18n.loading) || '');
        var loadingEstimatesMessage = String($dashboard.data('loading-estimates-message') || (window.salesPipelineI18n && window.salesPipelineI18n.loadingEstimates) || '');
        var errorMessage = String($dashboard.data('error-message') || (window.salesPipelineI18n && window.salesPipelineI18n.error) || '');
        var dashboardI18n = window.salesPipelineI18n || {};
        var numberLocale = String(dashboardI18n.locale || document.documentElement.lang || 'en-US');
        var activeRequest = null;
        var activeDashboardRequest = null;
        var lastTrigger = null;
        var activeDashboardTab = String(
            $dashboard.find('[data-dashboard-tab][aria-selected="true"]').data('dashboard-tab') || 'deals'
        );

        function todayIso() {
            var today = new Date();
            var month = String(today.getMonth() + 1);
            var day = String(today.getDate());
            return today.getFullYear() + '-' + (month.length < 2 ? '0' + month : month)
                + '-' + (day.length < 2 ? '0' + day : day);
        }

        function selectedPeriodAnchor() {
            var displayValue = $.trim(String($periodAnchorDisplay.val() || ''));
            var normalizedValue = '';
            if (displayValue && typeof unformat_date === 'function') {
                try {
                    normalizedValue = String(unformat_date(displayValue) || '');
                } catch (ignore) {
                    normalizedValue = '';
                }
            }
            if (/^\d{4}-\d{2}-\d{2}$/.test(normalizedValue)) {
                $periodAnchorInput.val(normalizedValue);
                return normalizedValue;
            }
            $periodAnchorInput.val('');
            return '';
        }

        function formatIsoDateForProject(isoDate) {
            var parts = String(isoDate || '').split('-');
            if (parts.length !== 3) {
                return '';
            }
            var format = String((window.app && app.options && app.options.date_format) || 'd/m/Y');
            var separator = format.indexOf('.') > -1 ? '.' : (format.indexOf('-') > -1 ? '-' : '/');
            if (format.charAt(0) === 'Y') {
                return [parts[0], parts[1], parts[2]].join(separator);
            }
            if (format.charAt(0) === 'm') {
                return [parts[1], parts[2], parts[0]].join(separator);
            }
            return [parts[2], parts[1], parts[0]].join(separator);
        }

        function updateDashboardUrl(period, tab, periodAnchor) {
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
            if (periodAnchor && periodAnchor !== todayIso()) {
                urlParams.set('period_anchor', periodAnchor);
            } else {
                urlParams.delete('period_anchor');
            }
            var nextQuery = urlParams.toString();
            window.history.replaceState({}, '', window.location.pathname + (nextQuery ? '?' + nextQuery : ''));
        }

        function activateDashboardTab(tab, moveFocus) {
            if (tab !== 'deals' && tab !== 'estimates') {
                tab = 'deals';
            }

            activeDashboardTab = tab;
            var $tabs = $dashboard.find('[data-dashboard-tab]');
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

            updateDashboardUrl(
                $('#sp-dashboard-time-filter').val() || 'this_month',
                tab,
                selectedPeriodAnchor()
            );
            if (tab === 'deals') {
                setTimeout(function () {
                    var $el = $('#sp-revenue-sparkline-chart');
                    dealsChartInstance = initSparklineChart($el, dealsChartInstance);
                }, 50);
            } else if (tab === 'estimates') {
                setTimeout(function () {
                    var $el = $('#sp-estimate-revenue-sparkline-chart');
                    estimatesChartInstance = initSparklineChart($el, estimatesChartInstance);
                }, 50);
            }
        }

        var dealsChartInstance = null;
        var estimatesChartInstance = null;

        function formatCompactVND(val) {
            var num = Number(val) || 0;
            var abs = Math.abs(num);
            if (abs >= 1000000000) {
                return (num / 1000000000).toLocaleString(numberLocale, { maximumFractionDigits: 1 })
                    + ' ' + String(dashboardI18n.currencyBillion || '');
            }
            if (abs >= 1000000) {
                return (num / 1000000).toLocaleString(numberLocale, { maximumFractionDigits: 1 })
                    + ' ' + String(dashboardI18n.currencyMillion || '');
            }
            return num.toLocaleString(numberLocale) + ' ' + String(dashboardI18n.currencyVnd || '');
        }

        function formatFullVND(val) {
            var num = Number(val) || 0;
            return num.toLocaleString(numberLocale) + ' ' + String(dashboardI18n.currencyVnd || '');
        }

        function createSparklineOptions(series, labels) {
            var labelCount = (labels || []).length;
            var tickAmt = undefined;
            if (labelCount > 20) {
                tickAmt = 10;
            } else if (labelCount > 10) {
                tickAmt = 6;
            }

            return {
                series: [
                    {
                        name: String(dashboardI18n.currentPeriod || ''),
                        data: series.current || []
                    },
                    {
                        name: String(dashboardI18n.previousPeriod || ''),
                        data: series.previous || []
                    }
                ],
                chart: {
                    type: 'area',
                    height: 160,
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    sparkline: { enabled: false },
                    parentHeightOffset: 0,
                    animations: {
                        enabled: true,
                        easing: 'easeinout',
                        speed: 350
                    }
                },
                colors: ['#0284c7', '#f97316'],
                stroke: {
                    curve: 'smooth',
                    width: [2.5, 1.8],
                    dashArray: [0, 5]
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.38,
                        opacityTo: 0.03,
                        stops: [0, 90, 100]
                    }
                },
                dataLabels: {
                    enabled: false
                },
                legend: {
                    show: false
                },
                xaxis: {
                    categories: labels || [],
                    tickAmount: tickAmt,
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: {
                        rotate: 0,
                        hideOverlappingLabels: true,
                        style: {
                            colors: '#64748b',
                            fontSize: '11px',
                            fontWeight: 600,
                            fontFamily: 'inherit'
                        },
                        offsetY: -2
                    },
                    tooltip: { enabled: false }
                },
                yaxis: {
                    show: true,
                    labels: {
                        formatter: function (val) {
                            return formatCompactVND(val);
                        },
                        style: {
                            colors: '#94a3b8',
                            fontSize: '10px',
                            fontWeight: 500,
                            fontFamily: 'inherit'
                        },
                        offsetX: -8
                    },
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                grid: {
                    show: true,
                    borderColor: '#f1f5f9',
                    strokeDashArray: 3,
                    padding: {
                        top: 0,
                        right: 12,
                        bottom: 0,
                        left: 10
                    }
                },
                tooltip: {
                    theme: 'light',
                    shared: true,
                    intersect: false,
                    y: {
                        formatter: function (val) {
                            return formatFullVND(val);
                        }
                    },
                    style: {
                        fontSize: '12px',
                        fontFamily: 'inherit'
                    }
                }
            };
        }

        function initSparklineChart($chartEl, currentInstance) {
            if (!$chartEl.length || typeof ApexCharts === 'undefined') {
                return null;
            }

            if (currentInstance) {
                try {
                    currentInstance.destroy();
                } catch (e) {}
            }

            var kpiData = $chartEl.data('revenue-kpi');
            if (typeof kpiData === 'string') {
                try {
                    kpiData = JSON.parse(kpiData);
                } catch (e) {
                    kpiData = null;
                }
            }

            if (!kpiData || !kpiData.series) {
                return null;
            }

            var options = createSparklineOptions(kpiData.series, kpiData.series.labels);
            var chart = new ApexCharts($chartEl[0], options);
            chart.render();
            return chart;
        }

        function initAllCharts() {
            var $dealsChart = $('#sp-revenue-sparkline-chart');
            if ($dealsChart.length && $dealsChart.is(':visible')) {
                dealsChartInstance = initSparklineChart($dealsChart, dealsChartInstance);
            }
            var $estimatesChart = $('#sp-estimate-revenue-sparkline-chart');
            if ($estimatesChart.length && $estimatesChart.is(':visible')) {
                estimatesChartInstance = initSparklineChart($estimatesChart, estimatesChartInstance);
            }
        }

        function initializeDashboardContent() {
            if ($.fn.tooltip) {
                $contentWrapper.find('[data-toggle="tooltip"]').tooltip();
            }
            initAllCharts();
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
            var isEstimatesTab = activeDashboardTab === 'estimates'
                || $(trigger).closest('[data-dashboard-panel]').data('dashboard-panel') === 'estimates';
            var drawerLoadingMessage = (isEstimatesTab && loadingEstimatesMessage) ? loadingEstimatesMessage : loadingMessage;
            setDrawerState('fa-circle-o-notch fa-spin', drawerLoadingMessage, false);
            window.setTimeout(function () {
                $drawerPanel.trigger('focus');
            }, 30);

            activeRequest = $.ajax({
                url: staffUrl + '/' + encodeURIComponent(staffId),
                method: 'GET',
                dataType: 'json',
                data: {
                    dashboard_tab: activeDashboardTab,
                    period: period,
                    period_anchor: selectedPeriodAnchor()
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

        function updateLastUpdatedTimestamp() {
            var now = new Date();
            var hours = String(now.getHours());
            if (hours.length < 2) hours = '0' + hours;
            var minutes = String(now.getMinutes());
            if (minutes.length < 2) minutes = '0' + minutes;
            var day = String(now.getDate());
            if (day.length < 2) day = '0' + day;
            var month = String(now.getMonth() + 1);
            if (month.length < 2) month = '0' + month;
            var onDateLabel = String($dashboard.data('on-date-text') || (window.salesPipelineI18n && window.salesPipelineI18n.onDate) || '');
            $dashboard.find('[data-last-updated-time]').text(hours + ':' + minutes + (onDateLabel ? ' ' + onDateLabel + ' ' : ' ') + day + '/' + month);
        }

        function fetchDashboardData(period, periodAnchor, onComplete) {
            if (!dashboardUrl || !$contentWrapper.length) {
                if (typeof onComplete === 'function') onComplete(false);
                return;
            }

            if (activeDashboardRequest) {
                activeDashboardRequest.abort();
            }

            periodAnchor = periodAnchor || todayIso();
            updateDashboardUrl(period, activeDashboardTab, periodAnchor);
            setDashboardLoading(true);

            var dashboardRequest = $.ajax({
                url: dashboardUrl,
                method: 'GET',
                dataType: 'json',
                data: {
                    period: period,
                    period_anchor: periodAnchor,
                    dashboard_tab: activeDashboardTab
                }
            }).done(function (response) {
                if (response && response.success && response.data && response.data.html) {
                    $contentWrapper.html(response.data.html);
                    initializeDashboardContent();

                    // Sync period-range badge in filterbar with the new server-rendered dates
                    var $periodMetadata = $contentWrapper.find('[data-sp-period-label]');
                    var newLabel = $periodMetadata.attr('data-sp-period-label') || '';
                    var newAnchor = $periodMetadata.attr('data-sp-period-anchor') || periodAnchor;
                    var newAnchorDisplay = $periodMetadata.attr('data-sp-period-anchor-display') || formatIsoDateForProject(newAnchor);
                    var newPeriod = $periodMetadata.attr('data-sp-period-key') || period;
                    if (newLabel) {
                        $dashboard.find('[data-period-badge] .sp-filter-period-badge__text').text(newLabel);
                    }
                    $dashboard.attr('data-period-anchor', newAnchor);
                    $periodAnchorInput.val(newAnchor);
                    $periodAnchorDisplay.val(newAnchorDisplay).get(0).setCustomValidity('');
                    updateDashboardUrl(newPeriod, activeDashboardTab, newAnchor);
                    $('#sp-dashboard-time-filter').val(newPeriod);
                    if ($.fn.selectpicker) {
                        $('#sp-dashboard-time-filter').selectpicker('refresh');
                    }
                    $dashboard.find('[data-history-period]').each(function () {
                        var isActive = String($(this).data('history-period')) === newPeriod;
                        $(this).toggleClass('is-active', isActive).attr('aria-pressed', isActive ? 'true' : 'false');
                    });
                    updateLastUpdatedTimestamp();
                    if (typeof onComplete === 'function') onComplete(true);
                    return;
                }

                var responseMessage = response && response.message ? response.message : errorMessage;
                if (typeof sp_alert === 'function') {
                    sp_alert('danger', responseMessage);
                }
                if (typeof onComplete === 'function') onComplete(false);
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
                if (typeof onComplete === 'function') onComplete(false);
            }).always(function () {
                if (activeDashboardRequest === dashboardRequest) {
                    activeDashboardRequest = null;
                    setDashboardLoading(false);
                }
            });

            activeDashboardRequest = dashboardRequest;
        }

        $dashboard.on('change', '#sp-dashboard-time-filter', function () {
            var period = $(this).val() || 'this_month';
            fetchDashboardData(period, selectedPeriodAnchor());
        });

        $dashboard.on('click', '.js-sp-dashboard-refresh', function (event) {
            event.preventDefault();
            var $btn = $(this);
            var $icon = $btn.find('.fa-refresh');
            $icon.addClass('fa-spin');
            $btn.prop('disabled', true);

            var period = $('#sp-dashboard-time-filter').val() || 'this_month';
            fetchDashboardData(period, selectedPeriodAnchor(), function () {
                $icon.removeClass('fa-spin');
                $btn.prop('disabled', false);
            });
        });

        function closeHistoryPicker(returnFocus) {
            if ($historyPicker.prop('hidden')) {
                return;
            }
            $periodAnchorDisplay.trigger('close.xdsoft');
            $historyPicker.prop('hidden', true);
            $historyToggle.attr('aria-expanded', 'false');
            if (returnFocus) {
                $historyToggle.trigger('focus');
            }
        }

        $dashboard.on('click', '.js-sp-history-picker-toggle', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var willOpen = $historyPicker.prop('hidden');
            $historyPicker.prop('hidden', !willOpen);
            $historyToggle.attr('aria-expanded', willOpen ? 'true' : 'false');
            if (willOpen) {
                window.setTimeout(function () {
                    $historyPicker.find('[data-history-period].is-active').trigger('focus');
                }, 0);
            }
        });

        $dashboard.on('click', '.js-sp-history-picker-close', function () {
            closeHistoryPicker(true);
        });

        $dashboard.on('click', '[data-history-period]', function () {
            var period = String($(this).data('history-period') || 'this_month');
            $dashboard.find('[data-history-period]')
                .removeClass('is-active')
                .attr('aria-pressed', 'false');
            $(this).addClass('is-active').attr('aria-pressed', 'true');
            $('#sp-dashboard-time-filter').val(period);
            if ($.fn.selectpicker) {
                $('#sp-dashboard-time-filter').selectpicker('refresh');
            }
        });

        $dashboard.on('click', '.js-sp-history-picker-apply', function () {
            var period = $('#sp-dashboard-time-filter').val() || 'this_month';
            var anchor = selectedPeriodAnchor();
            var invalidDateMessage = String($dashboard.data('invalid-date-message') || (window.salesPipelineI18n && window.salesPipelineI18n.invalidDate) || '');
            $periodAnchorDisplay.get(0).setCustomValidity(anchor ? '' : invalidDateMessage);
            if (!$periodAnchorDisplay[0].checkValidity()) {
                $periodAnchorDisplay[0].reportValidity();
                return;
            }
            closeHistoryPicker(false);
            fetchDashboardData(period, anchor);
        });

        $dashboard.on('click', '.js-sp-history-picker-current', function () {
            var period = $('#sp-dashboard-time-filter').val() || 'this_month';
            var anchor = todayIso();
            $periodAnchorInput.val(anchor);
            $periodAnchorDisplay.val(formatIsoDateForProject(anchor)).get(0).setCustomValidity('');
            closeHistoryPicker(false);
            fetchDashboardData(period, anchor);
        });

        $(document).on('click.salesPipelineHistoryPicker', function (event) {
            if (!$historyPicker.prop('hidden')
                && !$(event.target).closest('[data-history-picker], .js-sp-history-picker-toggle, .xdsoft_datetimepicker').length) {
                closeHistoryPicker(false);
            }
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

        $(document).on('keydown.salesPipelineDashboard', function (event) {
            if (event.key === 'Escape') {
                if (!$historyPicker.prop('hidden')) {
                    closeHistoryPicker(true);
                } else {
                    closeDrawer();
                }
            }
        });

        if ($.fn.tooltip) {
            $dashboard.find('[data-toggle="tooltip"]').tooltip();
        }
        if (typeof init_datepicker === 'function' && !$periodAnchorDisplay.data('xdsoft_datetimepicker')) {
            init_datepicker($periodAnchorDisplay);
        }
        updateLastUpdatedTimestamp();
        initializeDashboardContent();
    });
})(jQuery);
