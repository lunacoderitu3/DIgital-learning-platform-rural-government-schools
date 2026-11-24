@extends('layouts.master')

@section('title')
    {{ __('qr_attendance_settings') }}
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/qr-attendance.css') }}?v={{ config('app.version') }}">
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('qr_attendance_settings') }}
            </h3>
        </div>

        <form class="create-form-without-reset" id="qr-settings-form" action="{{ route('qr-attendance.settings.store') }}"
            method="POST" novalidate="novalidate">
            @csrf
            <div class="row">
                {{-- ============================ main column ============================ --}}
                <div class="col-12 col-lg-8 grid-margin">

                    {{-- master switch --}}
                    <div class="card mb-4">
                        <div class="card-body d-flex align-items-center justify-content-between flex-wrap">
                            <div class="pr-3">
                                <h4 class="card-title mb-1">{{ __('enable_qr_staff_attendance') }}</h4>
                                <p class="text-muted small mb-0">
                                    {{ __('qr_attendance_disabled') }}
                                </p>
                            </div>
                            <input type="hidden" name="qr_attendance_enabled" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="qr_attendance_enabled"
                                    name="qr_attendance_enabled" value="1"
                                    {{ $config['qr_attendance_enabled'] == 1 ? 'checked' : '' }}>
                                <label class="form-check-label" for="qr_attendance_enabled"></label>
                            </div>
                        </div>
                    </div>

                    {{-- school timing --}}
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="card-title">{{ __('school_start_and_end_time') }}</h4>
                            <div class="row">
                                <div class="form-group col-sm-12 col-md-4">
                                    <label for="qr_attendance_school_start_time">
                                        {{ __('school_start_time') }} <span class="text-danger">*</span>
                                    </label>
                                    <input type="time" class="form-control" id="qr_attendance_school_start_time"
                                        name="qr_attendance_school_start_time" required
                                        value="{{ old('qr_attendance_school_start_time', $config['qr_attendance_school_start_time']) }}">
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label for="qr_attendance_school_end_time">
                                        {{ __('school_end_time') }} <span class="text-danger">*</span>
                                    </label>
                                    <input type="time" class="form-control" id="qr_attendance_school_end_time"
                                        name="qr_attendance_school_end_time" required
                                        value="{{ old('qr_attendance_school_end_time', $config['qr_attendance_school_end_time']) }}">
                                    <small class="text-muted">{{ __('scanning_stops_at_school_end') }}</small>
                                </div>
                                <div class="form-group col-sm-12 col-md-4">
                                    <label>{{ __('finalization_runs_at') }}</label>
                                    <input type="text" class="form-control" id="finalization_time" readonly
                                        value="{{ $config['qr_attendance_school_end_time'] ? date('H:i', strtotime($config['qr_attendance_school_end_time']) + 3600) : '--:--' }}">
                                    <small class="text-muted">
                                        {{ __('school_end_plus_one_hour') }} &middot; {{ schoolTimezone() }}
                                    </small>
                                </div>
                            </div>

                            @if (
                                $latestShiftEnd &&
                                    $config['qr_attendance_school_end_time'] &&
                                    strtotime($latestShiftEnd) > strtotime($config['qr_attendance_school_end_time']))
                                <div class="alert alert-warning mb-0 py-2">
                                    <i class="fa fa-exclamation-triangle mr-1"></i>
                                    {{ __('school_end_time') }} ({{ $config['qr_attendance_school_end_time'] }})
                                    &lt; {{ __('shift') }} {{ __('end_time') }}
                                    ({{ date('H:i', strtotime($latestShiftEnd)) }}).
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- location --}}
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start flex-wrap">
                                <div>
                                    <h4 class="card-title">{{ __('attendance_location') }}</h4>
                                    <p class="text-muted small">{{ __('attendance_location_help') }}</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="use-current-location">
                                    <i class="fa fa-crosshairs mr-1"></i>{{ __('use_my_current_location') }}
                                </button>
                            </div>

                            <div class="row">
                                <div class="col-12 col-lg-5">
                                    <div class="form-group">
                                        <label for="qr_attendance_latitude">
                                            {{ __('latitude') }} <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="qr_attendance_latitude"
                                            name="qr_attendance_latitude" required
                                            value="{{ old('qr_attendance_latitude', $config['qr_attendance_latitude']) }}">
                                    </div>
                                    <div class="form-group">
                                        <label for="qr_attendance_longitude">
                                            {{ __('longitude') }} <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="qr_attendance_longitude"
                                            name="qr_attendance_longitude" required
                                            value="{{ old('qr_attendance_longitude', $config['qr_attendance_longitude']) }}">
                                    </div>
                                    <div class="form-group mb-0">
                                        <label for="qr_attendance_radius">
                                            {{ __('allowed_radius') }} <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="qr_attendance_radius"
                                                name="qr_attendance_radius" min="10" max="5000" step="10"
                                                required
                                                value="{{ old('qr_attendance_radius', $config['qr_attendance_radius'] ?? 150) }}">
                                            <span class="input-group-text">{{ __('metres') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-7">
                                    <div class="qr-map-wrap" id="qr-map-wrap">
                                        <div class="qr-map-canvas" id="qr-map-canvas"></div>
                                        <div class="qr-map-preview" id="qr-map-preview">
                                            <i class="fa fa-map-marker qr-pv-pin"></i>
                                            <div class="qr-pv-circle" id="qr-pv-circle"></div>
                                            <span class="qr-pv-flag">
                                                <i class="fa fa-info-circle mr-1"></i>{{ __('google_maps_api_key') }}
                                            </span>
                                        </div>
                                        <div class="qr-map-note" id="qr-map-note"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- calculation mode --}}
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="card-title">{{ __('working_hours_calculation') }}</h4>
                            <div class="row qr-calc-options">
                                <div class="col-12 col-md-6 mb-3 mb-md-0">
                                    <label
                                        class="qr-calc-card {{ $config['qr_attendance_calculation_mode'] === 'SHIFT_BASED' ? 'is-active' : '' }}"
                                        for="calc_mode_shift">
                                        <div class="qr-calc-radio-wrapper">
                                            <input class="qr-calc-radio" type="radio" id="calc_mode_shift"
                                                name="qr_attendance_calculation_mode" value="SHIFT_BASED"
                                                {{ $config['qr_attendance_calculation_mode'] === 'SHIFT_BASED' ? 'checked' : '' }}>
                                            <span class="qr-calc-custom-radio"></span>
                                        </div>
                                        <div class="qr-calc-content">
                                            <div class="qr-calc-title">{{ __('shift_based') }}</div>
                                            <p class="qr-calc-desc">{{ __('shift_based_help') }}</p>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label
                                        class="qr-calc-card {{ $config['qr_attendance_calculation_mode'] === 'DAY_BASED' ? 'is-active' : '' }}"
                                        for="calc_mode_day">
                                        <div class="qr-calc-radio-wrapper">
                                            <input class="qr-calc-radio" type="radio" id="calc_mode_day"
                                                name="qr_attendance_calculation_mode" value="DAY_BASED"
                                                {{ $config['qr_attendance_calculation_mode'] === 'DAY_BASED' ? 'checked' : '' }}>
                                            <span class="qr-calc-custom-radio"></span>
                                        </div>
                                        <div class="qr-calc-content">
                                            <div class="qr-calc-title">{{ __('day_based') }}</div>
                                            <p class="qr-calc-desc">{{ __('day_based_help') }}</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div class="alert alert-info mt-3 mb-0 py-2 small d-flex align-items-center">
                                <i class="fa fa-info-circle mr-2"></i>
                                <div>{{ __('thresholds_help') }}</div>
                            </div>
                            <div class="alert alert-info mt-3 mb-0 py-2 small d-flex align-items-center">
                                <i class="fa fa-info-circle mr-2"></i>
                                <div>{{ __('day_based_note') }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- punch rules --}}
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="card-title">{{ __('scan_cooldown') }}</h4>
                            <div class="row align-items-center">
                                <div class="form-group col-sm-12 col-md-5 col-lg-4 mb-0">
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="qr_attendance_cooldown_seconds"
                                            name="qr_attendance_cooldown_seconds" min="0" max="600" required
                                            value="{{ old('qr_attendance_cooldown_seconds', $config['qr_attendance_cooldown_seconds']) }}">
                                        <span class="input-group-text">{{ __('seconds') }}</span>
                                    </div>
                                </div>
                                <div class="col-sm-12 col-md-7 col-lg-8 mt-2 mt-md-0">
                                    <p class="text-muted small mb-0">
                                        {{ __('please_wait_before_scanning_again', ['seconds' => $config['qr_attendance_cooldown_seconds']]) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- missing punch out --}}
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start flex-wrap">
                                <h4 class="card-title">{{ __('missing_punch_out_report') }}</h4>
                                <input type="hidden" name="qr_attendance_notify_missing_punch_out" value="0">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        id="qr_attendance_notify_missing_punch_out"
                                        name="qr_attendance_notify_missing_punch_out" value="1"
                                        {{ $config['qr_attendance_notify_missing_punch_out'] == 1 ? 'checked' : '' }}>
                                    <label class="form-check-label" for="qr_attendance_notify_missing_punch_out"></label>
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label
                                    for="qr_attendance_notification_emails">{{ __('missing_punch_out_report_recipients') }}</label>
                                <input type="text" class="form-control" id="qr_attendance_notification_emails"
                                    name="qr_attendance_notification_emails"
                                    placeholder="principal@example.com, hr@example.com"
                                    value="{{ old('qr_attendance_notification_emails', $config['qr_attendance_notification_emails']) }}">
                                <small class="text-muted">{{ __('missing_punch_out_report_note') }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="text-right mb-4">
                        <input class="btn btn-theme" id="create-btn" type="submit" value="{{ __('save_settings') }}">
                    </div>
                </div>

                {{-- ============================ side column ============================ --}}
                <div class="col-12 col-lg-4 grid-margin">

                    {{-- readiness --}}
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="card-title mb-1">{{ __('readiness') }}</h4>
                            <p class="text-muted small">{{ __('all_items_must_pass_before_enabling') }}</p>
                            <ul class="qr-checklist" id="qr-readiness">
                                @foreach ($readiness as $check)
                                    <li class="{{ $check['passed'] ? 'ok' : 'bad' }}">
                                        <i
                                            class="fa {{ $check['passed'] ? 'fa-check-circle' : 'fa-exclamation-circle' }}"></i>
                                        <div>
                                            {{ $check['label'] }}
                                            @if (!empty($check['detail']))
                                                <small>{{ $check['detail'] }}</small>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    {{-- qr code --}}
                    <div class="card mb-4">
                        <div class="card-body text-center">
                            <h4 class="card-title text-left">{{ __('attendance_qr_code') }}</h4>

                            @if ($qrCode)
                                <div class="qr-frame mb-3">
                                    {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(190)->margin(1)->generate($qrCode->token) !!}
                                </div>

                                <div class="mb-3">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" id="print-qr" title="{{ __('print') }}">
                                        <i class="fa fa-print mr-1"></i>{{ __('print') }}
                                    </button>
                                </div>

                                <div class="mb-3" id="qr-status-badges">
                                    <span class="badge badge-success">{{ __('Active') }}</span>
                                    @if ($qrCode->end_date)
                                        <span class="badge badge-primary">
                                            {{ __('valid_till') }} {{ $qrCode->end_date->format('d-m-Y') }}
                                        </span>
                                    @else
                                        <span class="badge badge-danger">{{ __('no_expiry') }}</span>
                                    @endif
                                </div>

                                <div class="text-left">
                                    <label for="qr_end_date">{{ __('qr_end_date') }}</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control datepicker-popup" id="qr_end_date"
                                            value="{{ $qrCode->end_date?->format('d-m-Y') }}">
                                        <button class="btn btn-theme" type="button" id="save-qr-end-date"
                                            data-url="{{ route('qr-attendance.qr-code.end-date') }}">
                                            {{ __('submit') }}
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        {{ __('qr_code_expired') }}
                                    </small>

                                    <dl class="qr-kv mt-3 mb-0">
                                        <dt>{{ __('qr_code_token') }}</dt>
                                        <dd>{{ Str::limit($qrCode->token, 13) }}</dd>
                                        <dt>{{ __('generated_at') }}</dt>
                                        <dd>{{ $qrCode->generated_at?->format('d-m-Y H:i') }}</dd>
                                    </dl>
                                </div>
                            @else
                                <p class="text-muted small">{{ __('qr_code_not_generated_yet') }}</p>
                                <div class="text-left mb-3">
                                    <label for="new_qr_end_date">{{ __('qr_end_date') }}</label>
                                    <input type="date" class="form-control" id="new_qr_end_date"
                                        min="{{ date('Y-m-d') }}">
                                </div>
                                <button class="btn btn-theme btn-block" type="button" id="generate-qr"
                                    data-url="{{ route('qr-attendance.qr-code.generate') }}">
                                    <i class="fa fa-qrcode mr-1"></i>{{ __('generate_qr_code') }}
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- retention --}}
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="card-title">{{ __('punch_history_retention') }}</h4>
                            <div class="alert alert-warning mb-0 py-2 small">
                                <i class="fa fa-database mr-1"></i>{{ __('punch_retention_note') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('script')
    <script src="{{ asset('assets/js/custom/qr-attendance-settings.js') }}?v={{ config('app.version') }}"></script>
    <script>
        window.qrAttendance = {
            readinessUrl: "{{ route('qr-attendance.readiness') }}",
            mapsApiKey: @json($config['google_maps_api_key']),
        };

        $(document).ready(function() {
            setTimeout(() => {

                var key = $.trim(window.qrAttendance.mapsApiKey || "");

                if (!key) {
                    return;
                }

                if (window.google && window.google.maps) {
                    window.initQrAttendanceMap();
                    return;
                }

                var script = document.createElement('script');
                script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) +
                    '&callback=initQrAttendanceMap&loading=async';
                script.async = true;
                script.onerror = function() {
                    showErrorToast(window.trans['Error Occurred'] || 'Could not reach Google Maps');
                };
                document.head.appendChild(script);

            }, 500);
        });
    </script>
@endsection
