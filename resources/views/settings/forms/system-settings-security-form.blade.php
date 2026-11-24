{{-- System Settings :: Security & Server Tab --}}

<div class="row g-3">
    {{-- File upload size limit --}}
    <div class="form-group col-md-6 col-sm-12">
        <label>{{ __('file_upload_size_limit') }} (MB)</label>
        <input type="number" min="1" max="{{ str_replace('M', '', ini_get('upload_max_filesize')) }}"
            class="form-control" value="{{ $settings['file_upload_size_limit'] ?? 0 }}" id="file_upload_size_limit">
        <input type="hidden" name="file_upload_size_limit" id="txt_file_upload_size_limit"
            value="{{ $settings['file_upload_size_limit'] ?? 0 }}">
        <small class="form-text text-muted">
            {{ __('server_upload_limit') }} : {{ str_replace('M', 'MB', ini_get('upload_max_filesize')) }}
        </small>
    </div>

    {{-- Student web url --}}
    <div class="form-group col-md-6 col-sm-12">
        <label for="student_web_url">{{ __('student_web_url') }}</label>
        <input name="student_web_url" id="student_web_url" value="{{ $settings['student_web_url'] ?? '' }}"
            type="text" placeholder="{{ __('student_web_url') }}" class="form-control" />
    </div>

    <div class="form-group col-sm-12 col-md-12">
        <label for=""><strong>{{ __('cron_job_url') }}</strong> :</label>

        {!! Form::text('info-link', 'cd /your-project-path/ && php artisan schedule:run >> /dev/null 2>&1 ', [
            'class' => 'form-control',
            'readonly',
        ]) !!}
    </div>

    <div class="form-group col-sm-12 col-md-12">
        <div class="alert alert-danger" role="alert">
            {{ __('kindly_configure_the_cron_job_on_your_server_to_execute_the_URL_every_minute_This_will_facilitate_the_regular_examination_of_school_subscription_expirations_and_the_creation_of_bills') }}
        </div>
    </div>
</div>

<hr class="my-4">

{{-- Feature & security policy switches --}}
<label class="text-uppercase text-muted fw-bold mb-3 d-block" style="letter-spacing:.5px;">
    {{ __('security_feature_policies') }}
</label>
<div class="row g-3">

    {{-- Maintenance mode --}}
    <div class="col-md-6 col-lg-3 col-sm-12">
        <div class="policy-card border rounded p-3 h-100" data-key="web_maintenance">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <span class="policy-icon"><i class="mdi mdi-wrench-outline"></i></span>
                <div class="form-check form-switch m-0">
                    <input type="checkbox" class="form-check-input policy-toggle" role="switch"
                        data-key="web_maintenance" id="web_maintenance" style="pointer-events:none;"
                        @if (($settings['web_maintenance'] ?? 0) == 1) checked @endif>
                </div>
            </div>
            <p class="mb-1 fw-bold">{{ __('web_maintenance') }}</p>
            <small class="text-muted">{{ __('locks_all_public_student_parent_portals') }}</small>
        </div>
        <input type="hidden" name="web_maintenance" id="txt_web_maintenance"
            value="{{ $settings['web_maintenance'] ?? 0 }}">
    </div>

    {{-- Two factor verification --}}
    <div class="col-md-6 col-lg-3 col-sm-12">
        <div class="policy-card border rounded p-3 h-100" data-key="two_factor_verification">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <span class="policy-icon"><i class="mdi mdi-shield-key-outline"></i></span>
                <div class="form-check form-switch m-0">
                    <input type="checkbox" class="form-check-input policy-toggle" role="switch"
                        data-key="two_factor_verification" id="two_factor_verification" style="pointer-events:none;"
                        @if ($get_two_factor_verification == 1) checked @endif>
                </div>
            </div>
            <p class="mb-1 fw-bold">{{ __('two_factor_verification') }}</p>
            <small class="text-muted">{{ __('force_administrators_to_verify_logins_via_otp') }}</small>
        </div>
        <input type="hidden" name="two_factor_verification" id="txt_two_factor_verification"
            value="{{ $get_two_factor_verification }}">
    </div>

    {{-- School inquiry --}}
    <div class="col-md-6 col-lg-3 col-sm-12">
        <div class="policy-card border rounded p-3 h-100" data-key="school_inquiry">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <span class="policy-icon"><i class="mdi mdi-help-circle-outline"></i></span>
                <div class="form-check form-switch m-0">
                    <input type="checkbox" class="form-check-input policy-toggle" role="switch"
                        data-key="school_inquiry" id="school_inquiry" style="pointer-events:none;"
                        @if (($settings['school_inquiry'] ?? 0) == 1) checked @endif>
                </div>
            </div>
            <p class="mb-1 fw-bold">{{ __('school_inquiry') }}</p>
            <small class="text-muted">{{ __('allow_public_portal_visitor_feedback_submissions') }}</small>
        </div>
        <input type="hidden" name="school_inquiry" id="txt_school_inquiry"
            value="{{ $settings['school_inquiry'] ?? 0 }}">
    </div>

    {{-- Demo restrictions --}}
    <div class="col-md-6 col-lg-3 col-sm-12">
        <div class="policy-card border rounded p-3 h-100" data-key="demo_school_mode" data-accent="warn">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <span class="policy-icon"><i class="mdi mdi-shield-lock-outline"></i></span>
                <div class="form-check form-switch m-0">
                    <input type="checkbox" class="form-check-input policy-toggle" role="switch"
                        data-key="demo_school_mode" id="demo_school_mode" style="pointer-events:none;"
                        @if (($settings['demo_school_mode'] ?? 0) == 1) checked @endif>
                </div>
            </div>
            <p class="mb-1 fw-bold">{{ __('Demo Restrictions') }}</p>
            <small class="text-muted">{{ __('force_read_only_disallow_create_update_delete') }}</small>
        </div>
        <input type="hidden" name="demo_school_mode" id="txt_demo_school_mode"
            value="{{ $settings['demo_school_mode'] ?? 0 }}">
    </div>

</div>

{{-- End Security & Server Tab --}}
