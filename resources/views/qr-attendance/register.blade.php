@extends('layouts.master')

@section('title')
    {{ __('attendance_register') }}
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/qr-attendance.css') }}?v={{ config('app.version') }}">
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('attendance_register') }}</h3>
            <a href="{{ route('qr-attendance.today') }}" class="btn btn-outline-primary btn-sm">
                <i class="fa fa-bolt mr-1"></i>{{ __('todays_attendance') }}
            </a>
        </div>

        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#day-view">{{ __('day_view') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#month-view">{{ __('month_matrix') }}</a>
            </li>
        </ul>

        <div class="tab-content pt-3">
            {{-- ================================ DAY ================================ --}}
            <div class="tab-pane fade show active" id="day-view">

                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="{{ route('qr-attendance.register') }}" class="row">
                            <div class="form-group col-md-2">
                                <label for="date">{{ __('date') }}</label>
                                <input type="text" name="date" id="date" class="form-control datepicker-popup"
                                    value="{{ date('d-m-Y', strtotime($date)) }}">
                            </div>
                            <div class="form-group col-md-2">
                                <label for="shift_id">{{ __('staff_shift') }}</label>
                                <select name="shift_id" id="shift_id" class="form-control">
                                    <option value="">{{ __('all') }}</option>
                                    @foreach ($shifts as $shift)
                                        <option value="{{ $shift->id }}"
                                            {{ (int) request('shift_id') === $shift->id ? 'selected' : '' }}>
                                            {{ $shift->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="type">{{ __('status') }}</label>
                                <select name="type" id="type" class="form-control">
                                    <option value="all">{{ __('all') }}</option>
                                    <option value="1" {{ request('type') === '1' ? 'selected' : '' }}>
                                        {{ __('present') }}</option>
                                    <option value="2" {{ request('type') === '2' ? 'selected' : '' }}>
                                        {{ __('half_day') }}</option>
                                    <option value="0" {{ request('type') === '0' ? 'selected' : '' }}>
                                        {{ __('absent') }}</option>
                                    <option value="3" {{ request('type') === '3' ? 'selected' : '' }}>
                                        {{ __('holiday') }}</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="source">{{ __('source') }}</label>
                                <select name="source" id="source" class="form-control">
                                    <option value="">{{ __('all') }}</option>
                                    <option value="QR" {{ request('source') === 'QR' ? 'selected' : '' }}>QR</option>
                                    <option value="MANUAL" {{ request('source') === 'MANUAL' ? 'selected' : '' }}>
                                        {{ __('manual') }}</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-theme mr-2">{{ __('search') }}</button>
                                <a href="{{ route('qr-attendance.register') }}"
                                    class="btn btn-secondary">{{ __('reset') }}</a>
                            </div>
                            <div class="form-group col-12 mb-0">
                                <div class="form-check">
                                    <label class="form-check-label">
                                        <input type="checkbox" name="missing_punch_out" value="1"
                                            class="form-check-input" {{ request('missing_punch_out') ? 'checked' : '' }}>
                                        {{ __('only_missing_punch_out') }}
                                        <i class="input-helper"></i>
                                    </label>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row">
                    @php
                        $tiles = [
                            [__('present'), $summary['present'], 'text-success'],
                            [__('half_day'), $summary['half_day'], 'text-warning'],
                            [__('absent'), $summary['absent'], 'text-danger'],
                            [__('leave'), $summary['on_leave'], 'text-primary'],
                            [__('missing_punch_out'), $summary['open_sessions'], 'text-warning'],
                            [__('total_worked'), intdiv($summary['working_minutes'], 60) . 'h', ''],
                        ];
                    @endphp
                    @foreach ($tiles as [$label, $value, $class])
                        <div class="col-md-4 col-xl-2 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body py-3">
                                    <p class="text-muted mb-1 small text-uppercase">{{ $label }}</p>
                                    <h3 class="mb-0 {{ $class }}">{{ $value }}</h3>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ date('d M Y', strtotime($date)) }}</h4>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="width:190px">{{ __('name') }}</th>
                                        <th style="width:110px">{{ __('shift') }}</th>
                                        <th style="min-width:200px">{{ __('day') }}</th>
                                        <th class="text-right">{{ __('sessions') }}</th>
                                        <th class="text-right">{{ __('worked') }}</th>
                                        <th class="text-right">{{ __('required') }}</th>
                                        <th>{{ __('status') }}</th>
                                        <th>{{ __('source') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($rows as $row)
                                        <tr>
                                            <td>
                                                {{ $row['name'] }}
                                                <span class="d-block text-muted"
                                                    style="font-size:11.5px">{{ $row['staff_type'] }}</span>
                                            </td>
                                            <td>{{ $row['shift_name'] ?? '—' }}</td>
                                            <td>
                                                @include('qr-attendance.partials.ribbon', [
                                                    'shiftStart' => $row['shift_start'],
                                                    'shiftEnd' => $row['shift_end'],
                                                    'shiftName' => $row['shift_name'],
                                                    'sessions' => $row['sessions'],
                                                    'openSession' => $row['open_session'],
                                                    'now' => null,
                                                ])
                                            </td>
                                            <td class="text-right">
                                                {{ $row['completed_sessions'] }}
                                                @if ($row['incomplete_sessions'] > 0)
                                                    <span class="text-danger">+{{ $row['incomplete_sessions'] }}</span>
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                {{ intdiv($row['working_minutes'], 60) }}h
                                                {{ str_pad((string) ($row['working_minutes'] % 60), 2, '0', STR_PAD_LEFT) }}m
                                            </td>
                                            <td class="text-right text-muted">
                                                @if ($row['full_day_minutes'])
                                                    {{ intdiv($row['full_day_minutes'], 60) }}h
                                                    {{ str_pad((string) ($row['full_day_minutes'] % 60), 2, '0', STR_PAD_LEFT) }}m
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>
                                                @if ($row['on_leave'])
                                                    <span class="qr-pill qr-pill-leave">{{ __('leave') }}</span>
                                                @else
                                                    @include('qr-attendance.partials.status', [
                                                        'type' => $row['type'],
                                                    ])
                                                @endif
                                                @if ($row['incomplete_sessions'] > 0)
                                                    <span class="qr-pill qr-pill-open"
                                                        title="{{ __('missing_punch_out') }}">
                                                        <i class="fa fa-exclamation-triangle"></i>
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($row['source'])
                                                    <span
                                                        class="qr-pill {{ $row['source'] === 'MANUAL' ? 'qr-pill-manual' : 'qr-pill-qr' }}">
                                                        {{ $row['source'] }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('qr-attendance.detail', $row['staff_id']) }}?date={{ $date }}"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="fa fa-chevron-right"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                {{ __('no_data_found') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- =============================== MONTH =============================== --}}
            <div class="tab-pane fade" id="month-view">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap">
                            <div>
                                <h4 class="card-title mb-1">{{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</h4>
                                <p class="text-muted small">{{ __('month_matrix_help') }}</p>
                            </div>
                            <form method="GET" class="form-inline">
                                <input type="hidden" name="date" value="{{ $date }}">
                                <select name="month" class="form-control form-control-sm mr-2">
                                    @for ($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}" {{ $m === $month ? 'selected' : '' }}>
                                            {{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                                    @endfor
                                </select>
                                <select name="year" class="form-control form-control-sm mr-2">
                                    @for ($y = (int) date('Y') - 2; $y <= (int) date('Y') + 1; $y++)
                                        <option value="{{ $y }}" {{ $y === $year ? 'selected' : '' }}>
                                            {{ $y }}</option>
                                    @endfor
                                </select>
                                <button class="btn btn-sm btn-theme" type="submit">{{ __('search') }}</button>
                            </form>
                        </div>

                        <div class="qr-ribbon-legend mb-3">
                            <span><i style="background:#E6F4EF"></i>P {{ __('present') }}</span>
                            <span><i style="background:#FBF1DF"></i>H {{ __('half_day') }}</span>
                            <span><i style="background:#FBE9E8"></i>A {{ __('absent') }}</span>
                            <span><i style="background:#EDF1F4"></i>O {{ __('holiday') }}</span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th style="min-width:170px">{{ __('name') }}</th>
                                        @for ($d = 1; $d <= $matrix['days']; $d++)
                                            <th class="text-center px-1" style="font-size:10px">{{ $d }}</th>
                                        @endfor
                                        <th class="text-right">P</th>
                                        <th class="text-right">H</th>
                                        <th class="text-right">A</th>
                                        <th class="text-right">{{ __('hours') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $marks = [
                                            0 => ['A', 'qr-pill-absent'],
                                            1 => ['P', 'qr-pill-present'],
                                            2 => ['H', 'qr-pill-halfday'],
                                            3 => ['O', 'qr-pill-holiday'],
                                            4 => ['H', 'qr-pill-halfday'],
                                            5 => ['H', 'qr-pill-halfday'],
                                        ];
                                    @endphp
                                    @forelse ($matrix['staff'] as $staffRow)
                                        <tr>
                                            <td>{{ $staffRow['name'] }}</td>
                                            @for ($d = 1; $d <= $matrix['days']; $d++)
                                                <td class="text-center px-1">
                                                    @if (isset($staffRow['days'][$d]))
                                                        @php $mark = $marks[$staffRow['days'][$d]['type']] ?? ['-', '']; @endphp
                                                        <span class="qr-pill {{ $mark[1] }}"
                                                            style="padding:1px 5px;font-size:10px"
                                                            @if ($staffRow['days'][$d]['incomplete']) title="{{ __('missing_punch_out') }}" @endif>
                                                            {{ $mark[0] }}@if ($staffRow['days'][$d]['incomplete'])
                                                                *
                                                            @endif
                                                        </span>
                                                    @else
                                                        <span class="text-muted">·</span>
                                                    @endif
                                                </td>
                                            @endfor
                                            <td class="text-right">{{ $staffRow['present'] }}</td>
                                            <td class="text-right">{{ $staffRow['half_day'] }}</td>
                                            <td class="text-right">{{ $staffRow['absent'] }}</td>
                                            <td class="text-right">{{ intdiv($staffRow['minutes'], 60) }}h</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ $matrix['days'] + 5 }}" class="text-center text-muted py-4">
                                                {{ __('no_data_found') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <p class="text-muted small mb-0 mt-2">{{ __('month_matrix_asterisk_note') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
