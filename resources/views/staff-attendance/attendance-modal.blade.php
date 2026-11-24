<div class="modal fade" id="markAttendanceModal" tabindex="-1" aria-labelledby="markAttendanceLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-sm rounded-3">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-semibold" id="markAttendanceLabel">{{ __('mark_attendance') }}</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="multiple_staff" id="multipleStaff">
                <input type="hidden" name="staff_id" id="staffIds">
                <input type="hidden" name="attendance_ids" id="attendanceIds">
                <!-- Staff info -->
                <p class="text-muted mb-4">
                    <strong id="staffName"></strong> •
                    <span id="attendanceDate"></span>
                </p>

                <!-- Attendance Status -->
                <div class="attendance-status-group mb-4">
                    <label class="attendance-option">
                        <input type="radio" name="attendance_status" value="present" checked>
                        <div class="option-body">
                            <h6 class="fw-semibold mb-0">{{ __('present') }}</h6>
                            <small class="text-muted">{{ __('full_working_day') }}</small>
                        </div>
                    </label>

                    <label class="attendance-option">
                        <input type="radio" name="attendance_status" value="absent">
                        <div class="option-body">
                            <h6 class="fw-semibold mb-0">{{ __('absent') }}</h6>
                            <small class="text-muted">{{ __('will_automatically_create_a_leave_entry') }}</small>
                        </div>
                    </label>

                    <label class="attendance-option" id="halfDayOption">
                        <input type="radio" name="attendance_status" value="half_day">
                        <div class="option-body">
                            <h6 class="fw-semibold mb-0">{{ __('half_day') }}</h6>
                            <small class="text-muted">{{ __('half_day_present') }}</small>
                        </div>
                    </label>
                </div>

                <!-- Half-Day Selection -->
                <div id="halfDaySelection" class="ms-4 mb-4 d-none">
                    <p class="fw-semibold mb-2">{{ __('which_half_was_present') }}</p>
                    <label class="attendance-option sub-option">
                        <input type="radio" name="half_day_type" value="first_half">
                        <div class="option-body">
                            <h6 class="fw-semibold mb-0">{{ __('first_half') }}</h6>
                            <small class="text-muted">{{ __('morning_session_present') }}</small>
                        </div>
                    </label>

                    <label class="attendance-option sub-option">
                        <input type="radio" name="half_day_type" value="second_half">
                        <div class="option-body">
                            <h6 class="fw-semibold mb-0">{{ __('second_half') }}</h6>
                            <small class="text-muted">{{ __('afternoon_session_present') }}</small>
                        </div>
                    </label>
                </div>

                <!-- Reason -->
                <div class="mb-4 reasonSection">
                    <label class="form-label fw-semibold">{{ __('reason') }} <span
                            class="text-muted">({{ __('optional') }})</span></label>
                    <textarea class="form-control" id="reason" rows="2"
                        placeholder="E.g., {{ __('came_late') }}, {{ __('left_early') }}, {{ __('personal_work') }}, {{ __('medical_emergency') }}..."></textarea>
                    <small class="text-muted d-block mt-2">
                        {{ __('this_helps_in_maintaining_clarity_for_audits_and_reviews') }}
                    </small>
                </div>

                <!-- Footer buttons -->
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-secondary mr-2"
                        data-bs-dismiss="modal">{{ __('cancel') }}</button>
                    <button type="button" class="btn btn-theme"
                        id="saveAttendanceBtn">{{ __('save_attendance') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
