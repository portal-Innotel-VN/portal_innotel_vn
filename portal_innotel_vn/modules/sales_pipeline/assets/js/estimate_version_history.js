/**
 * Sales Pipeline - Estimate Version History & Manual Link / Unlink (Bước 6)
 */
function get_estimate_version_history_id() {
    'use strict';

    var hiddenId = parseInt($('input[name="_attachment_sale_id"]').first().val(), 10);
    if (hiddenId > 0) {
        return hiddenId;
    }

    var configuredId = parseInt(window.salesPipelineEstimateId || 0, 10);
    if (configuredId > 0) {
        return configuredId;
    }

    var pathMatch = window.location.pathname.match(/\/list_estimates\/(\d+)/);
    if (pathMatch && parseInt(pathMatch[1], 10) > 0) {
        return parseInt(pathMatch[1], 10);
    }

    var hashMatch = window.location.hash.match(/^#(\d+)$/);
    return hashMatch ? parseInt(hashMatch[1], 10) : 0;
}

function version_history_text(key) {
    'use strict';
    var i18n = window.salesPipelineVersionHistoryI18n || {};
    return i18n[key] || key;
}

function version_history_reason_text(reason) {
    'use strict';
    var raw = $.trim(String(reason || ''));
    if (!raw) {
        return '';
    }
    var normalized = raw.toLowerCase();
    var key = 'reason_' + normalized;
    var translated = version_history_text(key);
    if (translated && translated !== key) {
        return translated;
    }
    if (normalized === 'standalone') {
        return version_history_text('reasonStandalone');
    }
    return raw;
}

function formatDisplayDate(dateStr) {
    'use strict';
    if (!dateStr) {
        return '';
    }
    var str = $.trim(String(dateStr));
    var firstPart = str.split(' ')[0];
    var parts = firstPart.split('-');
    if (parts.length === 3 && parts[0].length === 4) {
        return parts[2] + '/' + parts[1] + '/' + parts[0];
    }
    return str;
}

function formatDisplayDateTime(dateTimeStr) {
    'use strict';
    if (!dateTimeStr) {
        return '';
    }
    var str = $.trim(String(dateTimeStr));
    var spaceParts = str.split(' ');
    var firstPart = spaceParts[0];
    var parts = firstPart.split('-');
    if (parts.length === 3 && parts[0].length === 4) {
        var dateFormatted = parts[2] + '/' + parts[1] + '/' + parts[0];
        if (spaceParts[1]) {
            var timeParts = spaceParts[1].split(':');
            if (timeParts.length >= 2) {
                return dateFormatted + ' ' + timeParts[0] + ':' + timeParts[1];
            }
        }
        return dateFormatted;
    }
    return str;
}

/**
 * Inject the Version History tab into the existing Perfex estimate view.
 * This keeps the integration module-only: no core estimate view override is required.
 */
function inject_estimate_version_history_tab() {
    'use strict';

    var $tabs = $('.preview-tabs-top ul.nav-tabs.nav-tabs-horizontal').first();
    var $tabContent = $('.preview-tabs-top').closest('.panel-body').children('.tab-content').first();
    var estimateId = get_estimate_version_history_id();

    if (!$tabs.length || !$tabContent.length || estimateId <= 0 || $('#tab_version_history').length) {
        return;
    }

    var title = version_history_text('title');
    var loading = version_history_text('loading');
    var $notesTab = $tabs.find('a[href="#tab_notes"]').closest('li');
    var $tab = $('<li role="presentation" class="tab-separator sp-version-history-tab"></li>');
    var $link = $('<a href="#tab_version_history" aria-controls="tab_version_history" role="tab" data-toggle="tab"></a>');
    $link.append($('<i class="fa fa-history" aria-hidden="true"></i>')).append(document.createTextNode(' ' + title));
    $tab.append($link);

    if ($notesTab.length) {
        $notesTab.after($tab);
    } else {
        $tabs.append($tab);
    }

    var $pane = $('<div role="tabpanel" class="tab-pane" id="tab_version_history"></div>');
    var $container = $('<div id="sp-version-history-container"></div>');
    $container.html('<div class="text-center ptop20"><i class="fa fa-spinner fa-spin fa-2x" aria-hidden="true"></i><p class="mtop10 text-muted"></p></div>');
    $container.find('p').text(loading);
    $pane.append($container);
    $tabContent.append($pane);

    $link.on('shown.bs.tab', function () {
        load_estimate_version_history(estimateId);
    });
}

function load_estimate_version_history(estimateId) {
    'use strict';
    var $container = $('#sp-version-history-container');
    if ($container.length === 0) {
        return;
    }

    $container.html('<div class="text-center ptop20"><i class="fa fa-spinner fa-spin fa-2x" aria-hidden="true"></i><p class="mtop10 text-muted">' + escapeHtml(version_history_text('loading')) + '</p></div>');

    var apiUrl = (typeof admin_url !== 'undefined' ? admin_url : '') + 'sales_pipeline/estimate_version_history?estimate_id=' + estimateId;

    $.ajax({
        url: apiUrl,
        type: 'GET',
        dataType: 'json',
        success: function (res) {
            if (!res || !res.status || !res.data) {
                $container.html('<div class="alert alert-warning">' + escapeHtml(version_history_text('loadWarning')) + '</div>');
                return;
            }

            renderVersionHistoryView($container, res.data, estimateId);
        },
        error: function () {
            $container.html('<div class="alert alert-danger">' + escapeHtml(version_history_text('loadError')) + '</div>');
        }
    });
}

function watch_estimate_version_history_mount() {
    'use strict';

    var $estimateRoot = $('#estimate');
    if (!$estimateRoot.length) {
        inject_estimate_version_history_tab();
        return;
    }

    var inject = function () {
        inject_estimate_version_history_tab();
    };

    inject();

    if (window.MutationObserver && !$estimateRoot.data('sp-version-history-observer')) {
        var observer = new MutationObserver(function () {
            if ($estimateRoot.find('.preview-tabs-top').length) {
                inject();
            }
        });
        observer.observe($estimateRoot[0], { childList: true, subtree: true });
        $estimateRoot.data('sp-version-history-observer', observer);
    }

    $(document)
        .off('ajaxComplete.salesPipelineVersionHistory')
        .on('ajaxComplete.salesPipelineVersionHistory', function (event, xhr, settings) {
            var url = settings && settings.url ? settings.url : '';
            if (url.indexOf('estimates/get_estimate_data_ajax/') !== -1
                || url.indexOf('estimates/list_estimates/') !== -1
                || $estimateRoot.find('.preview-tabs-top').length) {
                inject();
            }
        });
}

$(function () {
    watch_estimate_version_history_mount();
});

function renderVersionHistoryView($container, data, activeEstimateId) {
    'use strict';
    var group = data.group;
    var versions = data.versions || [];
    var events = data.events || [];
    var isManager = data.is_manager || false;

    if (!group && versions.length === 0) {
        $container.html('<div class="alert alert-info">' + escapeHtml(version_history_text('empty')) + '</div>');
        return;
    }

    var outcomeLabel = group ? (group.outcome === 'accepted' ? '<span class="label label-success">' + escapeHtml(version_history_text('statusAccepted')) + '</span>' : (group.outcome === 'declined' ? '<span class="label label-danger">' + escapeHtml(version_history_text('statusDeclined')) + '</span>' : '<span class="label label-warning">' + escapeHtml(version_history_text('statusPending')) + '</span>')) : '';

    var isStandalone = versions.length === 1 && group && group.outcome === 'pending';
    var latestVersion = versions.length > 0 ? versions[versions.length - 1] : null;
    var canUnlink = isManager && versions.length > 1 && latestVersion && parseInt(latestVersion.estimate_id) === parseInt(activeEstimateId) && !(group.outcome === 'accepted' && parseInt(group.decision_estimate_id) === parseInt(activeEstimateId));

    var html = [];

    // Header Summary Card
    html.push('<div class="sp-vh-header-card">');
    html.push('  <div class="row">');
    html.push('    <div class="col-md-7">');
    html.push('      <h4 class="no-margin font-medium-xs"><i class="fa fa-folder-open text-primary" aria-hidden="true"></i> ' + escapeHtml(version_history_text('groupTitle')) + ' #' + (group ? group.id : '') + '</h4>');
    html.push('      <p class="text-muted mtop5 font-size-12">' + escapeHtml(version_history_text('versionCount')) + ': <strong>' + versions.length + '</strong> &bull; ' + escapeHtml(version_history_text('groupOutcome')) + ': ' + outcomeLabel + '</p>');
    html.push('    </div>');
    html.push('    <div class="col-md-5 text-right">');
    if (isManager && isStandalone) {
        html.push('      <button type="button" class="btn btn-info btn-sm" onclick="openLinkModal(' + activeEstimateId + ');"><i class="fa fa-link" aria-hidden="true"></i> ' + escapeHtml(version_history_text('linkButton')) + '</button>');
    } else if (canUnlink) {
        html.push('      <button type="button" class="btn btn-warning btn-sm" onclick="openUnlinkModal(' + activeEstimateId + ');"><i class="fa fa-unlink" aria-hidden="true"></i> ' + escapeHtml(version_history_text('unlinkButton')) + '</button>');
    }
    html.push('    </div>');
    html.push('  </div>');
    html.push('</div>');

    // Version Tree / List
    html.push('<div class="sp-vh-section mtop20">');
    html.push('  <h5 class="bold text-muted text-uppercase font-size-12"><i class="fa fa-sitemap" aria-hidden="true"></i> ' + escapeHtml(version_history_text('treeTitle')) + '</h5>');
    html.push('  <div class="sp-version-tree">');

    $.each(versions, function (i, v) {
        var isCurrent = parseInt(v.estimate_id) === parseInt(activeEstimateId);
        var cardClass = isCurrent ? 'sp-vtree-card current' : 'sp-vtree-card';
        var methodKey = v.link_method === 'origin' ? 'methodOrigin' : (v.link_method === 'native_copy' ? 'methodNativeCopy' : (v.link_method === 'module_copy' ? 'methodModuleCopy' : (v.link_method === 'declared_revision' || v.link_method === 'revision' ? 'methodRevision' : (v.link_method === 'manual_link' ? 'methodManualLink' : (v.link_method === 'manual_unlink' ? 'methodManualUnlink' : 'methodUnknown')))));
        var linkMethodBadge = '<span class="label label-default">' + escapeHtml(version_history_text(methodKey)) + '</span>';

        html.push('    <div class="' + cardClass + '">');
        html.push('      <div class="sp-vtree-badge">' + escapeHtml(version_history_text('revisionPrefix')) + ' ' + v.revision_no + '</div>');
        html.push('      <div class="sp-vtree-body">');
        html.push('        <div class="sp-vtree-top">');
        html.push('          <a href="' + (typeof admin_url !== 'undefined' ? admin_url : '') + 'estimates/list_estimates/' + v.estimate_id + '" class="bold text-primary">' + v.estimate_number + '</a>');
        html.push('          <span class="sp-vtree-total">' + v.total_formatted + '</span>');
        html.push('          <span class="label label-default">' + v.status_label + '</span>');
        html.push('          ' + linkMethodBadge);
        if (isCurrent) {
            html.push('          <span class="label label-success"><i class="fa fa-check" aria-hidden="true"></i> ' + escapeHtml(version_history_text('current')) + '</span>');
        }
        html.push('        </div>');
        html.push('        <div class="sp-vtree-meta text-muted font-size-12 mtop5">');
            html.push('          <span><i class="fa fa-user" aria-hidden="true"></i> ' + escapeHtml(v.creator_name || version_history_text('notAvailable')) + '</span> &bull; ');
        var displayDate = v.date_formatted || formatDisplayDate(v.date || v.date_linked);
        html.push('          <span><i class="fa fa-calendar"></i> ' + escapeHtml(displayDate) + '</span>');
        if (v.parent_estimate_id) {
            html.push(' &bull; <span>' + escapeHtml(version_history_text('parentLabel')) + ' #' + v.parent_estimate_id + '</span>');
        }
        html.push('        </div>');
        html.push('      </div>');
        html.push('    </div>');
    });

    html.push('  </div>');
    html.push('</div>');

    // Audit Event Timeline
    if (events.length > 0) {
        html.push('<div class="sp-vh-section mtop25">');
        html.push('  <h5 class="bold text-muted text-uppercase font-size-12"><i class="fa fa-history" aria-hidden="true"></i> ' + escapeHtml(version_history_text('auditTitle')) + '</h5>');
        html.push('  <div class="sp-audit-timeline">');

        $.each(events, function (j, ev) {
            var icon = ev.event_type === 'revision_unlinked' ? 'fa-unlink text-warning' : (ev.event_type === 'revision_linked' || ev.event_type === 'accepted_override' ? 'fa-link text-success' : 'fa-info-circle text-primary');
            html.push('    <div class="sp-audit-item">');
            html.push('      <div class="sp-audit-icon"><i class="fa ' + icon + '"></i></div>');
            html.push('      <div class="sp-audit-content">');
            html.push('        <div class="sp-audit-header">');
            var eventKey = {group_created: 'eventGroupCreated', revision_linked: 'eventRevisionLinked', revision_unlinked: 'eventRevisionUnlinked', accepted_override: 'eventAcceptedOverride', revision_link_failed: 'eventRevisionLinkFailed', revision_fallback_standalone: 'eventRevisionFallbackStandalone'}[ev.event_type] || 'eventUnknown';
            html.push('          <strong>' + escapeHtml(version_history_text(eventKey)) + '</strong>');
            var displayEventDate = ev.datecreated_formatted || formatDisplayDateTime(ev.datecreated);
            html.push('          <span class="text-muted font-size-11 pull-right">' + escapeHtml(displayEventDate) + '</span>');
            html.push('        </div>');
            if (ev.actor_name) {
                html.push('        <div class="sp-audit-actor text-muted font-size-12">' + escapeHtml(version_history_text('actorLabel')) + ': ' + escapeHtml(ev.actor_name) + '</div>');
            }
            if (ev.reason) {
                html.push('        <div class="sp-audit-reason text-info font-size-12 mtop5"><em>" ' + escapeHtml(version_history_reason_text(ev.reason)) + ' "</em></div>');
            }
            html.push('      </div>');
            html.push('    </div>');
        });

        html.push('  </div>');
        html.push('</div>');
    }

    $container.html(html.join(''));
}

function openLinkModal(sourceEstimateId) {
    'use strict';
    var modalHtml = [
        '<div class="modal fade" id="sp-link-modal" tabindex="-1" role="dialog">',
        '  <div class="modal-dialog" role="document">',
        '    <div class="modal-content">',
        '      <div class="modal-header">',
        '        <button type="button" class="close" data-dismiss="modal" aria-label="' + escapeHtml(version_history_text('closeAria')) + '"><span aria-hidden="true">&times;</span></button>',
        '        <h4 class="modal-title"><i class="fa fa-link" aria-hidden="true"></i> ' + escapeHtml(version_history_text('linkModalTitle')) + '</h4>',
        '      </div>',
        '      <div class="modal-body">',
        '        <div class="form-group">',
        '          <label for="sp-target-estimate-id" class="control-label">' + escapeHtml(version_history_text('targetLabel')) + '</label>',
        '          <input type="number" id="sp-target-estimate-id" class="form-control" placeholder="' + escapeHtml(version_history_text('targetPlaceholder')) + '">',
        '        </div>',
        '        <div class="form-group">',
        '          <label for="sp-link-reason" class="control-label">' + escapeHtml(version_history_text('reasonLabel')) + '</label>',
        '          <textarea id="sp-link-reason" class="form-control" rows="3" placeholder="' + escapeHtml(version_history_text('reasonPlaceholder')) + '"></textarea>',
        '        </div>',
        '      </div>',
        '      <div class="modal-footer">',
        '        <button type="button" class="btn btn-default" data-dismiss="modal">' + escapeHtml(version_history_text('close')) + '</button>',
        '        <button type="button" class="btn btn-primary" id="sp-btn-confirm-link" onclick="submitLinkRevision(' + sourceEstimateId + ');">' + escapeHtml(version_history_text('confirmLink')) + '</button>',
        '      </div>',
        '    </div>',
        '  </div>',
        '</div>'
    ].join('');

    $('#sp-link-modal').remove();
    $('body').append(modalHtml);
    $('#sp-link-modal').modal('show');
}

function submitLinkRevision(sourceEstimateId) {
    'use strict';
    var targetId = $('#sp-target-estimate-id').val();
    var reason = $('#sp-link-reason').val();

    if (!targetId || parseInt(targetId) <= 0) {
        alert_float('warning', version_history_text('invalidTarget'));
        return;
    }

    var $btn = $('#sp-btn-confirm-link');
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin" aria-hidden="true"></i> ' + escapeHtml(version_history_text('processing')));

    var url = (typeof admin_url !== 'undefined' ? admin_url : '') + 'sales_pipeline/link_estimate_revision';
    var postData = $.extend({
        source_estimate_id: sourceEstimateId,
        target_estimate_id: targetId,
        reason: reason
    }, getCsrfData());

    $.ajax({
        url: url,
        type: 'POST',
        dataType: 'json',
        data: postData,
        success: function (res) {
            $('#sp-link-modal').modal('hide');
            if (res && res.status) {
                alert_float('success', res.message || version_history_text('linkSuccess'));
                load_estimate_version_history(sourceEstimateId);
            } else {
                alert_float('danger', res ? res.message : version_history_text('linkError'));
            }
        },
        error: function () {
            $('#sp-link-modal').modal('hide');
            alert_float('danger', version_history_text('connectionError'));
        }
    });
}

function openUnlinkModal(estimateId) {
    'use strict';
    var modalHtml = [
        '<div class="modal fade" id="sp-unlink-modal" tabindex="-1" role="dialog">',
        '  <div class="modal-dialog" role="document">',
        '    <div class="modal-content">',
        '      <div class="modal-header">',
        '        <button type="button" class="close" data-dismiss="modal" aria-label="' + escapeHtml(version_history_text('closeAria')) + '"><span aria-hidden="true">&times;</span></button>',
        '        <h4 class="modal-title text-warning"><i class="fa fa-unlink" aria-hidden="true"></i> ' + escapeHtml(version_history_text('unlinkModalTitle')) + '</h4>',
        '      </div>',
        '      <div class="modal-body">',
        '        <div class="alert alert-warning">',
        '          ' + escapeHtml(version_history_text('unlinkNotice')),
        '        </div>',
        '        <div class="form-group">',
        '          <label for="sp-unlink-reason" class="control-label">' + escapeHtml(version_history_text('unlinkReasonLabel')) + '</label>',
        '          <textarea id="sp-unlink-reason" class="form-control" rows="3" placeholder="' + escapeHtml(version_history_text('unlinkReasonPlaceholder')) + '"></textarea>',
        '        </div>',
        '      </div>',
        '      <div class="modal-footer">',
        '        <button type="button" class="btn btn-default" data-dismiss="modal">' + escapeHtml(version_history_text('close')) + '</button>',
        '        <button type="button" class="btn btn-warning" id="sp-btn-confirm-unlink" onclick="submitUnlinkRevision(' + estimateId + ');">' + escapeHtml(version_history_text('confirmUnlink')) + '</button>',
        '      </div>',
        '    </div>',
        '  </div>',
        '</div>'
    ].join('');

    $('#sp-unlink-modal').remove();
    $('body').append(modalHtml);
    $('#sp-unlink-modal').modal('show');
}

function submitUnlinkRevision(estimateId) {
    'use strict';
    var reason = $('#sp-unlink-reason').val();

    if (!reason || $.trim(reason) === '') {
        alert_float('warning', version_history_text('unlinkReasonRequired'));
        return;
    }

    var $btn = $('#sp-btn-confirm-unlink');
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin" aria-hidden="true"></i> ' + escapeHtml(version_history_text('processing')));

    var url = (typeof admin_url !== 'undefined' ? admin_url : '') + 'sales_pipeline/unlink_estimate_revision';
    var postData = $.extend({
        estimate_id: estimateId,
        reason: reason
    }, getCsrfData());

    $.ajax({
        url: url,
        type: 'POST',
        dataType: 'json',
        data: postData,
        success: function (res) {
            $('#sp-unlink-modal').modal('hide');
            if (res && res.status) {
                alert_float('success', res.message || version_history_text('unlinkSuccess'));
                load_estimate_version_history(estimateId);
            } else {
                alert_float('danger', res ? res.message : version_history_text('unlinkError'));
            }
        },
        error: function () {
            $('#sp-unlink-modal').modal('hide');
            alert_float('danger', version_history_text('connectionError'));
        }
    });
}

function getCsrfData() {
    'use strict';
    var data = {};
    if (typeof csrfData !== 'undefined' && csrfData && csrfData.token_name) {
        data[csrfData.token_name] = csrfData.hash;
    }
    return data;
}

function escapeHtml(string) {
    'use strict';
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
