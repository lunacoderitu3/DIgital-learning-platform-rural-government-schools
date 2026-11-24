@extends('layouts.master')

@section('title')
    {{ __('todays_attendance') }}
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/qr-attendance.css') }}?v={{ config('app.version') }}">
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('todays_attendance') }}</h3>
            <div class="d-flex flex-wrap">
                <a href="{{ route('qr-attendance.register') }}" class="btn btn-outline-primary btn-sm mr-2">
                    <i class="fa fa-calendar-check mr-1"></i>{{ __('attendance_register') }}
                </a>
                <a href="{{ route('qr-attendance.reports') }}" class="btn btn-outline-primary btn-sm mr-2">
                    <i class="fa fa-file-alt mr-1"></i>{{ __('reports') }}
                </a>
                @can('qr-attendance-settings')
                    <a href="{{ route('qr-attendance.settings') }}" class="btn btn-theme btn-sm">
                        <i class="fa fa-cog mr-1"></i>{{ __('settings') }}
                    </a>
                @endcan
            </div>
        </div>

        <p class="text-muted">
            {{ date('l, d M Y', strtotime($date)) }}
            @if ($config['qr_attendance_school_start_time'])
                · {{ $config['qr_attendance_school_start_time'] }} – {{ $config['qr_attendance_school_end_time'] }}
            @endif
            @if ($finalizationTime)
                · {{ __('finalization_runs_at') }} <strong>{{ substr($finalizationTime, 0, 5) }}</strong>
            @endif
        </p>

        @if ($config['qr_attendance_enabled'] != 1)
            <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle mr-1"></i>{{ __('qr_attendance_disabled') }}
                @can('qr-attendance-settings')
                    <a href="{{ route('qr-attendance.settings') }}">{{ __('qr_attendance_settings') }}</a>
                @endcan
            </div>
        @endif

        {{-- summary tiles --}}
        <div class="row">
            @php
                $tiles = [
                    [
                        'label' => __('on_duty_now'),
                        'value' => $summary['on_duty'],
                        'meta' => __('punched_in_not_out'),
                        'class' => '',
                    ],
                    [
                        'label' => __('punched_today'),
                        'value' => $summary['punched'] . ' / ' . $summary['total'],
                        'meta' => __('at_least_one_punch'),
                        'class' => 'text-success',
                    ],
                    [
                        'label' => __('open_sessions'),
                        'value' => $summary['open_sessions'],
                        'meta' => __('in_with_no_out'),
                        'class' => 'text-warning',
                    ],
                    [
                        'label' => __('no_punch_yet'),
                        'value' => $summary['no_punch'],
                        'meta' => __('excludes_leave'),
                        'class' => 'text-danger',
                    ],
                    [
                        'label' => __('approved_leave'),
                        'value' => $summary['on_leave'],
                        'meta' => __('will_not_be_absent'),
                        'class' => 'text-primary',
                    ],
                    [
                        'label' => __('scans_today'),
                        'value' => $summary['scans'],
                        'meta' => __('raw_punch_events'),
                        'class' => '',
                    ],
                ];
            @endphp
            @foreach ($tiles as $tile)
                <div class="col-md-4 col-xl-2 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body py-3">
                            <p class="text-muted mb-1 small text-uppercase">{{ $tile['label'] }}</p>
                            <h3 class="mb-1 {{ $tile['class'] }}">{{ $tile['value'] }}</h3>
                            <p class="text-muted mb-0" style="font-size:11.5px">{{ $tile['meta'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row">
            {{-- live floor --}}
            <div class="col-12 col-xl-8 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap">
                            <div>
                                <h4 class="card-title mb-1">{{ __('live_floor') }}</h4>
                                <p class="text-muted small">{{ __('live_floor_help') }}</p>
                            </div>
                            <form method="GET" class="form-inline">
                                <input type="hidden" name="date" value="{{ $date }}">
                                <input type="search" name="search" class="form-control form-control-sm mr-2"
                                    placeholder="{{ __('search') }}" value="{{ request('search') }}">
                                <button class="btn btn-sm btn-theme" onclick="this.form.submit()"
                                    type="submit">{{ __('search') }}</button>
                            </form>
                        </div>

                        <div class="qr-ribbon-legend mb-3">
                            <span><i
                                    style="background:repeating-linear-gradient(135deg,#E3EDF3 0 4px,#DCE8F0 4px 8px)"></i>{{ __('assigned_shift') }}</span>
                            <span><i style="background:#1E8E6A"></i>{{ __('completed_session') }}</span>
                            <span><i style="background:#B9C8D2"></i>{{ __('outside_shift') }}</span>
                            <span><i
                                    style="background:#FBE9E8;border:1px dashed #CF4A45"></i>{{ __('missing_punch_out') }}</span>
                            <span><i style="background:#D98A16;width:3px"></i>{{ __('now') }}</span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="width:200px">{{ __('name') }}</th>
                                        <th style="width:120px">{{ __('shift') }}</th>
                                        <th>{{ __('day') }} <span class="text-muted small">05:00 → 21:00</span></th>
                                        <th class="text-right" style="width:90px">{{ __('worked') }}</th>
                                        <th style="width:120px">{{ __('status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($rows as $row)
                                        <tr>
                                            <td>

                                                <div class="d-flex align-items-center">
                                                    <a href="{{ $row['image'] }}" class="glightbox"><img
                                                            src="{{ $row['image'] }}" class="" alt="image"
                                                            onerror="onErrorImage(event)" /></a>
                                                    <div class="mx-3"> <a
                                                            href="{{ route('qr-attendance.detail', $row['staff_id']) }}?date={{ $date }}"
                                                            class="text-dark link-underline-opacity-0 link-underline">
                                                            <h6 class="mb-0">{{ $row['name'] }}</h6>
                                                        </a> <small class="text-muted text-overflow-any">
                                                            {{ $row['staff_type'] }}
                                                        </small> </div>
                                                </div>

                                                {{-- <a href="{{ route('qr-attendance.detail', $row['staff_id']) }}?date={{ $date }}"
                                                    class="text-decoration-none">
                                                    {{ $row['name'] }}
                                                </a>
                                                <span class="d-block text-muted"
                                                    style="font-size:11.5px">{{ $row['staff_type'] }}</span> --}}
                                            </td>
                                            <td>
                                                @if ($row['shift_name'])
                                                    {{ $row['shift_name'] }}
                                                @else
                                                    <span
                                                        class="qr-pill qr-pill-absent">{{ __('no_shift_assigned') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @include('qr-attendance.partials.ribbon', [
                                                    'shiftStart' => $row['shift_start'],
                                                    'shiftEnd' => $row['shift_end'],
                                                    'shiftName' => $row['shift_name'],
                                                    'sessions' => $row['sessions'],
                                                    'openSession' => $row['open_session'],
                                                    'now' =>
                                                        $date === schoolNow()->toDateString()
                                                            ? schoolNow()->format('H:i:s')
                                                            : null,
                                                    'mode' => $config['qr_attendance_calculation_mode'],
                                                ])
                                            </td>
                                            <td class="text-right">
                                                {{ intdiv($row['working_minutes'], 60) }}h
                                                {{ str_pad((string) ($row['working_minutes'] % 60), 2, '0', STR_PAD_LEFT) }}m
                                            </td>
                                            <td>
                                                @switch($row['state'])
                                                    @case('on_duty')
                                                        <span class="qr-pill qr-pill-present">{{ __('on_duty') }}</span>
                                                    @break

                                                    @case('open_session')
                                                        <span class="qr-pill qr-pill-open">{{ __('open_session') }}</span>
                                                    @break

                                                    @case('leave')
                                                        <span class="qr-pill qr-pill-leave">{{ __('leave') }}</span>
                                                    @break

                                                    @case('no_punch')
                                                        <span class="qr-pill qr-pill-absent">{{ __('no_punch_yet') }}</span>
                                                    @break

                                                    @default
                                                        <span class="qr-pill qr-pill-qr">{{ __('off_duty') }}</span>
                                                @endswitch
                                            </td>
                                        </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">
                                                    {{ __('no_data_found') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- side column --}}
                <div class="col-12 col-xl-4">
                    {{-- recent scans --}}
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="card-title mb-1">{{ __('recent_scans') }}</h4>
                            <p class="text-muted small">{{ __('raw_punch_events_newest_first') }}</p>
                            <ul class="qr-punch-list">
                                @forelse ($scans as $scan)
                                    <li>
                                        <span class="p-time">{{ $scan['time'] }}</span>
                                        <span class="p-node {{ $scan['type'] === 'OUT' ? 'out' : '' }}">
                                            <i
                                                class="fa {{ $scan['type'] === 'OUT' ? 'fa-sign-out-alt' : 'fa-sign-in-alt' }}"></i>
                                        </span>
                                        <span>
                                            <strong>{{ $scan['name'] }}</strong>
                                            {{ $scan['type'] === 'OUT' ? __('punch_out') : __('punch_in') }}
                                            @if ($scan['distance'] !== null)
                                                <span class="p-meta">{{ $scan['distance'] }} m {{ __('from_school') }}</span>
                                            @endif
                                        </span>
                                    </li>
                                @empty
                                    <li class="text-muted">{{ __('no_data_found') }}</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>

                    {{-- needs attention --}}
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="card-title">{{ __('needs_attention') }}</h4>

                            @if ($summary['open_sessions'] > 0)
                                <div class="alert alert-warning py-2 small mb-2">
                                    <strong>{{ $summary['open_sessions'] }}</strong> {{ __('open_sessions') }}.
                                    {{ __('open_sessions_warning') }}
                                </div>
                            @endif

                            @if ($staffWithoutShift > 0)
                                <div class="alert alert-info py-2 small mb-2">
                                    <strong>{{ $staffWithoutShift }}</strong> {{ __('staff_without_shift_warning') }}
                                </div>
                            @endif

                            @if ($summary['open_sessions'] == 0 && $staffWithoutShift == 0)
                                <p class="text-muted small mb-0">{{ __('nothing_needs_attention') }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- finalization --}}
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="card-title">{{ __('finalization') }}</h4>
                            <dl class="qr-kv">
                                <dt>{{ __('school_end_time') }}</dt>
                                <dd>{{ $config['qr_attendance_school_end_time'] ?? '—' }}</dd>
                                <dt>{{ __('finalization_runs_at') }}</dt>
                                <dd>{{ $finalizationTime ? substr($finalizationTime, 0, 5) : '—' }}</dd>
                                <dt>{{ __('time_zone') }}</dt>
                                <dd>{{ schoolTimezone() }}</dd>
                                <dt>{{ __('calculation_mode') }}</dt>
                                <dd>{{ $config['qr_attendance_calculation_mode'] === 'DAY_BASED' ? __('day_based') : __('shift_based') }}
                                </dd>
                                <dt>{{ __('last_run') }}</dt>
                                <dd>{{ $lastFinalized ? date('d M Y H:i', strtotime($lastFinalized)) : '—' }}</dd>
                            </dl>
                            <p class="text-muted small mb-0 mt-2">{{ __('finalization_idempotent_note') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
