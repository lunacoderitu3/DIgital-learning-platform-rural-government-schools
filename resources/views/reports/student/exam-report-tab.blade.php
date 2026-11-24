{{-- Student Exam Report Tab --}}
<div id="exam_reports_container" data-session-year-id="{{ $session_year_id }}" data-student-id="{{ $student->user_id }}"
    data-report-url="{{ route('reports.student.exam.report') }}" data-trans-loading="{{ __('Loading') }}"
    data-trans-no-exam-results="{{ __('no_exam_results_available_for_this_student') }}"
    data-trans-no-records="{{ __('No exam records found for the selected session year.') }}"
    data-trans-exam="{{ __('Exam') }}" data-trans-subject="{{ __('Subject') }}"
    data-trans-max-marks="{{ __('Max Marks') }}" data-trans-obtained="{{ __('Obtained') }}"
    data-trans-grade="{{ __('Grade') }}" data-trans-status="{{ __('Status') }}"
    data-trans-pass="{{ __('Pass') }}" data-trans-fail="{{ __('Fail') }}"
    data-trans-not-attempted="{{ __('This exam was not attempted by the student') }}"
    data-trans-no-subject-data="{{ __('No subject data available') }}" data-trans-total="{{ __('Total') }}"
    data-trans-online-exams="{{ __('Online Exams') }}" data-trans-taken="{{ __('Taken') }}">

    @if (isset($hasSemesters) && $hasSemesters && isset($semesters) && $semesters->isNotEmpty())
        <div class="row mb-3">
            <div class="col-md-4">
                <select class="form-control" id="exam_semester_filter">
                    <option value="">{{ __('All Semesters') }}</option>
                    @foreach ($semesters as $semester)
                        <option value="{{ $semester->id }}">{{ $semester->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    {{-- Loading --}}
    <div class="svr-loading" id="exam_loading">
        <div class="spinner-border text-theme" role="status">
            <span class="sr-only">{{ __('Loading') }}</span>
        </div>
        <p class="mt-2 mb-0">{{ __('Loading') }}</p>
    </div>

    {{-- No data --}}
    <div class="svr-no-data" id="no_exam_data" style="display:none;">
        <i class="fa fa-folder-open fa-2x mb-2 d-block"></i>
        {{ __('no_exam_results_available_for_this_student') }}
    </div>

    {{-- Results --}}
    <div id="exam_results_wrapper" style="display:none;"></div>
</div>

<script src="{{ asset('js/student-exam-report-tab.js') }}?v={{ time() }}"></script>
