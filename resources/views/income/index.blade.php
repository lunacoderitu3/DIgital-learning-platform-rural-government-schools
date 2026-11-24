@extends('layouts.master')

@section('title')
    {{ __('income') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('manage_income') }}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            {{ __('create_income') }}
                        </h4>
                        <form class="pt-3" id="create-form" action="{{ route('income.store') }}" method="POST"
                            novalidate="novalidate" enctype="multipart/form-data">
                            <div class="row">
                                <div class="form-group col-sm-12 col-md-3">
                                    <label>{{ __('session_year') }} <span class="text-danger">*</span></label>
                                    {!! Form::select(
                                        'session_year_id',
                                        $sessionYears->pluck('name', 'id')->toArray(),
                                        $currentSessionYear->id ?? null,
                                        [
                                            'required',
                                            'class' => 'form-control custom-search-select',
                                            'id' => 'session_year_id',
                                            'placeholder' => __('select_option'),
                                        ],
                                    ) !!}
                                </div>

                                <div class="form-group col-sm-12 col-md-3">
                                    <label>{{ __('select_category') }} <span class="text-danger">*</span></label>
                                    {!! Form::select('income_category_id', $incomeCategory, null, [
                                        'required',
                                        'class' => 'form-control custom-search-select',
                                        'placeholder' => __('select_option'),
                                    ]) !!}
                                </div>

                                <div class="form-group col-sm-12 col-md-3">
                                    <label for="title">{{ __('title') }} <span class="text-danger">*</span></label>
                                    <input name="title" id="title" type="text" placeholder="{{ __('title') }}"
                                        class="form-control" required />
                                </div>

                                <div class="form-group col-sm-12 col-md-3">
                                    <label for="reference_no">{{ __('reference_no') }}</label>
                                    <input name="reference_no" id="reference_no" type="text"
                                        placeholder="{{ __('reference_no') }}" class="form-control" />
                                </div>

                                <div class="form-group col-sm-12 col-md-4">
                                    <label for="amount">{{ __('Amount') }} <span class="text-danger">*</span></label>
                                    <input name="amount" id="amount" type="number" min="0" step="0.01"
                                        placeholder="{{ __('Amount') }}" class="form-control" required />
                                </div>

                                <div class="form-group col-sm-12 col-md-4">
                                    <label for="date">{{ __('date') }} <span class="text-danger">*</span></label>
                                    <input name="date" id="date" type="text" placeholder="{{ __('date') }}"
                                        class="datepicker-popup-no-future form-control" autocomplete="off" required />
                                </div>

                                <div class="form-group col-sm-12 col-md-4">
                                    <label for="description">{{ __('description') }} </label>
                                    <textarea name="description" id="description" placeholder="{{ __('description') }}" class="form-control"></textarea>
                                </div>
                            </div>
                            <input class="btn btn-theme float-right ml-3" id="create-btn" type="submit"
                                value={{ __('submit') }}>
                            <input class="btn btn-secondary float-right" type="reset" value={{ __('reset') }}>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('list_income') }}</h4>
                        <div class="row" id="toolbar">

                            <div class="form-group col-sm-12 col-md-4">
                                <label class="filter-menu">{{ __('session_year') }}</label>
                                {!! Form::select(
                                    'session_year_id',
                                    $sessionYears->pluck('name', 'id')->toArray(),
                                    $currentSessionYear->id ?? null,
                                    ['class' => 'form-control custom-search-select', 'id' => 'filter_session_year_id'],
                                ) !!}
                            </div>

                            <div class="form-group col-sm-12 col-md-4">
                                <label class="filter-menu">{{ __('category') }}</label>
                                {!! Form::select('category_id', [0 => __('all')] + $incomeCategory, null, [
                                    'class' => 'form-control custom-search-select',
                                    'id' => 'filter_category_id',
                                    'placeholder' => __('all'),
                                ]) !!}
                            </div>

                            <div class="form-group col-sm-12 col-md-4">
                                <label class="filter-menu"> {{ __('month') }}</label>
                                {!! Form::select('month', ['0' => 'All'] + $months, null, [
                                    'class' => 'form-control custom-search-select',
                                    'id' => 'filter_month',
                                    'placeholder' => __('all'),
                                ]) !!}
                            </div>

                        </div>

                        <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                            data-url="{{ route('income.show', [1]) }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]"
                            data-search="true" data-show-columns="true" data-show-refresh="true" data-fixed-columns="false"
                            data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false"
                            data-mobile-responsive="true" data-sort-name="date" data-sort-order="desc"
                            data-maintain-selected="true" data-export-data-type='all' data-query-params="IncomeQueryParams"
                            data-toolbar="#toolbar" data-export-options='{ "fileName": "income-list-<?= date('d-m-y') ?>"
                            ,"ignoreColumn":["operate"]}'
                            data-show-export="true" data-show-footer="true" data-escape="true">
                            <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                        {{ __('id') }}
                                    </th>
                                    <th scope="col" data-field="no">{{ __('no.') }}</th>
                                    <th scope="col" data-field="reference_no" data-sortable="false">
                                        {{ __('reference_no') }}</th>
                                    <th scope="col" data-field="title" data-sortable="false">{{ __('title') }}</th>
                                    <th scope="col" data-field="category.name" data-sortable="false">
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
                                    <th scope="col" data-field="operate" data-events="IncomeEvents"
                                        data-escape="false">
                                        {{ __('action') }}
                                    </th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal -->
            <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">{{ __('edit_income') }}</h5>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form class="pt-3 edit-form" id="" action="{{ url('income') }}"
                            novalidate="novalidate">
                            @csrf
                            <div class="modal-body">
                                <input type="hidden" name="id" id="edit_id" value="" />
                                <div class="row">

                                    <div class="form-group col-sm-12 col-md-4">
                                        <label>{{ __('session_year') }} <span class="text-danger">*</span></label>
                                        {!! Form::select('session_year_id', $sessionYears->pluck('name', 'id')->toArray(), null, [
                                            'required',
                                            'class' => 'form-control',
                                            'placeholder' => __('select_option'),
                                            'id' => 'edit_session_year_id',
                                        ]) !!}
                                    </div>

                                    <div class="form-group col-sm-12 col-md-4">
                                        <label>{{ __('select_category') }} <span class="text-danger">*</span></label>
                                        {!! Form::select('income_category_id', $incomeCategory, null, [
                                            'required',
                                            'class' => 'form-control',
                                            'placeholder' => __('select_option'),
                                            'id' => 'edit_income_category_id',
                                        ]) !!}
                                    </div>

                                    <div class="form-group col-sm-12 col-md-4">
                                        <label for="edit_title">{{ __('title') }} <span
                                                class="text-danger">*</span></label>
                                        <input name="title" id="edit_title" type="text"
                                            placeholder="{{ __('title') }}" class="form-control" required />
                                    </div>

                                    <div class="form-group col-sm-12 col-md-4">
                                        <label for="edit_reference_no">{{ __('reference_no') }}</label>
                                        <input name="reference_no" id="edit_reference_no" type="text"
                                            placeholder="{{ __('reference_no') }}" class="form-control" />
                                    </div>

                                    <div class="form-group col-sm-12 col-md-4">
                                        <label for="edit_amount">{{ __('Amount') }} <span
                                                class="text-danger">*</span></label>
                                        <input name="amount" id="edit_amount" type="number" min="0"
                                            step="0.01" placeholder="{{ __('Amount') }}" class="form-control"
                                            required />
                                    </div>

                                    <div class="form-group col-sm-12 col-md-4">
                                        <label for="edit_date">{{ __('date') }} <span
                                                class="text-danger">*</span></label>
                                        <input name="date" id="edit_date" type="text"
                                            placeholder="{{ __('date') }}"
                                            class="datepicker-popup-no-future form-control" autocomplete="off" required />
                                    </div>

                                    <div class="form-group col-sm-12 col-md-12">
                                        <label for="edit_description">{{ __('description') }}</label>
                                        <textarea name="description" id="edit_description" class="form-control"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary"
                                    data-bs-dismiss="modal">{{ __('close') }}</button>
                                <input class="btn btn-theme" type="submit" value="{{ __('submit') }}" />
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('js')
    <script>
        // Map of every session year -> its date range, so the date picker can be
        // re-bounded whenever the user changes the selected session year.
        let sessionYearsData = {
            @foreach ($sessionYears as $sy)
                "{{ $sy->id }}": {
                    start: "{{ $sy->original_start_date }}",
                    end: "{{ $sy->original_end_date }}"
                },
            @endforeach
        };

        function isRTL() {
            return $('html').attr('dir') === 'rtl';
        }

        function applyDatepicker(selector, sessionYearId, selectedDate = null) {
            let sessionYear = sessionYearsData[sessionYearId];
            if (!sessionYear) return;

            $(selector).datepicker('destroy');
            $(selector).datepicker({
                format: datepickerReplacedFormat,
                autoclose: true,
                todayHighlight: true,
                startDate: new Date(sessionYear.start),
                endDate: new Date(sessionYear.end),
                orientation: 'bottom auto',
                rtl: isRTL()
            });

            if (selectedDate) {
                $(selector).datepicker('setDate', selectedDate);
            } else {
                $(selector).val('');
            }
        }

        function setEditDatepickerLimits(sessionYearId, selectedDate) {
            applyDatepicker('#edit_date', sessionYearId, selectedDate);
        }

        // Re-bound the create date picker when the session year changes.
        $('#session_year_id').on('change', function() {
            applyDatepicker('#date', $(this).val());
        });

        // Re-bound the edit date picker when the session year changes inside the modal.
        $('#edit_session_year_id').on('change', function() {
            applyDatepicker('#edit_date', $(this).val(), null);
        });

        // Initialise create date picker for the pre-selected session year.
        applyDatepicker('#date', $('#session_year_id').val());
    </script>
@endsection
