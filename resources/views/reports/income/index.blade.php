@extends('layouts.master')

@section('title')
    {{ __('income_report') }}
@endsection

@section('css')
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.14.0-beta3/css/bootstrap-select.min.css">
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('income_report') }}
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
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('list_income') }}</h4>
                        <div class="row" id="toolbar">

                            <div class="form-group col-sm-12 col-md-6">
                                <label class="filter-menu">{{ __('category') }}</label>
                                {!! Form::select('category_id', $incomeCategory, null, ['class' => 'form-control select2-dropdown select2-hidden-accessible', 'id' => 'filter_category_id', 'placeholder' => __('all')]) !!}
                            </div>

                            <div class="form-group col-sm-12 col-md-6">
                                <label class="filter-menu"> {{ __('month') }}</label>
                                {!! Form::select('month', $months, null, ['class' => 'form-control select2-dropdown select2-hidden-accessible', 'id' => 'filter_month', 'placeholder' => __('all')]) !!}
                            </div>

                        </div>

                        <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                            data-url="{{ route('reports.income.show', [1]) }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]"
                            data-search="true" data-show-columns="true" data-show-refresh="true" data-fixed-columns="false"
                            data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false"
                            data-mobile-responsive="true" data-sort-name="date" data-sort-order="desc"
                            data-maintain-selected="true" data-export-data-type='all'
                            data-query-params="IncomeReportQueryParams" data-toolbar="#toolbar"
                            data-export-options='{ "fileName": "income-list-<?= date('d-m-y') ?>" ,"ignoreColumn":["operate"]}'
                            data-show-export="true" data-show-footer="true" data-escape="true">
                            <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true" data-visible="false">{{ __('id') }}
                                    </th>
                                    <th scope="col" data-field="no">{{ __('no.') }}</th>
                                    <th scope="col" data-field="reference_no" data-sortable="false">{{ __('reference_no') }}
                                    </th>
                                    <th scope="col" data-field="title" data-sortable="false">{{ __('title') }}</th>
                                    <th scope="col" data-field="category.name" data-sortable="false">{{ __('category') }}
                                    </th>
                                    <th scope="col" data-field="description" data-sortable="false">{{ __('description') }}
                                    </th>
                                    <th scope="col" data-field="date" data-sortable="false"
                                        data-footer-formatter="totalFormatter">{{ __('date') }}</th>
                                    <th scope="col" data-field="amount" data-sortable="false"
                                        data-formatter="amountFormatter" data-footer-formatter="totalAmountFormatter">
                                        {{ __('Amount') }}
                                    </th>
                                    <th scope="col" data-field="created_by.full_name"
                                        data-formatter="CreatedByNameFormatter" data-sortable="false">{{ __('created_by') }}
                                    </th>
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

@endsection
