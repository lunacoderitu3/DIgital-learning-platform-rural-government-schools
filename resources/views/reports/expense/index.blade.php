@extends('layouts.master')

@section('title')
    {{ __('expense_report') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('expense_report') }}
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
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('list_expense') }}</h4>
                        <div class="row" id="toolbar">

                            <div class="form-group col-sm-12 col-md-4">
                                <label class="filter-menu">{{ __('category') }}</label>
                                {!! Form::select(
                                    'category_id',
                                    $expenseCategory + ['salary' => __('salary'), 'transportation' => __('transportation')],
                                    null,
                                    [
                                        'class' => 'form-control select2-dropdown select2-hidden-accessible',
                                        'id' => 'filter_category_id',
                                        'placeholder' => __('all'),
                                    ],
                                ) !!}
                            </div>

                            <div class="form-group col-sm-12 col-md-4">
                                <label class="filter-menu"> {{ __('vehicle') }}</label>
                                <select name="vehicle_id" id="filter_vehicle_id"
                                    class="form-control select2-dropdown select2-hidden-accessible" data-live-search="true"
                                    required>
                                    <option value="">{{ __('all') }}</option>
                                    @foreach ($vehicles as $vehicle)
                                        <option value="{{ $vehicle->id }}">
                                            {{ $vehicle->name . ' (' . $vehicle->vehicle_number . ')' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group col-sm-12 col-md-4">
                                <label class="filter-menu"> {{ __('month') }}</label>
                                {!! Form::select('month', $months, null, [
                                    'class' => 'form-control select2-dropdown select2-hidden-accessible',
                                    'id' => 'filter_month',
                                    'placeholder' => __('all'),
                                ]) !!}
                            </div>

                        </div>

                        <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                            data-url="{{ route('reports.expense.show', [1]) }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]"
                            data-search="true" data-show-columns="true" data-show-refresh="true" data-fixed-columns="false"
                            data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false"
                            data-mobile-responsive="true" data-sort-name="date" data-sort-order="desc"
                            data-maintain-selected="true" data-export-data-type='all'
                            data-query-params="TransportationExpenseQueryParams" data-toolbar="#toolbar"
                            data-export-options='{ "fileName": "expense-list-<?= date('d-m-y') ?>"
                            ,"ignoreColumn":["operate"]}'
                            data-show-export="true" data-show-footer="true" data-escape="true">
                            <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                        {{ __('id') }}
                                    </th>
                                    <th scope="col" data-field="no">{{ __('no.') }}</th>
                                    <th scope="col" data-field="ref_no" data-sortable="false">{{ __('reference_no') }}
                                    </th>
                                    <th scope="col" data-field="vehicle" data-sortable="false">{{ __('vehicle') }}</th>
                                    <th scope="col" data-field="title" data-sortable="false">{{ __('title') }}</th>
                                    <th scope="col" data-field="category_name" data-sortable="false">
                                        {{ __('category') }}
                                    </th>
                                    <th scope="col" data-field="description" data-sortable="false">
                                        {{ __('description') }}
                                    </th>
                                    <th scope="col" data-field="date" data-sortable="false"
                                        data-footer-formatter="totalFormatter">{{ __('date') }}</th>
                                    <th scope="col" data-field="amount" data-sortable="false"
                                        data-formatter="amountFormatter" data-footer-formatter="totalAmountFormatter">
                                        {{ __('Amount') }}
                                    </th>
                                    <th scope="col" data-field="created_by.full_name"
                                        data-formatter="CreatedByNameFormatter" data-sortable="false">
                                        {{ __('created_by') }}
                                    </th>
                                    <th scope="col" data-field="file" data-escape="false" data-sortable="false">
                                        {{ __('file') }}
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
