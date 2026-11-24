@extends('layouts.master')

@section('title')
    {{ __('attendance_reports') }}
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/qr-attendance.css') }}?v={{ config('app.version') }}">
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('attendance_reports') }}</h3>
            <a href="{{ route('qr-attendance.register') }}" class="btn btn-outline-primary btn-sm">
                <i class="fa fa-calendar-check mr-1"></i>{{ __('attendance_register') }}
            </a>
        </div>

        {{-- shared filter bar --}}
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="form-group col-md-2">
                        <label for="from_date">{{ __('from_date') }}</label>
                        <input type="text" name="from_date" id="from_date" class="form-control datepicker-popup"
                            value="{{ date('d-m-Y', strtotime($from)) }}">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="to_date">{{ __('to_date') }}</label>
                        <input type="text" name="to_date" id="to_date" class="form-control datepicker-popup"
                            value="{{ date('d-m-Y', strtotime($to)) }}">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="staff_id">{{ __('staff') }}</label>
                        <select name="staff_id" id="staff_id" class="form-control custom-search-select">
                            <option value="">{{ __('all') }}</option>
                            @foreach ($staff as $person)
                                <option value="{{ $person->id }}"
                                    {{ (int) $filters['staff_id'] === $person->id ? 'selected' : '' }}>
                                    {{ $person->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="shift_id">{{ __('staff_shift') }}</label>
                        <select name="shift_id" id="shift_id" class="form-control">
                            <option value="">{{ __('all') }}</option>
                            @foreach ($shifts as $shift)
                                <option value="{{ $shift->id }}"
                                    {{ (int) $filters['shift_id'] === $shift->id ? 'selected' : '' }}>
                                    {{ $shift->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-1">
                        <label for="type">{{ __('status') }}</label>
                        <select name="type" id="type" class="form-control">
                            <option value="">{{ __('all') }}</option>
                            <option value="1" {{ $filters['type'] === '1' ? 'selected' : '' }}>{{ __('present') }}
                            </option>
                            <option value="2" {{ $filters['type'] === '2' ? 'selected' : '' }}>{{ __('half_day') }}
                            </option>
                            <option value="0" {{ $filters['type'] === '0' ? 'selected' : '' }}>{{ __('absent') }}
                            </option>
                            <option value="3" {{ $filters['type'] === '3' ? 'selected' : '' }}>{{ __('holiday') }}
                            </option>
                        </select>
                    </div>
                    <div class="form-group col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-theme mr-2">{{ __('search') }}</button>
                        <a href="{{ route('qr-attendance.reports') }}" class="btn btn-secondary">{{ __('reset') }}</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- tabs --}}
        @php
            $tabs = [
                'datewise' => __('date_wise'),
                'staffwise' => __('staff_wise'),
                'shiftwise' => __('shift_wise'),
                'statuswise' => __('status_wise'),
                'mpo' => __('missing_punch_out'),
                'punches' => __('qr_punch_history'),
                'corrections' => __('manual_corrections'),
            ];
            $query = request()->except('tab', 'page');
        @endphp
        <ul class="nav nav-tabs mb-3">
            @foreach ($tabs as $key => $label)
                <li class="nav-item">
                    <a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                        href="{{ route('qr-attendance.reports', array_merge($query, ['tab' => $key])) }}">
                        {{ $label }}
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="card">
            <div class="card-body">

                {{-- ===================== date-wise / staff-wise / mpo ===================== --}}
                @if (in_array($tab, ['datewise', 'staffwise', 'mpo']))
                    <h4 class="card-title">{{ $tabs[$tab] }}</h4>

                    @if ($tab === 'mpo')
                        <p class="text-muted small">{{ __('missing_punch_out_report_note') }}</p>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('date') }}</th>
                                    <th>{{ __('name') }}</th>
                                    <th>{{ __('shift') }}</th>
                                    <th class="text-right">{{ __('sessions') }}</th>
                                    <th class="text-right">{{ __('working_hours') }}</th>
                                    <th class="text-right">{{ __('required') }}</th>
                                    <th>{{ __('status') }}</th>
                                    <th>{{ __('source') }}</th>
                                    <th>{{ __('finalized_at') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($attendance as $row)
                                    <tr>
                                        <td>{{ date('d M Y', strtotime($row->getRawOriginal('date'))) }}</td>
                                        <td>{{ $row->user?->full_name ?? '—' }}</td>
                                        <td>{{ $row->shift_name ?? '—' }}</td>
                                        <td class="text-right">
                                            {{ $row->completed_sessions ?? 0 }}
                                            @if ($row->incomplete_sessions > 0)
                                                <span class="text-danger">+{{ $row->incomplete_sessions }}</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            {{ intdiv((int) $row->working_minutes, 60) }}h
                                            {{ str_pad((string) ((int) $row->working_minutes % 60), 2, '0', STR_PAD_LEFT) }}m
                                        </td>
                                        <td class="text-right text-muted">{{ $row->full_day_minutes ?? '—' }}</td>
                                        <td>@include('qr-attendance.partials.status', [
                                            'type' => (int) $row->type,
                                        ])</td>
                                        <td>
                                            <span
                                                class="qr-pill {{ $row->source === 'MANUAL' ? 'qr-pill-manual' : 'qr-pill-qr' }}">
                                                {{ $row->source }}</span>
                                        </td>
                                        <td class="text-muted">
                                            {{ $row->finalized_at ? date('H:i', strtotime($row->finalized_at)) : '—' }}
                                        </td>
                                        <td>
                                            <a href="{{ route('qr-attendance.detail', $row->staff_id) }}?date={{ $row->getRawOriginal('date') }}"
                                                class="btn btn-sm btn-outline-primary">
                                                {{ $tab === 'mpo' ? __('correct') : '' }}
                                                <i class="fa fa-chevron-right"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">
                                            {{ __('no_data_found') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $attendance->links('pagination::bootstrap-5') }}
                    </div>
                @endif

                {{-- ============================== shift-wise ============================== --}}
                @if ($tab === 'shiftwise')
                    <h4 class="card-title mb-1">{{ __('shift_wise') }}</h4>
                    <p class="text-muted small">{{ __('shift_wise_note') }}</p>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('shift') }}</th>
                                    <th class="text-right">{{ __('staff_days') }}</th>
                                    <th style="min-width:160px">{{ __('distribution') }}</th>
                                    <th class="text-right">{{ __('present') }}</th>
                                    <th class="text-right">{{ __('half_day') }}</th>
                                    <th class="text-right">{{ __('absent') }}</th>
                                    <th class="text-right">{{ __('average_hours') }}</th>
                                    <th class="text-right">{{ __('missing_punch_out') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($shiftWise as $row)
                                    @php $total = max(1, $row['staff_days']); @endphp
                                    <tr>
                                        <td><strong>{{ $row['shift_name'] }}</strong></td>
                                        <td class="text-right">{{ $row['staff_days'] }}</td>
                                        <td>
                                            <div class="progress" style="height:8px">
                                                <div class="progress-bar bg-success"
                                                    style="width:{{ ($row['present'] / $total) * 100 }}%"></div>
                                                <div class="progress-bar bg-warning"
                                                    style="width:{{ ($row['half_day'] / $total) * 100 }}%"></div>
                                                <div class="progress-bar bg-danger"
                                                    style="width:{{ ($row['absent'] / $total) * 100 }}%"></div>
                                            </div>
                                        </td>
                                        <td class="text-right">{{ $row['present'] }}</td>
                                        <td class="text-right">{{ $row['half_day'] }}</td>
                                        <td class="text-right">{{ $row['absent'] }}</td>
                                        <td class="text-right">
                                            {{ intdiv($row['avg_minutes'], 60) }}h
                                            {{ str_pad((string) ($row['avg_minutes'] % 60), 2, '0', STR_PAD_LEFT) }}m
                                        </td>
                                        <td class="text-right">{{ $row['missing_punch_out'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">{{ __('no_data_found') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- ============================== status-wise ============================= --}}
                @if ($tab === 'statuswise')
                    <h4 class="card-title">{{ __('status_wise') }}</h4>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('date') }}</th>
                                    <th class="text-right">{{ __('staff') }}</th>
                                    <th style="min-width:180px">{{ __('distribution') }}</th>
                                    <th class="text-right">{{ __('present') }}</th>
                                    <th class="text-right">{{ __('half_day') }}</th>
                                    <th class="text-right">{{ __('absent') }}</th>
                                    <th class="text-right">{{ __('holiday') }}</th>
                                    <th class="text-right">{{ __('total_hours') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($statusWise as $row)
                                    @php $total = max(1, $row['staff_count']); @endphp
                                    <tr>
                                        <td>{{ date('d M Y', strtotime($row['date'])) }}</td>
                                        <td class="text-right">{{ $row['staff_count'] }}</td>
                                        <td>
                                            <div class="progress" style="height:8px">
                                                <div class="progress-bar bg-success"
                                                    style="width:{{ ($row['present'] / $total) * 100 }}%"></div>
                                                <div class="progress-bar bg-warning"
                                                    style="width:{{ ($row['half_day'] / $total) * 100 }}%"></div>
                                                <div class="progress-bar bg-danger"
                                                    style="width:{{ ($row['absent'] / $total) * 100 }}%"></div>
                                                <div class="progress-bar bg-secondary"
                                                    style="width:{{ ($row['holiday'] / $total) * 100 }}%"></div>
                                            </div>
                                        </td>
                                        <td class="text-right">{{ $row['present'] }}</td>
                                        <td class="text-right">{{ $row['half_day'] }}</td>
                                        <td class="text-right">{{ $row['absent'] }}</td>
                                        <td class="text-right">{{ $row['holiday'] }}</td>
                                        <td class="text-right">{{ intdiv($row['total_minutes'], 60) }}h</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">{{ __('no_data_found') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- ============================ punch history ============================ --}}
                @if ($tab === 'punches')
                    <h4 class="card-title mb-1">{{ __('qr_punch_history') }}</h4>
                    <div class="alert alert-warning py-2 small">
                        <i class="fa fa-database mr-1"></i>
                        {{ __('punch_retention_note') }}
                        @if ($punchesFrom)
                            {{ __('report_starts_at') }} <strong>{{ date('d M Y', strtotime($punchesFrom)) }}</strong>.
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('date') }}</th>
                                    <th class="text-right">{{ __('time') }}</th>
                                    <th>{{ __('name') }}</th>
                                    <th>{{ __('type') }}</th>
                                    <th class="text-right">{{ __('latitude') }}</th>
                                    <th class="text-right">{{ __('longitude') }}</th>
                                    <th class="text-right">{{ __('distance') }}</th>
                                    <th>{{ __('source') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($punches as $punch)
                                    <tr>
                                        <td>{{ $punch->date->format('d M Y') }}</td>
                                        <td class="text-right">{{ $punch->punched_at->format('H:i:s') }}</td>
                                        <td>{{ $punch->user?->full_name ?? '—' }}</td>
                                        <td>
                                            <span
                                                class="qr-pill {{ $punch->type === 'OUT' ? 'qr-pill-qr' : 'qr-pill-present' }}">
                                                {{ $punch->type }}</span>
                                        </td>
                                        <td class="text-right">{{ $punch->latitude ?? '—' }}</td>
                                        <td class="text-right">{{ $punch->longitude ?? '—' }}</td>
                                        <td class="text-right">
                                            {{ $punch->distance !== null ? $punch->distance . ' m' : '—' }}</td>
                                        <td>{{ $punch->source }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">{{ __('no_data_found') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $punches->links('pagination::bootstrap-5') }}
                    </div>
                @endif

                {{-- ============================= corrections ============================= --}}
                @if ($tab === 'corrections')
                    <h4 class="card-title mb-1">{{ __('manual_corrections') }}</h4>
                    <p class="text-muted small">{{ __('correction_history_note') }}</p>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('changed_at') }}</th>
                                    <th>{{ __('date') }}</th>
                                    <th>{{ __('name') }}</th>
                                    <th>{{ __('status') }}</th>
                                    <th class="text-right">{{ __('working_minutes') }}</th>
                                    <th>{{ __('reason') }}</th>
                                    <th>{{ __('changed_by') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($corrections as $correction)
                                    <tr>
                                        <td>{{ $correction->created_at }}</td>
                                        <td>{{ $correction->date->format('d M Y') }}</td>
                                        <td>{{ $correction->user?->full_name ?? '—' }}</td>
                                        <td>
                                            @include('qr-attendance.partials.status', [
                                                'type' => $correction->old_type,
                                            ])
                                            <i class="fa fa-arrow-right text-muted mx-1"></i>
                                            @include('qr-attendance.partials.status', [
                                                'type' => $correction->new_type,
                                            ])
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
                                        <td colspan="7" class="text-center text-muted py-4">{{ __('no_data_found') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $corrections->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
