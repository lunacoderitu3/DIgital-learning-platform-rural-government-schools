{{-- @dd($subjectTeachers) --}}
@extends('layouts.master')

@section('title')
    {{ __('manage_online_exam') }}
@endsection
@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/online-exam-table.css') }}">
@endsection
@section('content')

    <div class="content-wrapper">
        <div class="page-header">

            <h3 class="page-title">
                {{ __('manage_online_exam') }}
            </h3>
        </div>
        <div class="row">
            @can('online-exam-create')
                <div class="col-md-12 grid-margin stretch-card search-container">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title mb-4">
                                {{ __('create_online_exam') }}
                            </h4>
                            <form class="pt-3 mt-6" id="create-form" method="POST" action="{{ route('online-exam.store') }}">
                                {{-- {!! Form::hidden('user_id', Auth::user()->id, ['id' => 'user_id']) !!} --}}
                                {!! Form::hidden('semester_id', null, ['class' => 'semester_id']) !!}
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label>{{ __('Class') }} <span class="text-danger">*</span></label>
                                        <select name="class_id" required id="class-id"
                                            class="form-control custom-search-select">
                                            <option value="">-- {{ __('Select Class') }} --</option>
                                            @foreach ($classes as $class)
                                                <option value="{{ $class['id'] }}">{{ $class['full_name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>{{ __('Class Section') }} <span class="text-danger">*</span></label>
                                          <select name="class_section_id[]" id="class-section-id" class="form-control select2"
                                            style="width:100%;" multiple disabled>
                                            <option value="">-- {{ __('Select Class First') }} --</option>
                                        </select>  
                                       
                                         
                                        
                                        <div class="form-check w-fit-content mt-2">
                                            <label class="form-check-label user-select-none">
                                                <input type="checkbox" class="form-check-input" id="select-all"
                                                    value="1">{{ __('Select All') }}
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>{{ __('Subject') }} <span class="text-danger">*</span></label>
                                        <select required name="subject_id" id="subject-id"
                                            class="form-control custom-search-select" disabled>
                                            <option value="">-- {{ __('Select Section First') }} --</option>
                                        </select>
                                        
                                       
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>{{ __('title') }} <span class="text-danger">*</span></label>
                                        {!! Form::text('title', '', [
                                            'required',
                                            'id' => 'title',
                                            'placeholder' => trans('title'),
                                            'class' => 'form-control',
                                        ]) !!}
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>{{ __('exam_key') }} <span class="text-danger">*</span></label>
                                        {!! Form::number('exam_key', '', [
                                            'required',
                                            'id' => 'key',
                                            'placeholder' => trans('exam_key'),
                                            'class' => 'form-control',
                                            'min' => 1,
                                            'readonly' => true,
                                        ]) !!}
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>{{ __('duration') }} <span class="text-danger">*</span> <span
                                                class="text-info small">( {{ __('in_minutes') }} )</span></label>
                                        {!! Form::number('duration', '', [
                                            'required',
                                            'id' => 'duration',
                                            'placeholder' => trans('duration'),
                                            'class' => 'form-control',
                                            'min' => 1,
                                        ]) !!}
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>{{ __('start_date') }} <span class="text-danger">*</span></label>
                                        {!! Form::datetimeLocal('start_date', '', [
                                            'required',
                                            'id' => 'start-date timepicker-example',
                                            'placeholder' => trans('start_date'),
                                            'class' => 'form-control',
                                        ]) !!}
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>{{ __('end_date') }} <span class="text-danger">*</span></label>
                                        {!! Form::datetimeLocal('end_date', '', [
                                            'required',
                                            'id' => 'end-date',
                                            'placeholder' => trans('end_date'),
                                            'class' => 'form-control',
                                        ]) !!}
                                    </div>
                                </div>

                                {{-- <input class="btn btn-theme" id="add-online-exam-btn" type="submit" value={{ __('submit')
                                    }}> --}}
                                <input class="btn btn-theme float-right ml-3" id="create-btn" type="submit"
                                    value={{ __('submit') }}>
                                <input class="btn btn-secondary float-right" type="reset" value={{ __('reset') }}>
                            </form>
                        </div>
                    </div>
                </div>
            @endcan

            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            {{ __('list_exams') }}
                        </h4>
                        <div class="row" id="">
                            <div class="form-group col-sm-12 col-md-3">
                                <label for="filter-class-id" class="filter-menu">{{ __('Class') }}</label>
                                <select name="class_id" id="filter-class-id" class="form-control custom-search-select">
                                    <option value="">{{ __('all') }}</option>
                                    @foreach ($classes as $class)
                                        <option value="{{ $class['id'] }}">{{ $class['full_name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-sm-12 col-md-2">
                                <label for="filter-class-section-id" class="filter-menu">{{ __('Class Section') }}</label>
                                <select name="class_section_id" id="filter-class-section-id"
                                    class="form-control custom-search-select" disabled>
                                    <option value="">{{ __('all') }}</option>
                                </select>
                            </div>
                            <div class="form-group col-sm-12 col-md-3">
                                <label for="filter-subject-id" class="filter-menu">{{ __('subject') }}</label>
                                <select name="subject_id" id="filter-subject-id" class="form-control custom-search-select"
                                    disabled>
                                    <option value="">{{ __('all') }}</option>
                                </select>
                            </div>
                            <div class="form-group col-sm-12 col-md-2">
                                <label for="filter-exam-status" class="filter-menu">{{ __('status') }}</label>
                                <select name="exam_status" id="filter-exam-status"
                                    class="form-control custom-search-select">
                                    <option value="">{{ __('all') }}</option>
                                    <option value="Upcoming">{{ __('Upcoming') }}</option>
                                    <option value="On Going">{{ __('On Going') }}</option>
                                    <option value="Completed">{{ __('Completed') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-block">
                            <div class="">
                                <div class="col-12 text-right d-flex justify-content-end text-right align-items-end">
                                    <b><a href="#" class="table-list-type active mr-2"
                                            data-id="0">{{ __('all') }}</a></b> | <a href="#"
                                        class="ml-2 table-list-type" data-id="1">{{ __('Trashed') }}</a>
                                </div>
                            </div>
                        </div>

                        
                        <div class="oe-card-wrap">
                            <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                                data-url="{{ route('online-exam.show', 1) }}" data-click-to-select="true"
                                data-side-pagination="server" data-pagination="false"
                                data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                                data-show-columns="true" data-show-refresh="true" data-fixed-columns="false"
                                data-fixed-right-number="1" data-trim-on-search="false" data-mobile-responsive="true"
                                data-sort-name="id" data-sort-order="desc" data-maintain-selected="true"
                                data-export-data-type='all'
                                data-export-options='{ "fileName": "{{ __('online_exam') }}-<?= date(' d-m-y') ?>"
                                ,"ignoreColumn":["operate"]}'
                                data-show-export="true" data-query-params="onlineExamQueryParams" data-escape="true"
                                data-escape-title="false" data-row-style="onlineExamRowStyle"
                                data-response-handler="onlineExamResponseHandler"
                                data-detail-view="true" data-detail-formatter="onlineExamDetailFormatter">
                                <thead>
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                            {{ __('id') }}
                                        </th>
                                        <th scope="col" data-field="no" data-visible="false">{{ __('no.') }}</th>

                                        
                                        <th scope="col" data-field="title" data-formatter="examDetailsFormatter"
                                            data-class="oe-col-details">
                                            {{ __('exam_name') }}
                                        </th>
                                        

                                        
                                        <th scope="col" data-field="start_date_db" data-sortable="false"
                                            data-formatter="scheduleFormatter" data-class="oe-col-schedule">
                                            {{ __('schedule_duration') }} 
                                        </th>

                                        
                                        <th scope="col" data-field="class_section_with_medium"
                                            data-formatter="assignedSectionsFormatter" data-class="oe-col-sections">
                                            {{ __('assigned_sections') }}
                                        </th>

                                        <th scope="col" data-field="participants" data-align="center"
                                            data-formatter="participantsFormatter" data-class="oe-col-participants">
                                            {{ __('participants') }}
                                        </th>

                                        <th scope="col" data-field="exam_status_name"
                                            data-formatter="examStatusFormatter" data-class="oe-col-status">
                                            {{ __('status') }}
                                        </th>

                                        <th scope="col" data-field="created_at" data-sortable="true"
                                            data-visible="false">
                                            {{ __('created_at') }}</th>
                                        <th scope="col" data-field="updated_at" data-sortable="true"
                                            data-visible="false">
                                            {{ __('updated_at') }}</th>

                                        <th scope="col" data-field="operate" data-formatter="actionColumnFormatter"
                                            data-events="onlineExamEvents" data-escape="false"
                                            data-class="oe-col-actions">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                       

                    </div>
                </div>
            </div>


            <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
                aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">{{ __('edit_online_exam') }}
                            </h5>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true"><i class="fa fa-close"></i></span>
                            </button>
                        </div>
                        <form id="edit-form" class="pt-3 edit-form" action="{{ url('online-exam') }}">
                            <input type="hidden" name="edit_id" id="edit_id">
                            <div class="modal-body">
                                <div class="form-group">
                                    <label>{{ __('title') }} <span class="text-danger">*</span></label>
                                    <input type="text" id="edit-online-exam-title" required name="edit_title"
                                        placeholder="{{ __('title') }}" class="form-control" />
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label>{{ __('exam') }} {{ __('key') }} <span
                                                class="text-danger">*</span></label>
                                        <input type="number" id="edit-online-exam-key" required name="edit_exam_key"
                                            placeholder="{{ __('exam_key') }}" class="form-control" readonly />
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>{{ __('duration') }} <span class="text-danger">*</span></label><span
                                            class="text-info small">( {{ __('in_minutes') }} )</span>
                                        <input type="number" id="edit-online-exam-duration" required
                                            name="edit_duration" placeholder="{{ __('duration') }}"
                                            class="form-control" />
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label>{{ __('start_date') }} <span class="text-danger">*</span></label>
                                        <input type="datetime-local" id="edit-online-exam-start-date" required
                                            name="edit_start_date" placeholder="{{ __('start_date') }}"
                                            class='form-control'>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>{{ __('end_date') }} <span class="text-danger">*</span></label>
                                        <input type="datetime-local" id="edit-online-exam-end-date" required
                                            name="edit_end_date" placeholder="{{ __('end_date') }}"
                                            class='form-control'>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary"
                                    data-bs-dismiss="modal">{{ __('close') }}</button>
                                <input class="btn btn-theme" type="submit" value={{ __('submit') }} />
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
        
        const oeLabels = {
            key: "{{ __('key') }}",
            questions: "{{ __('questions') }}",
            mins: "{{ __('mins') }}",
            aggregated: "{{ __('aggregated') }}",
            section: "{{ __('section') }}",
            sections: "{{ __('sections') }}",
            assignedClassSections: "{{ __('assigned_class_sections') }}",
            resultsManaged: "{{ __('results_are_managed_per_section') }}",
            viewResults: "{{ __('view_results') }}",
            classSectionName: "{{ __('class_section_name') }}",
            participants: "{{ __('participants') }}",
            sectionAction: "{{ __('section_action') }}",
        };

        
        const oeResultRouteTemplate = "{{ route('online-exam.result.index', ['id' => '__ID__']) }}";
        
      
    </script>
    <script src="{{ asset('assets/js/formatter.js') }}"></script>
@endsection