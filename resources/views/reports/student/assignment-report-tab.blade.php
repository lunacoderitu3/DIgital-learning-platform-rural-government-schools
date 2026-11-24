<div class="assignment-report-container card p-4" id="assignment_report_container"
    data-session-year-id="{{ $session_year_id ?? '' }}" data-student-id="{{ $student->user_id ?? '' }}"
    data-report-url="{{ route('reports.student.assignment.report') }}">

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div class="mb-3 mb-lg-0">
            <h4 class="mb-0">{{ __('student_assignment_submission_report') }}</h4>
            <small
                class="text-muted">{{ __('filter_assignment_records_check_feedback_view_uploaded_files_track_points') }}</small>
        </div>
        <div class="d-flex flex-wrap w-100 justify-content-lg-end" style="gap: 15px;">
            @if (isset($hasSemesters) && $hasSemesters && isset($semesters) && $semesters->isNotEmpty())
                <div class="form-group mb-0 flex-grow-1" style="max-width: 200px;">
                    <label class="small text-muted mb-1">{{ __('semester') }}</label>
                    <select class="form-control form-control-sm" id="assignment_semester_filter">
                        <option value="">{{ __('All Semesters') }}</option>
                        @foreach ($semesters as $semester)
                            <option value="{{ $semester->id }}">{{ $semester->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="form-group mb-0 flex-grow-1" style="max-width: 200px;">
                <label class="small text-muted mb-1 text-uppercase">{{ __('status') }}</label>
                <select class="form-control form-control-sm" id="assignment_status_filter">
                    <option value="">{{ __('all_statuses') }}</option>
                    <option value="Accepted">{{ __('accepted') }}</option>
                    <option value="Pending">{{ __('pending_review') }}</option>
                    <option value="Rejected">{{ __('rejected') }}</option>
                    <option value="Overdue">{{ __('overdue') }}</option>
                    <option value="Late Submitted">{{ __('late_submitted') }}</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-6 col-lg-3 mb-3 mb-lg-0">
            <div class="card bg-light border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-primary text-white rounded p-3 mr-3">
                        <i class="fa fa-trophy fa-lg"></i>
                    </div>
                    <div>
                        <div class="text-muted small font-weight-bold text-uppercase">{{ __('overall_points_ratio') }}
                        </div>
                        <h4 class="mb-0 font-weight-bold"><span id="asr_overall_obtained">0</span> / <span
                                id="asr_overall_max">0</span></h4>
                        <small class="text-primary font-weight-bold"><span id="asr_overall_percentage">0</span>%
                            {{ __('grade_average') }}</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mb-3 mb-lg-0">
            <div class="card bg-light border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-success text-white rounded p-3 mr-3">
                        <i class="fa fa-check-circle fa-lg"></i>
                    </div>
                    <div>
                        <div class="text-muted small font-weight-bold text-uppercase">{{ __('accepted') }}</div>
                        <h4 class="mb-0 font-weight-bold" id="asr_accepted_count">0</h4>
                        <small class="text-success font-weight-bold">{{ __('assignments') }}</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mb-3 mb-lg-0">
            <div class="card bg-light border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-info text-white rounded p-3 mr-3">
                        <i class="fa fa-hourglass-half fa-lg"></i>
                    </div>
                    <div>
                        <div class="text-muted small font-weight-bold text-uppercase">{{ __('pending_review') }}</div>
                        <h4 class="mb-0 font-weight-bold" id="asr_pending_count">0</h4>
                        <small class="text-info font-weight-bold">{{ __('awaiting_score_input') }}</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mb-3 mb-lg-0">
            <div class="card bg-light border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-danger text-white rounded p-3 mr-3">
                        <i class="fa fa-exclamation-triangle fa-lg"></i>
                    </div>
                    <div>
                        <div class="text-muted small font-weight-bold text-uppercase">{{ __('overdue_and_rejected') }}
                        </div>
                        <h4 class="mb-0 font-weight-bold" id="asr_critical_count">0</h4>
                        <small class="text-danger font-weight-bold">{{ __('action_required') }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Tracker -->
    <div class="mb-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between mb-2">
            <small
                class="text-muted font-weight-bold mb-1 mb-sm-0">{{ __('task_completion_progress_tracker_relational_breakdown') }}</small>
            <small class="text-muted font-weight-bold"><span id="asr_total_completed">0</span> / <span
                    id="asr_total_assignments">0</span> {{ __('completed_submitted') }} (<span
                    id="asr_completion_percentage">0</span>%)</small>
        </div>
        <div class="progress" style="height: 10px;" id="asr_progress_bar_container">
            <!-- Dynamically populated -->
        </div>
        <div class="d-flex flex-wrap mt-2">
            <small class="mr-3 mt-1 d-flex align-items-center">
                <div class="rounded-circle bg-success mr-2" style="width: 8px; height: 8px;"></div>
                {{ __('accepted') }}
            </small>
            <small class="mr-3 mt-1 d-flex align-items-center">
                <div class="rounded-circle bg-warning mr-2" style="width: 8px; height: 8px;"></div>
                {{ __('late_submitted') }}
            </small>
            <small class="mr-3 mt-1 d-flex align-items-center">
                <div class="rounded-circle bg-info mr-2" style="width: 8px; height: 8px;"></div>
                {{ __('pending_review') }}
            </small>
            <small class="mr-3 mt-1 d-flex align-items-center">
                <div class="rounded-circle bg-danger mr-2" style="width: 8px; height: 8px;"></div>
                {{ __('overdue_and_rejected') }}
            </small>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div class="text-center py-5" id="asr_loading">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">{{ __('loading') }}</span>
        </div>
    </div>

    <!-- No Data Placeholder -->
    <div class="text-center py-5 d-none" id="asr_no_data">
        <i class="fa fa-folder-open text-muted fa-3x mb-3"></i>
        <h5 class="text-muted">{{ __('no_assignments_found') }}</h5>
    </div>

    <!-- Subject Group Container -->
    <div id="asr_subject_container" class="d-none">
        <!-- Dynamically populated -->
    </div>
</div>

<!-- View Only Modal Dialog Component -->
<div class="modal fade" id="assignmentDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">{{ __('submission_details_and_review') }}</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body bg-light p-4">
                <p class="text-muted mb-4 small" id="modal_header_subtitle"></p>
                <div class="row">
                    <div class="col-md-7">
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body d-flex align-items-center">
                                <div class="bg-light rounded-circle p-3 mr-3">
                                    <i class="fa fa-user fa-2x text-secondary"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">{{ $student->user->full_name }}</h5>
                                    <small class="text-muted" id="modal_subject_name"></small>
                                    <small class="text-muted ml-2"><i class="fa fa-clock"></i> <span
                                            id="modal_submitted_at"></span></small>
                                </div>
                            </div>
                        </div>

                        <h6 class="text-muted small font-weight-bold mb-2 text-uppercase"><i
                                class="fa fa-paperclip"></i>
                            {{ __('attached_files') }}</h6>
                        <div id="modal_attached_files" class="mb-4">
                            <!-- Files dynamically populated -->
                        </div>

                        <h6 class="text-muted small font-weight-bold mb-2 text-uppercase"><i class="fa fa-link"></i>
                            {{ __('submission_link') }}</h6>
                        <div class="card border-0 shadow-sm border-light" style="border-style: dashed !important;">
                            <div class="card-body text-center py-3">
                                <span class="text-muted font-italic"
                                    id="modal_submission_link">{{ __('no_link_assets_provided') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <h6 class="text-muted small font-weight-bold mb-2 text-uppercase">
                            {{ __('submission_status') }}
                        </h6>
                        <div class="card border-0 shadow-sm mb-4" id="modal_status_card">
                            <div class="card-body text-center p-3">
                                <div id="modal_status_icon" class="mb-2"></div>
                                <h6 class="font-weight-bold mb-0" id="modal_status_text"></h6>
                                <small id="modal_status_desc"></small>
                            </div>
                        </div>

                        <h6 class="text-muted small font-weight-bold mb-2 text-uppercase">{{ __('grade_points') }}
                        </h6>
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0 font-weight-bold"><span id="modal_points_obtained"></span> <span
                                            class="text-muted h5">/ <span id="modal_points_max"></span></span></h3>
                                </div>
                                <span class="badge badge-success p-2" id="modal_points_badge"></span>
                            </div>
                        </div>

                        <h6 class="text-muted small font-weight-bold mb-2 text-uppercase">
                            {{ __('teachers_feedback') }}
                        </h6>
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-3">
                                <div class="d-flex">
                                    <div class="text-primary mr-2"><i class="fa fa-quote-left fa-lg"></i></div>
                                    <p class="font-italic text-muted mb-0" id="modal_feedback"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><i
                        class="fa fa-times-circle"></i>
                    {{ __('close_details') }}</button>
            </div>
        </div>
    </div>
</div>
