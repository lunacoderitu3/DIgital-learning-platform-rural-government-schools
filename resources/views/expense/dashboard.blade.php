@extends('layouts.master')

@section('title')
    {{ __('expense_dashboard') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('expense_dashboard') }}
            </h3>
        </div>

        {{-- Metric Cards --}}
        <div class="row">
            <div class="col-sm-12 col-md-4 col-xl-4 grid-margin stretch-card">
                <div class="card" style="border-left: 4px solid #ef4444;">
                    <div class="card-body">
                        <p class="text-muted mb-1">{{ __('total_subscription_paid_amount') }}</p>
                        <h3 class="mb-0" style="color: #dc2626;">
                            {{ $currencySymbol }}{{ number_format($summary['subscription_amount'], 2) }}</h3>
                    </div>
                </div>
            </div>
            {{-- <div class="col-sm-12 col-md-4 col-xl-4 grid-margin stretch-card">
                <div class="card" style="border-left: 4px solid #f97316;">
                    <div class="card-body">
                        <p class="text-muted mb-1">{{ __('total_fees_relief_amount') }}</p>
                        <h3 class="mb-0" style="color: #ea580c;">
                            {{ $currencySymbol }}{{ number_format($summary['fees_discount_amount'], 2) }}</h3>
                    </div>
                </div>
            </div> --}}
            <div class="col-sm-12 col-md-4 col-xl-4 grid-margin stretch-card">
                <div class="card" style="border-left: 4px solid #a855f7;">
                    <div class="card-body">
                        <p class="text-muted mb-1">{{ __('manual_expense_amount') }}</p>
                        <h3 class="mb-0" style="color: #9333ea;">
                            {{ $currencySymbol }}{{ number_format($summary['manual_expense_amount'], 2) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-12 col-md-4 col-xl-4 grid-margin stretch-card">
                <div class="card" style="border-left: 4px solid #be123c;">
                    <div class="card-body">
                        <p class="text-muted mb-1">{{ __('total_expense') }}</p>
                        <h3 class="mb-0" style="color: #9f1239;">
                            {{ $currencySymbol }}{{ number_format($summary['total_expense'], 2) }}</h3>
                    </div>
                </div>
            </div>
        </div>

        {{-- Charts --}}
        <div class="row">
            <div class="col-lg-8 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('expense_overview') }}</h4>
                        <div id="expenseOverviewChart"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('expense_breakdown') }}</h4>
                        <div id="expenseBreakdownChart"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Manual Expense --}}
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title mb-0">{{ __('recent_manual_expense') }}</h4>
                            <a href="{{ route('expense.index') }}" class="btn btn-sm btn-theme">{{ __('view_all') }}</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>{{ __('reference_no') }}</th>
                                        <th>{{ __('title') }}</th>
                                        <th>{{ __('category') }}</th>
                                        <th>{{ __('date') }}</th>
                                        <th class="text-right">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($recentExpenses as $expense)
                                        <tr>
                                            <td>{{ $expense->ref_no ?? '-' }}</td>
                                            <td>{{ $expense->title }}</td>
                                            <td>{{ $expense->category->name ?? '-' }}</td>
                                            <td>{{ $expense->date }}</td>
                                            <td class="text-right">
                                                {{ $currencySymbol }}{{ number_format($expense->amount, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">{{ __('no_data_available') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        let expenseMonthly = @json($monthly);
        let expenseDistribution = @json($distribution);
        let expenseCurrency = "{{ $currencySymbol }}";

        function renderExpenseOverviewChart() {
            let options = {
                series: [{
                        name: "{{ __('total_subscription_paid_amount') }}",
                        data: expenseMonthly.subscription
                    },
                    {
                        name: "{{ __('total_fees_relief_amount') }}",
                        data: expenseMonthly.discount
                    },
                    {
                        name: "{{ __('manual_expense_amount') }}",
                        data: expenseMonthly.manual
                    },
                ],
                chart: {
                    type: 'bar',
                    height: 340,
                    stacked: true,
                    toolbar: {
                        show: false
                    }
                },
                colors: ['#ef4444', '#f97316', '#a855f7'],
                plotOptions: {
                    bar: {
                        borderRadius: 4,
                        columnWidth: '45%'
                    }
                },
                dataLabels: {
                    enabled: false
                },
                xaxis: {
                    categories: expenseMonthly.labels
                },
                yaxis: {
                    labels: {
                        formatter: function(val) {
                            return expenseCurrency + Math.round(val);
                        }
                    }
                },
                legend: {
                    position: 'top'
                },
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return expenseCurrency + val.toLocaleString();
                        }
                    }
                },
                fill: {
                    opacity: 1
                }
            };
            $('#expenseOverviewChart').html('');
            new ApexCharts(document.querySelector("#expenseOverviewChart"), options).render();
        }

        function renderExpenseBreakdownChart() {
            let options = {
                series: expenseDistribution.series.map(Number),
                labels: expenseDistribution.labels,
                chart: {
                    type: 'donut',
                    height: 320
                },
                colors: ['#ef4444', '#f97316', '#a855f7'],
                legend: {
                    position: 'bottom'
                },
                dataLabels: {
                    enabled: true
                },
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return expenseCurrency + val.toLocaleString();
                        }
                    }
                }
            };
            $('#expenseBreakdownChart').html('');
            new ApexCharts(document.querySelector("#expenseBreakdownChart"), options).render();
        }

        renderExpenseOverviewChart();
        renderExpenseBreakdownChart();
    </script>
@endsection
