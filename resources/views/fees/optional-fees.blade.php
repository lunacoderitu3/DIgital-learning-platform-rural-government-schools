{{-- @dd($feesClassTypes) --}}
@extends('layouts.master')

@section('title')
    {{ __('optional_fees') }}
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/custom/cheque_status_modal.css') }}">
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('optional_fees') }}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title"></h4>
                        <div id="toolbar">
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label for="filter-class-section-id" class="filter-menu">{{ __('Class Section') }} <span
                                            class="text-danger">*</span></label>
                                    <select name="filter-class-section-id" id="filter-class-section-id"
                                        class="form-control custom-search-select">
                                        <option value="">{{ __('select') }}</option>
                                        @foreach ($class_section as $class)
                                            <option value="{{ $class->id }}"
                                                data-class-section-id="{{ $class->class_id }}">
                                                {{ $class->full_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-4">
                                    <label class="filter-menu" for="filter_optional_fees">{{ __('optional_fees') }} <span
                                            class="text-danger">*</span></label>
                                    <select name="filter_optional_fees" id="filter_optional_fees"
                                        class="form-control custom-search-select">
                                        <option value="">{{ __('select') }}</option>
                                        @foreach ($feesClassTypes as $item)
                                            <option value="{{ $item->id }}"
                                                data-class-section-id="{{ $item->class_id }}">{{ $item->fees_type->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                            data-url="{{ route('fees.optional.list', 1) }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]"
                            data-search="true" data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                            data-fixed-columns="false" data-trim-on-search="false" data-mobile-responsive="true"
                            data-sort-name="id" data-sort-order="desc" data-maintain-selected="true"
                            data-export-data-type='all'
                            data-export-options='{ "fileName": "{{ __('fees') }}-{{ __('paid') }}-{{ __('list') }}-<?= date('d-m-y')
                            ?>" ,"ignoreColumn":["operate"]}'
                            data-show-export="true" data-query-params="optionalFeesPaidListQueryParams" data-escape="true">

                            <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true" data-visible="false"
                                        data-align="center">{{ __('id') }}</th>
                                    <th scope="col" data-field="no" data-formatter="totalFeesFormatter"
                                        data-sortable="false" data-align="center">{{ __('no.') }}</th>
                                    <th scope="col" data-field="full_name" data-sortable="false"
                                        data-formatter="NotificationUserNameFormatter"> {{ __('Student Name') }}</th>
                                    <th scope="col" data-field="optional_fees_amount" data-sortable="false"
                                        data-align="center">{{ __('Optional Fees') }}</th>
                                    <th scope="col" data-field="payment_method" data-sortable="false" data-align="center"
                                        data-formatter="optionalFeesPaymentMethodFormatter"> {{ __('Payment Method') }}
                                    </th>
                                    <th scope="col" data-field="status" data-sortable="false" data-align="center"
                                        data-formatter="optionalFeesStatusFormatter">
                                        {{ __('Status') }}
                                    </th>
                                    <th scope="col" data-field="fees_paid.date" data-sortable="false"
                                        data-align="center">{{ __('Date') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal for Updating Payment Status --}}
    <div class="modal fade chq-modal" id="updateOptionalChequeStatusModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="chq-header">
                    <div class="chq-header-icon">
                        <i class="fa fa-credit-card"></i>
                    </div>
                    <div class="chq-header-text">
                        <h5 id="opt-cheque-status-modal-title">{{ __('update_cheque_status') }}</h5>
                        <p class="chq-header-sub" id="opt-cheque-modal-subtitle">
                            {{ __('review_the_payment_and_set_its_final_status') }}
                        </p>
                    </div>
                    <button type="button" class="chq-close" data-dismiss="modal" data-bs-dismiss="modal"
                        aria-label="Close">
                        <i class="fa fa-close"></i>
                    </button>
                </div>

                <form id="update-optional-cheque-status-form" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="id" id="opt-cheque-status-id">
                    <input type="hidden" name="status" id="opt-cheque-status-value" value="pending">

                    <div class="chq-body">
                        <div id="opt-cheque-details-loader" class="chq-loader" style="display:none">
                            <i class="fa fa-spinner fa-spin"></i> {{ __('loading') }}
                        </div>

                        {{-- Payment summary : every optional fee covered by this cheque --}}
                        <div id="opt-cheque-details-wrapper" style="display:none">
                            <div class="chq-card">
                                <div class="chq-student">
                                    <div class="chq-avatar" id="opt-cheque-detail-initials">-</div>
                                    <div>
                                        <div class="chq-student-name" id="opt-cheque-detail-student">-</div>
                                        <p class="chq-student-meta">
                                            <span id="opt-cheque-detail-class">-</span>
                                            <span id="opt-cheque-detail-parent-wrap">
                                                &middot; {{ __('parent_name') }}:
                                                <span id="opt-cheque-detail-parent">-</span>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="chq-total">
                                        <span class="chq-label">{{ __('total_amount') }}</span>
                                        <div class="chq-total-value" id="opt-cheque-detail-total">-</div>
                                    </div>
                                </div>

                                <div class="chq-meta-grid">
                                    <div>
                                        <span class="chq-label"
                                            id="opt-cheque-detail-no-label">{{ __('cheque_no') }}</span>
                                        <div class="chq-value" id="opt-cheque-detail-no">-</div>
                                    </div>
                                    <div>
                                        <span class="chq-label">{{ __('payment_date') }}</span>
                                        <div class="chq-value" id="opt-cheque-detail-date">-</div>
                                    </div>
                                    <div>
                                        <span class="chq-label">{{ __('payment_method') }}</span>
                                        <div class="chq-value" id="opt-cheque-detail-mode">-</div>
                                    </div>
                                    <div>
                                        <span class="chq-label">{{ __('current_status') }}</span>
                                        <div class="chq-value">
                                            <span class="chq-pill chq-pill-pending" id="opt-cheque-detail-status">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="chq-card">
                                <h6 class="chq-section-title">
                                    <i class="fa fa-list-alt"></i>
                                    <span
                                        id="opt-cheque-covered-title">{{ __('optional_fees_covered_by_this_cheque') }}</span>
                                </h6>

                                <div id="opt-cheque-details-items"></div>

                                <div class="chq-fee-total">
                                    <span>{{ __('total') }}</span>
                                    <span id="opt-cheque-detail-sum-amount">-</span>
                                </div>

                                <div class="chq-hint" id="opt-cheque-multi-note" style="display:none">
                                    <i class="fa fa-info-circle"></i>
                                    <span>{{ __('this_status_will_be_applied_to_all_fees_paid_by_this_cheque') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="chq-card mb-0">
                            <div class="chq-status-bar">
                                <h6 class="chq-section-title mb-0">
                                    <i class="fa fa-check-circle"></i>
                                    {{ __('update_status') }} <span class="text-danger">*</span>
                                </h6>

                                <div class="chq-status-options">
                                    <label class="chq-status-opt active" data-value="pending">
                                        <i class="fa fa-hourglass-half"></i>
                                        <span>{{ __('under_review') }}</span>
                                    </label>
                                    <label class="chq-status-opt" data-value="success">
                                        <i class="fa fa-check"></i>
                                        <span class="opt-status-label-success">{{ __('paid') }}</span>
                                    </label>
                                    <label class="chq-status-opt" data-value="bounced">
                                        <i class="fa fa-times"></i>
                                        <span class="opt-status-label-bounced">{{ __('bounced') }}</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group" id="opt-rejection-reason-div" style="display: none;">
                                <span class="chq-label">{{ __('Rejection Reason') }} <span
                                        class="text-danger">*</span></span>
                                <textarea name="rejection_reason" id="opt-rejection-reason" class="form-control" rows="2"
                                    placeholder="{{ __('enter_rejection_reason_or_note') }}"></textarea>
                            </div>

                            <div class="form-group mb-0">
                                <span class="chq-label">{{ __('note') }}</span>
                                <textarea name="note" id="opt-cheque-status-note-modal" class="form-control" rows="2"
                                    placeholder="{{ __('note') }}"></textarea>
                            </div>

                            <!-- Payment Receipt Preview (Only shown when a file is attached) -->
                            <div class="mt-3" id="opt_receipt_preview_div" style="display: none;">
                                <span class="chq-label" id="opt-receipt-label">{{ __('Payment Receipt') }}</span>
                                <a id="opt-cheque-status-receipt-link" href="#" target="_blank"
                                    rel="noopener noreferrer" class="chq-receipt-link">
                                    <i class="fa fa-paperclip"></i> {{ __('View Receipt File') }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="chq-footer">
                        <button type="button" class="btn btn-light border" data-dismiss="modal"
                            data-bs-dismiss="modal">{{ __('close') }}</button>
                        <button type="submit" class="btn btn-theme">
                            <i class="fa fa-check mr-1"></i> {{ __('update_status') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        // Format Payment Method Column (Cash, Cheque, Manual, Online)
        function optionalFeesPaymentMethodFormatter(value, row) {
            let optionalFeesList = (row.optional_fees && row.optional_fees.length > 0) ?
                row.optional_fees :
                (row.fees_paid?.optional_fee || []);

            let latestTxn = optionalFeesList[0] || null;
            let mode = value || (latestTxn ? latestTxn.mode : '') || row.payment_method;

            if (!mode && mode !== 0) return '-';

            let modeStr = String(mode).toLowerCase();
            if (modeStr === '1' || modeStr === 'cash') return (window.trans["Cash"] || "Cash");
            if (modeStr === '2' || modeStr === 'cheque') return (window.trans["Cheque"] || "Cheque");
            if (modeStr === '3' || modeStr === 'online') return (window.trans["Online"] || "Online");
            if (modeStr === 'manual') return (window.trans["Manual"] || "Manual");

            return mode;
        }

        // Format Status Column & Update Status Button
        function optionalFeesStatusFormatter(value, row) {
            // 1. Get optional fees list
            let optionalFeesList = (row.optional_fees && row.optional_fees.length > 0) ?
                row.optional_fees :
                (row.fees_paid?.optional_fee || []);

            // 2. Find ANY Cheque or Manual transaction that is strictly PENDING
            let pendingTxn = optionalFeesList.find(item => {
                let st = String(item.status).toLowerCase();
                let mode = String(item.mode).toLowerCase();
                return (mode === 'cheque' || mode === 'manual') && (st === 'pending' || item.status == 0);
            });

            // 3. If a Pending transaction exists -> Show Update Status button
            if (pendingTxn) {
                let feeId = pendingTxn.id || '';
                let feeNote = pendingTxn.note || pendingTxn.notes || '';
                let receiptUrl = pendingTxn.receipt_url ||
                    (pendingTxn.file ? pendingTxn.file.file_url : '') ||
                    (pendingTxn.file_url ? pendingTxn.file_url : '') ||
                    row.receipt_url ||
                    '';
                let modeVal = pendingTxn.mode || '';
                let isManual = String(modeVal).toLowerCase() === 'manual';

                let btnClass = isManual ?
                    'btn btn-sm btn-outline-info update-optional-cheque-status-btn' :
                    'btn btn-sm btn-outline-secondary update-optional-cheque-status-btn';

                return '<button type="button" ' +
                    'class="' + btnClass + '" ' +
                    'data-id="' + feeId + '" ' +
                    'data-mode="' + modeVal + '" ' +
                    'data-status="pending" ' +
                    'data-note="' + feeNote + '" ' +
                    'data-cheque-no="' + (pendingTxn.cheque_no || pendingTxn.reference_no || '') + '" ' +
                    'data-receipt="' + receiptUrl + '" ' +
                    'data-toggle="modal" ' +
                    'data-target="#updateOptionalChequeStatusModal">' +
                    (window.trans["Update Status"] || "Update Status") +
                    '</button>';
            }

            // 4. Determine status for completed/paid/failed records
            let statusVal = value;
            if (statusVal === undefined || statusVal === null || statusVal === '') {
                if (optionalFeesList.length > 0) {
                    statusVal = optionalFeesList[0].status;
                } else if (row.fees_status !== undefined) {
                    statusVal = row.fees_status;
                }
            }

            let st = String(statusVal).toLowerCase();

            if (st === '1' || st === 'success' || st === 'paid') {
                return '<span class="badge badge-success">' + (window.trans["Paid"] || "Paid") + '</span>';
            } else if (st === '0' || st === 'pending') {
                return '<span class="badge badge-warning">' + (window.trans["Pending"] || "Pending") + '</span>';
            } else if (st === '2' || st === 'failed' || st === 'bounced') {
                return '<span class="badge badge-danger">' + (window.trans["Failed"] || "Failed") + '</span>';
            }

            // Default fallback: If optional fee record exists, display Paid badge
            if (optionalFeesList.length > 0) {
                return '<span class="badge badge-success">' + (window.trans["Paid"] || "Paid") + '</span>';
            }

            return '-';
        }

        // ---- Status choice (segmented control) ----
        function setOptionalChequeStatus(status) {
            status = status || 'pending';
            $('#updateOptionalChequeStatusModal .chq-status-opt').removeClass('active');
            $('#updateOptionalChequeStatusModal .chq-status-opt[data-value="' + status + '"]').addClass('active');
            $('#opt-cheque-status-value').val(status);

            if (status === 'bounced') {
                $('#opt-rejection-reason-div').show();
                $('#opt-rejection-reason').attr('required', true);
            } else {
                $('#opt-rejection-reason-div').hide();
                $('#opt-rejection-reason').removeAttr('required');
            }
        }

        $(document).on('click', '#updateOptionalChequeStatusModal .chq-status-opt', function() {
            setOptionalChequeStatus($(this).data('value'));
        });

        // Manual payments use approval wording, cheques use cheque wording
        function applyOptionalModeLabels(mode) {
            if (String(mode).toLowerCase() === 'manual') {
                $('#opt-cheque-status-modal-title').text("{{ __('Update Manual Payment Status') }}");
                $('#opt-cheque-covered-title').text("{{ __('payment_details') }}");
                $('.opt-status-label-success').text("{{ __('Approved') }}");
                $('.opt-status-label-bounced').text("{{ __('Rejected') }}");
                $('#updateOptionalChequeStatusModal .chq-header-icon i').attr('class', 'fa fa-file-text');
                $('#opt-receipt-label').text("{{ __('Payment Receipt / Proof File') }}");
            } else {
                $('#opt-cheque-status-modal-title').text("{{ __('update_cheque_status') }}");
                $('#opt-cheque-covered-title').text("{{ __('optional_fees_covered_by_this_cheque') }}");
                $('.opt-status-label-success').text("{{ __('Paid') }}");
                $('.opt-status-label-bounced').text("{{ __('Bounced') }}");
                $('#updateOptionalChequeStatusModal .chq-header-icon i').attr('class', 'fa fa-credit-card');
                $('#opt-receipt-label').text("{{ __('Payment Receipt') }}");
            }
        }

        function optionalChequeInitials(name) {
            return (name || '')
                .split(' ')
                .filter(Boolean)
                .map(function(part) {
                    return part.charAt(0).toUpperCase();
                })
                .join('')
                .substring(0, 2) || '-';
        }

        $(document).on('click', '.update-optional-cheque-status-btn', function() {
            let id = $(this).attr('data-id') || $(this).data('id');
            let mode = String($(this).attr('data-mode') || $(this).data('mode') || '').trim();
            let note = $(this).attr('data-note') || $(this).data('note') || '';
            let status = $(this).attr('data-status') || $(this).data('status') || 'pending';
            let receiptUrl = $(this).attr('data-receipt') || $(this).data('receipt');

            $('#opt-cheque-status-id').val(id);
            $('#opt-cheque-status-note-modal').val(note);
            $('#opt-rejection-reason').val('');
            setOptionalChequeStatus(status);
            applyOptionalModeLabels(mode);

            if (receiptUrl && receiptUrl !== '' && receiptUrl !== 'null' && receiptUrl !== 'undefined') {
                $('#opt-cheque-status-receipt-link').attr('href', receiptUrl).show();
                $('#opt_receipt_preview_div').show();
            } else {
                $('#opt-cheque-status-receipt-link').attr('href', '#').hide();
                $('#opt_receipt_preview_div').hide();
            }

            loadOptionalChequeDetails(id);
        });

        // Load the full payment detail : one cheque can cover several optional fees
        function loadOptionalChequeDetails(id) {
            $('#opt-cheque-details-wrapper').hide();
            $('#opt-cheque-details-items').empty();
            $('#opt-cheque-multi-note').hide();
            $('#opt-cheque-details-loader').show();
            $('#opt-cheque-modal-subtitle').text("{{ __('review_the_payment_and_set_its_final_status') }}");

            $.ajax({
                url: "{{ route('fees.optional.cheque-details', ':id') }}".replace(':id', id),
                method: 'GET',
                success: function(res) {
                    $('#opt-cheque-details-loader').hide();
                    let data = res.data;
                    if (!data) {
                        return;
                    }

                    // Submit every optional fee paid by this cheque in one go
                    $('#opt-cheque-status-id').val(data.ids);
                    $('#opt-cheque-status-note-modal').val(data.note || '');
                    $('#opt-rejection-reason').val(data.reason || '');
                    setOptionalChequeStatus(data.status);
                    applyOptionalModeLabels(data.mode);

                    let currency = data.currency_symbol || '';
                    let money = function(value) {
                        return currency + ' ' + parseFloat(value || 0).toFixed(2);
                    };
                    let escape = function(value) {
                        return $('<div>').text(value == null ? '' : value).html();
                    };

                    // Manual payments carry no cheque number : they are identified by the
                    // reference of the payment transaction the parent submitted.
                    let isManual = String(data.mode).toLowerCase() === 'manual';
                    let identifier = isManual ? (data.reference || '-') : (data.cheque_no || '-');
                    let identifierLabel = isManual ? "{{ __('payment_reference') }}" :
                        "{{ __('cheque_no') }}";

                    $('#opt-cheque-modal-subtitle').text(identifierLabel + ' ' + identifier + ' · ' + data
                        .fee_name);
                    $('#opt-cheque-detail-no-label').text(identifierLabel);
                    $('#opt-cheque-detail-no').text(identifier);
                    $('#opt-cheque-detail-date').text(data.cheque_date || '-');
                    $('#opt-cheque-detail-mode').text(data.mode || '-');
                    $('#opt-cheque-detail-student').text(data.student_name || '-');
                    $('#opt-cheque-detail-initials').text(optionalChequeInitials(data.student_name));
                    $('#opt-cheque-detail-class').text(data.class_section || '-');
                    $('#opt-cheque-detail-total').text(money(data.grand_total));

                    let pillClass = data.status === 'success' ? 'chq-pill-success' :
                        (data.status === 'bounced' ? 'chq-pill-failed' : 'chq-pill-pending');
                    $('#opt-cheque-detail-status')
                        .attr('class', 'chq-pill ' + pillClass)
                        .text(data.status_label || '-');

                    if (data.parent_name) {
                        $('#opt-cheque-detail-parent').text(data.parent_name);
                        $('#opt-cheque-detail-parent-wrap').show();
                    } else {
                        $('#opt-cheque-detail-parent-wrap').hide();
                    }

                    let rows = '';
                    $.each(data.items || [], function(i, item) {
                        rows += '<div class="chq-fee-row">' +
                            '<span class="chq-fee-index">' + (i + 1) + '</span>' +
                            '<div>' +
                            '<div class="chq-fee-name">' + escape(item.name) + '</div>' +
                            '<div class="chq-fee-due">' + escape(item.status) + '</div>' +
                            '</div>' +
                            '<div class="chq-fee-amounts">' +
                            '<div class="chq-fee-amount">' + money(item.amount) + '</div>' +
                            '</div>' +
                            '</div>';
                    });
                    $('#opt-cheque-details-items').html(rows);
                    $('#opt-cheque-detail-sum-amount').text(money(data.grand_total));

                    if (data.count > 1) {
                        $('#opt-cheque-multi-note').show();
                    }

                    if (data.receipt_url) {
                        $('#opt-cheque-status-receipt-link').attr('href', data.receipt_url).show();
                        $('#opt_receipt_preview_div').show();
                    }

                    $('#opt-cheque-details-wrapper').show();
                },
                error: function(xhr) {
                    $('#opt-cheque-details-loader').hide();
                    console.error(xhr.responseJSON);
                }
            });
        }

        $('#update-optional-cheque-status-form').on('submit', function(e) {
            e.preventDefault();

            let formData = new FormData(this);

            $.ajax({
                url: "{{ route('fees.optional.update-cheque-status') }}",
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    $('#updateOptionalChequeStatusModal').modal('hide');
                    showSuccessToast(res.message || "{{ __('Data Updated Successfully') }}");
                    $('#table_list').bootstrapTable('refresh');
                    $('#update-optional-cheque-status-form')[0].reset();
                    setOptionalChequeStatus('pending');
                    $('#opt-cheque-details-wrapper').hide();
                    $('#opt_receipt_preview_div').hide();
                    $('#opt-cheque-status-receipt-link').attr('href', '#');
                },
                error: function(xhr) {
                    showErrorToast(xhr.responseJSON?.message || "{{ __('An error occurred') }}");
                    console.error(xhr.responseJSON);
                }
            });
        });
    </script>
@endsection
