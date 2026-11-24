@extends('layouts.master')

@section('title')
    {{ __('manage_fees_paid') }}
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/custom/cheque_status_modal.css') }}">
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('manage_fees_paid') }}
            </h3>
        </div>
        <div class="row">
            {{-- Total Fees --}}
            <div class="col-md-4 col-sm-12 grid-margin stretch-card">
                <div class="card card-statistics">
                    <div class="custom-card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-6">
                                <p class="font-weight-bold">{{ __('total_fees') }}</p>
                                <div class="d-flex align-items-center">
                                    <h4 class="font-weight-semibold total_fees_statistics">0</h4>
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-6 border-left text-right">
                                <p class="text-muted mt-2">{{ __('compulsory_fees') }} : <span
                                        class="total_compulsory_fees">0</span></p>
                                <p class="text-muted mb-0">{{ __('optional_fees') }} : <span
                                        class="total_optional_fees">0</span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{-- Total Collected Fees --}}
            <div class="col-md-4 col-sm-12 grid-margin stretch-card">
                <div class="card card-statistics">
                    <div class="custom-card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-6">
                                <p class="font-weight-bold"> {{ __('collected_fees') }}</p>
                                <div class="d-flex align-items-center">
                                    <h4 class="font-weight-semibold total_fees_collected">0</h4>
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-6 border-left text-right">
                                <p class="text-muted mt-2">{{ __('compulsory_fees') }} : <span
                                        class="total_compulsory_fees_collected">0</span></p>
                                <p class="text-muted mb-0">{{ __('optional_fees') }} : <span
                                        class="total_optional_fees_collected">0</span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{-- Total Pending Fees --}}
            <div class="col-md-4 col-sm-12 grid-margin stretch-card">
                <div class="card card-statistics">
                    <div class="custom-card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-6">
                                <p class="font-weight-bold"> {{ __('pending_fees') }}</p>
                                <div class="d-flex align-items-center">
                                    <h4 class="font-weight-semibold total_fees_pending">0</h4>
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-6 border-left text-right">
                                <p class="text-muted mt-2">{{ __('compulsory_fees') }} : <span
                                        class="total_compulsory_fees_pending">0</span></p>
                                <p class="text-muted mb-0">{{ __('optional_fees') }} : <span
                                        class="total_optional_fees_pending">0</span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title"></h4>
                        <div id="toolbar">
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label class="filter-menu" for="filter_fees_id">{{ __('Fees') }}</label>
                                    <select name="filter_fees_id" id="filter_fees_id"
                                        class="form-control custom-search-select">
                                        @foreach ($fees as $key => $fee)
                                            <option value="{{ $fee->id }}"
                                                data-class-section-id="{{ $fee->class_id }}"
                                                {{ $key == 0 ? 'selected' : '' }}>
                                                {{ $fee->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="filter-class-section-id"
                                        class="filter-menu">{{ __('Class Section') }}</label>
                                    <select name="filter-class-section-id" id="filter-class-section-id"
                                        class="form-control custom-search-select">
                                        <option value="">{{ __('all') }}</option>
                                        @foreach ($class_section as $class)
                                            <option value="{{ $class->id }}"
                                                data-class-section-id="{{ $class->class_id }}">
                                                {{ $class->full_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-3">
                                    <label class="filter-menu" for="filter_paid_status"> {{ __('status') }} </label>
                                    <select name="filter_paid_status" id="filter_paid_status"
                                        class="form-control custom-search-select">
                                        <option value="-1">{{ __('all') }}</option>
                                        <option value="0">{{ __('unpaid') }}</option>
                                        <option value="1">{{ __('paid') }}</option>
                                        <option value="2">{{ __('Partial Paid') }}</option>

                                    </select>
                                </div>

                            </div>

                            {{-- Paid filter --}}
                            <div class="row paid-filter" style="display: none">
                                <div class="form-group col-md-3">
                                    <label class="filter-menu" for="filter_paid_status"> {{ __('month') }} </label>
                                    {!! Form::select('month', $months, date('n'), [
                                        'class' => 'form-control custom-search-select paid-month',
                                        'placeholder' => __('all'),
                                    ]) !!}
                                </div>

                                {{-- <div class="form-group col-md-3">
                                    <label for="filter_online_offline_payment"
                                        class="filter-menu">{{ __('online_offline_payment') }}</label>
                                    <select name="filter_online_offline_payment" id="filter_online_offline_payment"
                                        class="form-control custom-search-select select2">
                                        <option value="0">{{ __('all') }}</option>
                                        <option value="1">{{ __('online') }}</option>
                                        <option value="2">{{ __('offline') }}</option>
                                    </select>
                                </div> --}}

                                <div class="form-group col-md-3">
                                    <label for="filter_online_offline_payment"
                                        class="filter-menu">{{ __('payment_method') }}</label>
                                    <select name="filter_online_offline_payment" id="filter_online_offline_payment"
                                        class="form-control custom-search-select select2">
                                        <option value="0">{{ __('all') }}</option>
                                        <option value="Cash">{{ __('cash') }}</option>
                                        <option value="Cheque">{{ __('cheque') }}</option>
                                        <option value="Manual">{{ __('manual') }}</option>
                                        <option value="Stripe">{{ __('stripe') }}</option>
                                        <option value="Razorpay">{{ __('razorpay') }}</option>
                                        <option value="Flutterwave">{{ __('flutterwave') }}</option>
                                        <option value="Paystack">{{ __('paystack') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                            data-url="{{ route('fees.paid.list', 1) }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true"
                            data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                            data-show-columns="true" data-show-refresh="true" data-fixed-columns="false"
                            data-trim-on-search="false" data-mobile-responsive="true" data-sort-name="id"
                            data-sort-order="desc" data-maintain-selected="true" data-export-data-type='all'
                            data-export-options='{ "fileName": "{{ __('fees') }}-{{ __('paid') }}-{{ __('list') }}-<?= date('d-m-y')
                            ?>" ,"ignoreColumn":["operate"]}'
                            data-show-export="true" data-query-params="feesPaidListQueryParams" data-escape="true">

                            <thead>
                                <tr>

                                    <th scope="col" data-field="id" data-sortable="true" data-visible="false"
                                        data-align="center">{{ __('id') }}</th>
                                    <th scope="col" data-field="no" data-formatter="totalFeesFormatter"
                                        data-sortable="false" data-align="center">{{ __('no.') }}</th>
                                    <th scope="col" data-field="student.id" data-sortable="false"
                                        data-visible="false" data-align="center">{{ __('Student Id') }}</th>
                                    <th scope="col" data-field="full_name"
                                        data-formatter="NotificationUserNameFormatter" data-sortable="false">
                                        {{ __('Student Name') }}
                                    </th>
                                    <th scope="col" data-field="student.class_section.full_name" data-sortable="false"
                                        data-align="center">{{ __('Class') }}</th>
                                    <th scope="col" data-field="fees.total_compulsory_fees" data-sortable="false"
                                        data-align="center">{{ __('Compulsory Fees') }}</th>
                                    <th scope="col" data-field="fees.total_optional_fees" data-sortable="false"
                                        data-align="center">{{ __('Optional Fees') }}</th>
                                    <th scope="col" data-field="payment_method" data-sortable="false"
                                        data-align="center"> {{ __('Payment Method') }}</th>
                                    <th scope="col" data-field="fees_status" data-sortable="false"
                                        data-formatter="feesPaidStatusFormatter" data-align="center">
                                        {{ __('Fees Status') }}
                                    </th>
                                    <th scope="col" data-field="fees_paid.date" data-sortable="false"
                                        data-align="center">{{ __('Date') }}</th>
                                    <th scope="col" data-field="paid_amount" data-sortable="false">
                                        {{ __('paid_amount') }}
                                    </th>
                                    <th scope="col" data-field="due_charges" data-sortable="false">
                                        {{ __('due_charges') }}
                                    </th>
                                    <th scope="col" data-field="operate" data-sortable="false"
                                        data-events="feesPaidEvents" data-align="center" data-escape="false">
                                        {{ __('Action') }}
                                    </th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade chq-modal" id="updateChequeStatusModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="chq-header">
                    <div class="chq-header-icon">
                        <i class="fa fa-credit-card"></i>
                    </div>
                    <div class="chq-header-text">
                        <h5 id="cheque-modal-title">{{ __('update_cheque_status') }}</h5>
                        <p class="chq-header-sub" id="cheque-modal-subtitle">
                            {{ __('review_the_payment_and_set_its_final_status') }}
                        </p>
                    </div>
                    <button type="button" class="chq-close" data-dismiss="modal" data-bs-dismiss="modal"
                        aria-label="Close">
                        <i class="fa fa-close"></i>
                    </button>
                </div>

                <form id="update-cheque-status-form">
                    @csrf
                    <input type="hidden" name="id" id="cheque-status-id">
                    <input type="hidden" name="status" id="cheque-status-value" value="pending">

                    <div class="chq-body">
                        <div id="cheque-details-loader" class="chq-loader" style="display:none">
                            <i class="fa fa-spinner fa-spin"></i> {{ __('loading') }}
                        </div>

                        {{-- Payment summary : every fee/installment covered by this cheque --}}
                        <div id="cheque-details-wrapper" style="display:none">
                            <div class="chq-card">
                                <div class="chq-student">
                                    <div class="chq-avatar" id="cheque-detail-initials">-</div>
                                    <div>
                                        <div class="chq-student-name" id="cheque-detail-student">-</div>
                                        <p class="chq-student-meta">
                                            <span id="cheque-detail-class">-</span>
                                            <span id="cheque-detail-parent-wrap">
                                                &middot; {{ __('parent_name') }}:
                                                <span id="cheque-detail-parent">-</span>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="chq-total">
                                        <span class="chq-label">{{ __('total_amount') }}</span>
                                        <div class="chq-total-value" id="cheque-detail-total">-</div>
                                    </div>
                                </div>

                                <div class="chq-meta-grid">
                                    <div>
                                        <span class="chq-label" id="cheque-detail-no-label">{{ __('cheque_no') }}</span>
                                        <div class="chq-value" id="cheque-detail-no">-</div>
                                    </div>
                                    <div>
                                        <span class="chq-label">{{ __('payment_date') }}</span>
                                        <div class="chq-value" id="cheque-detail-date">-</div>
                                    </div>
                                    <div>
                                        <span class="chq-label">{{ __('payment_method') }}</span>
                                        <div class="chq-value" id="cheque-detail-mode">-</div>
                                    </div>
                                    <div>
                                        <span class="chq-label">{{ __('current_status') }}</span>
                                        <div class="chq-value">
                                            <span class="chq-pill chq-pill-pending" id="cheque-detail-status">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="chq-card">
                                <h6 class="chq-section-title">
                                    <i class="fa fa-list-alt"></i>
                                    <span id="cheque-covered-title">{{ __('fees_covered_by_this_cheque') }}</span>
                                </h6>

                                <div id="cheque-details-items"></div>

                                <div class="chq-fee-total">
                                    <span>{{ __('total') }}</span>
                                    <span id="cheque-detail-sum-amount">-</span>
                                </div>

                                <div class="chq-hint" id="cheque-multi-note" style="display:none">
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
                                        <span class="chq-status-label-pending">{{ __('under_review') }}</span>
                                    </label>
                                    <label class="chq-status-opt" data-value="success">
                                        <i class="fa fa-check"></i>
                                        <span class="chq-status-label-success">{{ __('paid') }}</span>
                                    </label>
                                    <label class="chq-status-opt" data-value="bounced">
                                        <i class="fa fa-times"></i>
                                        <span class="chq-status-label-bounced">{{ __('bounced') }}</span>
                                    </label>
                                </div>
                            </div>

                            {{-- Rejection Reason Field (Displays when Bounced / Rejected is selected) --}}
                            <div class="form-group" id="rejection_reason_div" style="display:none;">
                                <span class="chq-label">{{ __('Reason for Rejection') }} <span
                                        class="text-danger">*</span></span>
                                <textarea name="reason" id="cheque-status-reason" class="form-control" rows="2"
                                    placeholder="{{ __('Enter rejection reason') }}"></textarea>
                            </div>

                            <div class="form-group mb-0">
                                <span class="chq-label">{{ __('note') }}</span>
                                <textarea name="note" id="cheque-status-note" class="form-control" rows="2"
                                    placeholder="{{ __('note') }}"></textarea>
                            </div>

                            {{-- Uploaded Receipt Viewer (Read-only for Admin) --}}
                            <div class="mt-3" id="receipt_preview_div" style="display:none">
                                <span class="chq-label">{{ __('Uploaded Payment Receipt') }}</span>
                                <a id="cheque-status-receipt-link" href="#" target="_blank"
                                    class="chq-receipt-link">
                                    <i class="fa fa-paperclip"></i> {{ __('View Full Receipt') }}
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
        window.customSelectTranslations = {
            // chooseOption: "{{ __('choose_an_option') }}",
            searchHere: "{{ __('search_here') }}",
            noMatchingOptions: "{{ __('no_matching_options_found') }}"
        };
        $('#filter_paid_status').change(function(e) {
            e.preventDefault();
            $('.paid-filter').hide(500);

            if ($(this).val() == 1 || $(this).val() == 2) {
                $('.paid-filter').show(500);
            }
        });

        $('#table_list').on('load-success.bs.table', function(e, data) {
            if (data.total === 0) {
                $('.total_fees_statistics').html(0);
                $('.total_compulsory_fees').html(0);
                $('.total_optional_fees').html(0);
                $('.total_fees_collected').html(0);
                $('.total_compulsory_fees_collected').html(0);
                $('.total_optional_fees_collected').html(0);
                $('.total_fees_pending').html(0);
                $('.total_compulsory_fees_pending').html(0);
                $('.total_optional_fees_pending').html(0);
            }
        });

        // ---- Status choice (segmented control) ----
        function setChequeStatus(status) {
            status = status || 'pending';
            $('.chq-status-opt').removeClass('active');
            $('.chq-status-opt[data-value="' + status + '"]').addClass('active');
            $('#cheque-status-value').val(status);

            if (status === 'bounced') {
                $('#rejection_reason_div').show();
                $('#cheque-status-reason').attr('required', true);
            } else {
                $('#rejection_reason_div').hide();
                $('#cheque-status-reason').removeAttr('required');
            }
        }

        $(document).on('click', '.chq-status-opt', function() {
            setChequeStatus($(this).data('value'));
        });

        // Manual payments use approval wording, cheques use cheque wording
        function applyChequeModeLabels(mode) {
            if (mode === 'Manual') {
                $('#cheque-modal-title').text("{{ __('update_payment_status') }}");
                $('#cheque-covered-title').text("{{ __('payment_details') }}");
                $('.chq-status-label-success').text("{{ __('approved') }}");
                $('.chq-status-label-bounced').text("{{ __('reject') }}");
                $('.chq-header-icon i').attr('class', 'fa fa-file-text');
            } else {
                $('#cheque-modal-title').text("{{ __('update_cheque_status') }}");
                $('#cheque-covered-title').text("{{ __('fees_covered_by_this_cheque') }}");
                $('.chq-status-label-success').text("{{ __('paid') }}");
                $('.chq-status-label-bounced').text("{{ __('bounced') }}");
                $('.chq-header-icon i').attr('class', 'fa fa-credit-card');
            }
        }

        // Populate Modal on click
        $(document).on(
            'click',
            '.update-optional-cheque-status-btn, .update-cheque-status-btn',
            function() {
                $('#cheque-status-id').val($(this).data('id'));
                $('#cheque-status-note').val($(this).data('note') || '');
                $('#cheque-status-reason').val($(this).data('reason') || '');
                setChequeStatus($(this).data('status'));
                applyChequeModeLabels($(this).attr('data-mode'));

                let receiptUrl = $(this).attr('data-receipt-url');

                if (receiptUrl && receiptUrl !== '' && receiptUrl !== 'null') {
                    $('#receipt_preview_div').show();
                    $('#cheque-status-receipt-link').attr('href', receiptUrl);
                } else {
                    $('#receipt_preview_div').hide();
                    $('#cheque-status-receipt-link').attr('href', '#');
                }

                loadChequePaymentDetails($(this).data('id'));
            }
        );

        function chequeInitials(name) {
            return (name || '')
                .split(' ')
                .filter(Boolean)
                .map(function(part) {
                    return part.charAt(0).toUpperCase();
                })
                .join('')
                .substring(0, 2) || '-';
        }

        // Load the full payment detail : one cheque can cover several installments
        function loadChequePaymentDetails(id) {
            $('#cheque-details-wrapper').hide();
            $('#cheque-details-items').empty();
            $('#cheque-multi-note').hide();
            $('#cheque-details-loader').show();
            $('#cheque-modal-subtitle').text("{{ __('review_the_payment_and_set_its_final_status') }}");

            $.ajax({
                url: "{{ route('fees.paid.cheque-details', ':id') }}".replace(':id', id),
                method: 'GET',
                success: function(res) {
                    $('#cheque-details-loader').hide();
                    let data = res.data;
                    if (!data) {
                        return;
                    }

                    // Submit every fee row paid by this cheque in one go
                    $('#cheque-status-id').val(data.ids);
                    $('#cheque-status-note').val(data.note || '');
                    $('#cheque-status-reason').val(data.reason || '');
                    setChequeStatus(data.status);
                    applyChequeModeLabels(data.mode);

                    let currency = data.currency_symbol || '';
                    let money = function(value) {
                        return currency + ' ' + parseFloat(value || 0).toFixed(2);
                    };
                    let escape = function(value) {
                        return $('<div>').text(value == null ? '' : value).html();
                    };

                    // Manual payments carry no cheque number : they are identified by the
                    // reference of the payment transaction the parent submitted.
                    let isManual = data.mode === 'Manual';
                    let identifier = isManual ? (data.reference || '-') : (data.cheque_no || '-');
                    let identifierLabel = isManual ? "{{ __('payment_reference') }}" :
                        "{{ __('cheque_no') }}";

                    $('#cheque-modal-subtitle').text(identifierLabel + ' ' + identifier + ' · ' + data
                    .fee_name);
                    $('#cheque-detail-no-label').text(identifierLabel);
                    $('#cheque-detail-no').text(identifier);
                    $('#cheque-detail-date').text(data.cheque_date || '-');
                    $('#cheque-detail-mode').text(data.mode || '-');
                    $('#cheque-detail-student').text(data.student_name || '-');
                    $('#cheque-detail-initials').text(chequeInitials(data.student_name));
                    $('#cheque-detail-class').text(data.class_section || '-');
                    $('#cheque-detail-total').text(money(data.grand_total));

                    let pillClass = data.status === 'success' ? 'chq-pill-success' :
                        (data.status === 'bounced' ? 'chq-pill-failed' : 'chq-pill-pending');
                    $('#cheque-detail-status')
                        .attr('class', 'chq-pill ' + pillClass)
                        .text(data.status_label || '-');

                    if (data.parent_name) {
                        $('#cheque-detail-parent').text(data.parent_name);
                        $('#cheque-detail-parent-wrap').show();
                    } else {
                        $('#cheque-detail-parent-wrap').hide();
                    }

                    let rows = '';
                    $.each(data.items || [], function(i, item) {
                        rows += '<div class="chq-fee-row">' +
                            '<span class="chq-fee-index">' + (i + 1) + '</span>' +
                            '<div>' +
                            '<div class="chq-fee-name">' + escape(item.name) + '</div>' +
                            '<div class="chq-fee-due"><i class="fa fa-calendar mr-1"></i>' +
                            "{{ __('due_date') }}: " + escape(item.due_date) + '</div>' +
                            '</div>' +
                            '<div class="chq-fee-amounts">' +
                            '<div class="chq-fee-amount">' + money(item.amount) + '</div>' +
                            (parseFloat(item.due_charges) > 0 ?
                                '<div class="chq-fee-charge">+ ' + money(item.due_charges) + ' ' +
                                "{{ __('due_charges') }}" + '</div>' : '') +
                            '</div>' +
                            '</div>';
                    });
                    $('#cheque-details-items').html(rows);
                    $('#cheque-detail-sum-amount').text(money(data.grand_total));

                    if (data.count > 1) {
                        $('#cheque-multi-note').show();
                    }

                    if (data.receipt_url) {
                        $('#receipt_preview_div').show();
                        $('#cheque-status-receipt-link').attr('href', data.receipt_url);
                    }

                    $('#cheque-details-wrapper').show();
                },
                error: function(xhr) {
                    $('#cheque-details-loader').hide();
                    console.error(xhr.responseJSON);
                }
            });
        }
        // ---- 2. SUBMIT FORM VIA AJAX ----
        $('#update-cheque-status-form').on('submit', function(e) {
            e.preventDefault();

            $.ajax({
                url: "{{ route('fees.paid.update-cheque-status') }}",
                method: 'POST',
                data: $(this).serialize(),
                success: function(res) {
                    $('#updateChequeStatusModal').modal('hide');
                    showSuccessToast(res.message || "{{ __('Data Updated Successfully') }}");
                    $('#table_list').bootstrapTable('refresh');
                    $('#update-cheque-status-form')[0].reset();
                    setChequeStatus('pending');
                    $('#cheque-details-wrapper').hide();
                    $('#receipt_preview_div').hide();
                    $('#cheque-status-receipt-link').attr('href', '#');
                },
                error: function(xhr) {
                    showErrorToast(xhr.responseJSON?.message || "{{ __('An error occurred') }}");
                    console.error(xhr.responseJSON);
                }
            });
        });
    </script>
@endsection
