@extends('layouts.master')

@section('title')
    {{ __('pay_compulsory_fees') }}
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/custom/pay_compulsory.css') }}">
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('pay_compulsory_fees') }}
            </h3>
        </div>

        <div class="row">
            <div class="col-md-12">

                {{-- === PHP Calculations === --}}
                @php
                    $total_fee = $fees->total_compulsory_fees;
                    // Compute confirmed paid across all modes (Cash, Cheque, Online, Manual)
                    $successStatuses = [\App\Models\CompulsoryFee::CHEQUE_SUCCESS, 'Success', 1, '1'];
                    $total_paid = $student->compulsory_fees
                        ->whereNull('deleted_at')
                        ->whereIn('status', $successStatuses)
                        ->sum('amount');
                    $paid_due = $student->compulsory_fees
                        ->whereNull('deleted_at')
                        ->whereIn('status', $successStatuses)
                        ->sum('due_charges');
                    $percentage = $net_payable_fee > 0 ? round(($total_paid / $net_payable_fee) * 100) : 100;
                    $percentage = min(100, $percentage);
                    if ($fees->include_fee_installments) {
                        $global_balance = $fees->installments->sum('balance_due');
                    } else {
                        $remaining_base = max(0, $net_payable_fee - $total_paid);
                        $remaining_due = max(0, $due_charges - $paid_due);
                        $global_balance = $remaining_base + $remaining_due;
                    }
                    // Installment counts
                    $total_installments = count($fees->installments);
                    $paid_installments = 0;
                    $unpaid_installments = 0;
                    if ($fees->include_fee_installments) {
                        foreach ($fees->installments as $inst) {
                            if (($inst->status ?? 0) == 1 || ($inst->status ?? 0) == 3) {
                                $paid_installments++;
                            }
                        }
                        $unpaid_installments = $total_installments - $paid_installments;
                    }

                    // Next due date
                    $next_due_date = null;
                    if ($fees->include_fee_installments) {
                        foreach ($fees->installments as $inst) {
                            if (($inst->status ?? 0) == 0 || ($inst->status ?? 0) == 2) {
                                $next_due_date = date('d M Y', strtotime($inst->due_date));
                                break;
                            }
                        }
                    } else {
                        $next_due_date = $fees->due_date;
                    }

                    // Student initials
                    $nameParts = explode(' ', $student->full_name);
                    $initials = '';
                    foreach ($nameParts as $part) {
                        $initials .= strtoupper(substr($part, 0, 1));
                    }
                    $initials = substr($initials, 0, 2);

                    // Check for submitted cheque or manual transaction that is NOT yet Success
                    $pendingTxns = $student->compulsory_fees
                        ->whereIn('mode', ['Cheque', 'Manual', '2'])
                        ->where('status', \App\Models\CompulsoryFee::CHEQUE_PENDING)
                        ->whereNull('deleted_at');
                    $pendingChequeTxn = $pendingTxns->first();
                    $hasPendingCheque = (bool) $pendingChequeTxn;
                    $isManualPending = $hasPendingCheque && $pendingChequeTxn && $pendingChequeTxn->mode === 'Manual';

                    // A single cheque can cover several installments : reconcile them together
                    if ($hasPendingCheque && $pendingChequeTxn->mode === 'Cheque' && !empty($pendingChequeTxn->cheque_no)) {
                        $chequeGroupTxns = $pendingTxns
                            ->where('mode', 'Cheque')
                            ->where('cheque_no', $pendingChequeTxn->cheque_no)
                            ->values();
                    } else {
                        $chequeGroupTxns = collect([$pendingChequeTxn])->filter()->values();
                    }
                    $pendingGroupIds = $chequeGroupTxns->pluck('id')->implode(',');
                    $pendingGroupTotal = $chequeGroupTxns->sum(fn($txn) => $txn->amount + $txn->due_charges);

                    // Payment proof URL resolution
                    $proofUrl = null;
                    if ($hasPendingCheque && $pendingChequeTxn) {
                        $proofUrl =
                            $pendingChequeTxn->receipt_url ??
                            ($pendingChequeTxn->file ? $pendingChequeTxn->file->file_url : null);
                    }
                @endphp

                <form class="create-form form-validation" method="post"
                    action="{{ $hasPendingCheque ? route('fees.paid.update-cheque-status') : route('fees.compulsory.store') }}"
                    data-success-function="successFunction" novalidate="novalidate">

                    @if ($hasPendingCheque && $pendingChequeTxn)
                        {{-- Every fee row paid by this cheque is updated in one action --}}
                        <input type="hidden" name="id" value="{{ $pendingGroupIds }}" />
                    @endif

                    <input type="hidden" name="fees_id" id="compulsory-fees-id" value="{{ $fees->id }}" />
                    <input type="hidden" name="student_id" id="student-id" value="{{ $student->id }}" />
                    <input type="hidden" name="parent_id" id="parent-id" value="{{ $student->student->guardian_id }}" />
                    <input type="hidden" name="total_amount" id="total-amount"
                        value="{{ $fees->total_compulsory_fees }}" />
                    <input type="hidden" id="remaining_amount" value="{{ $fees->remaining_amount }}">
                    <input type="hidden" name="due_charges_amount" value="{{ $due_charges }}">
                    <input type="hidden" name="oneInstallmentPaid" class="oneInstallmentPaid"
                        value="{{ $oneInstallmentPaid }}">

                    {{-- SECTION 1: Student Hero & Financial Metrics --}}
                    <div class="row">
                        <div class="col-xl-8 col-lg-7">
                            <div class="pc-hero-card">
                                <div class="row">
                                    {{-- Student Identity --}}
                                    <div class="col-lg-4 mb-3 mb-lg-0">
                                        <div class="align-items-start" style="gap: 1.25rem;">
                                            <div class="pc-student-avatar">
                                                <span class="avatar-initials">{{ $initials }}</span>
                                                <div class="avatar-badge">
                                                    <i class="fa fa-check"></i>
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <h2 class="pc-student-name">{{ $student->full_name }}</h2>
                                                <p class="pc-student-meta">
                                                    {{ $student->student->class_section->full_name }}
                                                </p>
                                                <p class="pc-student-id">
                                                    {{ $student->email ?? $student->id }}
                                                </p>
                                                <div class="d-flex flex-wrap mt-2" style="gap: 0.5rem;">
                                                    <span
                                                        class="pc-tag pc-tag-default">{{ $fees->session_year->name ?? '' }}</span>
                                                    <span class="pc-tag pc-tag-brand">{{ $fees->name }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Financial Metrics Grid --}}
                                    <div class="col-lg-8">
                                        <div class="pc-metrics-grid">
                                            <div class="pc-metric-card pc-metric-default">
                                                <span class="metric-label">
                                                    <i class="fa fa-file-text"></i> {{ __('original') }}
                                                </span>
                                                <span
                                                    class="metric-value">{{ number_format($total_fee, 2) . ' ' . $currencySymbol }}</span>
                                            </div>

                                            <div class="pc-metric-card pc-metric-emerald">
                                                <span class="metric-label">
                                                    <i class="fa fa-tag"></i> {{ __('relief') }}
                                                </span>
                                                <span
                                                    class="metric-value">-{{ number_format($totalCompulsoryDiscount, 2) . ' ' . $currencySymbol }}</span>
                                            </div>

                                            <div class="pc-metric-card pc-metric-blue">
                                                <span class="metric-label">
                                                    <i class="fa fa-check-circle"></i> {{ __('paid') }}
                                                </span>
                                                <span
                                                    class="metric-value">{{ number_format($total_paid, 2) . ' ' . $currencySymbol }}</span>
                                            </div>

                                            <div class="pc-metric-card pc-metric-rose">
                                                <span class="metric-label">
                                                    <i class="fa fa-clock"></i> {{ __('pending') }}
                                                </span>
                                                <span
                                                    class="metric-value">{{ number_format($global_balance, 2) . ' ' . $currencySymbol }}</span>
                                            </div>

                                            <div class="pc-metric-card pc-metric-default">
                                                <span class="metric-label">
                                                    <i class="fa fa-calendar"></i> {{ __('next_due') }}
                                                </span>
                                                <span class="metric-value"
                                                    style="font-size: 1.125rem;">{{ $next_due_date ?? __('N/A') }}</span>
                                            </div>

                                            @if ($fees->include_fee_installments)
                                                <div class="pc-metric-card pc-metric-default">
                                                    <span class="metric-label">
                                                        <i class="fa fa-list-ol"></i> {{ __('remaining_inst') }}
                                                    </span>
                                                    <span class="metric-value">
                                                        {{ $unpaid_installments }}
                                                        <span
                                                            style="font-size: 0.875rem; font-weight: 500; color: var(--pc-text-secondary);">{{ __('of') }}
                                                            {{ $total_installments }}</span>
                                                    </span>
                                                </div>
                                            @else
                                                @if ($due_charges > 0)
                                                    <div class="pc-metric-card pc-metric-default"
                                                        style="background: #fff7ed; border-color: #fed7aa;">
                                                        <span class="metric-label" style="color: #ea580c;">
                                                            <i class="fa fa-exclamation-triangle"></i>
                                                            {{ __('due_charges') }}
                                                        </span>
                                                        <span class="metric-value"
                                                            style="color: #c2410c;">+{{ number_format($due_charges, 2) . ' ' . $currencySymbol }}</span>
                                                    </div>
                                                @else
                                                    <div class="pc-metric-card pc-metric-default">
                                                        <span class="metric-label">
                                                            <i class="fa fa-money"></i> {{ __('net_payable') }}
                                                        </span>
                                                        <span class="metric-value"
                                                            style="color: var(--pc-brand);">{{ number_format($net_payable_fee, 2) . ' ' . $currencySymbol }}</span>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- SECTION 2: Collection Progress --}}
                            <div class="pc-progress-card">
                                <div class="pc-progress-header">
                                    <div>
                                        <h3>{{ __('collection_progress') }}</h3>
                                        <p>{{ __('tracking_fee_recovery_against_net_payable_for_the_session') }}</p>
                                    </div>
                                    <div>
                                        <span class="pc-progress-percent">{{ $percentage }}%</span>
                                    </div>
                                </div>

                                <div class="pc-progress-track">
                                    <div class="pc-progress-fill" style="width: {{ $percentage }}%;"></div>
                                    <div class="pc-progress-marker" style="left: 25%;"></div>
                                    <div class="pc-progress-marker" style="left: 50%;"></div>
                                    <div class="pc-progress-marker" style="left: 75%;"></div>
                                </div>

                                <div class="pc-progress-stats">
                                    <div class="pc-stat-item">
                                        <div class="pc-stat-icon pc-stat-icon-emerald">
                                            <i class="fa fa-check-circle"></i>
                                        </div>
                                        <div>
                                            <p class="pc-stat-label">{{ __('amount_collected') }}</p>
                                            <p class="pc-stat-value">
                                                {{ number_format($total_paid, 2) . ' ' . $currencySymbol }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="pc-stat-item">
                                        <div class="pc-stat-icon pc-stat-icon-rose">
                                            <i class="fa fa-arrow-trend-down"></i>
                                        </div>
                                        <div>
                                            <p class="pc-stat-label">{{ __('remaining_balance') }}</p>
                                            <p class="pc-stat-value pc-stat-value-rose">
                                                {{ number_format($global_balance, 2) . ' ' . $currencySymbol }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="pc-stat-item">
                                        <div class="pc-stat-icon pc-stat-icon-blue">
                                            <i class="fa fa-calendar-check"></i>
                                        </div>
                                        <div>
                                            @if ($fees->include_fee_installments)
                                                <p class="pc-stat-label">{{ __('installments_left') }}</p>
                                                <p class="pc-stat-value">
                                                    {{ $unpaid_installments }}
                                                    <span
                                                        style="font-size: 0.875rem; font-weight: 500; color: var(--pc-text-secondary);">{{ __('of') }}
                                                        {{ $total_installments }}</span>
                                                </p>
                                            @else
                                                <p class="pc-stat-label">{{ __('due_date') }}</p>
                                                <p class="pc-stat-value" style="font-size: 1rem;">
                                                    {{ $next_due_date ?? __('N/A') }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- SECTION 3: Fee Breakdown (Accordions) --}}
                            <div class="mb-4">
                                <h3 class="pc-section-title">
                                    <i class="fa fa-list-alt"></i> {{ __('fee_breakdown') }}
                                </h3>

                                @if (count($fees->compulsory_fees) > 0)
                                    <div class="pc-accordion-card">
                                        <a class="pc-accordion-btn" data-bs-toggle="collapse" href="#compulsoryAccordion"
                                            role="button" aria-expanded="true" aria-controls="compulsoryAccordion">
                                            <div class="btn-left">
                                                <div class="pc-accordion-icon pc-accordion-icon-indigo">
                                                    <i class="fa fa-book"></i>
                                                </div>
                                                <span class="btn-title">{{ __('compulsory_components') }}</span>
                                            </div>
                                            <div class="btn-right">
                                                <span
                                                    class="pc-accordion-net-badge">{{ number_format($net_payable_fee, 2) . ' ' . $currencySymbol }}
                                                    {{ __('Net') }}</span>
                                                <i class="fa fa-chevron-down pc-accordion-chevron"></i>
                                            </div>
                                        </a>
                                        <div class="collapse show" id="compulsoryAccordion">
                                            <div class="pc-accordion-body">
                                                <div class="pc-component-header">
                                                    <span>{{ __('component') }}</span>
                                                    <span>{{ __('original_amount') }}</span>
                                                </div>

                                                @foreach ($fees->compulsory_fees as $c_fee)
                                                    <div class="pc-component-row">
                                                        <div class="pc-component-name">
                                                            <div class="pc-component-dot"></div>
                                                            {{ $c_fee->fees_type_name }}
                                                        </div>
                                                        <div class="pc-component-amount">
                                                            {{ number_format($c_fee->amount, 2) . ' ' . $currencySymbol }}
                                                        </div>
                                                    </div>
                                                @endforeach

                                                <div class="pc-summary-footer">
                                                    <div class="pc-summary-row">
                                                        <span
                                                            class="pc-summary-label">{{ __('original_total_fee') }}</span>
                                                        <span
                                                            class="pc-summary-value">{{ number_format($fees->total_compulsory_fees, 2) . ' ' . $currencySymbol }}</span>
                                                    </div>
                                                    @if ($totalCompulsoryDiscount > 0)
                                                        <div class="pc-summary-row">
                                                            <span class="pc-summary-label">
                                                                <i class="fa fa-tag"></i> {{ __('total_relief_applied') }}
                                                            </span>
                                                            <span
                                                                class="pc-summary-discount">-{{ number_format($totalCompulsoryDiscount, 2) . ' ' . $currencySymbol }}</span>
                                                        </div>
                                                    @endif
                                                    <div class="pc-summary-total">
                                                        <span
                                                            class="pc-summary-total-label">{{ __('final_amount_payable') }}</span>
                                                        <span
                                                            class="pc-summary-total-value">{{ number_format($net_payable_fee, 2) . ' ' . $currencySymbol }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if (count($optionalFeesData) > 0)
                                    @php
                                        $totalOptOrig = 0;
                                        $totalOptDisc = 0;
                                        $totalOptNet = 0;
                                        foreach ($optionalFeesData as $optionalFee) {
                                            $opt_discount = $optionalDiscounts[$optionalFee->id] ?? 0;
                                            $opt_net = max(0, $optionalFee->amount - $opt_discount);
                                            $totalOptOrig += $optionalFee->amount;
                                            $totalOptDisc += $opt_discount;
                                            $totalOptNet += $opt_net;
                                        }
                                    @endphp
                                    <div class="pc-accordion-card">
                                        <a class="pc-accordion-btn collapsed" data-bs-toggle="collapse"
                                            href="#optionalAccordion" role="button" aria-expanded="false"
                                            aria-controls="optionalAccordion">
                                            <div class="btn-left">
                                                <div class="pc-accordion-icon pc-accordion-icon-orange">
                                                    <i class="fa fa-puzzle-piece"></i>
                                                </div>
                                                <span class="btn-title">{{ __('optional_components') }}</span>
                                            </div>
                                            <div class="btn-right">
                                                <span class="pc-accordion-net-badge"
                                                    style="color: var(--pc-text-secondary);">{{ number_format($totalOptNet, 2) . ' ' . $currencySymbol }}
                                                    {{ __('net') }}</span>
                                                <i class="fa fa-chevron-down pc-accordion-chevron"></i>
                                            </div>
                                        </a>
                                        <div class="collapse" id="optionalAccordion">
                                            <div class="pc-accordion-body">
                                                <div class="pc-component-header">
                                                    <span>{{ __('component') }}</span>
                                                    <span>{{ __('original_amount') }}</span>
                                                </div>
                                                @foreach ($optionalFeesData as $optionalFee)
                                                    <div class="pc-component-row">
                                                        <div class="pc-component-name">
                                                            <div class="pc-component-dot"></div>
                                                            {{ $optionalFee->fees_type_name }}
                                                        </div>
                                                        <div class="pc-component-amount">
                                                            {{ number_format($optionalFee->amount, 2) . ' ' . $currencySymbol }}
                                                        </div>
                                                    </div>
                                                @endforeach

                                                <div class="pc-summary-footer">
                                                    <div class="pc-summary-row">
                                                        <span
                                                            class="pc-summary-label">{{ __('original_total_fee') }}</span>
                                                        <span
                                                            class="pc-summary-value">{{ number_format($totalOptOrig, 2) . ' ' . $currencySymbol }}</span>
                                                    </div>
                                                    @if ($totalOptDisc > 0)
                                                        <div class="pc-summary-row">
                                                            <span class="pc-summary-label">
                                                                <i class="fa fa-tag"></i> {{ __('total_relief_applied') }}
                                                            </span>
                                                            <span
                                                                class="pc-summary-discount">-{{ number_format($totalOptDisc, 2) . ' ' . $currencySymbol }}</span>
                                                        </div>
                                                    @endif
                                                    <div class="pc-summary-total">
                                                        <span
                                                            class="pc-summary-total-label">{{ __('final_amount_payable') }}</span>
                                                        <span
                                                            class="pc-summary-total-value">{{ number_format($totalOptNet, 2) . ' ' . $currencySymbol }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- SECTION 4: Installment Timeline --}}
                            @if ($fees->include_fee_installments && count($fees->installments) > 0)
                                <div class="mb-4">
                                    <h3 class="pc-section-title">
                                        <i class="fa fa-clock"></i> {{ __('installment_schedule') }}
                                    </h3>

                                    <div
                                        class="pc-timeline-wrap bg-white rounded-3 shadow-sm border p-4 position-relative mb-4">
                                        <div class="pc-timeline-line"></div>

                                        <div class="d-flex flex-column gap-3">
                                            @php
                                                $next_installment_found = false;
                                            @endphp

                                            @foreach ($fees->installments as $key => $installment)
                                                @php
                                                    $status = $installment->status ?? 0;
                                                    $balance =
                                                        $installment->balance_due ??
                                                        ($installment->total_expected_amount ??
                                                            $installment->total_amount);
                                                    $paid_amount = $installment->paid_amount ?? 0;
                                                    $due_date_fmt = date('d M Y', strtotime($installment->due_date));

                                                    $inst_amount =
                                                        $installment['original_amount'] ??
                                                        $installment->installment_amount;
                                                    $inst_discount = $installment['discount_amount'] ?? 0;
                                                    $inst_net_amount = $installment['net_amount'] ?? $inst_amount;

                                                    if ($status == 3) {
                                                        $dot_class = 'pc-timeline-dot-discounted';
                                                        $dot_icon = '<i class="fa fa-gift"></i>';
                                                        $card_class = 'pc-timeline-card-muted';
                                                        $badge_class = 'pc-badge-discounted';
                                                        $badge_label = __('DISCOUNTED');
                                                        $sub_label_class = 'pc-inst-sub-label-muted';
                                                        $sub_label = __('Fully Discounted');
                                                    } elseif ($status == 1) {
                                                        $dot_class = 'pc-timeline-dot-success';
                                                        $dot_icon = '<i class="fa fa-check"></i>';
                                                        $card_class = 'pc-timeline-card-muted';
                                                        $badge_class = 'pc-badge-paid';
                                                        $badge_label = __('PAID');
                                                        $sub_label_class = 'pc-inst-sub-label-muted';
                                                        $sub_label = __('Paid');
                                                    } elseif ($status == 2) {
                                                        $dot_class = 'pc-timeline-dot-warning';
                                                        $dot_icon = '<i class="fa fa-adjust"></i>';
                                                        $card_class = '';
                                                        $badge_class = 'pc-badge-partial';
                                                        $badge_label = __('PARTIAL');
                                                        $sub_label_class = '';
                                                        $sub_label = __('Payable Amount');
                                                    } else {
                                                        $due_date_obj = \Carbon\Carbon::parse($installment->due_date);
                                                        $is_overdue_item =
                                                            $due_date_obj->isPast() && !$due_date_obj->isToday();

                                                        if ($is_overdue_item) {
                                                            $dot_class = 'pc-timeline-dot-danger';
                                                            $dot_icon = '<i class="fa fa-exclamation"></i>';
                                                            $card_class = '';
                                                            $badge_class = 'pc-badge-overdue';
                                                            $badge_label = __('OVERDUE');
                                                            $sub_label_class = '';
                                                            $sub_label = __('Payable Amount');
                                                        } elseif (!$next_installment_found) {
                                                            $next_installment_found = true;
                                                            $dot_class = 'pc-timeline-dot-brand';
                                                            $dot_icon = '<div class="dot-inner"></div>';
                                                            $card_class = 'pc-timeline-card-active';
                                                            $badge_class = 'pc-badge-next';
                                                            $badge_label = __('NEXT DUE');
                                                            $sub_label_class = '';
                                                            $sub_label = __('Payable Amount');
                                                        } else {
                                                            $dot_class = 'pc-timeline-dot-default';
                                                            $dot_icon = '<div class="dot-inner"></div>';
                                                            $card_class = 'pc-timeline-card-muted';
                                                            $badge_class = 'pc-badge-upcoming';
                                                            $badge_label = __('UPCOMING');
                                                            $sub_label_class = 'pc-inst-sub-label-muted';
                                                            $sub_label = __('Scheduled');
                                                        }
                                                    }
                                                @endphp

                                                <div class="pc-timeline-item installment-card d-flex align-items-start gap-3"
                                                    data-id="{{ $installment->id }}" data-status="{{ $status }}">
                                                    <div
                                                        class="pc-timeline-dot rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 {{ $dot_class }}">
                                                        {!! $dot_icon !!}
                                                    </div>

                                                    <div
                                                        class="pc-timeline-card {{ $card_class }} flex-grow-1 bg-white rounded-3 border p-3 shadow-sm">
                                                        <div
                                                            class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3">
                                                            <div>
                                                                <div class="d-flex align-items-center mb-1"
                                                                    style="gap: 0.5rem;">
                                                                    <h4 class="pc-inst-title mb-0">
                                                                        {{ $installment->name }}
                                                                    </h4>
                                                                    <span
                                                                        class="pc-inst-badge {{ $badge_class }}">{{ $badge_label }}</span>
                                                                </div>

                                                                @if ($installment->transactions && count($installment->transactions) > 0)
                                                                    @foreach ($installment->transactions as $txn)
                                                                        <div class="mt-2" style="font-size: 0.8125rem;">
                                                                            @if ($txn->mode == 'Cash' || $txn->mode == 'Cheque')
                                                                                <span class="d-block text-secondary">
                                                                                    <i
                                                                                        class="fa fa-dollar mr-1 text-muted"></i>
                                                                                    {{ __('Paid via') }}
                                                                                    {{ $txn->mode_name }}
                                                                                </span>
                                                                                <span class="d-block text-muted mt-1">
                                                                                    <i class="fa fa-calendar mr-1"></i>
                                                                                    {{ date('d M Y', strtotime($txn->getRawOriginal('date') ?? $txn->date)) }}
                                                                                </span>
                                                                            @else
                                                                                <span class="d-block text-secondary">
                                                                                    <i class="fa fa-money-check-alt mr-1 text-muted"
                                                                                        style="font-size: 0.875rem; vertical-align: middle;"></i>
                                                                                    {{ __('Manual') }}
                                                                                </span>
                                                                                @php
                                                                                    $txnId =
                                                                                        $txn->payment_transaction
                                                                                            ->payment_id ??
                                                                                        ($txn->payment_id ??
                                                                                            ($txn->payment_transaction
                                                                                                ->order_id ??
                                                                                                ''));
                                                                                @endphp
                                                                                @if (!empty($txnId))
                                                                                    <div class="mt-1 d-inline-flex align-items-center bg-light border px-2 py-1 rounded text-dark font-monospace"
                                                                                        style="font-size: 0.75rem; gap: 0.5rem;">
                                                                                        <span># <strong
                                                                                                class="txn-id-val">{{ $txnId }}</strong></span>
                                                                                        <button type="button"
                                                                                            class="btn btn-link p-0 text-decoration-none copy-txn-btn ml-1"
                                                                                            data-clipboard-text="{{ $txnId }}"
                                                                                            style="font-size: 0.6875rem; color: var(--pc-brand); font-weight: 700; border: none; background: transparent;">
                                                                                            <i class="fa fa-copy"></i>
                                                                                            {{ __('COPY') }}
                                                                                        </button>
                                                                                    </div>
                                                                                @endif
                                                                            @endif
                                                                        </div>
                                                                    @endforeach
                                                                @else
                                                                    <p class="pc-inst-due-date mb-0">
                                                                        <i class="fa fa-calendar"></i>
                                                                        {{ __('due') }}: {{ $due_date_fmt }}
                                                                    </p>
                                                                @endif
                                                            </div>
                                                            <div class="text-sm-right text-right">
                                                                <div class="d-flex align-items-center justify-content-end mb-1"
                                                                    style="gap: 0.5rem;">
                                                                    @if ($inst_discount > 0)
                                                                        <span
                                                                            class="pc-inst-orig-strike">{{ __('orig') }}:
                                                                            {{ number_format($inst_amount, 2) }}</span>
                                                                        <span
                                                                            class="pc-inst-discount-tag">-{{ number_format($inst_discount, 2) }}</span>
                                                                    @endif
                                                                </div>
                                                                <div class="pc-inst-amount installment-amount"
                                                                    data-amount="{{ $balance }}">
                                                                    {{ number_format($status == 1 || $status == 3 ? $inst_net_amount : $balance, 2) . ' ' . $currencySymbol }}
                                                                </div>

                                                                @if ($installment->transactions && count($installment->transactions) > 0)
                                                                    @foreach ($installment->transactions as $txn)
                                                                        <div class="mt-2">
                                                                            @if ($txn->mode == 'Cash' || $txn->mode == 'Cheque')
                                                                                <button type="button"
                                                                                    class="btn btn-outline-danger btn-sm p-2 delete-transaction-btn font-weight-bold"
                                                                                    data-id="{{ $txn->id }}">
                                                                                    <i class="fa fa-trash"></i>
                                                                                </button>
                                                                            @else
                                                                                <span class="text-success font-weight-bold"
                                                                                    style="font-size: 0.8125rem;">
                                                                                    <i class="fa fa-check-circle"></i>
                                                                                    {{ __('Verified') }}
                                                                                </span>
                                                                            @endif
                                                                        </div>
                                                                    @endforeach
                                                                @else
                                                                    <p
                                                                        class="pc-inst-sub-label {{ $sub_label_class }} mb-0">
                                                                        {{ $sub_label }}
                                                                    </p>
                                                                @endif

                                                                @if (
                                                                    $paid_amount > 0 &&
                                                                        $status != 1 &&
                                                                        $status != 3 &&
                                                                        (!$installment->transactions || count($installment->transactions) == 0))
                                                                    <p class="pc-inst-paid-info mb-0 mt-1">
                                                                        {{ __('paid_amount') }}:
                                                                        <strong>{{ number_format($paid_amount, 2) . ' ' . $currencySymbol }}</strong>
                                                                        | {{ __('pending') }}:
                                                                        {{ number_format($balance, 2) . ' ' . $currencySymbol }}
                                                                    </p>
                                                                @endif

                                                                @if (isset($installment->due_charges_amount) && $installment->due_charges_amount > 0 && $status != 1 && $status != 3)
                                                                    <p class="pc-inst-due-charges mb-0 mt-1">
                                                                        <i class="fa fa-exclamation-triangle"></i>
                                                                        {{ __('due_charges') }}:
                                                                        +{{ number_format($installment->due_charges_amount, 2) . ' ' . $currencySymbol }}
                                                                    </p>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- SECTION 5: Non-Installment Fee Summary Card --}}
                            @if (!$fees->include_fee_installments)
                                @php
                                    $payment = $fees->complusory_details
                                        ->where('status', \App\Models\CompulsoryFee::CHEQUE_SUCCESS)
                                        ->sortByDesc('id')
                                        ->first();

                                    if (!$payment) {
                                        $payment = $fees->complusory_details
                                            ->where('status', \App\Models\CompulsoryFee::CHEQUE_PENDING)
                                            ->sortByDesc('id')
                                            ->first();
                                    }

                                    $is_overdue =
                                        $due_charges > 0 ||
                                        (date('Y-m-d') > $fees->getRawOriginal('due_date') && $global_balance > 0);

                                    if ($payment) {
                                        switch ($payment->status) {
                                            case \App\Models\CompulsoryFee::CHEQUE_SUCCESS:
                                                $card_status_label = __('PAID');
                                                $ni_badge_class = 'pc-badge-paid';
                                                break;
                                            case \App\Models\CompulsoryFee::CHEQUE_PENDING:
                                                $card_status_label = __('PENDING');
                                                $ni_badge_class = 'pc-badge-upcoming';
                                                break;
                                            case \App\Models\CompulsoryFee::CHEQUE_FAILED:
                                                $card_status_label = __('REJECTED');
                                                $ni_badge_class = 'pc-badge-overdue';
                                                break;
                                            default:
                                                $card_status_label = __('UNPAID');
                                                $ni_badge_class = 'pc-badge-upcoming';
                                        }
                                    } else {
                                        $card_status_label = __('UNPAID');
                                        $ni_badge_class = 'pc-badge-upcoming';
                                    }
                                @endphp

                                <div class="pc-noninst-card">
                                    <div class="row align-items-center">
                                        <div class="col-md-4 mb-3 mb-md-0">
                                            <h5 class="mb-1 font-weight-bold" style="color: var(--pc-brand);">
                                                <i class="fa fa-file-text mr-2"></i> {{ $fees->name }}
                                            </h5>
                                            <span
                                                class="pc-inst-badge {{ $ni_badge_class }}">{{ $card_status_label }}</span>
                                        </div>

                                        <div class="col-md-3 text-center mb-3 mb-md-0"
                                            style="border-left: 1px solid var(--pc-border-color); border-right: 1px solid var(--pc-border-color);">
                                            <small class="d-block font-weight-bold"
                                                style="color: var(--pc-text-muted); font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('due_date') }}</small>
                                            <div class="font-weight-bold"
                                                style="font-size: 1.0625rem; color: var(--pc-text-primary);">
                                                {{ $fees->due_date }}
                                                @if ($is_overdue && $global_balance > 0)
                                                    <span
                                                        class="text-danger small font-weight-bold ml-1">({{ __('overdue') }})</span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="col-md-5">
                                            <div class="text-right">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span
                                                        style="color: var(--pc-text-muted); font-size: 0.8125rem;">{{ __('total_fee') }}:</span>
                                                    <span
                                                        class="font-weight-bold">{{ number_format($fees->total_compulsory_fees, 2) . ' ' . $currencySymbol }}</span>
                                                </div>

                                                @if ($totalCompulsoryDiscount > 0)
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span
                                                            style="color: var(--pc-emerald); font-size: 0.8125rem;">{{ __('relief_applied') }}:</span>
                                                        <span class="font-weight-bold"
                                                            style="color: var(--pc-emerald);">-{{ number_format($totalCompulsoryDiscount, 2) . ' ' . $currencySymbol }}</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span
                                                            style="color: var(--pc-text-muted); font-size: 0.8125rem;">{{ __('net_payable') }}:</span>
                                                        <span
                                                            class="font-weight-bold">{{ number_format($net_payable_fee, 2) . ' ' . $currencySymbol }}</span>
                                                    </div>
                                                @endif

                                                @if ($due_charges > 0)
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span
                                                            style="color: var(--pc-rose); font-size: 0.8125rem;">{{ __('due_charges') }}:</span>
                                                        <span class="font-weight-bold"
                                                            style="color: var(--pc-rose);">+{{ number_format($due_charges, 2) . ' ' . $currencySymbol }}</span>
                                                    </div>
                                                @endif

                                                <div class="d-flex justify-content-between mb-2">
                                                    <span
                                                        style="color: var(--pc-text-muted); font-size: 0.8125rem;">{{ __('paid_amount') }}:</span>
                                                    <span class="font-weight-bold"
                                                        style="color: var(--pc-emerald);">{{ number_format($total_paid, 2) . ' ' . $currencySymbol }}</span>
                                                </div>

                                                <div class="d-flex justify-content-between align-items-center pt-2"
                                                    style="border-top: 1px solid var(--pc-border-color);">
                                                    <span class="font-weight-bold"
                                                        style="color: var(--pc-brand);">{{ __('balance_due') }}:</span>
                                                    <span class="font-weight-bold"
                                                        style="font-size: 1.25rem; color: var(--pc-text-primary);">{{ number_format($global_balance, 2) . ' ' . $currencySymbol }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- SECTION 6: Transaction History --}}
                            @php
                                // compulsory_fees.type is an enum : 1 maps to 'Full Payment'
                                $global_transactions = $student->compulsory_fees
                                    ->where('type', 'Full Payment')
                                    ->where('status', \App\Models\CompulsoryFee::CHEQUE_SUCCESS);
                            @endphp

                            @if ($global_transactions->count() > 0)
                                <div class="pc-txn-card mb-4">
                                    <div class="card-header">
                                        <h5>{{ __('transaction_history') }}
                                            <small
                                                style="color: var(--pc-text-muted);">({{ __('full_payments') }})</small>
                                        </h5>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>{{ __('date') }}</th>
                                                    <th>{{ __('payment_mode') }}</th>
                                                    <th>{{ __('transaction_type') }}</th>
                                                    <th class="text-right">{{ __('amount') }}</th>
                                                    <th class="text-right">{{ __('discount_applied') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($global_transactions as $transaction)
                                                    <tr>
                                                        <td>{{ date('d-m-Y', strtotime($transaction->date)) }}</td>
                                                        <td>
                                                            @if ($transaction->mode == 1)
                                                                {{ __('Cash') }}
                                                            @elseif($transaction->mode == 2)
                                                                {{ __('Cheque') }}
                                                            @elseif($transaction->mode == 3)
                                                                {{ __('Online') }}
                                                            @else
                                                                {{ $transaction->mode }}
                                                            @endif
                                                        </td>
                                                        <td><span
                                                                class="pc-txn-badge pc-txn-badge-info">{{ __('full_payment') }}</span>
                                                        </td>
                                                        <td class="text-right font-weight-bold">
                                                            {{ number_format($transaction->amount, 2) . ' ' . $currencySymbol }}
                                                        </td>
                                                        <td class="text-right font-weight-bold"
                                                            style="color: var(--pc-emerald);">
                                                            {{ number_format($totalCompulsoryDiscount, 2) . ' ' . $currencySymbol }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                        </div> {{-- End Left Column --}}

                        {{-- Right Column (Payment Action Panel) --}}
                        <div class="col-xl-4 col-lg-5">
                            {{-- SECTION 7: Payment Action Panel (Sticky) --}}
                            @if ($global_balance > 0 || $hasPendingCheque)
                                <div class="pc-payment-panel">
                                    <div class="pc-payment-header">
                                        <h5>
                                            <span class="pc-payment-pulse"></span>
                                            {{ $isManualPending ? __('review_manual_payment') : ($hasPendingCheque ? __('update_cheque_status') : __('process_payment')) }}
                                        </h5>
                                    </div>

                                    <div class="pc-payment-body">
                                        {!! Form::hidden('include_fee_installments', $fees->include_fee_installments, [
                                            'id' => 'include_fee_installments',
                                        ]) !!}

                                        {{-- Advance Banner --}}
                                        @if ($student_advance > 0 && !$hasPendingCheque)
                                            <div class="pc-advance-banner">
                                                <i class="fa fa-info-circle"></i> {{ __('advance') }}:
                                                <strong>{{ $student_advance . ' ' . $currencySymbol }}</strong>
                                                <input type="hidden" name="advance" value="{{ $student_advance }}">
                                            </div>
                                        @else
                                            <input type="hidden" name="advance" value="0">
                                        @endif

                                        {{-- ========================================================================= --}}
                                        {{-- SCENARIO A: MANUAL PAYMENT UNDER REVIEW                                  --}}
                                        {{-- ========================================================================= --}}
                                        @if ($isManualPending)
                                            {{-- Hidden mode input to preserve backend schema --}}
                                            <input type="hidden" name="mode" value="2">

                                            {{-- 1. Payment Proof Card with View File Button (Matching attached UI) --}}
                                            <div class="p-3 mb-3 bg-light rounded border d-flex align-items-center justify-content-between"
                                                style="border-radius: var(--pc-radius-sm);">
                                                <div class="d-flex align-items-center text-dark font-weight-bold"
                                                    style="gap: 0.5rem; font-size: 0.875rem;">
                                                    <i class="fa fa-file-text text-muted"></i>
                                                    <span>{{ __('payment_proof') }}</span>
                                                </div>
                                                @if (!empty($proofUrl))
                                                    <a href="{{ $proofUrl }}" target="_blank"
                                                        class="btn btn-outline-primary btn-sm px-3 rounded-pill"
                                                        style="font-size: 0.8125rem; font-weight: 600;">
                                                        <i class="fa fa-eye mr-1"></i> {{ __('view_file') }}
                                                    </a>
                                                @else
                                                    <span
                                                        class="badge badge-secondary">{{ __('no_file_attached') }}</span>
                                                @endif
                                            </div>

                                            {{-- 2. Amount & Submission Date (Read-only summary) --}}
                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <label
                                                        style="font-size: 0.6875rem; font-weight: 700; color: var(--pc-text-secondary); text-transform: uppercase; letter-spacing: 0.05em;">{{ __('submitted_amount') }}</label>
                                                    <div class="font-weight-bold text-dark" style="font-size: 1.125rem;">
                                                        {{ number_format($pendingChequeTxn->amount, 2) . ' ' . $currencySymbol }}
                                                    </div>
                                                    <input type="hidden" name="amount"
                                                        value="{{ $pendingChequeTxn->amount }}">
                                                </div>
                                                <div class="col-6 text-right">
                                                    <label
                                                        style="font-size: 0.6875rem; font-weight: 700; color: var(--pc-text-secondary); text-transform: uppercase; letter-spacing: 0.05em;">{{ __('date') }}</label>
                                                    <div class="font-weight-bold text-dark" style="font-size: 0.9375rem;">
                                                        {{ date('d-m-Y', strtotime($pendingChequeTxn->date)) }}
                                                    </div>
                                                    <input type="hidden" name="date"
                                                        value="{{ date('d-m-Y', strtotime($pendingChequeTxn->date)) }}">
                                                </div>
                                            </div>

                                            {{-- 3. Parent's Note (Read-Only) --}}
                                            @if (!empty($pendingChequeTxn->notes))
                                                <div class="mb-3">
                                                    <label
                                                        style="font-size: 0.6875rem; font-weight: 700; color: var(--pc-text-primary); text-transform: uppercase; letter-spacing: 0.05em;">
                                                        {{ __('parent_note') }}
                                                    </label>
                                                    <div class="p-2 bg-light text-muted border rounded"
                                                        style="font-size: 0.8125rem; border-radius: var(--pc-radius-sm);">
                                                        {{ $pendingChequeTxn->notes }}
                                                    </div>
                                                </div>
                                            @endif

                                            {{-- 4. Status Dropdown (Pending, Success, Reject) --}}
                                            <div class="mb-3">
                                                <label
                                                    style="font-size: 0.6875rem; font-weight: 700; color: var(--pc-text-primary); text-transform: uppercase; letter-spacing: 0.05em;">
                                                    {{ __('STATUS') }} <span class="text-danger">*</span>
                                                </label>
                                                {!! Form::select(
                                                    'status',
                                                    [
                                                        'pending' => __('under_review'),
                                                        'success' => __('approved'),
                                                        'bounced' => __('reject'),
                                                    ],
                                                    strtolower($pendingChequeTxn->status) == 'failed' ? 'bounced' : strtolower($pendingChequeTxn->status),
                                                    [
                                                        'class' => 'form-control',
                                                        'id' => 'manual_payment_status',
                                                        'style' => 'border-radius: var(--pc-radius-sm); font-weight: 500;',
                                                        'required' => true,
                                                    ],
                                                ) !!}
                                            </div>

                                            {{-- 5. Rejection Reason Textarea (Hidden by default, required if rejected) --}}
                                            <div class="mb-3 d-none" id="manual_rejection_reason_div">
                                                <label
                                                    style="font-size: 0.6875rem; font-weight: 700; color: var(--pc-text-primary); text-transform: uppercase; letter-spacing: 0.05em;">
                                                    {{ __('rejection_reason') }} <span class="text-danger">*</span>
                                                </label>
                                                {!! Form::textarea('reason', $pendingChequeTxn->reason ?? null, [
                                                    'placeholder' => __('enter_rejection_reason_or_note'),
                                                    'class' => 'form-control',
                                                    'id' => 'manual_reject_reason',
                                                    'rows' => 2,
                                                    'style' => 'border-radius: var(--pc-radius-sm);',
                                                ]) !!}
                                            </div>

                                            {{-- 6. Submit Button --}}
                                            <div class="pc-txn-summary mt-4">
                                                <button type="submit" id="collect_btn"
                                                    class="pc-btn-collect btn-theme w-100">
                                                    <i class="fa fa-check-circle mr-1"></i>
                                                    {{ __('update_payment_status') }}
                                                </button>
                                            </div>

                                            {{-- ========================================================================= --}}
                                            {{-- SCENARIO B: STANDARD CHEQUE OR NEW CASH/CHEQUE PAYMENT                   --}}
                                            {{-- ========================================================================= --}}
                                        @else
                                            {{-- Mode Toggle (Installment vs Full) --}}
                                            @if ($fees->include_fee_installments && !$oneInstallmentPaid)
                                                <div class="pc-mode-toggle">
                                                    <label class="pc-mode-btn active" data-mode="1">
                                                        <input type="radio" class="payment-mode-toggle d-none"
                                                            name="installment_mode" value="1" checked>
                                                        {{ __('installment') }}
                                                    </label>
                                                    <label class="pc-mode-btn" data-mode="0">
                                                        <input type="radio" class="payment-mode-toggle d-none"
                                                            name="installment_mode" value="0">
                                                        {{ __('full_amount') }}
                                                    </label>
                                                </div>
                                            @elseif (!$fees->include_fee_installments)
                                                <div class="d-none">
                                                    <input type="radio" checked class="payment-mode-toggle"
                                                        name="installment_mode" value="0">
                                                </div>
                                            @endif

                                            @if ($oneInstallmentPaid)
                                                <div class="d-none">
                                                    <input type="radio" class="payment-mode-toggle"
                                                        name="installment_mode" value="1" checked>
                                                </div>
                                            @endif

                                            @if ($hasPendingCheque)
                                                <div class="alert alert-warning d-flex align-items-center mb-3"
                                                    role="alert">
                                                    <i class="fa fa-exclamation-triangle mr-2"></i>
                                                    <div>
                                                        <strong>{{ __('pending_cheque_clearance') }}</strong>:
                                                        {{ __('A cheque payment is currently under review. Cash payment option is locked until the cheque status is updated.') }}
                                                    </div>
                                                </div>
                                            @endif

                                            {{-- Payment Method Selection --}}
                                            <div class="mb-3">
                                                <label class="d-block mb-2"
                                                    style="font-size: 0.6875rem; font-weight: 700; color: var(--pc-text-primary); text-transform: uppercase; letter-spacing: 0.05em;">{{ __('payment_method') }}</label>
                                                <div class="row" style="margin: 0 -0.375rem;">
                                                    <div class="col-6" style="padding: 0 0.375rem;">
                                                        <label
                                                            class="pc-method-card d-block mb-0 {{ $hasPendingCheque ? 'disabled opacity-50' : '' }}">
                                                            <input type="radio" name="mode" value="1"
                                                                id="cash" {{ $hasPendingCheque ? '' : 'checked' }}
                                                                {{ $hasPendingCheque ? 'disabled' : '' }}>
                                                            <div class="pc-method-label">
                                                                <i class="fa fa-dollar"></i> {{ __('cash') }}
                                                            </div>
                                                        </label>
                                                    </div>

                                                    <div class="col-6" style="padding: 0 0.375rem;">
                                                        <label
                                                            class="pc-method-card d-block mb-0 {{ $hasPendingCheque ? 'disabled opacity-50' : '' }}">
                                                            <input type="radio" name="mode" value="2"
                                                                id="cheque"
                                                                {{ $hasPendingCheque ? 'checked disabled' : '' }}>
                                                            <div class="pc-method-label">
                                                                <i class="fa fa-credit-card"></i> {{ __('cheque') }}
                                                                @if ($hasPendingCheque)
                                                                    <span
                                                                        class="badge badge-warning text-dark ml-1">{{ __('Pending') }}</span>
                                                                @endif
                                                            </div>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="{{ $hasPendingCheque ? 'mb-3' : 'd-none mb-3' }}"
                                                id="cheque_details_div">
                                                <div class="mb-3">
                                                    <label
                                                        style="font-size: 0.6875rem; font-weight: 700; color: var(--pc-text-primary); text-transform: uppercase; letter-spacing: 0.05em;">
                                                        {{ __('cheque_no') }} <span class="text-danger">*</span>
                                                    </label>
                                                    {!! Form::text('cheque_no', $hasPendingCheque ? $pendingChequeTxn->cheque_no : null, [
                                                        'placeholder' => __('cheque_no'),
                                                        'class' => 'form-control',
                                                        'id' => 'cheque_no',
                                                        'readonly' => $hasPendingCheque,
                                                        'style' => 'border-radius: var(--pc-radius-sm);',
                                                    ]) !!}
                                                </div>

                                                {{-- Fees covered by this cheque : the status below applies to all of them --}}
                                                @if ($hasPendingCheque && count($chequeGroupTxns) > 0)
                                                    <div class="mb-3">
                                                        <label
                                                            style="font-size: 0.6875rem; font-weight: 700; color: var(--pc-text-primary); text-transform: uppercase; letter-spacing: 0.05em;">
                                                            {{ __('fees_covered_by_this_cheque') }}
                                                        </label>
                                                        <div class="border rounded bg-light p-2"
                                                            style="border-radius: var(--pc-radius-sm); font-size: 0.8125rem;">
                                                            @foreach ($chequeGroupTxns as $groupTxn)
                                                                <div
                                                                    class="d-flex justify-content-between align-items-center py-1">
                                                                    <span class="text-secondary">
                                                                        {{ $groupTxn->installment_fee->name ?? __('full_payment') }}
                                                                        @if ($groupTxn->installment_fee)
                                                                            <small class="text-muted d-block">
                                                                                {{ __('due') }}:
                                                                                {{ date('d M Y', strtotime($groupTxn->installment_fee->due_date)) }}
                                                                            </small>
                                                                        @endif
                                                                    </span>
                                                                    <span class="font-weight-bold text-dark">
                                                                        {{ number_format($groupTxn->amount + $groupTxn->due_charges, 2) . ' ' . $currencySymbol }}
                                                                    </span>
                                                                </div>
                                                            @endforeach
                                                            <div
                                                                class="d-flex justify-content-between align-items-center pt-2 mt-1 border-top font-weight-bold">
                                                                <span>{{ __('total') }}</span>
                                                                <span>{{ number_format($pendingGroupTotal, 2) . ' ' . $currencySymbol }}</span>
                                                            </div>
                                                        </div>
                                                        @if (count($chequeGroupTxns) > 1)
                                                            <small class="text-muted d-block mt-1">
                                                                <i class="fa fa-info-circle"></i>
                                                                {{ __('this_status_will_be_applied_to_all_fees_paid_by_this_cheque') }}
                                                            </small>
                                                        @endif
                                                    </div>
                                                @endif

                                                <div class="mb-3">
                                                    <label
                                                        style="font-size: 0.6875rem; font-weight: 700; color: var(--pc-text-primary); text-transform: uppercase; letter-spacing: 0.05em;">{{ __('status') }}
                                                        <span class="text-danger">*</span></label>
                                                    {!! Form::select(
                                                        'status',
                                                        [
                                                            'pending' => __('under_review'),
                                                            'success' => __('paid'),
                                                            'bounced' => __('Bounced'),
                                                        ],
                                                        $hasPendingCheque
                                                            ? (strtolower($pendingChequeTxn->status) == 'failed'
                                                                ? 'bounced'
                                                                : strtolower($pendingChequeTxn->status))
                                                            : 'pending',
                                                        [
                                                            'class' => 'form-control',
                                                            'id' => 'cheque_status',
                                                            'style' => 'border-radius: var(--pc-radius-sm);',
                                                        ],
                                                    ) !!}
                                                    <div class="mb-3 d-none" id="cheque_rejection_reason_div">
                                                        <label
                                                            style="font-size: 0.6875rem; font-weight: 700; color: var(--pc-text-primary); text-transform: uppercase; letter-spacing: 0.05em;">
                                                            {{ __('cheque_bounced_reason') }} <span
                                                                class="text-danger">*</span>
                                                        </label>
                                                        {!! Form::textarea('reason', $hasPendingCheque ? $pendingChequeTxn->reason : null, [
                                                            'placeholder' => __('enter_rejection_reason_or_note'),
                                                            'class' => 'form-control',
                                                            'id' => 'cheque_reject_reason',
                                                            'rows' => 2,
                                                            'style' => 'border-radius: var(--pc-radius-sm);',
                                                        ]) !!}
                                                    </div>



                                                </div>


                                                <div class="mb-3">
                                                    <label
                                                        style="font-size: 0.6875rem; font-weight: 700; color: var(--pc-text-primary); text-transform: uppercase; letter-spacing: 0.05em;">
                                                        {{ __('note') }}
                                                    </label>

                                                    @if ($hasPendingCheque)
                                                        {{-- Read-only display of existing transaction notes column --}}
                                                        <div class="p-2 bg-light text-muted border rounded"
                                                            style="font-size: 0.8125rem; border-radius: var(--pc-radius-sm);">
                                                            {{ !empty($pendingChequeTxn->notes) ? $pendingChequeTxn->notes : __('no_note_provided') }}
                                                        </div>
                                                        {{-- Preserve notes in hidden input for request payload --}}
                                                        <input type="hidden" name="note"
                                                            value="{{ $pendingChequeTxn->notes }}">
                                                    @else
                                                        {{-- Editable textarea for new payments --}}
                                                        {!! Form::textarea('note', null, [
                                                            'placeholder' => __('note'),
                                                            'class' => 'form-control',
                                                            'id' => 'note',
                                                            'rows' => 2,
                                                            'style' => 'border-radius: var(--pc-radius-sm);',
                                                        ]) !!}
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Collection Date --}}
                                            <div class="mb-3">
                                                <label
                                                    style="font-size: 0.6875rem; font-weight: 700; color: var(--pc-text-primary); text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Collection Date') }}</label>
                                                {!! Form::text('date', $hasPendingCheque ? date('d-m-Y', strtotime($pendingChequeTxn->date)) : null, [
                                                    'required',
                                                    'placeholder' => __('date'),
                                                    'class' => 'datepicker-popup form-control',
                                                    'autocomplete' => 'off',
                                                    'style' => 'border-radius: var(--pc-radius-sm);',
                                                    'readonly' => $hasPendingCheque,
                                                ]) !!}
                                            </div>

                                            {{-- Amount Input --}}
                                            <div class="mb-3">
                                                <label
                                                    style="font-size: 0.6875rem; font-weight: 700; color: var(--pc-text-primary); text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Amount to Collect') }}</label>
                                                <div class="pc-amount-input-wrap">
                                                    <span class="pc-amount-prefix">{{ $currencySymbol }}</span>
                                                    <input type="number" name="amount" id="enter_amount"
                                                        class="pc-amount-input" min="1" required
                                                        value="{{ $hasPendingCheque ? $pendingGroupTotal : '' }}"
                                                        {{ $hasPendingCheque ? 'readonly' : '' }} placeholder="0.00">
                                                </div>
                                                <small id="amount_feedback" class="mt-2 font-weight-bold text-danger"
                                                    style="display:none; font-size: 0.75rem;"></small>
                                            </div>

                                            {{-- CTA Button --}}
                                            <div class="pc-txn-summary">
                                                <button type="submit" id="collect_btn" class="pc-btn-collect btn-theme">
                                                    <i
                                                        class="fa {{ $hasPendingCheque ? 'fa-check-circle' : 'fa-lock' }}"></i>
                                                    {{ $hasPendingCheque ? __('update_cheque_status') : __('collect_fee') }}
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div> {{-- End Right Column --}}
                    </div> {{-- End Main Row --}}
                </form>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ asset('assets/js/custom/pay_compulsory.js') }}"></script>
    <script>
        // Translation strings for JS
        window.pcTranslations = {
            minPaymentRequired: "{{ __('minimum_payment_required') }}",
            toCoverOverdue: "{{ __('for current active installment') }}",
            excessAmount: "{{ __('excess_amount_of') }}",
            willBeAdvance: "{{ __('will_be_recorded_as_advance') }}",
            clearingAllDues: "{{ __('Great! You are clearing all outstanding dues.') }}",
            paymentCoversOverdue: "{{ __('Payment covers current installment') }}",
            towardsFuture: "{{ __('towards future installments') }}",
            copied: "{{ __('copied') }}"
        };
        window.pcDeleteUrl = "{{ route('fees.transaction.delete', ':id') }}";

        // Initialize payment logic
        initPayCompulsory({
            currencySymbol: "{{ $currencySymbol }}",
            studentAdvance: parseFloat($('input[name="advance"]').val()) || 0,
            remainingAmount: parseFloat("{{ $global_balance }}"),
            minPayableAmount: parseFloat("{{ $min_payable_amount ?? 0 }}"),
            totalPayableAmount: parseFloat("{{ $total_payable_amount ?? 0 }}"),
            overdueInstallmentIds: @json($overdue_installment_ids ?? []),
            instalmentFeesFullPaid: {{ $instalmentFeesFullPaid ?? 0 }},
            hasInstallments: {{ $fees->include_fee_installments ? 'true' : 'false' }},
            hasPendingCheque: {{ $hasPendingCheque ? 'true' : 'false' }}
        });

        // Toggle Cheque fields for standard payment
        $('input[name="mode"]').on('change', function() {
            let mode = $(this).val();
            if (mode == '2') {
                $('#cheque_details_div').removeClass('d-none');
                $('#cheque_no').attr('required', true);
            } else {
                $('#cheque_details_div').addClass('d-none');
                $('#cheque_no').removeAttr('required');
            }
        });

        // Toggle Rejection Reason field when status is changed on manual payment review
        $('#manual_payment_status').on('change', function() {
            let status = $(this).val();
            if (status === 'bounced') {
                $('#manual_rejection_reason_div').removeClass('d-none');
                $('#manual_reject_reason').attr('required', true);
            } else {
                $('#manual_rejection_reason_div').addClass('d-none');
                $('#manual_reject_reason').removeAttr('required').val('');
            }
        }).trigger('change');

        // Toggle Cheque Bounce Reason field when Cheque Status is changed
        $('#cheque_status').on('change', function() {
            let status = $(this).val();
            if (status === 'bounced') {
                $('#cheque_rejection_reason_div').removeClass('d-none');
                $('#cheque_reject_reason').attr('required', true);
            } else {
                $('#cheque_rejection_reason_div').addClass('d-none');
                $('#cheque_reject_reason').removeAttr('required').val('');
            }
        }).trigger('change');
    </script>
@endsection
