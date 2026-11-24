@extends('layouts.master')

@section('title')
    {{ __('assign_elective_subject') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('manage_elective_subject') }}
            </h3>
        </div>

        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            {{ __('list_elective_subject') }}
                        </h4>

                        <div class="row" id="toolbar">


                            <div class="form-group col-sm-12 col-md-3">
                                <label for="class_section_id" class="filter-menu">{{ __('Class Section') }}</label>
                                <select name="class_section_id" id="filter-class-section-id"
                                    class="form-control custom-search-select select2">
                                    <option value="">{{ __('select_option') }}</option>
                                    @foreach ($class_sections as $class_section)
                                        <option value={{ $class_section->id }}
                                            data-class-id="{{ $class_section->class_id }}"
                                            data-include-semesters="{{ $class_section->class->include_semesters }}">
                                            {{ $class_section->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group col-sm-12 col-md-3" id="semester-filter-div" style="display: none;">
                                <label for="filter-semester-id" class="filter-menu">{{ __('semester') }}</label>
                                <select name="semester_id" id="filter-semester-id"
                                    class="form-control custom-search-select select2">
                                    <option value="">{{ __('select_option') }}</option>
                                    @foreach ($semesters as $semester)
                                        <option value="{{ $semester->id }}">{{ $semester->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group col-sm-12 col-md-3">
                                <label for="elective_subject_id" class="filter-menu">{{ __('elective_subject') }}</label>
                                <select name="elective_subject_id" id="filter-elective-subject-id"
                                    class="form-control custom-search-select select2">
                                    <option value="">{{ __('select_option') }}</option>
                                    <option value="data-not-found">-- {{ __('no_data_found') }} --</option>
                                </select>
                            </div>

                            <div class="form-group col-sm-12 col-md-3">
                                <label for="status" class="filter-menu">{{ __('status') }}</label>
                                <select name="status" id="filter-status" class="form-control custom-search-select select2">
                                    <option value="">{{ __('select_option') }}</option>
                                    <option value="complete">{{ __('complete') }}</option>
                                    <option value="incomplete">{{ __('incomplete') }}</option>
                                    <option value="not_assigned">{{ __('not_assigned') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                                    data-url="{{ route('assign.elective.subject.show') }}" data-click-to-select="true"
                                    data-side-pagination="server" data-pagination="true"
                                    data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                                    data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                                    data-fixed-number="1" data-fixed-right-number="1" data-trim-on-search="false"
                                    data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc"
                                    data-maintain-selected="true" data-export-types='["txt","excel"]'
                                    data-export-options='{ "fileName": "elective-subject-list-<?= date('d-m-y') ?>"
                                    ,"ignoreColumn": ["operate"]}'
                                    data-query-params="assignElectiveSubjectQueryParams">
                                    <thead>
                                        <tr>
                                            {{-- <th scope="col" data-field="state" data-checkbox="true"></th> --}}
                                            <th scope="col" data-field="no" data-sortable="false" data-visible="false">
                                                {{ __('no.') }}</th>
                                            <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                                {{ __('id') }}</th>
                                            <th scope="col" data-field="user.full_name" data-sortable="false"
                                                data-formatter="StudentNameFormatter"> {{ __('student') }}</th>
                                            <th scope="col" data-field="status"
                                                data-formatter="assignElectiveSubjectStatusFormatter"> {{ __('status') }}
                                            </th>
                                            <th scope="col" data-field="elective_subjects"
                                                data-formatter="assignElectiveSubjectsFormatter">
                                                {{ __('selected_subjects') }}</th>
                                            <th scope="col" data-field="operate"
                                                data-events="assignElectiveSubjectEvents"
                                                data-formatter="actionColumnFormatter"> {{ __('action') }}</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="assignElectiveSubjectModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header border-bottom pb-3">
                    <h5 class="modal-title font-weight-bold" id="assignElectiveSubjectModalLabel">
                        {{ __('Assign Elective Subjects') }}</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body px-4 py-3">
                    <!-- Student Name and Class Section -->
                    <div class="mb-4">
                        <h6 class="font-weight-bold mb-0" id="modal-student-name">Student Name • Class Section</h6>
                    </div>

                    <form class="section-edit-form" id="edit-form" action="{{ url('assign.elective.subject.update') }}"
                        novalidate="novalidate">
                        <input type="hidden" name="student_id" id="edit-student-id" value="" />
                        <input type="hidden" name="user_id" id="edit-user-id" value="" />
                        <input type="hidden" name="session_year_id" id="edit-session-year-id" value="" />
                        <input type="hidden" name="class_section_id" id="edit-class-section-id" value="" />

                        <!-- Elective Subject Group Selection -->
                        <div class="form-group mb-4">
                            <label for="edit-elective-subject-group-id" class="form-label font-weight-bold mb-2">
                                {{ __('select_elective_group') }}
                            </label>
                            <select name="elective_subject_group_id" id="edit-elective-subject-group-id"
                                class="form-control" required>
                                <option value="">
                                    {{ __('select_option') }}
                                </option>
                            </select>
                        </div>

                        <!-- Validation Message -->
                        <div class="alert alert-info d-flex align-items-center mb-4" id="validation-message"
                            style="display: none !important; visibility: hidden !important; opacity: 0 !important; height: 0 !important; padding: 0 !important; margin: 0 !important; overflow: hidden !important;">
                            <i class="fa fa-info-circle mr-2"></i>
                            <span id="validation-text"></span>
                        </div>

                        <!-- Available Subjects Section -->
                        <div class="form-group mb-0">
                            <label class="form-label font-weight-bold mb-3">{{ __('Available Subjects') }}</label>
                            <div id="available-subjects-list" class="border rounded p-3 bg-light">
                                <p class="text-muted mb-0 text-center">
                                    {{ __('Please select an elective subject group first') }}</p>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-top pt-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-theme" id="save-assignment-btn">{{ __('submit') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/assign-elective-subject.css') }}">
@endsection

@section('script')
    <script>
        window.electiveSubjectGroups = @json($electiveSubjectGroups);
        window.sessionYearId = {{ $sessionYearId }};
        window.transSelectOption = "{{ __('select_option') }}";
        window.transNoDataFound = "-- {{ __('no_data_found') }} --";
        window.transSelectElectiveGroup = "{{ __('select_elective_subject_group') }}";
        window.transNoSubjectsFound = "{{ __('No subjects found in this group') }}";
        window.transGroupNotFound = "{{ __('Group not found') }}";
        window.transPleaseSelectGroup = "{{ __('Please select an elective subject group first') }}";
        window.transLoadingSubjects = "{{ __('Loading subjects...') }}";
        window.transYouMustSelect = "{{ __('You must select') }}";
        window.transSubjects = "{{ __('subjects') }}";
        window.transCurrentlySelected = "{{ __('Currently selected') }}";
        window.transYouCanSelectMax = "{{ __('You can select maximum') }}";
        window.transPerfectSelected = "{{ __('Perfect! You have selected') }}";
        window.transSaving = "{{ __('Saving') }}";
        window.assignElectiveSubjectStoreRoute = "{{ route('assign.elective.subject.store') }}";
    </script>
    <script src="{{ asset('assets/js/custom/assign-elective-subject.js') }}"></script>
@endsection
