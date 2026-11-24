/* WhatsApp Messaging Module JS (School Admin) */

function waTrans(key, fallback) {
    return (window.trans && window.trans[key]) ? window.trans[key] : fallback;
}

function waEscape(value) {
    return $('<span>').text(value == null ? '' : value).html();
}

function waIsTrue(value) {
    return value === true || value === 1 || value === '1';
}



// Recipients table: query params + selection state
var waBulkSelections = [];

function waUsersQueryParams(params) {
    params.roles = $('#wa-bulk-roles').val();
    return params;
}

function waUsersResponseHandler(res) {
    $.each(res.rows, function (i, row) {
        row.state = $.inArray(row.id, waBulkSelections) !== -1;
    });
    return res;
}

// Logs table: selection state for retries
var waLogsSelections = [];

function waLogsResponseHandler(res) {
    $.each(res.rows, function (i, row) {
        row.state = $.inArray(row.id, waLogsSelections) !== -1;
    });
    return res;
}

// Logs table query params (status + date filters)
function waLogsQueryParams(params) {
    params.status = $('#wa-log-status-filter').val();
    params.date = $('#wa-log-date-filter').val();
    return params;
}

// =====================================================
// Table action events
// =====================================================

function waUpdateDeliveryHint() {
    var metaName = $('#wa-edit-meta-name').val();
    var passParam = $('#wa-edit-meta-param').is(':checked');
    var hint = $('#wa-edit-delivery-hint');

    $('#wa-edit-meta-param-wrap').toggleClass('d-none', !metaName);

    if (metaName && !passParam) {
        hint.removeClass('d-none').html('<i class="fa-solid fa-triangle-exclamation"></i> ' +
            waTrans('meta_delivery_fixed_hint', 'The approved content of the Meta template "' + metaName + '" will be delivered — the message text below is used for preview and logs only.'));
    } else if (metaName && passParam) {
        hint.removeClass('d-none').html('<i class="fa-solid fa-circle-info"></i> ' +
            waTrans('meta_delivery_param_hint', 'Your message text will be delivered through the Meta template parameter. Line breaks are converted to spaces.'));
    } else if (typeof whatsappDefaultMetaTemplate !== 'undefined' && whatsappDefaultMetaTemplate) {
        hint.removeClass('d-none').html('<i class="fa-solid fa-circle-check"></i> ' +
            waTrans('meta_delivery_relay_hint', 'Your message text will be delivered through the default relay template') + ' "' + waEscape(whatsappDefaultMetaTemplate) + '".');
    } else {
        hint.removeClass('d-none').html('<i class="fa-solid fa-circle-info"></i> ' +
            waTrans('meta_delivery_text_hint', 'Free text is only delivered within 24 hours after the recipient last messaged your WhatsApp number.'));
    }
}

function waRenderEditPreview() {
    var parts = [];
    if ($('#wa-edit-header').val()) parts.push($('#wa-edit-header').val());
    if ($('#wa-edit-body').val()) parts.push($('#wa-edit-body').val());
    if ($('#wa-edit-footer').val()) parts.push($('#wa-edit-footer').val());
    $('#wa-edit-preview').text(parts.join('\n'));
}

// Client side sample values for the test preview (server renders authoritatively on send)
var waSampleData = {
    student_name: 'Aarav Sharma', parent_name: 'Ramesh Sharma', teacher_name: 'Priya Patel',
    recipient_name: 'Ramesh Sharma', class: '5 - A', section: 'A', amount: '1,500',
    exam_name: 'Mid Term Examination', result: 'Passed with 82%', subject: 'Mathematics',
    holiday_name: 'Diwali', gr_number: 'GR-1024'
};

function waRenderSample(text) {
    var today = new Date().toLocaleDateString('en-GB').replace(/\//g, '-');
    var samples = $.extend({ date: today, due_date: today, from_date: today, to_date: today }, waSampleData);
    return text.replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, function (match, name) {
        return samples[name] || '[' + name + ']';
    });
}

