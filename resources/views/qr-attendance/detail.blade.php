@extends('layouts.master')

@section('title')
    {{ __('staff_day_detail') }}
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/qr-attendance.css') }}?v={{ config('app.version') }}">
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <div>
                <a href="{{ route('qr-attendance.register') }}?date={{ $date }}" class="text-muted small">
                    <i class="fa fa-arrow-left mr-1"></i>{{ __('attendance_register') }}
                </a>
                <h3 class="page-title mb-0">
                    {{ $day['user']->full_name }} — {{ date('d M Y', strtotime($date)) }}
                </h3>
                <p class="text-muted small mb-0">
                    {{ $day['user']->roles->pluck('name')->first() }}
                    @if ($day['snapshot']['shift_name'])
                        · {{ $day['snapshot']['shift_name'] }}
                    @endif
                    · {{ $day['snapshot']['calculation_mode'] === 'DAY_BASED' ? __('day_based') : __('shift_based') }}
                </p>
            </div>
            <div class="d-flex">
                <a href="{{ route('qr-attendance.detail', $day['user']->id) }}?date={{ date('Y-m-d', strtotime($date . ' -1 day')) }}"
                    class="btn btn-outline-primary btn-sm mr-2"><i class="fa fa-chevron-left"></i></a>
                <a href="{{ route('qr-attendance.detail', $day['user']->id) }}?date={{ date('Y-m-d', strtotime($date . ' +1 day')) }}"
                    class="btn btn-outline-primary btn-sm mr-2"><i class="fa fa-chevron-right"></i></a>
                @can('staff-attendance-edit')
                    <button class="btn btn-theme btn-sm" data-toggle="modal" data-target="#correctModal">
                        <i class="fa fa-pencil-alt mr-1"></i>{{ __('correct_attendance') }}
                    </button>
                @endcan
            </div>
        </div>

        @if ($day['open_session'])
            <div class="alert alert-warning">
                <i class="fa fa-clock mr-1"></i>
                <strong>{{ __('missing_punch_out') }}</strong> —
                {{ __('missing_punch_out_detail_note', ['time' => date('H:i', strtotime($day['open_session']))]) }}
            </div>
        @endif

        <div class="row">
            <div class="col-12 col-xl-7">

                {{-- the day --}}
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-1">{{ __('the_day') }}</h4>
                        <p class="text-muted small">{{ __('shift_window_against_punches') }}</p>

                        @include('qr-attendance.partials.ribbon', [
                            'shiftStart' => $day['snapshot']['shift_start_time'],
                            'shiftEnd' => $day['snapshot']['shift_end_time'],
                            'shiftName' => $day['snapshot']['shift_name'],
                            'sessions' => $day['sessions'],
                            'openSession' => $day['open_session'],
                            'now' => null,
                            'mode' => $day['snapshot']['calculation_mode'],
                        ])

                        <div class="qr-ribbon-legend mt-3">
                            <span><i style="background:repeating-linear-gradient(135deg,#E3EDF3 0 4px,#DCE8F0 4px 8px)"></i>{{ $day['snapshot']['shift_name'] ?? __('no_shift_assigned') }}</span>
                            <span><i style="background:#1E8E6A"></i>{{ __('completed_session') }}</span>
                            <span><i style="background:#FBE9E8;border:1px dashed #CF4A45"></i>{{ __('missing_punch_out') }}</span>
                        </div>
                    </div>
                </div>

                {{-- punch history --}}
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h4 class="card-title mb-1">{{ __('punch_history') }}</h4>
                                <p class="text-muted small">{{ __('raw_qr_events_note') }}</p>
                            </div>
                            <span class="qr-pill qr-pill-qr">{{ $day['punches']->count() }} {{ __('events') }}</span>
                        </div>

                        <div class="alert alert-light border py-2 small">
                            <i class="fa fa-database mr-1"></i>{{ __('punch_retention_note') }}
                        </div>

                        <ul class="qr-punch-list">
                            @forelse ($day['punches'] as $index => $punch)
                                @php $isOpen = $day['open_session'] && $punch->punched_at->format('Y-m-d H:i:s') === $day['open_session']; @endphp
                                <li>
                                    <span class="p-time">{{ $punch->punched_at->format('H:i') }}</span>
                                    <span
                                        class="p-node {{ $punch->type === 'OUT' ? 'out' : '' }} {{ $isOpen ? 'missing' : '' }}">
                                        <i
                                            class="fa {{ $punch->type === 'OUT' ? 'fa-sign-out-alt' : 'fa-sign-in-alt' }}"></i>
                                    </span>
                                    <span>
                                        <strong>{{ $punch->type === 'OUT' ? __('punch_out') : __('punch_in') }}</strong>
                                        @if ($isOpen)
                                            — <span class="text-danger">{{ __('no_punch_out_recorded') }}</span>
                                        @endif
                                        <span class="p-meta">
                                            @if ($punch->latitude)
                                                {{ $punch->latitude }}, {{ $punch->longitude }}
                                            @endif
                                            @if ($punch->distance !== null)
                                                · {{ $punch->distance }} m {{ __('from_school') }}
                                            @endif
                                            · {{ $punch->source }}
                                        </span>
                                    </span>
                                </li>
                            @empty
                                <li class="text-muted">{{ __('no_data_found') }}</li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                {{-- correction history --}}
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-1">{{ __('correction_history') }}</h4>
                        <p class="text-muted small">{{ __('correction_history_note') }}</p>

                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('changed_at') }}</th>
                                        <th>{{ __('status') }}</th>
                                        <th class="text-right">{{ __('working_minutes') }}</th>
                                        <th>{{ __('reason') }}</th>
                                        <th>{{ __('changed_by') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($day['corrections'] as $correction)
                                        <tr>
                                            <td>{{ $correction->created_at }}</td>
                                            <td>
                                                @include('qr-attendance.partials.status', ['type' => $correction->old_type])
                                                <i class="fa fa-arrow-right text-muted mx-1"></i>
                                                @include('qr-attendance.partials.status', ['type' => $correction->new_type])
                                            </td>
                                            <td class="text-right">
                                                {{ $correction->old_working_minutes ?? '—' }}
                                                <i class="fa fa-arrow-right text-muted mx-1"></i>
                                                {{ $correction->new_working_minutes ?? '—' }}
                                            </td>
                                            <td>
                                                {{ $correction->reason }}
                                                @if ($correction->note)
                                                    <span class="d-block text-muted"
                                                        style="font-size:11.5px">{{ $correction->note }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $correction->changedBy?->full_name ?? '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-muted text-center py-3">
                                                {{ __('no_corrections_yet') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- side column --}}
            <div class="col-12 col-xl-5">

                {{-- calculation --}}
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-1">{{ __('how_this_was_calculated') }}</h4>
                        <p class="text-muted small">
                            {{ $day['snapshot']['calculation_mode'] === 'DAY_BASED' ? __('day_based') : __('shift_based') }}
                            · {{ __('whole_minutes') }}
                        </p>

                        <table class="table table-sm mb-0">
                            <tbody>
                                @forelse ($day['breakdown'] as $line)
                                    <tr class="{{ $line['counted'] ? '' : 'text-muted' }}">
                                        <td>
                                            {{ $line['label'] }} ·
                                            {{ $line['in'] }} → {{ $line['out'] ?? __('no_punch_out') }}
                                            @if (!$line['counted'])
                                                <span class="d-block"
                                                    style="font-size:11px">{{ __('excluded_incomplete') }}</span>
                                            @elseif ($line['minutes'] < $line['full'])
                                                <span class="d-block" style="font-size:11px">
                                                    {{ __('clipped_to_shift', ['full' => $line['full']]) }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-right">+ {{ $line['minutes'] }} {{ __('min') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted">{{ __('no_sessions') }}</td>
                                        <td class="text-right">0 {{ __('min') }}</td>
                                    </tr>
                                @endforelse
                                <tr class="font-weight-bold border-top">
                                    <td>{{ __('counted_working_time') }}</td>
                                    <td class="text-right">
                                        {{ $day['working_minutes'] }} {{ __('min') }} ·
                                        {{ intdiv($day['working_minutes'], 60) }}h
                                        {{ str_pad((string) ($day['working_minutes'] % 60), 2, '0', STR_PAD_LEFT) }}m
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        @if ($day['snapshot']['full_day_minutes'])
                            <div class="alert alert-info py-2 small mt-3 mb-0">
                                {{ __('threshold_explanation', [
                                    'worked' => $day['working_minutes'],
                                    'half' => $day['snapshot']['half_day_minutes'],
                                    'full' => $day['snapshot']['full_day_minutes'],
                                ]) }}
                            </div>
                        @endif
                    </div>
                </div>

                {{-- final attendance --}}
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('final_attendance') }}</h4>
                        <div class="mb-3">
                            @include('qr-attendance.partials.status', ['type' => $day['type']])
                            @if ($day['attendance'])
                                <span
                                    class="qr-pill {{ $day['attendance']->source === 'MANUAL' ? 'qr-pill-manual' : 'qr-pill-qr' }}">
                                    {{ __('source') }}: {{ $day['attendance']->source }}</span>
                            @endif
                        </div>

                        <dl class="qr-kv">
                            <dt>{{ __('working_minutes') }}</dt>
                            <dd>{{ $day['working_minutes'] }}</dd>
                            <dt>{{ __('completed_sessions') }}</dt>
                            <dd>{{ count($day['sessions']) }}</dd>
                            <dt>{{ __('incomplete_sessions') }}</dt>
                            <dd class="{{ $day['open_session'] ? 'text-danger' : '' }}">
                                {{ $day['open_session'] ? 1 : 0 }}</dd>
                            <dt>{{ __('finalized_at') }}</dt>
                            <dd>{{ $day['attendance']?->finalized_at ? date('d M Y H:i', strtotime($day['attendance']->finalized_at)) : '—' }}
                            </dd>
                        </dl>
                    </div>
                </div>

                {{-- shift snapshot --}}
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-1">{{ __('shift_snapshot') }}</h4>
                        <p class="text-muted small">{{ __('shift_snapshot_note') }}</p>
                        <dl class="qr-kv">
                            <dt>{{ __('staff_shift') }}</dt>
                            <dd>{{ $day['snapshot']['shift_name'] ?? '—' }}</dd>
                            <dt>{{ __('window') }}</dt>
                            <dd>
                                @if ($day['snapshot']['shift_start_time'])
                                    {{ date('H:i', strtotime($day['snapshot']['shift_start_time'])) }} –
                                    {{ date('H:i', strtotime($day['snapshot']['shift_end_time'])) }}
                                @else
                                    —
                                @endif
                            </dd>
                            <dt>{{ __('full_day_minutes') }}</dt>
                            <dd>{{ $day['snapshot']['full_day_minutes'] ?? '—' }}</dd>
                            <dt>{{ __('half_day_minutes') }}</dt>
                            <dd>{{ $day['snapshot']['half_day_minutes'] ?? '—' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- manual correction --}}
    @can('staff-attendance-edit')
        <div class="modal fade" id="correctModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ __('correct_attendance') }} — {{ $day['user']->full_name }},
                            {{ date('d M Y', strtotime($date)) }}
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form class="create-form-without-reset" action="{{ route('qr-attendance.correct') }}" method="POST"
                        novalidate="novalidate">
                        @csrf
                        <input type="hidden" name="staff_id" value="{{ $day['user']->id }}">
                        <input type="hidden" name="date" value="{{ $date }}">
                        <div class="modal-body">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="correct_type">{{ __('status') }} <span
                                            class="text-danger">*</span></label>
                                    <select name="type" id="correct_type" class="form-control" required>
                                        <option value="1" {{ $day['type'] == 1 ? 'selected' : '' }}>
                                            {{ __('present') }}</option>
                                        <option value="2" {{ $day['type'] == 2 ? 'selected' : '' }}>
                                            {{ __('half_day') }}</option>
                                        <option value="0" {{ $day['type'] == 0 ? 'selected' : '' }}>
                                            {{ __('absent') }}</option>
                                        <option value="3" {{ $day['type'] == 3 ? 'selected' : '' }}>
                                            {{ __('holiday') }}</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="correct_minutes">{{ __('working_minutes') }}</label>
                                    <input type="number" name="working_minutes" id="correct_minutes"
                                        class="form-control" min="0" max="1440"
                                        value="{{ $day['working_minutes'] }}">
                                    <small class="text-muted">{{ __('leave_as_is_to_keep_calculated') }}</small>
                                </div>
                                <div class="form-group col-12">
                                    <label for="correct_reason">{{ __('reason') }} <span
                                            class="text-danger">*</span></label>
                                    <select name="reason" id="correct_reason" class="form-control" required>
                                        <option value="{{ __('staff_forgot_to_punch_out') }}">
                                            {{ __('staff_forgot_to_punch_out') }}</option>
                                        <option value="{{ __('gps_or_location_failure') }}">
                                            {{ __('gps_or_location_failure') }}</option>
                                        <option value="{{ __('qr_code_unavailable') }}">
                                            {{ __('qr_code_unavailable') }}</option>
                                        <option value="{{ __('network_failure') }}">{{ __('network_failure') }}
                                        </option>
                                        <option value="{{ __('device_failure') }}">{{ __('device_failure') }}
                                        </option>
                                        <option value="{{ __('authorized_off_site_duty') }}">
                                            {{ __('authorized_off_site_duty') }}</option>
                                        <option value="{{ __('other') }}">{{ __('other') }}</option>
                                    </select>
                                </div>
                                <div class="form-group col-12">
                                    <label for="correct_note">{{ __('note') }}</label>
                                    <textarea name="note" id="correct_note" rows="2" class="form-control"></textarea>
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-info py-2 small mb-0">
                                        <i class="fa fa-info-circle mr-1"></i>{{ __('correction_keeps_punches_note') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary"
                                data-dismiss="modal">{{ __('close') }}</button>
                            <input class="btn btn-theme" type="submit" value="{{ __('submit') }}">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan
@endsection
