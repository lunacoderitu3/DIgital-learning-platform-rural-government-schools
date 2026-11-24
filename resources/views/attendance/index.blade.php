@extends('layouts.master')

@section('title')
{{ __('attendance') }}
@endsection

@section('css')
<style>
    .attendance-badge {
        display: inline-flex;
        align-items: center;
        padding: 8px 16px;
        border-radius: 30px;
        /* Pill-shaped modern design */
        font-size: 0.85rem;
        font-weight: 600;
        letter-spacing: 0.3px;
        white-space: nowrap;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease-in-out;
    }

    /* Eye-catching Green Badge for Attendance Filled */
    .attendance-badge-filled {
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        color: #166534;
        border: 1px solid #86efac;
    }

    /* Eye-catching Amber/Yellow Badge for Attendance Pending */
    .attendance-badge-pending {
        background: linear-gradient(135deg, #fef9c3 0%, #fde047 100%);
        color: #854d0e;
        border: 1px solid #facc15;
    }

    .attendance-badge i {
        font-size: 1rem;
        margin-right: 8px;
    }
</style>
@endsection


@section('content')
<div class="content-wrapper">
    <div class="page-header">
        <h3 class="page-title">
            {{ __('manage_attendance') }}
        </h3>
    </div>
    <div class="row">
        <div class="col-md-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">
                        {{ __('create_attendance') }}
                    </h4>
                    <form action="{{ route('attendance.store') }}" class="create-form attendance-table" id="formdata" data-success-function="attendanceSuccessHandler">
                        @csrf

                        {{-- Main Inputs Row --}}
                        <div class="row align-items-center mb-3">
                            {{-- Class Section Select --}}
                            <div class="form-group col-sm-12 col-md-3 mb-2 mb-md-0">
                                <select required name="class_section_id" id="timetable_class_section"
                                    class="form-control custom-search-select select2" style="width:100%;" tabindex="-1"
                                    aria-hidden="true">
                                    <option value="">{{ __('select_option') }}</option>
                                    @foreach ($classSections as $section)
                                    <option value="{{ $section->id }}" data-class="{{ $section->class->id }}">
                                        {{ $section->full_name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Date Picker --}}
                            <div class="form-group col-sm-12 col-md-3 mb-2 mb-md-0">
                                {!! Form::text('date', null, [
                                'required',
                                'placeholder' => __('date'),
                                'class' => 'datepicker-popup form-control',
                                'id' => 'date',
                                'data-date-end-date' => '0d',
                                'autocomplete' => 'off',
                                ]) !!}
                                <span class="input-group-addon input-group-append"></span>
                            </div>

                            {{-- Holiday Checkbox --}}
                            <div class="form-group col-sm-12 col-md-2 mb-2 mb-md-0 holiday-div">
                                <div class="form-check m-0">
                                    <label class="form-check-label d-flex align-items-center">
                                        <input type="checkbox" class="form-check-input mt-0 mr-2" name="holiday" id="holiday"
                                            value="0">
                                        {{ __('holiday') }}
                                        <i class="input-helper"></i>
                                    </label>
                                </div>
                            </div>

                            {{-- Attendance Status Badge --}}
                            <div class="col-12 col-md-4 text-md-right mb-2 mb-md-0 d-flex justify-content-md-end align-items-center">
                                <div id="attendance_status_badge" class="attendance-badge d-none">
                                    <i id="attendance_badge_icon" class="fa"></i>
                                    <span id="attendance_badge_label"></span>
                                </div>
                            </div>

                            {{-- Absent Notification Checkbox --}}
                            <div class="form-group col-12 col-md-6 mt-3 mb-0">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text py-2">
                                            <input type="checkbox" name="absent_notification" class="cursor-pointer"
                                                aria-label="Checkbox for following text input" id="gridCheck">
                                        </div>
                                    </div>
                                    <label class="form-control d-flex align-items-center min-height-auto cursor-pointer"
                                        for="gridCheck">
                                        {{ __('send_a_notification_to_the_guardian_if_the_student_is_absent') }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div id="toolbar" class="d-none"></div>

                        <div class="show_student_list mt-3">
                            <table aria-describedby="mydesc" class='table student_table' id='table_list'
                                data-toggle="table" data-url="{{ route('attendance.show', [1]) }}"
                                data-click-to-select="true" data-side-pagination="server" data-pagination="false"
                                data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-show-refresh="true"
                                data-toolbar="#toolbar" data-show-columns="true" data-trim-on-search="false"
                                data-mobile-responsive="true" data-sort-name="roll_number" data-sort-order="asc"
                                data-maintain-selected="true" data-export-data-type='all' data-show-export="true"
                                data-export-options='{ "fileName": "attendance-<?= date('d-m-y') ?>" ,"ignoreColumn":
                ["operate"]}'
                                data-query-params="attendanceQueryParams" data-escape="true">
                                <thead>
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="false" data-visible="false">
                                            {{ __('id') }}
                                        </th>
                                        <th scope="col" data-field="no">{{ __('no.') }}</th>
                                        <th scope="col" data-field="student_id" data-sortable="false"
                                            data-visible="false" data-formatter="addStudentIdInputAttendance">
                                            {{ __('student_id') }}
                                        </th>
                                        <th scope="col" data-field="admission_no" data-sortable="false">
                                            {{ __('admission_no') }}
                                        </th>
                                        <th scope="col" data-field="roll_no" data-sortable="false">
                                            {{ __('roll_no') }}
                                        </th>
                                        <th scope="col" data-field="name" data-escape="false">{{ __('name') }}
                                        </th>
                                        <th scope="col" data-field="type" data-formatter="addRadioInputAttendance">
                                            {{ __('type') }}
                                        </th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                        <input class="btn btn-theme btn_attendance mt-4 float-right" id="create-btn" type="submit"
                            value={{ __('submit') }}>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    $('body').on('focus', ".datepicker-popup", function() {
        let startdate =
            "{{ Carbon\Carbon::createFromFormat($originalDateFormat, $sessionYear->start_date)->format('d-m-Y') }}";
        let endDate =
            "{{ Carbon\Carbon::createFromFormat($originalDateFormat, $sessionYear->end_date)->format('d-m-Y') }}";

        let today = new Date();

        let start = new Date(startdate.split("-").reverse().join("-"));
        let end = new Date(endDate.split("-").reverse().join("-"));

        let highlightToday = today >= start && today <= end;

        let finalEndDate = endDate;
        if (today >= start && today <= end) {
            finalEndDate = "{{ Carbon\Carbon::now()->format('d-m-Y') }}";
        }

        $(this).datepicker({
            enableOnReadonly: false,
            format: "dd-mm-yyyy",
            startDate: startdate,
            endDate: finalEndDate,
            todayHighlight: highlightToday,
            rtl: isRTL()
        });
    });

    $('#date').on('input change', function() {
        $('.student_table').bootstrapTable('refresh');
    });

    $('.btn_attendance').hide();
    $('.holiday-div').hide();

    function set_data() {
        $(document).ready(function() {
            let student_class = $('#timetable_class_section').val();
            let dateVal = $('#date').val();

            if (student_class != '' && dateVal != '') {
                $('.btn_attendance').show();
                $('.holiday-div').show();
            } else {
                $('.btn_attendance').hide();
                $('.holiday-div').hide();
            }
        });
    }

    $('#timetable_class_section, #date').on('change', function() {
        set_data();
    });

    $('input[name="holiday"]').click(function() {
        let class_section_id = $('#timetable_class_section').val();
        let date = $('#date').val();
        let checkBox = document.getElementById('holiday');
        if (class_section_id != '' && date != '') {
            Swal.fire({
                title: "{{ __('are_you_sure') }}",
                text: "",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: "{{ __('Yes') }}",
                cancelButtonText: window.trans["Cancel"]
            }).then((result) => {
                if (checkBox.checked) {
                    if (result.isConfirmed == true) {
                        $("#holiday").val(3);
                        $('input[name="holiday"]').prop('checked', true);
                        $('.type').prop('required', false);
                        $('#table_list').slideUp(500);
                    } else {
                        checkBox.checked = false;
                    }
                } else {
                    if (result.isConfirmed == true) {
                        $("#holiday").val(0);
                        $('#table_list').slideDown(500);
                        $('.type').prop('required', true);
                        return true;
                    } else {
                        checkBox.checked = true;
                    }
                }
            });
        }
    });

    function updateAttendanceBadge(isFilled) {
        let badge = $('#attendance_status_badge');
        let icon = $('#attendance_badge_icon');
        let label = $('#attendance_badge_label');

        badge.removeClass('d-none attendance-badge-filled attendance-badge-pending');

        if (isFilled) {
            badge.addClass('attendance-badge-filled');
            icon.attr('class', 'fa fa-check-circle mr-1 text-success');
            label.text("{{ __('Attendance Filled') }}");
        } else {
            badge.addClass('attendance-badge-pending');
            icon.attr('class', 'fa fa-info-circle mr-1 text-warning');
            label.text("{{ __('Attendance Pending') }}");
        }
    }

    $('#timetable_class_section, #date').on('change input', function() {
        let date = $('#date').val();
        let class_section_id = $('#timetable_class_section').val();

        if (!date || !class_section_id) {
            $('#attendance_status_badge').addClass('d-none');
            return;
        }

        $.ajax({
            url: "{{ url('attendance/getAttendanceData') }}",
            type: "GET",
            data: {
                date: date,
                class_section_id: class_section_id
            },
            success: function(response) {
                updateAttendanceBadge(response.attendance_filled);

                if (response.is_holiday || response.type == 3) {
                    $('input[name="holiday"]').prop('checked', true);
                    $("#holiday").val(3);
                    $('.type').prop('required', false);
                    $('#table_list').slideUp(500);
                } else {
                    $('input[name="holiday"]').prop('checked', false);
                    $("#holiday").val(0);
                    $('#table_list').slideDown(500);
                    $('.type').prop('required', true);
                }
            }
        });
    });

    function attendanceSuccessHandler(response) {
        if (!response.error) {
            updateAttendanceBadge(true);
        }
    }

    let attendanceState = {};
    $('#table_list').on('change', 'input[type="radio"]', function() {
        let studentNo = $(this).data('id');
        let attendanceType = $(this).val();
        attendanceState[studentNo] = attendanceType;
    });

    $('#table_list').on('load-success.bs.table', function() {
        restoreAttendanceState();
    });

    function restoreAttendanceState() {
        for (let studentNo in attendanceState) {
            $(`input[type="radio"][data-id="` + studentNo + `"][value="${attendanceState[studentNo]}"]`).prop('checked', true);
        }
    }
</script>
@endsection