function waOpenTemplateTest(e, row) {
    e.preventDefault();
    var parts = [];
    if (row.header) parts.push(row.header);
    parts.push(row.body);
    if (row.footer) parts.push(row.footer);

    $('#wa-test-template-name').text(row.name);
    $('#wa-test-template-preview').text(waRenderSample(parts.join('\n')));
    $('#wa-test-template-send').data('url', $(e.currentTarget).attr('href'));
    $('#wa-test-template-mobile').val('');
    $('#waTemplateTestModal').modal('show');
}

function waManualRenderPreview() {
    var parts = [];
    if ($('#wa-manual-header').val()) parts.push($('#wa-manual-header').val());
    if ($('#wa-manual-body').val()) parts.push($('#wa-manual-body').val());
    if ($('#wa-manual-footer').val()) parts.push($('#wa-manual-footer').val());
    $('#wa-manual-preview').text(parts.join('\n'));

    var matches = ($('#wa-manual-body').val() || '').match(/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/g) || [];
    var chips = matches.filter(function (v, i, self) { return self.indexOf(v) === i; })
        .map(function (v) { return '<span class="wa-var-chip wa-insert-var" data-target="#wa-manual-body" title="' + waTrans('click_to_insert', 'Click to insert') + '">' + waEscape(v) + '</span>'; });
    $('#wa-manual-variables').html(chips.join(' ') || '-');
}

var waExtSelections = [];

function waExtResponseHandler(res) {
    $.each(res.rows, function (i, row) {
        row.state = $.inArray(row.id, waExtSelections) !== -1;
    });
    return res;
}

function waOpenContactModal(row, url) {
    $('#wa-contact-modal-title').text(row ? (row.name || row.mobile) : waTrans('add_contact', 'Add Contact'));
    $('#wa-contact-name').val(row ? (row.name || '') : '');
    $('#wa-contact-mobile').val(row ? row.mobile : '');
    $('#wa-contact-tag').val(row && row.tag ? row.tag : '');
    $('#wa-contact-save').data('url', url).data('method', row ? 'PUT' : 'POST');
    $('#waContactModal').modal('show');
}

function waOpenManualModal(row, url) {
    $('#wa-manual-modal-title').text(row ? row.name : waTrans('create_template', 'Create Template'));
    $('#wa-manual-name').val(row ? row.name : '');
    $('#wa-manual-category').val(row && row.category ? row.category : '');
    $('#wa-manual-header').val(row && row.header ? row.header : '');
    $('#wa-manual-body').val(row ? row.body : '');
    $('#wa-manual-footer').val(row && row.footer ? row.footer : '');
    $('#wa-manual-status').prop('checked', row ? waIsTrue(row.status) : true);
    $('#wa-manual-save').data('url', url).data('method', row ? 'PUT' : 'POST');
    waManualRenderPreview();
    $('#waManualModal').modal('show');
}

var WA_AUTO_VARS = ['recipient_name', 'parent_name', 'teacher_name', 'school_name'];

function waBulkSelectedTemplate() {
    var id = $('#wa-bulk-template').val();
    if (!id || typeof whatsappBulkTemplates === 'undefined') return null;
    return whatsappBulkTemplates.find(function (t) { return t.id == id; }) || null;
}

function waBulkRenderPreview() {
    var template = waBulkSelectedTemplate();
    if (!template) {
        $('#wa-bulk-preview').text($('#wa-bulk-template option:first').text());
        return;
    }
    var parts = [];
    if (template.header) parts.push(template.header);
    parts.push(template.body);
    if (template.footer) parts.push(template.footer);
    var text = parts.join('\n');

    text = text.replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, function (match, name) {
        var input = $('#wa-bulk-variables').find('[data-variable="' + name + '"]');
        if (input.length && input.val().trim()) return input.val().trim();
        if (WA_AUTO_VARS.indexOf(name) !== -1) return waRenderSample('{{' + name + '}}');
        return match;
    });
    $('#wa-bulk-preview').text(text);
}

