@extends('layouts.master')

@section('title')
    {{ __('Pay Optional Fees') }}
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/custom/pay_optional.css') }}">
@endsection

@section('content')
    <div class="content-wrapper po-page">
        @php
            $nameParts = explode(' ', $student->full_name);
            $initials = '';
            foreach ($nameParts as $part) {
                $initials .= strtoupper(substr($part, 0, 1));
            }
            $initials = substr($initials, 0, 2);
        @endphp

        <form class="create-form form-validation" method="post" action="{{ route('fees.optional.store') }}"
            novalidate="novalidate" data-success-function="formSuccessFunction">
            {{-- Hidden Fields --}}
            <input type="hidden" name="fees_id" id="optional-fees-id" value="{{ $fees->id }}" />
            <input type="hidden" name="student_id" id="student-id" value="{{ $student->id }}" />
            <input type="hidden" name="class_id" id="class-id"
                value="{{ $student->student->class_section->class_id }}" />

            <div class="row">
                {{-- LEFT : student + fee selection --}}
                <div class="col-xl-8 col-lg-7">
                    <div class="po-hero">
                        <div class="po-avatar">{{ $initials }}</div>
                        <div>
                            <h4 class="po-student-name">{{ $student->full_name }}</h4>
                            <p class="po-student-meta">
                                {{ $student->student->class_section->full_name }} &middot; {{ __('Student') }}
                            </p>
                        </div>
                        <div class="po-hero-tags">
                            <span class="po-tag">{{ $fees->name }}</span>
                            <a href="{{ route('fees.paid.index') }}" class="po-back" title="{{ __('close') }}">
                                <i class="fa fa-times"></i>
                            </a>
                        </div>
                    </div>

                    <div class="po-card">
                        <div class="po-card-head">
                            <h6 class="po-title">
                                <i class="fa fa-puzzle-piece"></i> {{ __('Optional Fees') }}
                            </h6>
                            <span class="po-count"><span id="po-selected-count">0</span> {{ __('selected') }}</span>
                        </div>

                        <ul class="po-fee-list">
                            @foreach ($optionalFeesData as $key => $optionalFee)
                                @php
                                    $latestPaid = $optionalFee->optional_fees_paid->sortByDesc('id')->first();

                                    $isDiscounted =
                                        $optionalFee->net_amount <= 0 && $optionalFee->discount_amount > 0;

                                    $isSuccess =
                                        $latestPaid &&
                                        $latestPaid->status === \App\Models\CompulsoryFee::CHEQUE_SUCCESS;
                                    $isPending =
                                        $latestPaid &&
                                        $latestPaid->status === \App\Models\CompulsoryFee::CHEQUE_PENDING;
                                    $isFailed =
                                        $latestPaid &&
                                        $latestPaid->status === \App\Models\CompulsoryFee::CHEQUE_FAILED;

                                    // Lock checkbox for Success or Pending — Failed re-opens it for retry
                                    $isLocked = $isSuccess || $isPending;
                                @endphp
                                <li class="po-fee {{ $isLocked || $isDiscounted ? 'locked' : '' }}"
                                    data-name="{{ $optionalFee->fees_type_name }}"
                                    onclick="toggleCheckbox('optional-{{ $optionalFee->id }}')">
                                    <input type="checkbox"
                                        class="po-fee-check optional-fee-payment {{ $isLocked || $isDiscounted ? 'd-none' : '' }}"
                                        id="optional-{{ $optionalFee->id }}"
                                        data-amount="{{ $optionalFee->net_amount }}"
                                        data-name="{{ $optionalFee->fees_type_name }}"
                                        name="fees_class_type[{{ $key }}][id]" value="{{ $optionalFee->id }}"
                                        onclick="event.stopPropagation()"
                                        {{ $isLocked || $isDiscounted ? 'disabled' : '' }}>

                                    <div class="po-fee-body">
                                        <label for="optional-{{ $optionalFee->id }}" class="po-fee-name mb-0"
                                            style="cursor: inherit;" onclick="event.stopPropagation()">
                                            {{ $optionalFee->fees_type_name }}
                                        </label>

                                        @if ($optionalFee->discount_amount > 0)
                                            <p class="po-fee-sub">
                                                <span
                                                    class="po-fee-strike">{{ number_format($optionalFee->amount, 2) . ' ' . $currencySymbol }}</span>
                                                {{ __('relief') }}:
                                                -{{ number_format($optionalFee->discount_amount, 2) . ' ' . $currencySymbol }}
                                            </p>
                                        @endif

                                        @if ($isSuccess || $isPending)
                                            <span data-id="{{ $latestPaid->id }}"
                                                class="remove-paid-optional-fees po-remove"
                                                onclick="removeFee(event, this)">
                                                <i class="fa fa-trash"></i>
                                                {{ $isSuccess ? __('Remove') : __('Cancel') }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="po-fee-right">
                                        @if ($isSuccess)
                                            <span class="po-badge po-badge-paid">{{ __('Paid') }}</span>
                                        @elseif ($isPending)
                                            <span class="po-badge po-badge-pending">{{ __('Pending') }}</span>
                                        @elseif ($isFailed)
                                            <span
                                                class="po-badge po-badge-failed">{{ __('Bounced - Retry Payment') }}</span>
                                        @endif

                                        @if ($isDiscounted)
                                            <span class="po-badge po-badge-paid">{{ __('Discounted') }}</span>
                                        @endif

                                        <div class="po-fee-amount">
                                            {{ number_format($optionalFee->net_amount, 2) . ' ' . $currencySymbol }}
                                        </div>
                                        {!! Form::hidden('fees_class_type[' . $key . '][amount]', $optionalFee->net_amount) !!}
                                    </div>
                                </li>
                            @endforeach
                        </ul>

                        {{-- Empty State Message --}}
                        <div class="po-empty" id="no-fees-message">
                            <i class="fa fa-clipboard fa-2x mb-3 d-block"></i>
                            <p class="mb-0">{{ __('No optional fees available.') }}</p>
                        </div>
                    </div>
                </div>

                {{-- RIGHT : payment panel --}}
                <div class="col-xl-4 col-lg-5">
                    <div class="po-panel">
                        <div class="po-panel-head">
                            <i class="fa fa-money"></i> {{ __('process_payment') }}
                        </div>

                        <div class="po-panel-body">
                            {{-- Date --}}
                            <div class="form-group">
                                <label class="po-label" for="payment-date">{{ __('Date') }} <span
                                        class="text-danger">*</span></label>
                                <input id="payment-date" type="text" name="date"
                                    class="datepicker-popup paid-date form-control-custom"
                                    placeholder="{{ __('Select Date') }}" autocomplete="off" required>
                            </div>

                            {{-- Selected fees summary --}}
                            <label class="po-label">{{ __('selected_fees') }}</label>
                            <div class="po-summary">
                                <div id="po-summary-list"></div>
                                <div class="po-summary-empty" id="po-summary-empty">
                                    {{ __('No optional fee selected yet.') }}
                                </div>
                                <div class="po-summary-total">
                                    <span>{{ __('Total Amount To Pay') }}</span>
                                    <span class="po-amount" id="optional-total-amount">
                                        {{ $currencySymbol }} 0.00
                                    </span>
                                </div>
                                {!! Form::hidden('total_amount', null, ['id' => 'form-total-optional-amount']) !!}
                            </div>

                            {{-- Payment Mode --}}
                            <div class="form-group">
                                <label class="po-label">{{ __('Payment Mode') }} <span
                                        class="text-danger">*</span></label>
                                <div class="po-methods">
                                    <label class="po-method active" id="mode-cash" onclick="selectMode('cash')">
                                        <input type="radio" name="mode" class="cash-compulsory-mode mode"
                                            value="1" checked id="radio-cash">
                                        <i class="fa fa-dollar"></i>{{ __('Cash') }}
                                    </label>
                                    <label class="po-method" id="mode-cheque" onclick="selectMode('cheque')">
                                        <input type="radio" name="mode" class="cheque-compulsory-mode mode"
                                            value="2" id="radio-cheque">
                                        <i class="fa fa-credit-card"></i>{{ __('Cheque') }}
                                    </label>
                                </div>
                            </div>

                            {{-- Cheque Fields --}}
                            <div class="cheque-no-container" style="display: none">
                                <div class="po-hint">
                                    <i class="fa fa-info-circle"></i>
                                    <span>{{ __('this_status_will_be_applied_to_all_fees_paid_by_this_cheque') }}</span>
                                </div>

                                <div class="form-group">
                                    <label class="po-label" for="cheque_no">
                                        {{ __('Cheque Number') }} <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" id="cheque_no" name="cheque_no"
                                        placeholder="{{ __('Enter Cheque Number') }}"
                                        class="form-control-custom cheque-no" />
                                </div>

                                <div class="form-group">
                                    <label class="po-label">{{ __('Status') }} <span
                                            class="text-danger">*</span></label>
                                    <input type="hidden" name="status" id="opt-pay-status-value" value="pending">
                                    <div class="po-status-options">
                                        <label class="po-status-opt active" data-value="pending">
                                            <i class="fa fa-hourglass-half"></i> {{ __('under_review') }}
                                        </label>
                                        <label class="po-status-opt" data-value="success">
                                            <i class="fa fa-check"></i> {{ __('Paid') }}
                                        </label>
                                        <label class="po-status-opt" data-value="bounced">
                                            <i class="fa fa-times"></i> {{ __('Bounced') }}
                                        </label>
                                    </div>
                                </div>

                                {{-- Rejection Reason (shown only when Bounced is selected) --}}
                                <div class="form-group" id="opt-rejection-reason-div" style="display: none;">
                                    <label class="po-label" for="opt-rejection-reason">
                                        {{ __('Rejection Reason') }} <span class="text-danger">*</span>
                                    </label>
                                    <textarea name="rejection_reason" id="opt-rejection-reason" rows="2" class="form-control"
                                        placeholder="{{ __('enter_rejection_reason_or_note') }}"></textarea>
                                </div>

                                <div class="form-group">
                                    <label class="po-label" for="opt-cheque-status-note">{{ __('Note') }}</label>
                                    <textarea name="note" id="opt-cheque-status-note" rows="2" class="form-control"
                                        placeholder="{{ __('Note') }}"></textarea>
                                </div>
                            </div>

                            {{-- Submit Button --}}
                            <button class="po-pay" type="submit" id="pay-button" disabled>
                                <i class="fa fa-lock mr-1"></i> {{ __('Pay Now') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('js')
    <script>
        const poCurrency = "{{ $currencySymbol }}";

        // Init Datepicker
        $('#payment-date').datepicker({
            format: "dd-mm-yyyy",
            rtl: isRTL()
        }).datepicker("setDate", 'now');

        // Check if there are fees on load
        checkEmptyState();

        // Logic for total calculation
        let totalAmount = 0;
        $('.optional-fee-payment').on('change', function() {
            updateTotal();
        });

        function toggleCheckbox(id) {
            let checkbox = $('#' + id);
            if (!checkbox.length || checkbox.is(':disabled')) return; // Don't toggle if disabled (paid)
            checkbox.prop('checked', !checkbox.prop('checked'));
            updateTotal();
        }

        function poMoney(value) {
            return poCurrency + ' ' + parseFloat(value || 0).toFixed(2);
        }

        function updateTotal() {
            totalAmount = 0;
            let rows = '';
            let count = 0;

            $('.optional-fee-payment').each(function() {
                if ($(this).is(':checked') && !$(this).is(':disabled')) {
                    let amount = parseFloat($(this).data("amount")) || 0;
                    totalAmount += amount;
                    count++;

                    rows += '<div class="po-summary-row">' +
                        '<span>' + $('<div>').text($(this).data('name') || '').html() + '</span>' +
                        '<strong>' + poMoney(amount) + '</strong>' +
                        '</div>';

                    $(this).closest('.po-fee').addClass('selected');
                } else {
                    $(this).closest('.po-fee').removeClass('selected');
                }
            });

            $('#po-selected-count').text(count);
            $('#po-summary-list').html(rows);
            $('#po-summary-empty').toggle(count === 0);
            $('#optional-total-amount').text(poMoney(totalAmount));
            $('#form-total-optional-amount').val(totalAmount);

            if (totalAmount > 0) {
                $('#pay-button').removeAttr('disabled');
            } else {
                $('#pay-button').attr('disabled', true);
            }
        }

        // Remove Fee Functionality
        function removeFee(e, element) {
            e.stopPropagation(); // Prevent row click
            e.preventDefault();

            let id = $(element).data('id');
            let row = $(element).closest('.po-fee');
            let removeBtn = $(element);
            let checkbox = row.find('.optional-fee-payment');

            Swal.fire({
                    title: "{{ __('Are you sure?') }}",
                    text: "{{ __('You want to remove this fees?') }}",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: "{{ __('Yes, remove it!') }}"
                })
                .then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "{{ route('fees.paid.remove.optional.fees', '') }}/" + id,
                            type: "DELETE",
                            success: function(response) {
                                if (!response.error) {
                                    // Dynamic UI Reset instead of row removal
                                    removeBtn.fadeOut(300, function() {
                                        $(this).remove(); // Remove button from DOM

                                        row.removeClass('locked');
                                        row.find('.po-badge').remove();

                                        // Reset checkbox state
                                        checkbox.removeClass('d-none')
                                            .prop('disabled', false)
                                            .prop('checked', false)
                                            .hide()
                                            .fadeIn(300);

                                        // Recalculate total (shouldn't change, but ensuring consistency)
                                        updateTotal();
                                    });

                                    $.toast({
                                        text: response.message,
                                        showHideTransition: 'slide',
                                        icon: 'success',
                                        loaderBg: '#f96868',
                                        position: 'top-right'
                                    });
                                } else {
                                    $.toast({
                                        text: response.message,
                                        showHideTransition: 'slide',
                                        icon: 'error',
                                        loaderBg: '#f2a654',
                                        position: 'top-right'
                                    });
                                }
                            },
                            error: function(xhr) {
                                $.toast({
                                    text: "{{ __('Something went wrong') }}",
                                    showHideTransition: 'slide',
                                    icon: 'error',
                                    loaderBg: '#f2a654',
                                    position: 'top-right'
                                });
                            }
                        });
                    }
                });
        }

        function checkEmptyState() {
            if ($('.po-fee').length === 0) {
                $('#no-fees-message').show();
            } else {
                $('#no-fees-message').hide();
            }
        }

        function selectMode(mode) {
            $('.po-method').removeClass('active');
            $('#mode-' + mode).addClass('active');

            if (mode === 'cheque') {
                $('#radio-cheque').prop('checked', true);
                $('#cheque_no').attr('required', true);
                $('.cheque-no-container').slideDown();
            } else {
                $('#radio-cash').prop('checked', true);
                $('#cheque_no').removeAttr('required');
                $('.cheque-no-container').slideUp();
                // Reset rejection reason when switching away from cheque
                setOptionalPayStatus('pending');
            }
        }

        // Cheque status choice (segmented control)
        function setOptionalPayStatus(status) {
            status = status || 'pending';
            $('.po-status-opt').removeClass('active');
            $('.po-status-opt[data-value="' + status + '"]').addClass('active');
            $('#opt-pay-status-value').val(status);

            if (status === 'bounced') {
                $('#opt-rejection-reason-div').show();
                $('#opt-rejection-reason').attr('required', true);
            } else {
                $('#opt-rejection-reason-div').hide();
                $('#opt-rejection-reason').removeAttr('required').val('');
            }
        }

        $(document).on('click', '.po-status-opt', function() {
            setOptionalPayStatus($(this).data('value'));
        });

        function formSuccessFunction() {
            setTimeout(function() {
                window.location.reload();
            }, 1000)
        }
    </script>
@endsection
