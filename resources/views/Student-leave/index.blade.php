@extends('layouts.master')

@section('title')
    {{ __('manage_leave') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('manage_leave') }}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-12 text-right">
                                <button type="button" class="btn btn-theme border-0" data-toggle="modal"
                                    data-target="#createLeaveModal">
                                    <i class="fa fa-plus"></i> {{ __('add_leave') }}
                                </button>
                            </div>
                        </div>

                        {{-- Filter Toolbar --}}
                        <div class="row" id="toolbar">
                            <div class="form-group col-sm-12 col-md-3">
                                <label class="filter-menu">{{ __('class_section') }}</label>
                                <select required name="class_section_id" id="timetable_class_section"
                                    class="form-control custom-search-select select2" style="width:100%;" tabindex="-1"
                                    aria-hidden="true">
                                    <option value="">{{ __('select') }}</option>
                                    @foreach ($class_sections as $section)
                                        <option value="{{ $section->id }}" data-class="{{ $section->class->id }}">
                                            {{ $section->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-sm-12 col-md-3">
                                <label class="filter-menu">{{ __('filter') }}</label>
                                <select name="filter_upcoming" id="filter_upcoming" class="form-control custom-search-select select2"
                                    style="width:100%;">
                                    <option value="All">{{ __('all') }}</option>
                                    <option value="Today">{{ __('today') }}</option>
                                    <option value="Tomorrow">{{ __('tomorrow') }}</option>
                                    <option value="Upcoming">{{ __('upcoming') }}</option>
                                </select>
                            </div>
                            <div class="form-group col-sm-12 col-md-3">
                                <label class="filter-menu">{{ __('status') }}</label>
                                <select name="status" id="filter_status" class="form-control custom-search-select select2"
                                    style="width:100%;">
                                    <option value="all">{{ __('all') }}</option>
                                    <option value="0">{{ __('pending') }}</option>
                                    <option value="1">{{ __('approved') }}</option>
                                    <option value="2">{{ __('rejected') }}</option>
                                </select>
                            </div>
                        </div>

                        {{-- Bootstrap Table --}}
                        <div class="table">
                            <table aria-describedby="mydesc" class="table student_leave_table" id="table_list"
                                data-toggle="table" data-url="{{ route('student-leave.show') }}"
                                data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                                data-page-list="[10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                                data-show-columns="true" data-show-refresh="true" data-trim-on-search="false"
                                data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc"
                                data-query-params="queryParams" data-escape="true">
                                <thead>
                                    <tr>

                                        <th scope="col" data-field="id" data-sortable="true" data-visible="false">
                                            {{ __('id') }}</th>
                                        <th scope="col" data-field="no">{{ __('no.') }}</th>
                                        <th scope="col" data-field="student_name" data-formatter="StudentNameFormatter"
                                            data-escape="false">{{ __('student_name') }}</th>
                                        <th scope="col" data-field="class_section">{{ __('class_section') }}</th>
                                        <th scope="col" data-field="days" data-formatter="leaveDaysFormatter"
                                            data-sortable="false">{{ __('duration') }}</th>
                                        <th scope="col" data-field="added_by_user"
                                            data-formatter="appliedByUserFormatter" data-escape="false">
                                            {{ __('applied_by') }}</th>
                                        <th scope="col" data-field="reason">{{ __('reason') }}</th>
                                        <th scope="col" data-field="note" data-visible="false">{{ __('note') }}</th>
                                        <th scope="col" data-field="file" data-formatter="studentLeaveFileFormatter"
                                            data-escape="false">{{ __('attachments') }}</th>
                                        <th scope="col" data-field="status" data-formatter="leaveStatusFormatter"
                                            data-escape="false">{{ __('status') }}</th>
                                        <th scope="col" data-field="operate" data-events="studentLeaveEvents"
                                            data-escape="false">{{ __('action') }}</th>


                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit / Update Modal --}}
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">{{ __('edit_leave') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form class="create-form-without-reset" id="edit-leave-form"
                    action="{{ route('student-leave.update-status') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_id">

                        {{-- From Date --}}
                        <div class="form-group">
                            <label>{{ __('from_date') }} <span class="text-danger">*</span></label>
                            <input type="text" name="from_date" id="edit_from_date"
                                class="form-control datepicker-popup" placeholder="{{ __('from_date') }}" required
                                autocomplete="off">
                        </div>

                        {{-- To Date --}}
                        <div class="form-group">
                            <label>{{ __('to_date') }} <span class="text-danger">*</span></label>
                            <input type="text" name="to_date" id="edit_to_date" class="form-control datepicker-popup"
                                placeholder="{{ __('to_date') }}" required autocomplete="off">
                        </div>

                        {{-- Reason --}}
                        <div class="form-group">
                            <label>{{ __('reason') }} <span class="text-danger">*</span></label>
                            <textarea name="reason" id="edit_reason" class="form-control" rows="3" required
                                placeholder="{{ __('reason') }}"></textarea>
                        </div>

                        {{-- Status --}}
                        <div class="form-group">
                            <label>{{ __('status') }} <span class="text-danger">*</span></label>
                            <select name="status" id="edit_status" class="form-control select2" style="width:100%;"
                                required>
                                <option value="0">{{ __('pending') }}</option>
                                <option value="1">{{ __('approved') }}</option>
                                <option value="2">{{ __('rejected') }}</option>
                            </select>
                        </div>

                        {{-- Rejection Reason / Note --}}
                        <div class="form-group" id="rejection_note_group" style="display: none;">
                            <label>{{ __('rejection_reason') }}</label>
                            <textarea name="note" id="edit_note" class="form-control" rows="2"
                                placeholder="{{ __('enter_rejection_reason_or_note') }}"></textarea>
                        </div>


                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary"
                            data-dismiss="modal">{{ __('close') }}</button>
                        <button type="submit" class="btn btn-theme">{{ __('update') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Create Student Leave Modal --}}
    <div class="modal fade" id="createLeaveModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('add_leave') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form class="create-form" id="create-leave-form" action="{{ route('student-leave.store') }}"
                    method="POST">
                    @csrf
                    <div class="modal-body">
                        {{-- Step 1: Select Class Section --}}
                        <div class="form-group">
                            <label>{{ __('class_section') }} <span class="text-danger">*</span></label>
                            <select id="modal_class_section_id" class="form-control custom-search-select select2"
                                style="width:100%;">
                                <option value="">{{ __('select_class_section') }}</option>
                                @foreach ($class_sections as $section)
                                    <option value="{{ $section->id }}">{{ $section->full_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Step 2: Select Student --}}
                        <div class="form-group">
                            <label>{{ __('student') }} <span class="text-danger">*</span></label>
                            <select name="student_id" id="modal_student_id"
                                class="form-control custom-search-select select2" style="width:100%;" required>
                                <option value="">{{ __('select_student') }}</option>
                                @foreach ($students as $student)
                                    <option value="{{ $student->id }}"
                                        data-class-section="{{ $student->class_section_id }}">
                                        {{ $student->user->full_name ?? '' }}
                                        ({{ $student->class_section->full_name ?? '' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- From Date --}}
                        <div class="form-group">
                            <label>{{ __('from_date') }} <span class="text-danger">*</span></label>
                            <input type="text" name="from_date" class="form-control datepicker-popup"
                                placeholder="{{ __('from_date') }}" required autocomplete="off">
                        </div>

                        {{-- To Date --}}
                        <div class="form-group">
                            <label>{{ __('to_date') }} <span class="text-danger">*</span></label>
                            <input type="text" name="to_date" class="form-control datepicker-popup"
                                placeholder="{{ __('to_date') }}" required autocomplete="off">
                        </div>

                        {{-- Reason --}}
                        <div class="form-group">
                            <label>{{ __('reason') }} <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="3" required placeholder="{{ __('reason') }}"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary"
                            data-dismiss="modal">{{ __('close') }}</button>
                        <button type="submit" class="btn btn-theme">{{ __('submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
