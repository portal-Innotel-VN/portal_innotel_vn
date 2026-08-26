/**
 * Sales Pipeline - Estimate Revision Intent Selector & Smart Prompt
 */
(function ($) {
    'use strict';

    var i18n = $.extend({
        intentLabel: 'Mục đích Báo giá',
        intentStandalone: 'Báo giá mới độc lập',
        intentStandaloneHelp: 'Khởi tạo một chuỗi thương vụ / giao dịch hoàn toàn mới cho khách hàng này.',
        intentRevision: 'Bản điều chỉnh / Thay thế',
        intentRevisionHelp: 'Sửa đổi số lượng, cấu hình, giá hoặc báo lại cho Báo giá đã hết hạn trước đó.',
        selectSource: 'Chọn một Báo giá để điều chỉnh hoặc thay thế',
        sourceEstimate: 'Báo giá',
        sourceTotal: 'Tổng tiền',
        sourceStatus: 'Trạng thái',
        sourceExpiry: 'Hạn Báo giá',
        sourceRevision: 'Phiên bản',
        selectCustomerFirst: 'Vui lòng chọn Khách hàng ở ô phía trên trước để tải danh sách Báo giá.',
        noSources: 'Khách hàng này chưa có Báo giá nào trước đó để liên kết.',
        loadingSources: 'Đang tải danh sách Báo giá...',
        acceptedWarning: 'Báo giá này đã được chấp nhận. Để tạo bản điều chỉnh sau chấp nhận, bạn cần nhập lý do xác nhận.',
        overrideReasonLabel: 'Lý do điều chỉnh sau chấp nhận (Bắt buộc):',
        overrideReasonPlaceholder: 'Nhập lý do điều chỉnh kỹ thuật / bổ sung phụ lục...',
        smartPromptTitle: 'Gợi ý Báo giá Thông minh',
        smartPromptIntro: 'Có một Báo giá gần đây phù hợp với khách hàng này:',
        smartPromptEstimateNumber: 'Số Báo giá',
        smartPromptCustomer: 'Khách hàng',
        smartPromptTotal: 'Tổng tiền',
        smartPromptStatus: 'Trạng thái',
        smartPromptExpiry: 'Hạn Báo giá',
        smartPromptQuestion: 'Bạn có đang tạo bản điều chỉnh cho Báo giá này không?',
        smartPromptApply: 'Chọn làm bản điều chỉnh',
        smartPromptDismiss: 'Bỏ qua',
        sourcesUrl: (typeof admin_url !== 'undefined' ? admin_url : '') + 'sales_pipeline/estimate_revision_sources',
        candidatesUrl: (typeof admin_url !== 'undefined' ? admin_url : '') + 'sales_pipeline/estimate_revision_candidates'
    }, window.salesPipelineEstimateRevisionI18n || {});

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

                '<!-- Smart Prompt Banner (Bước 5) -->',
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
                    '<label for="sp_revision_source" class="sp-source-label">' + escapeHtml(i18n.selectSource) + '</label>',
                    '<select id="sp_revision_source" name="sales_pipeline[revision_of_estimate_id]" class="sp-source-select">',
                        '<option value="">' + escapeHtml(i18n.selectSource) + '</option>',
                    '</select>',
                    '<div class="sp-source-empty-hint" id="sp-source-empty" style="display: none;">' + escapeHtml(i18n.noSources) + '</div>',
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

        // Click or focus on source select when empty
        $('#sp_revision_source').on('focus click', function () {
            var currentClient = $('#clientid').val();
            if (currentClient && state.sources.length === 0) {
                fetchSources(currentClient);
            } else if (!currentClient) {
                $('#sp-source-empty').text(i18n.selectCustomerFirst).show();
            }
        });

        // Source dropdown change
        $('#sp_revision_source').on('change', function () {
            var selectedId = $(this).val();
            state.selectedSourceId = selectedId;

            if (!selectedId) {
                $('#sp-override-container').hide();
                $('#sp_override_accepted').val('0');
                return;
            }

            var $selectedOption = $(this).find('option:selected');
            var isAccepted = $selectedOption.data('accepted') === 1 || $selectedOption.data('accepted') === '1';

            if (isAccepted) {
                $('#sp-override-container').slideDown(150);
                $('#sp_override_accepted').val('1');
            } else {
                $('#sp-override-container').slideUp(150);
                $('#sp_override_accepted').val('0');
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

        var $select = $('#sp_revision_source');
        $select.html('<option value="">' + escapeHtml(i18n.loadingSources) + '</option>').prop('disabled', true);
        $('#sp-source-empty').hide();

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
                    $select.prop('disabled', false).empty();
                    $select.append('<option value="">' + escapeHtml(i18n.selectSource) + '</option>');

                    if (res && res.data && res.data.sources && res.data.sources.length > 0) {
                        state.sources = res.data.sources;
                        state.isManager = res.data.is_manager || false;

                        $.each(res.data.sources, function (i, item) {
                            var textParts = [i18n.sourceEstimate + ' ' + item.estimate_number];
                            if (item.total_formatted) {
                                textParts.push(i18n.sourceTotal + ': ' + item.total_formatted);
                            }
                            if (item.status_label) {
                                textParts.push(i18n.sourceStatus + ': ' + item.status_label);
                            }
                            if (item.expirydate) {
                                textParts.push(i18n.sourceExpiry + ': ' + item.expirydate);
                            }
                            if (item.revision_no > 1) {
                                textParts.push(i18n.sourceRevision + ': ' + item.revision_no);
                            }
                            var text = textParts.join(' · ');

                            var $opt = $('<option></option>')
                                .val(item.estimate_id)
                                .text(text)
                                .attr('data-accepted', item.is_accepted ? '1' : '0');

                            $select.append($opt);
                        });

                        if (state.selectedSourceId) {
                            $select.val(state.selectedSourceId).trigger('change');
                        }

                        $('#sp-source-empty').hide();
                    } else {
                        state.sources = [];
                        $('#sp-source-empty').show();
                    }
                },
                error: function (xhr, status) {
                    if (status === 'abort') {
                        return;
                    }
                    if (String(clientId) !== String(state.clientId || $('#clientid').val())) {
                        return;
                    }
                    $select.prop('disabled', false).empty();
                    $select.append('<option value="">' + escapeHtml(i18n.selectSource) + '</option>');
                    $('#sp-source-empty').show();
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
        $('#sp_revision_source').empty().append('<option value="">' + escapeHtml(i18n.selectSource) + '</option>');
        $('#sp-override-container').hide();
        $('#sp_override_accepted').val('0');
        $('#sp-source-empty').hide();
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