function waBulkBuildVariables() {
    var template = waBulkSelectedTemplate();
    var manualWrap = $('#wa-bulk-variables').empty();
    var autoWrap = $('#wa-bulk-auto-vars').empty();
    $('#wa-bulk-auto-wrap').addClass('d-none');
    if (!template) {
        waBulkRenderPreview();
        return;
    }

    var autoChips = [];
    (template.variables || []).forEach(function (variable) {
        if (WA_AUTO_VARS.indexOf(variable) !== -1) {
            autoChips.push('<span class="wa-var-chip wa-var-emerald">{{' + waEscape(variable) + '}}</span>');
            return;
        }
        manualWrap.append(
            '<div class="wa-field">' +
            '<label class="wa-label">{{' + waEscape(variable) + '}} <span class="text-danger">*</span></label>' +
            '<input type="text" class="wa-input wa-bulk-variable" data-variable="' + waEscape(variable) + '" placeholder="' + waEscape(variable) + '">' +
            '</div>'
        );
    });
    if (autoChips.length) {
        autoWrap.html(autoChips.join(' '));
        $('#wa-bulk-auto-wrap').removeClass('d-none');
    }
    waBulkRenderPreview();
}

$(document).ready(function () {

    window.formSuccessFunction = function () {
        $('#table_list').bootstrapTable('refresh');
    };

    // Reload the page after saving credentials so the connection chips / stats update
    window.whatsappSettingsSaved = function () {
        setTimeout(function () {
            window.location.reload();
        }, 1200);
    };

    // Live preview inside the edit modal
    $('#wa-edit-header, #wa-edit-body, #wa-edit-footer').on('input', waRenderEditPreview);
    $('#wa-edit-meta-name').on('input', waUpdateDeliveryHint);
    $('#wa-edit-meta-param').on('change', waUpdateDeliveryHint);

    // Insert variable on click
    $(document).on('click', '.wa-insert-var', function () {
        var targetSelector = $(this).data('target');
        var input = $(targetSelector)[0];
        if (!input) return;

        var textToInsert = $(this).text();
        var startPos = input.selectionStart || 0;
        var endPos = input.selectionEnd || 0;
        var currentValue = input.value;

        input.value = currentValue.substring(0, startPos) + textToInsert + currentValue.substring(endPos, currentValue.length);

        input.focus();
        input.selectionStart = input.selectionEnd = startPos + textToInsert.length;

        $(input).trigger('input');
    });

    // Refresh logs table when its filters change / the tab opens
    $('#wa-log-status-filter, #wa-log-date-filter').on('change', function () {
        $('#wa_logs_table').bootstrapTable('refresh');
    });
    $('#wa-logs-tab').on('shown.bs.tab', function () {
        $('#wa_logs_table').bootstrapTable('resetView');
    });

    // "View logs" shortcut on the failed messages stat card
    $('#wa-view-logs-link').on('click', function () {
        var tab = $('#wa-logs-tab')[0];
        if (tab) tab.click();
    });

    // Empty state for the logs table
    $('#wa_logs_table').on('load-success.bs.table', function (e, data) {
        var isEmpty = !data || !data.total;
        var hasFilters = $('#wa-log-status-filter').val() || $('#wa-log-date-filter').val();
        $('#wa-logs-empty').toggleClass('d-none', !isEmpty || !!hasFilters);
        $('#wa-logs').find('.bootstrap-table').toggleClass('d-none', isEmpty && !hasFilters);
    });
    $('#wa-refresh-logs').on('click', function () {
        $('#wa_logs_table').bootstrapTable('refresh');
    });

    // ===== Retry failed messages =====
    $('#wa_logs_table').on('check.bs.table check-all.bs.table uncheck.bs.table uncheck-all.bs.table',
        function (e, rowsAfter, rowsBefore) {
            var rows = e.type === 'uncheck-all' ? rowsBefore : rowsAfter;
            var ids = $.map(!$.isArray(rows) ? [rows] : rows, function (row) { return row.id; });
            var checked = $.inArray(e.type, ['check', 'check-all']) > -1;
            ids.forEach(function (id) {
                var index = waLogsSelections.indexOf(id);
                if (checked && index === -1) waLogsSelections.push(id);
                if (!checked && index !== -1) waLogsSelections.splice(index, 1);
            });
        });

    function waRetry(payload, confirmText) {
        Swal.fire({
            title: waTrans('are_you_sure', 'Are you sure?'),
            text: confirmText,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: waTrans('yes', 'Yes')
        }).then(function (result) {
            if (!result.isConfirmed) return;
            payload._token = $('meta[name="csrf-token"]').attr('content');
            $.post(whatsappLogsRetryUrl, payload)
                .done(function (response) {
                    if (response.error) {
                        showErrorToast(response.message);
                    } else {
                        showSuccessToast(response.message);
                        waLogsSelections = [];
                        $('#wa_logs_table').bootstrapTable('uncheckAll');
                        $('#wa_logs_table').bootstrapTable('refresh');
                    }
                })
                .fail(function (jqXHR) {
                    showErrorToast(jqXHR.responseJSON && jqXHR.responseJSON.message ? jqXHR.responseJSON.message : 'Error');
                });
        });
    }

    $('#wa-retry-selected').on('click', function () {
        if (!waLogsSelections.length) {
            showErrorToast(waTrans('no_failed_messages_selected', 'Please select the failed messages to retry'));
            return;
        }
        waRetry({ ids: waLogsSelections.join(',') }, waTrans('retry_selected_confirm', 'Retry the selected failed messages? Only rows with a failed status are retried.'));
    });

    $('#wa-retry-all').on('click', function () {
        waRetry({ all: 1 }, waTrans('retry_all_confirm', 'Retry ALL failed messages?'));
    });

    // Access token visibility toggle
    $('#wa-toggle-token').on('click', function () {
        var input = $('#wa-access-token');
        var show = input.attr('type') === 'password';
        input.attr('type', show ? 'text' : 'password');
        $(this).find('i').attr('class', show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye');
    });

    // Copy helpers
    $('.wa-copy').on('click', function () {
        var input = $($(this).data('target'))[0];
        if (!input || !input.value) return;
        input.select();
        input.setSelectionRange(0, 99999);
        try {
            navigator.clipboard.writeText(input.value);
        } catch (e) {
            document.execCommand('copy');
        }
        showSuccessToast(waTrans('copied', 'Copied!'));
    });

    // Test connection
    $('#wa-test-connection').on('click', function () {
        var button = $(this);
        button.attr('disabled', true);
        $.post(whatsappTestConnectionUrl, { _token: $('meta[name="csrf-token"]').attr('content') })
            .done(function (response) {
                if (response.error) {
                    showErrorToast(response.message);
                } else {
                    showSuccessToast(response.message);
                    setTimeout(function () { window.location.reload(); }, 1200);
                }
            })
            .fail(function (jqXHR) {
                showErrorToast(jqXHR.responseJSON && jqXHR.responseJSON.message ? jqXHR.responseJSON.message : waTrans('connection_failed', 'Connection failed'));
            })
            .always(function () {
                button.attr('disabled', false);
            });
    });

    // ===== Manual templates =====
    $('#wa-manual-create').on('click', function () {
        waOpenManualModal(null, whatsappManualStoreUrl);
    });
    $('#wa-manual-header, #wa-manual-body, #wa-manual-footer').on('input', waManualRenderPreview);

    $('#wa-manual-save').on('click', function () {
        var name = $('#wa-manual-name').val().trim();
        var body = $('#wa-manual-body').val().trim();
        if (!name || !body) {
            showErrorToast(waTrans('please_fill_required_fields', 'Please fill the required fields'));
            return;
        }
        var button = $(this);
        button.attr('disabled', true);
        var payload = {
            _token: $('meta[name="csrf-token"]').attr('content'),
            name: name,
            category: $('#wa-manual-category').val().trim(),
            header: $('#wa-manual-header').val().trim(),
            body: body,
            footer: $('#wa-manual-footer').val().trim(),
            status: $('#wa-manual-status').is(':checked') ? 1 : 0
        };
        if (button.data('method') === 'PUT') payload._method = 'PUT';

        $.post(button.data('url'), payload)
            .done(function (response) {
                if (response.error) {
                    showErrorToast(response.message);
                    return;
                }
                showSuccessToast(response.message);
                $('#waManualModal').modal('hide');
                $('#wa_manual_table').bootstrapTable('refresh');
                // Bulk dropdown reads templates rendered at page load — reload keeps it in sync
                setTimeout(function () { window.location.reload(); }, 900);
            })
            .fail(function (jqXHR) {
                showErrorToast(jqXHR.responseJSON && jqXHR.responseJSON.message ? jqXHR.responseJSON.message : 'Error');
            })
            .always(function () {
                button.attr('disabled', false);
            });
    });

    // ===== External users (persistent contacts directory) =====
    function waUpdateBulkCount() {
        var source = $('input[name="wa_recipient_source"]:checked').val() || 'users';
        $('#wa-bulk-count').text(source === 'csv' ? waExtSelections.length : waBulkSelections.length);
    }

    $('input[name="wa_recipient_source"]').on('change', function () {
        var source = $('input[name="wa_recipient_source"]:checked').val();
        $('#wa-csv-panel').toggleClass('d-none', source !== 'csv');
        $('#wa-users-panel').toggleClass('d-none', source === 'csv');
        if (source === 'csv') {
            $('#wa_ext_table').bootstrapTable('resetView');
        }
        waUpdateBulkCount();
    });

    $('#wa_ext_table').on('check.bs.table check-all.bs.table uncheck.bs.table uncheck-all.bs.table',
        function (e, rowsAfter, rowsBefore) {
            var rows = e.type === 'uncheck-all' ? rowsBefore : rowsAfter;
            var ids = $.map(!$.isArray(rows) ? [rows] : rows, function (row) { return row.id; });
            var checked = $.inArray(e.type, ['check', 'check-all']) > -1;
            ids.forEach(function (id) {
                var index = waExtSelections.indexOf(id);
                if (checked && index === -1) waExtSelections.push(id);
                if (!checked && index !== -1) waExtSelections.splice(index, 1);
            });
            waUpdateBulkCount();
        });

    // Directory management (External Users tab)
    $('#wa-contact-create').on('click', function () {
        waOpenContactModal(null, whatsappContactStoreUrl);
    });

    $('#wa-contact-save').on('click', function () {
        var mobile = $('#wa-contact-mobile').val().trim();
        if (!mobile) {
            showErrorToast(waTrans('enter_mobile_with_country_code', 'Enter mobile number with country code'));
            return;
        }
        var button = $(this);
        button.attr('disabled', true);
        var payload = {
            _token: $('meta[name="csrf-token"]').attr('content'),
            name: $('#wa-contact-name').val().trim(),
            mobile: mobile,
            tag: $('#wa-contact-tag').val().trim()
        };
        if (button.data('method') === 'PUT') payload._method = 'PUT';

        $.post(button.data('url'), payload)
            .done(function (response) {
                if (response.error) {
                    showErrorToast(response.message);
                    return;
                }
                showSuccessToast(response.message);
                $('#waContactModal').modal('hide');
                $('#wa_contacts_table').bootstrapTable('refresh');
                $('#wa_ext_table').bootstrapTable('refresh');
            })
            .fail(function (jqXHR) {
                showErrorToast(jqXHR.responseJSON && jqXHR.responseJSON.message ? jqXHR.responseJSON.message : 'Error');
            })
            .always(function () {
                button.attr('disabled', false);
            });
    });

    $('#wa-contacts-import-btn').on('click', function () {
        $('#wa-contacts-import-file').trigger('click');
    });

    $('#wa-contacts-import-file').on('change', function () {
        var file = this.files[0];
        if (!file) return;

        var formData = new FormData();
        formData.append('file', file);
        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));

        var input = $(this);
        $.ajax({ url: whatsappContactsImportUrl, type: 'POST', data: formData, processData: false, contentType: false })
            .done(function (response) {
                if (response.error) {
                    showErrorToast(response.message);
                } else {
                    showSuccessToast(response.message);
                    $('#wa_contacts_table').bootstrapTable('refresh');
                    $('#wa_ext_table').bootstrapTable('refresh');
                }
            })
            .fail(function (jqXHR) {
                showErrorToast(jqXHR.responseJSON && jqXHR.responseJSON.message ? jqXHR.responseJSON.message : 'Error');
            })
            .always(function () {
                input.val('');
            });
    });

    // ===== Bulk messaging =====
    $('input[name="wa_schedule_type"]').on('change', function () {
        var type = $('input[name="wa_schedule_type"]:checked').val();
        $('#wa-schedule-datetime-wrap').toggleClass('d-none', type === 'now');
        $('#wa-schedule-hint-weekly').toggleClass('d-none', type !== 'weekly');
        $('#wa-schedule-hint-monthly').toggleClass('d-none', type !== 'monthly');
        $('#wa-bulk-send-label').text(type === 'now' ? waTrans('send', 'Send') : waTrans('schedule', 'Schedule'));
    });

    $('#wa-bulk-template').on('change', waBulkBuildVariables);
    $(document).on('input', '.wa-bulk-variable', waBulkRenderPreview);

    $('#wa-bulk-roles').on('change', function () {
        waBulkSelections = [];
        $('#wa-bulk-count').text(0);
        $('#wa_users_table').bootstrapTable('uncheckAll');
        $('#wa_users_table').bootstrapTable('refresh');
    });

    $('#wa_users_table').on('check.bs.table check-all.bs.table uncheck.bs.table uncheck-all.bs.table',
        function (e, rowsAfter, rowsBefore) {
            var rows = e.type === 'uncheck-all' ? rowsBefore : rowsAfter;
            var ids = $.map(!$.isArray(rows) ? [rows] : rows, function (row) { return row.id; });
            var checked = $.inArray(e.type, ['check', 'check-all']) > -1;

            ids.forEach(function (id) {
                var index = waBulkSelections.indexOf(id);
                if (checked && index === -1) waBulkSelections.push(id);
                if (!checked && index !== -1) waBulkSelections.splice(index, 1);
            });
            waUpdateBulkCount();
        });

    $('#wa-bulk-send').on('click', function () {
        var template = waBulkSelectedTemplate();
        if (!template) {
            showErrorToast(waTrans('select_template', 'Please select a template'));
            return;
        }
        var recipientSource = $('input[name="wa_recipient_source"]:checked').val() || 'users';
        if (recipientSource === 'csv' && !waExtSelections.length) {
            showErrorToast(waTrans('please_select_users', 'Please select at least one recipient'));
            return;
        }
        if (recipientSource === 'users' && !waBulkSelections.length) {
            showErrorToast(waTrans('please_select_users', 'Please select at least one recipient'));
            return;
        }
        var missing = [];
        var variables = {};
        $('.wa-bulk-variable').each(function () {
            var value = $(this).val().trim();
            if (!value) missing.push($(this).data('variable'));
            variables[$(this).data('variable')] = value;
        });
        if (missing.length) {
            showErrorToast(waTrans('please_fill_template_variables', 'Please fill all template variables') + ': {{' + missing.join('}}, {{') + '}}');
            return;
        }

        var scheduleType = $('input[name="wa_schedule_type"]:checked').val() || 'now';
        var scheduledAt = $('#wa-schedule-datetime').val();
        if (scheduleType !== 'now' && !scheduledAt) {
            showErrorToast(waTrans('please_select_schedule_datetime', 'Please select the schedule date & time'));
            return;
        }

        var button = $(this);
        button.attr('disabled', true);
        var formData = new FormData();
        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
        formData.append('template_id', template.id);
        formData.append('user_id', recipientSource === 'users' ? waBulkSelections.join(',') : '');
        formData.append('contact_ids', recipientSource === 'csv' ? waExtSelections.join(',') : '');
        $.each(variables, function (name, value) {
            formData.append('variables[' + name + ']', value);
        });
        formData.append('schedule_type', scheduleType);
        formData.append('scheduled_at', scheduleType === 'now' ? '' : scheduledAt.replace('T', ' '));
        var attachmentInput = $('#wa-bulk-attachment')[0];
        if (attachmentInput && attachmentInput.files.length) {
            formData.append('attachment', attachmentInput.files[0]);
        }
        $.ajax({ url: whatsappBulkSendUrl, type: 'POST', data: formData, processData: false, contentType: false })
            .done(function (response) {
                if (response.error) {
                    showErrorToast(response.message);
                    return;
                }
                showSuccessToast(response.message);
                waBulkSelections = [];
                waExtSelections = [];
                $('#wa-bulk-count').text(0);
                $('#wa_users_table').bootstrapTable('uncheckAll');
                $('#wa_ext_table').bootstrapTable('uncheckAll');
                $('#wa-bulk-attachment').val('');
                // Scheduled: show it in the Scheduled tab
                if (response.data && response.data.scheduled) {
                    var tab = $('#wa-scheduled-tab')[0];
                    if (tab) tab.click();
                    $('#wa_scheduled_table').bootstrapTable('refresh');
                    return;
                }
                // Jump to the logs filtered on this batch so per-recipient status is visible
                if (response.data && response.data.batch_id && $('#wa-logs-tab').length) {
                    // var logTab = $('#wa-logs-tab')[0];
                    // if (logTab) logTab.click();
                    // setTimeout(function () {
                    //     $('#wa_logs_table').bootstrapTable('resetSearch', response.data.batch_id);
                    // }, 300);
                }
            })
            .fail(function (jqXHR) {
                showErrorToast(jqXHR.responseJSON && jqXHR.responseJSON.message ? jqXHR.responseJSON.message : 'Error');
            })
            .always(function () {
                button.attr('disabled', false);
            });
    });

    // Per template test send
    $('#wa-test-template-send').on('click', function () {
        var mobile = $('#wa-test-template-mobile').val().trim();
        if (!mobile) {
            showErrorToast(waTrans('enter_mobile_with_country_code', 'Enter mobile number with country code'));
            return;
        }
        var button = $(this);
        button.attr('disabled', true);
        $.post(button.data('url'), { _token: $('meta[name="csrf-token"]').attr('content'), mobile: mobile })
            .done(function (response) {
                if (response.error) {
                    showErrorToast(response.message);
                } else {
                    showSuccessToast(response.message);
                    $('#waTemplateTestModal').modal('hide');
                    $('#wa_logs_table').bootstrapTable('refresh');
                }
            })
            .fail(function (jqXHR) {
                showErrorToast(jqXHR.responseJSON && jqXHR.responseJSON.message ? jqXHR.responseJSON.message : 'Error');
            })
            .always(function () {
                button.attr('disabled', false);
            });
    });

    // Test message
    $('#wa-send-test').on('click', function () {
        var mobile = $('#wa-test-mobile').val().trim();
        if (!mobile) {
            showErrorToast(waTrans('enter_mobile_with_country_code', 'Enter mobile number with country code'));
            return;
        }
        var button = $(this);
        button.attr('disabled', true);
        $.post(whatsappTestMessageUrl, { _token: $('meta[name="csrf-token"]').attr('content'), mobile: mobile })
            .done(function (response) {
                if (response.error) {
                    showErrorToast(response.message);
                } else {
                    showSuccessToast(response.message);
                    $('#wa-test-mobile').val('');
                    $('#waTestMessage').modal('hide');
                }
            })
            .fail(function (jqXHR) {
                showErrorToast(jqXHR.responseJSON && jqXHR.responseJSON.message ? jqXHR.responseJSON.message : 'Error');
            })
            .always(function () {
                button.attr('disabled', false);
            });
    });
});
