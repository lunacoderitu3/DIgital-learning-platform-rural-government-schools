@extends('layouts.master')

@section('title')
    {{ __('income_dashboard') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('income_dashboard') }}
            </h3>
        </div>

        {{-- Metric Cards --}}
        <div class="row">
            <div class="col-md-6 col-xl-3 grid-margin stretch-card">
                <div class="card" style="border-left: 4px solid #3b82f6;">
                    <div class="card-body">
                        <p class="text-muted mb-1">{{ __('fee_income') }}</p>
                        <h3 class="mb-0" style="color: #2563eb;">{{ $currencySymbol }}{{ number_format($summary['fee_income'], 2) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3 grid-margin stretch-card">
                <div class="card" style="border-left: 4px solid #f97316;">
                    <div class="card-body">
                        <p class="text-muted mb-1">{{ __('transportation_income') }}</p>
                        <h3 class="mb-0" style="color: #ea580c;">{{ $currencySymbol }}{{ number_format($summary['transportation_income'], 2) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3 grid-margin stretch-card">
                <div class="card" style="border-left: 4px solid #a855f7;">
                    <div class="card-body">
                        <p class="text-muted mb-1">{{ __('manual_income') }}</p>
                        <h3 class="mb-0" style="color: #9333ea;">{{ $currencySymbol }}{{ number_format($summary['manual_income'], 2) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3 grid-margin stretch-card">
                <div class="card" style="border-left: 4px solid #10b981;">
                    <div class="card-body">
                        <p class="text-muted mb-1">{{ __('total_income') }}</p>
                        <h3 class="mb-0" style="color: #059669;">{{ $currencySymbol }}{{ number_format($summary['total_income'], 2) }}</h3>
                    </div>
                </div>
            </div>
        </div>

        {{-- Charts --}}
        <div class="row">
            <div class="col-lg-8 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('income_overview') }}</h4>
                        <div id="incomeOverviewChart"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('income_breakdown') }}</h4>
                        <div id="incomeBreakdownChart"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Manual Income --}}
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title mb-0">{{ __('recent_manual_income') }}</h4>
                            <a href="{{ route('income.index') }}" class="btn btn-sm btn-theme">{{ __('view_all') }}</a>
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
                                    @forelse ($recentIncomes as $income)
                                        <tr>
                                            <td>{{ $income->reference_no ?? '-' }}</td>
                                            <td>{{ $income->title }}</td>
                                            <td>{{ $income->category->name ?? '-' }}</td>
                                            <td>{{ $income->date }}</td>
                                            <td class="text-right">{{ $currencySymbol }}{{ number_format($income->amount, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">{{ __('no_data_available') }}</td>
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
        let incomeMonthly = @json($monthly);
        let incomeDistribution = @json($distribution);
        let incomeCurrency = "{{ $currencySymbol }}";

        function renderIncomeOverviewChart() {
            let options = {
                series: [
                    { name: "{{ __('fee_income') }}", data: incomeMonthly.fee },
                    { name: "{{ __('transportation_income') }}", data: incomeMonthly.transport },
                    { name: "{{ __('manual_income') }}", data: incomeMonthly.manual },
                ],
                chart: { type: 'bar', height: 340, stacked: true, toolbar: { show: false } },
                colors: ['#3b82f6', '#f97316', '#a855f7'],
                plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
                dataLabels: { enabled: false },
                xaxis: { categories: incomeMonthly.labels },
                yaxis: { labels: { formatter: function (val) { return incomeCurrency + Math.round(val); } } },
                legend: { position: 'top' },
                tooltip: { y: { formatter: function (val) { return incomeCurrency + val.toLocaleString(); } } },
                fill: { opacity: 1 }
            };
            $('#incomeOverviewChart').html('');
            new ApexCharts(document.querySelector("#incomeOverviewChart"), options).render();
        }

        function renderIncomeBreakdownChart() {
            let options = {
                series: incomeDistribution.series.map(Number),
                labels: incomeDistribution.labels,
                chart: { type: 'donut', height: 320 },
                colors: ['#3b82f6', '#f97316', '#a855f7'],
                legend: { position: 'bottom' },
                dataLabels: { enabled: true },
                tooltip: { y: { formatter: function (val) { return incomeCurrency + val.toLocaleString(); } } }
            };
            $('#incomeBreakdownChart').html('');
            new ApexCharts(document.querySelector("#incomeBreakdownChart"), options).render();
        }

        renderIncomeOverviewChart();
        renderIncomeBreakdownChart();
    </script>
@endsection
