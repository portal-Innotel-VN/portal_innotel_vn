/**
 * Sales Pipeline - Estimate Revision Intent Selector & Smart Prompt
 */
(function ($) {
    'use strict';

    var i18n = window.salesPipelineEstimateRevisionI18n || {};

    var state = {
        clientId: null,
        projectId: null,
        sources: [],
        candidates: [],
        topCandidate: null,
        isManager: false,
        selectedSourceId: null
    };

    var currentSourcesXhr = null;
    var currentCandidatesXhr = null;
    var candidatesDebounceTimer = null;
    var sourcesDebounceTimer = null;

    function init() {
        if ($('#sp-estimate-intent-panel').length > 0) {
            return;
        }

        // Only run on estimate creation form
        var $form = $('#estimate-form');
        if ($form.length === 0 || $('input[name="isedit"]').length > 0) {
            return;
        }

        var $targetContainer = $('.f_client_id').first();
        if ($targetContainer.length === 0) {
            $targetContainer = $('select#clientid').closest('.form-group');
        }
        if ($targetContainer.length === 0) {
            $targetContainer = $('.accounting-template.estimate .col-md-6').first();
        }
        if ($targetContainer.length === 0) {
            $targetContainer = $('.accounting-template.estimate .panel-body').first();
        }
        if ($targetContainer.length === 0) {
            $targetContainer = $form.first();
        }

        renderPanel($targetContainer);
        bindEvents();

        // Check if customer is already selected on page load (e.g. from customer profile URL)
        var initialClientId = $('#clientid').val();
        if (initialClientId) {
            state.clientId = initialClientId;
            fetchSources(initialClientId);
            fetchCandidates(initialClientId, $('#project_id').val());
        }
    }

    function renderPanel($target) {
        if ($('#sp-estimate-intent-panel').length > 0) {
            return;
        }

        var html = [
            '<div class="sp-estimate-intent-panel" id="sp-estimate-intent-panel">',
                '<div class="sp-intent-header">',
                    '<i class="fa fa-question-circle" aria-hidden="true"></i>',
                    '<span>' + escapeHtml(i18n.intentLabel) + '</span>',
                '</div>',

                '<div class="sp-intent-options-grid">',
                    '<label class="sp-intent-card active" id="sp-card-standalone">',
                        '<input type="radio" name="sales_pipeline[intent]" value="standalone" checked="checked">',
                        '<div class="sp-intent-card-content">',
                            '<div class="sp-intent-title"><i class="fa fa-file-o" aria-hidden="true"></i>' + escapeHtml(i18n.intentStandalone) + '</div>',
                            '<div class="sp-intent-desc">' + escapeHtml(i18n.intentStandaloneHelp) + '</div>',
                        '</div>',
                    '</label>',
                    '<label class="sp-intent-card" id="sp-card-revision">',
                        '<input type="radio" name="sales_pipeline[intent]" value="revision">',
                        '<div class="sp-intent-card-content">',
                            '<div class="sp-intent-title"><i class="fa fa-refresh" aria-hidden="true"></i>' + escapeHtml(i18n.intentRevision) + '</div>',
                            '<div class="sp-intent-desc">' + escapeHtml(i18n.intentRevisionHelp) + '</div>',
                        '</div>',
                    '</label>',
                '</div>',

                '<!-- Smart Prompt Banner -->',
                '<div class="sp-smart-prompt-banner" id="sp-smart-prompt-banner" style="display: none;">',
                    '<div class="sp-prompt-icon"><i class="fa fa-lightbulb-o"></i></div>',
                    '<div class="sp-prompt-content">',
                        '<div class="sp-prompt-title">' + escapeHtml(i18n.smartPromptTitle) + '</div>',
                        '<div class="sp-prompt-text" id="sp-prompt-message"></div>',
                    '</div>',
                    '<div class="sp-prompt-actions">',
                        '<button type="button" class="btn btn-info btn-xs sp-prompt-apply-btn" id="sp-btn-apply-prompt">',
                            '<i class="fa fa-check"></i> ' + escapeHtml(i18n.smartPromptApply),
                        '</button>',
                        '<button type="button" class="btn btn-default btn-xs sp-prompt-dismiss-btn" id="sp-btn-dismiss-prompt">',
                            '<i class="fa fa-times" aria-hidden="true"></i> ',
                            escapeHtml(i18n.smartPromptDismiss),
                        '</button>',
                    '</div>',
                '</div>',

                '<div class="sp-source-selection-container" id="sp-source-container" style="display: none;">',
                    '<label class="sp-source-label">' + escapeHtml(i18n.selectSource) + '</label>',

                    /* Custom dropdown wrapper */
                    '<div class="sp-custom-select-wrapper" id="sp-custom-select-wrapper">',
                        /* Hidden native select for form submission */
                        '<select id="sp_revision_source" name="sales_pipeline[revision_of_estimate_id]" class="sp-source-select" aria-hidden="true" tabindex="-1">',
                            '<option value=""></option>',
                        '</select>',

                        /* Visible trigger button */
                        '<button type="button" class="sp-custom-select-trigger" id="sp-select-trigger" aria-haspopup="listbox" aria-expanded="false" aria-controls="sp-select-panel">',
                            '<span class="sp-trigger-value is-placeholder" id="sp-trigger-value">' + escapeHtml(i18n.selectSource) + '</span>',
                            '<i class="fa fa-chevron-down sp-trigger-chevron" aria-hidden="true"></i>',
                        '</button>',

                        /* Dropdown panel */
                        '<div class="sp-custom-select-panel" id="sp-select-panel" role="listbox">',
                            '<div class="sp-select-search-wrap">',
                                '<div class="sp-select-search-input">',
                                    '<i class="fa fa-search" aria-hidden="true"></i>',
                                    '<input type="text" id="sp-select-search" autocomplete="off" placeholder="' + escapeHtml(i18n.searchPlaceholder) + '">',
                                '</div>',
                            '</div>',
                            '<div class="sp-select-listbox" id="sp-select-listbox" role="presentation">',
                                '<div class="sp-select-panel-msg">' + escapeHtml(i18n.loadingSources) + '</div>',
                            '</div>',
                        '</div>',
                    '</div>',

                    '<div class="sp-source-empty-hint" id="sp-source-empty" style="display: none;"></div>',
                    '<div class="sp-override-reason-container" id="sp-override-container" style="display: none;">',
                        '<div class="sp-override-warning">',
                            '<i class="fa fa-exclamation-triangle"></i>',
                            '<span>' + escapeHtml(i18n.acceptedWarning) + '</span>',
                        '</div>',
                        '<label for="sp_override_reason" class="sp-source-label">' + escapeHtml(i18n.overrideReasonLabel) + '</label>',
                        '<textarea id="sp_override_reason" name="sales_pipeline[override_reason]" class="sp-override-textarea" placeholder="' + escapeHtml(i18n.overrideReasonPlaceholder) + '"></textarea>',
                        '<input type="hidden" id="sp_override_accepted" name="sales_pipeline[override_accepted]" value="0">',
                    '</div>',
                '</div>',
            '</div>'
        ].join('');

        $target.after(html);
    }

    function bindEvents() {
        // Intent Radio card toggle
        $('input[name="sales_pipeline[intent]"]').on('change', function () {
            var val = $(this).val();
            $('.sp-intent-card').removeClass('active');
            $(this).closest('.sp-intent-card').addClass('active');

            if (val === 'revision') {
                $('#sp-smart-prompt-banner').slideUp(150);
                $('#sp-source-container').slideDown(150);
                var currentClient = $('#clientid').val();
                if (currentClient) {
                    fetchSources(currentClient);
                } else {
                    $('#sp-source-empty').text(i18n.selectCustomerFirst).show();
                }
            } else {
                $('#sp-source-container').slideUp(150);
                $('#sp_revision_source').val('');
                $('#sp_override_container').hide();
                $('#sp_override_accepted').val('0');
            }
        });

        // Listen for client change on Perfex form (native and bootstrap-select)
        $(document).on('change changed.bs.select', '#clientid, select[name="clientid"]', function () {
            var newClientId = $(this).val();
            state.clientId = newClientId;
            resetSourceDropdown();
            $('#sp-smart-prompt-banner').hide();

            if (newClientId) {
                fetchSources(newClientId);
                if ($('input[name="sales_pipeline[intent]"]:checked').val() === 'standalone') {
                    fetchCandidates(newClientId, $('#project_id').val());
                }
            } else {
                if (currentSourcesXhr && currentSourcesXhr.readyState !== 4) {
                    currentSourcesXhr.abort();
                }
                if (currentCandidatesXhr && currentCandidatesXhr.readyState !== 4) {
                    currentCandidatesXhr.abort();
                }
                if ($('input[name="sales_pipeline[intent]"]:checked').val() === 'revision') {
                    $('#sp-source-empty').text(i18n.selectCustomerFirst).show();
                }
            }
        });

        // Listen for project change
        $(document).on('change changed.bs.select', '#project_id, select[name="project_id"]', function () {
            var newProjectId = $(this).val();
            state.projectId = newProjectId;
            if (state.clientId && $('input[name="sales_pipeline[intent]"]:checked').val() === 'standalone') {
                fetchCandidates(state.clientId, newProjectId);
            }
        });

        // ── Custom dropdown: open/close trigger ──────────────────────
        $(document).on('click', '#sp-select-trigger', function (e) {
            e.stopPropagation();
            var isOpen = $(this).hasClass('is-open');
            closeCustomSelect();
            if (!isOpen) {
                openCustomSelect();
            }
        });

        // Keyboard navigation on trigger
        $(document).on('keydown', '#sp-select-trigger', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                e.preventDefault();
                openCustomSelect();
            } else if (e.key === 'Escape') {
                closeCustomSelect();
            }
        });

        // Search filter
        $(document).on('input', '#sp-select-search', function () {
            var q = $(this).val().toLowerCase().trim();
            filterCustomOptions(q);
        });

        // Keyboard navigation in listbox
        $(document).on('keydown', '#sp-select-search', function (e) {
            var $focused = $('#sp-select-listbox .sp-select-option.is-focused');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                var $next = $focused.length ? $focused.nextAll('.sp-select-option:not([data-hidden="true"]):first') : $('#sp-select-listbox .sp-select-option:not([data-hidden="true"]):first');
                if ($next.length) { $focused.removeClass('is-focused'); $next.addClass('is-focused'); $next[0].scrollIntoView({ block: 'nearest' }); }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                var $prev = $focused.prevAll('.sp-select-option:not([data-hidden="true"]):first');
                if ($prev.length) { $focused.removeClass('is-focused'); $prev.addClass('is-focused'); $prev[0].scrollIntoView({ block: 'nearest' }); }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if ($focused.length && !$focused.hasClass('is-placeholder')) {
                    selectCustomOption($focused.data('value'), $focused.data('accepted'), $focused.find('.sp-option-number').clone().find('.sp-option-status').remove().end().text().trim());
                    closeCustomSelect();
                }
            } else if (e.key === 'Escape') {
                closeCustomSelect();
                $('#sp-select-trigger').focus();
            }
        });

        // Click on option in listbox
        $(document).on('click', '#sp-select-listbox .sp-select-option', function () {
            var val      = $(this).data('value');
            var accepted = $(this).data('accepted');
            var label    = $(this).find('.sp-option-number').clone().find('.sp-option-status').remove().end().text().trim();
            if ($(this).hasClass('is-placeholder')) {
                selectCustomOption('', 0, '');
            } else {
                selectCustomOption(val, accepted, label);
            }
            closeCustomSelect();
            $('#sp-select-trigger').focus();
        });

        // Close on outside click
        $(document).on('click.sp-custom-select', function (e) {
            if (!$(e.target).closest('#sp-custom-select-wrapper').length) {
                closeCustomSelect();
            }
        });

        // Smart Prompt Apply Button
        $(document).on('click', '#sp-btn-apply-prompt', function () {
            if (!state.topCandidate) {
                return;
            }
            // Switch to revision mode
            $('input[name="sales_pipeline[intent]"][value="revision"]').prop('checked', true).trigger('change');
            // Auto select candidate
            setTimeout(function () {
                $('#sp_revision_source').val(state.topCandidate.estimate_id).trigger('change');
            }, 100);
            $('#sp-smart-prompt-banner').slideUp(150);
        });

        // Smart Prompt Dismiss Button
        $(document).on('click', '#sp-btn-dismiss-prompt', function () {
            $('#sp-smart-prompt-banner').slideUp(150);
        });
    }

    function fetchSources(clientId) {
        if (sourcesDebounceTimer) {
            clearTimeout(sourcesDebounceTimer);
            sourcesDebounceTimer = null;
        }

        if (currentSourcesXhr && currentSourcesXhr.readyState !== 4) {
            currentSourcesXhr.abort();
            currentSourcesXhr = null;
        }

        if (!clientId) {
            resetSourceDropdown();
            return;
        }

        // Show loading state on custom trigger
        setCustomSelectLoading(true);
        $('#sp-source-empty').hide();
        $('#sp-select-listbox').html('<div class="sp-select-panel-msg"><i class="fa fa-circle-o-notch fa-spin"></i> ' + escapeHtml(i18n.loadingSources) + '</div>');

        sourcesDebounceTimer = setTimeout(function () {
            currentSourcesXhr = $.ajax({
                url: i18n.sourcesUrl,
                type: 'GET',
                dataType: 'json',
                data: { client_id: clientId },
                success: function (res) {
                    if (String(clientId) !== String(state.clientId || $('#clientid').val())) {
                        return;
                    }
                    setCustomSelectLoading(false);

                    // Reset native select
                    var $nativeSelect = $('#sp_revision_source');
                    $nativeSelect.empty().append('<option value=""></option>');

                    if (res && res.data && res.data.sources && res.data.sources.length > 0) {
                        state.sources = res.data.sources;
                        state.isManager = res.data.is_manager || false;

                        var listHtml = '';
                        $.each(res.data.sources, function (i, item) {
                            var statusClass = getStatusClass(item.status, item.is_accepted);

                            // Populate native select for form submission
                            $nativeSelect.append(
                                $('<option></option>')
                                    .val(item.estimate_id)
                                    .attr('data-accepted', item.is_accepted ? '1' : '0')
                            );

                            // Build custom option HTML
                            var metaHtml = '';
                            if (item.total_formatted) {
                                metaHtml += '<span class="sp-option-meta-item"><i class="fa fa-money" aria-hidden="true"></i>' + escapeHtml(item.total_formatted) + '</span>';
                            }
                            // Option B: Display both Created Date and Expiry Date
                            var dateParts = [];
                            if (item.date) {
                                dateParts.push(escapeHtml(i18n.sourceDateShort) + ': ' + escapeHtml(item.date));
                            }
                            if (item.expirydate) {
                                dateParts.push(escapeHtml(i18n.sourceExpiryShort) + ': ' + escapeHtml(item.expirydate));
                            }
                            if (dateParts.length > 0) {
                                metaHtml += '<span class="sp-option-meta-item"><i class="fa fa-calendar" aria-hidden="true"></i>' + dateParts.join(' • ') + '</span>';
                            }

                            if (item.revision_no > 1) {
                                metaHtml += '<span class="sp-option-meta-item"><i class="fa fa-code-fork" aria-hidden="true"></i>v' + item.revision_no + '</span>';
                            }

                            listHtml += '<div class="sp-select-option"'
                                + ' role="option"'
                                + ' data-value="' + escapeHtml(String(item.estimate_id)) + '"'
                                + ' data-accepted="' + (item.is_accepted ? '1' : '0') + '"'
                                + ' data-search="' + escapeHtml((item.estimate_number + ' ' + (item.status_label || '') + (item.date ? ' ' + item.date : '') + (item.expirydate ? ' ' + item.expirydate : '')).toLowerCase()) + '"'
                                + ' tabindex="-1">'
                                + '<span class="sp-option-icon"><i class="fa fa-file-text-o" aria-hidden="true"></i></span>'
                                + '<span class="sp-option-body">'
                                    + '<span class="sp-option-number">'
                                        + escapeHtml(item.estimate_number)
                                        + '<span class="sp-option-status sp-option-status--' + statusClass + '">' + escapeHtml(item.status_label || '') + '</span>'
                                    + '</span>'
                                    + (metaHtml ? '<span class="sp-option-meta">' + metaHtml + '</span>' : '')
                                + '</span>'
                                + '</div>';
                        });

                        $('#sp-select-listbox').html(listHtml);
                        $('#sp-source-empty').hide();

                        if (state.selectedSourceId) {
                            var $opt = $('#sp-select-listbox .sp-select-option[data-value="' + state.selectedSourceId + '"]');
                            if ($opt.length) {
                                $opt.addClass('is-selected');
                                selectCustomOption(state.selectedSourceId, $opt.data('accepted'), $opt.find('.sp-option-number').clone().find('.sp-option-status').remove().end().text().trim());
                            }
                        }
                    } else {
                        state.sources = [];
                        $('#sp-select-listbox').html('<div class="sp-select-panel-msg">' + escapeHtml(i18n.noSources) + '</div>');
                        $('#sp-source-empty').hide();
                    }
                },
                error: function (xhr, status) {
                    if (status === 'abort') { return; }
                    if (String(clientId) !== String(state.clientId || $('#clientid').val())) { return; }
                    setCustomSelectLoading(false);
                    $('#sp-select-listbox').html('<div class="sp-select-panel-msg">' + escapeHtml(i18n.noSources) + '</div>');
                },
                complete: function () {
                    currentSourcesXhr = null;
                }
            });
        }, 150);
    }

    function fetchCandidates(clientId, projectId) {
        if (candidatesDebounceTimer) {
            clearTimeout(candidatesDebounceTimer);
            candidatesDebounceTimer = null;
        }

        if (currentCandidatesXhr && currentCandidatesXhr.readyState !== 4) {
            currentCandidatesXhr.abort();
            currentCandidatesXhr = null;
        }

        if (!clientId) {
            $('#sp-smart-prompt-banner').hide();
            state.candidates = [];
            state.topCandidate = null;
            return;
        }

        candidatesDebounceTimer = setTimeout(function () {
            currentCandidatesXhr = $.ajax({
                url: i18n.candidatesUrl,
                type: 'GET',
                dataType: 'json',
                data: { client_id: clientId, project_id: projectId || '' },
                success: function (res) {
                    if (String(clientId) !== String(state.clientId || $('#clientid').val())) {
                        return;
                    }

                    if (res && res.data && res.data.candidates && res.data.candidates.length > 0) {
                        state.candidates = res.data.candidates;
                        var top = res.data.candidates[0];

                        // Only prompt if top candidate has significant relevance score (>= 40)
                        if (top && top.score >= 40 && $('input[name="sales_pipeline[intent]"]:checked').val() === 'standalone') {
                            state.topCandidate = top;
                            var msg = '<p class="sp-prompt-intro">' + escapeHtml(i18n.smartPromptIntro) + '</p>'
                                + '<dl class="sp-prompt-details">'
                                + promptDetail(i18n.smartPromptEstimateNumber, top.estimate_number)
                                + promptDetail(i18n.smartPromptCustomer, top.customer_name)
                                + promptDetail(i18n.smartPromptTotal, top.total_formatted)
                                + promptDetail(i18n.smartPromptStatus, top.status_label)
                                + promptDetail(i18n.smartPromptDate, top.date)
                                + promptDetail(i18n.smartPromptExpiry, top.expirydate)
                                + '</dl>'
                                + '<p class="sp-prompt-question">' + escapeHtml(i18n.smartPromptQuestion) + '</p>';
                            $('#sp-prompt-message').html(msg);
                            $('#sp-smart-prompt-banner').slideDown(150);
                        } else {
                            state.topCandidate = null;
                            $('#sp-smart-prompt-banner').slideUp(150);
                        }
                    } else {
                        state.candidates = [];
                        state.topCandidate = null;
                        $('#sp-smart-prompt-banner').slideUp(150);
                    }
                },
                error: function (xhr, status) {
                    if (status === 'abort') {
                        return;
                    }
                    if (String(clientId) !== String(state.clientId || $('#clientid').val())) {
                        return;
                    }
                    $('#sp-smart-prompt-banner').hide();
                },
                complete: function () {
                    currentCandidatesXhr = null;
                }
            });
        }, 150);
    }

    function resetSourceDropdown() {
        // Reset native select
        $('#sp_revision_source').empty().append('<option value=""></option>');
        // Reset custom UI
        $('#sp-trigger-value').text(i18n.selectSource).removeClass('is-selected').addClass('is-placeholder');
        $('#sp-select-listbox').html('<div class="sp-select-panel-msg">' + escapeHtml(i18n.loadingSources) + '</div>');
        $('#sp-select-search').val('');
        state.selectedSourceId = null;
        closeCustomSelect();
        setCustomSelectLoading(false);
        // Reset override
        $('#sp-override-container').hide();
        $('#sp_override_accepted').val('0');
        $('#sp-source-empty').hide();
    }

    // ── Custom dropdown helpers ───────────────────────────────────────
    function openCustomSelect() {
        var $trigger = $('#sp-select-trigger');
        var $panel   = $('#sp-select-panel');
        if ($trigger.hasClass('is-loading')) { return; }
        $trigger.addClass('is-open').attr('aria-expanded', 'true');
        $panel.addClass('is-open');
        // Focus search after opening
        setTimeout(function () { $('#sp-select-search').focus(); }, 50);
    }

    function closeCustomSelect() {
        $('#sp-select-trigger').removeClass('is-open').attr('aria-expanded', 'false');
        $('#sp-select-panel').removeClass('is-open');
        $('#sp-select-listbox .sp-select-option').removeClass('is-focused');
        $('#sp-select-search').val('');
        filterCustomOptions('');
    }

    function selectCustomOption(val, accepted, label) {
        state.selectedSourceId = val || null;
        // Sync native select
        $('#sp_revision_source').val(val || '');
        // Update trigger label
        var $triggerVal = $('#sp-trigger-value');
        if (val) {
            $triggerVal.text(label).removeClass('is-placeholder').addClass('is-selected');
        } else {
            $triggerVal.text(i18n.selectSource).removeClass('is-selected').addClass('is-placeholder');
        }
        // Highlight selected item in listbox
        $('#sp-select-listbox .sp-select-option').removeClass('is-selected');
        if (val) {
            $('#sp-select-listbox .sp-select-option[data-value="' + val + '"]').addClass('is-selected');
        }
        // Handle override reason
        var isAccepted = parseInt(accepted, 10) === 1;
        if (val && isAccepted) {
            $('#sp-override-container').slideDown(150);
            $('#sp_override_accepted').val('1');
        } else {
            $('#sp-override-container').slideUp(150);
            $('#sp_override_accepted').val('0');
        }
    }

    function setCustomSelectLoading(loading) {
        var $trigger = $('#sp-select-trigger');
        if (loading) {
            $trigger.addClass('is-loading');
        } else {
            $trigger.removeClass('is-loading');
        }
    }

    function filterCustomOptions(q) {
        $('#sp-select-listbox .sp-select-option').each(function () {
            var searchData = $(this).data('search') || '';
            var match = !q || searchData.indexOf(q) !== -1;
            $(this).attr('data-hidden', match ? null : 'true').toggle(match);
        });
        var visibleCount = $('#sp-select-listbox .sp-select-option:visible').length;
        var $noResult = $('#sp-select-listbox .sp-select-no-result');
        if (!visibleCount && q) {
            if (!$noResult.length) {
                $('#sp-select-listbox').append('<div class="sp-select-panel-msg sp-select-no-result">' + escapeHtml(i18n.noSearchResults) + '</div>');
            }
        } else {
            $noResult.remove();
        }
    }

    function getStatusClass(status, isAccepted) {
        if (isAccepted) { return 'accepted'; }
        var statusClasses = {
            1: 'draft',
            2: 'sent',
            3: 'declined',
            4: 'accepted',
            5: 'expired'
        };
        if (Object.prototype.hasOwnProperty.call(statusClasses, status)) {
            return statusClasses[status];
        }
        return 'default';
    }

    function promptDetail(label, value) {
        if (!value) {
            return '';
        }
        return '<div class="sp-prompt-detail"><dt>' + escapeHtml(label) + '</dt><dd>' + escapeHtml(value) + '</dd></div>';
    }

    function escapeHtml(string) {
        var entityMap = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        };
        return String(string).replace(/[&<>"']/g, function (s) {
            return entityMap[s];
        });
    }

    if (document.readyState === 'loading') {
        $(document).ready(init);
    } else {
        init();
    }

})(jQuery);
