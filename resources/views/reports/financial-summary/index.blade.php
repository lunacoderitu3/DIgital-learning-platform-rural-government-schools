@extends('layouts.master')

@section('title')
    {{ __('financial_summary_report') }}
@endsection

@section('css')
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.14.0-beta3/css/bootstrap-select.min.css">
@endsection

@section('content')
    @php
        $net = $totals['net'] ?? 0;
        $netColor = $net > 0 ? '#4f46e5' : ($net < 0 ? '#e11d48' : '#64748b');
        $netBg = $net > 0 ? '#e0e7ff' : ($net < 0 ? '#ffe4e6' : '#f1f5f9');
    @endphp
    <div class="content-wrapper">
        <div class="page-header">
            <div>
                <h3 class="page-title">{{ __('financial_summary_report') }}</h3>
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="row">
            <div class="col-md-4 grid-margin stretch-card">
                <div class="card" style="border-left: 4px solid #10b981;">
                    <div class="card-body d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted text-uppercase mb-2" style="font-size: .72rem; letter-spacing: .05em;">
                                {{ __('total_income') }}</p>
                            <h3 class="mb-1" style="color: #059669;" id="card_income_amount">
                                {{ $currencySymbol }}{{ number_format($totals['income'] ?? 0, 2) }}</h3>
                            <small class="text-muted">{{ __('total_collected_revenue') }}</small>
                        </div>
                        <div class="rounded d-flex align-items-center justify-content-center"
                            style="width: 46px; height: 46px; background: #d1fae5; color: #059669;">
                            <i class="fa fa-arrow-up"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 grid-margin stretch-card">
                <div class="card" style="border-left: 4px solid #f43f5e;">
                    <div class="card-body d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted text-uppercase mb-2" style="font-size: .72rem; letter-spacing: .05em;">
                                {{ __('total_expense') }}</p>
                            <h3 class="mb-1" style="color: #e11d48;" id="card_expense_amount">
                                {{ $currencySymbol }}{{ number_format($totals['expense'] ?? 0, 2) }}</h3>
                            <small class="text-muted">{{ __('total_operating_costs') }}</small>
                        </div>
                        <div class="rounded d-flex align-items-center justify-content-center"
                            style="width: 46px; height: 46px; background: #ffe4e6; color: #e11d48;">
                            <i class="fa fa-arrow-down"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 grid-margin stretch-card">
                <div class="card" id="card_net" style="border-left: 4px solid {{ $netColor }};">
                    <div class="card-body d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted text-uppercase mb-2" style="font-size: .72rem; letter-spacing: .05em;">
                                {{ __('net_result') }}</p>
                            <h3 class="mb-1" id="card_net_amount" style="color: {{ $netColor }};">
                                {{ $net > 0 ? '+' : '' }}{{ $currencySymbol }}{{ number_format($net, 2) }}</h3>
                            <small class="text-muted" id="card_net_trend" data-profit="{{ __('operating_at_a_profit') }}"
                                data-loss="{{ __('operating_at_a_loss') }}" data-zero="{{ __('balanced') }}">
                                {{ $net > 0 ? __('operating_at_a_profit') : ($net < 0 ? __('operating_at_a_loss') : __('balanced')) }}
                            </small>
                        </div>
                        <div class="rounded d-flex align-items-center justify-content-center" id="card_net_icon"
                            style="width: 46px; height: 46px; background: {{ $netBg }}; color: {{ $netColor }};">
                            <i class="fa fa-credit-card-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Report Table --}}
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('financial_summary_report') }}</h4>
                        <div class="row" id="toolbar">
                            <div class="form-group col-sm-12 col-md-4">
                                <label class="filter-menu" style="color: #333;">{{ __('session_year') }}</label>
                                <select name="session_year_id" id="filter_session_year_id"
                                    class="form-control select2-dropdown select2-hidden-accessible">
                                    <option value="">{{ __('all') }}</option>
                                    @foreach ($sessionYears as $sessionYear)
                                        <option value="{{ $sessionYear->id }}">{{ $sessionYear->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                            data-url="{{ route('reports.financial-summary.show', [1]) }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]"
                            data-search="true" data-show-columns="true" data-show-refresh="true" data-fixed-columns="false"
                            data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false"
                            data-mobile-responsive="true" data-sort-name="session_year_id" data-sort-order="desc"
                            data-maintain-selected="true" data-export-data-type='all'
                            data-query-params="FinancialSummaryQueryParams" data-toolbar="#toolbar"
                            data-export-options='{ "fileName": "financial-summary-<?= date('d-m-y') ?>"
                            ,"ignoreColumn":["operate"]}'
                            data-show-export="true" data-show-footer="true" data-escape="false">
                            <thead>
                                <tr>
                                    <th scope="col" data-field="session_year_id" data-sortable="true"
                                        data-visible="false">
                                        {{ __('id') }}</th>
                                    <th scope="col" data-field="no">{{ __('no.') }}</th>
                                    <th scope="col" data-field="session_year" data-sortable="true"
                                        data-formatter="financialSessionFormatter" data-footer-formatter="totalFormatter">
                                        {{ __('session_year') }}</th>
                                    <th scope="col" data-field="total_income" data-sortable="true"
                                        data-formatter="amountFormatter" data-footer-formatter="totalAmountFormatter">
                                        {{ __('total_income') }}</th>
                                    <th scope="col" data-field="total_expense" data-sortable="true"
                                        data-formatter="amountFormatter" data-footer-formatter="totalAmountFormatter">
                                        {{ __('total_expense') }}</th>
                                    <th scope="col" data-field="net_result" data-sortable="true"
                                        data-formatter="netResultFormatter"
                                        data-footer-formatter="netResultTotalFormatter">{{ __('net_result') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        let financialCurrency = "{{ $currencySymbol }}";

        function financialMoney(value) {
            let n = parseFloat(value) || 0;
            return financialCurrency + formatMoney(Math.abs(n));
        }

        // Keep the summary cards in sync with the (filtered) table totals.
        $('#table_list').on('load-success.bs.table', function(e, data) {
            if (!data || typeof data.totals === 'undefined') return;
            let t = data.totals;

            $('#card_income_amount').text(financialMoney(t.income));
            $('#card_expense_amount').text(financialMoney(t.expense));

            let net = parseFloat(t.net) || 0;
            let color = net > 0 ? '#4f46e5' : (net < 0 ? '#e11d48' : '#64748b');
            let bg = net > 0 ? '#e0e7ff' : (net < 0 ? '#ffe4e6' : '#f1f5f9');

            $('#card_net_amount').text((net > 0 ? '+' : (net < 0 ? '-' : '')) + financialMoney(net))
                .css('color', color);
            $('#card_net').css('border-left-color', color);
            $('#card_net_icon').css({
                'background': bg,
                'color': color
            });

            let trend = $('#card_net_trend');
            trend.text(net > 0 ? trend.data('profit') : (net < 0 ? trend.data('loss') : trend.data('zero')));
        });
    </script>
@endsection
