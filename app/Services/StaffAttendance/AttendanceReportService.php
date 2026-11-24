<?php

namespace App\Services\StaffAttendance;

use App\Models\Leave;
use App\Models\LeaveDetail;
use App\Models\StaffAttendance;
use App\Models\StaffAttendancePunch;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Assembles the rows the attendance screens read.
 *
 * Every method bulk-fetches for a whole date or range — the pages render up to
 * a few hundred staff-days at a time and must not issue one query per row.
 */
class AttendanceReportService
{
    public function __construct(
        private readonly AttendanceCalculationService $calculator,
        private readonly QrAttendanceSettingService $settings,
        private readonly QrAttendanceService $qrAttendance
    ) {}

    /**
     * One row per staff member for a single date: shift window, punch sessions,
     * counted minutes and the state to show.
     *
     * Used by both Today's Attendance (live, not yet finalized) and the
     * Attendance Register (finalized).
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function dayRows(int $schoolId, string $date, array $filters = []): array
    {
        $mode  = $this->settings->calculationMode($schoolId);
        $staff = $this->staffQuery($schoolId, $filters)->get();

        if ($staff->isEmpty()) {
            return [];
        }

        $staffIds = $staff->pluck('id')->all();

        $punchesByStaff = StaffAttendancePunch::where('school_id', $schoolId)
            ->whereDate('date', $date)
            ->whereIn('staff_id', $staffIds)
            ->orderBy('punched_at')
            ->get()
            ->groupBy('staff_id');

        $attendanceByStaff = StaffAttendance::where('school_id', $schoolId)
            ->whereDate('date', $date)
            ->whereIn('staff_id', $staffIds)
            ->get()
            ->keyBy('staff_id');

        $shiftsByStaff = $this->shiftsFor($schoolId, $staffIds, $date);
        $leaveByStaff  = $this->leaveFor($schoolId, $staffIds, $date);

        $rows = [];

        foreach ($staff as $user) {
            $shift      = $shiftsByStaff[$user->id] ?? null;
            $punches    = $punchesByStaff->get($user->id) ?? collect();
            $attendance = $attendanceByStaff->get($user->id);
            $result     = $this->calculator->calculate($punches, $shift, $mode);

            $rows[] = [
                'staff_id'            => $user->id,
                'name'                => $user->full_name,
                'staff_type'          => $user->roles->pluck('name')->first(),
                'shift_id'            => $shift?->id,
                'shift_name'          => $shift?->name,
                'shift_start'         => $shift?->rawStartTime(),
                'shift_end'           => $shift?->rawEndTime(),
                'full_day_minutes'    => $shift?->full_day_minutes,
                'half_day_minutes'    => $shift?->half_day_minutes,
                'sessions'            => $result['sessions'],
                'open_session'        => $result['open'][0] ?? null,
                'completed_sessions'  => $result['completed_sessions'],
                'incomplete_sessions' => $result['incomplete_sessions'],
                'working_minutes'     => $attendance?->working_minutes ?? $result['working_minutes'],
                'first_in'            => $punches->firstWhere('type', StaffAttendancePunch::TYPE_IN)?->punched_at?->format('H:i'),
                'last_out'            => $punches->where('type', StaffAttendancePunch::TYPE_OUT)->last()?->punched_at?->format('H:i'),
                'punch_count'         => $punches->count(),
                'on_leave'            => isset($leaveByStaff[$user->id]),
                'finalized'           => (bool) $attendance?->finalized_at,
                'type'                => $attendance?->type ?? $result['type'],
                'source'              => $attendance?->source,
                'state'               => $this->liveState($punches, $result, isset($leaveByStaff[$user->id])),
                'image'               => $user->image
            ];
        }

        return $rows;
    }

    /**
     * Counters for the tile row on Today's Attendance.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public function daySummary(array $rows, int $schoolId, string $date): array
    {
        $collection = collect($rows);

        return [
            'total'          => $collection->count(),
            'on_duty'        => $collection->where('state', 'on_duty')->count(),
            'punched'        => $collection->where('punch_count', '>', 0)->count(),
            'open_sessions'  => $collection->sum('incomplete_sessions'),
            'no_punch'       => $collection->where('punch_count', 0)->where('on_leave', false)->count(),
            'on_leave'       => $collection->where('on_leave', true)->count(),
            'present'        => $collection->where('type', StaffAttendance::TYPE_PRESENT)->count(),
            'half_day'       => $collection->where('type', StaffAttendance::TYPE_HALF_DAY)->count(),
            'absent'         => $collection->where('type', StaffAttendance::TYPE_ABSENT)->count(),
            'holiday'        => $collection->where('type', StaffAttendance::TYPE_HOLIDAY)->count(),
            'working_minutes' => (int) $collection->sum('working_minutes'),
            'scans'          => StaffAttendancePunch::where('school_id', $schoolId)
                ->whereDate('date', $date)
                ->count(),
        ];
    }

    /**
     * Newest punch events for the live feed.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recentScans(int $schoolId, string $date, int $limit = 15): Collection
    {
        return StaffAttendancePunch::with('user:id,first_name,last_name')
            ->where('school_id', $schoolId)
            ->whereDate('date', $date)
            ->orderByDesc('punched_at')
            ->limit($limit)
            ->get()
            ->map(static fn($punch) => [
                'time'     => $punch->punched_at?->format('H:i'),
                'name'     => $punch->user?->full_name ?? '—',
                'type'     => $punch->type,
                'distance' => $punch->distance,
            ]);
    }

    /**
     * Everything the staff day detail page shows.
     *
     * @return array<string, mixed>|null
     */
    public function staffDay(int $schoolId, int $staffId, string $date): ?array
    {
        $user = User::with('roles')->where('school_id', $schoolId)->find($staffId);
        if (!$user) {
            return null;
        }

        $attendance = StaffAttendance::where('school_id', $schoolId)
            ->where('staff_id', $staffId)
            ->whereDate('date', $date)
            ->first();

        $punches = StaffAttendancePunch::where('school_id', $schoolId)
            ->where('staff_id', $staffId)
            ->whereDate('date', $date)
            ->orderBy('punched_at')
            ->get();

        // The snapshot on a finalized row wins: the shift may have been edited
        // since, and this day must not silently change.
        $shift = $this->qrAttendance->resolveShift($staffId, $schoolId, $date);
        $mode  = $attendance?->calculation_mode ?: $this->settings->calculationMode($schoolId);

        $result = $this->calculator->calculate($punches, $shift, $mode);

        $snapshot = [
            'shift_name'       => $attendance?->shift_name ?? $shift?->name,
            'shift_start_time' => $attendance?->getRawOriginal('shift_start_time') ?? $shift?->rawStartTime(),
            'shift_end_time'   => $attendance?->getRawOriginal('shift_end_time') ?? $shift?->rawEndTime(),
            'full_day_minutes' => $attendance?->full_day_minutes ?? $shift?->full_day_minutes,
            'half_day_minutes' => $attendance?->half_day_minutes ?? $shift?->half_day_minutes,
            'calculation_mode' => $mode,
        ];

        // Per-session contribution, so the arithmetic behind the status is
        // visible rather than a single unexplained number.
        $breakdown = [];
        foreach ($result['sessions'] as $index => $session) {
            $breakdown[] = [
                'label'   => trans('session') . ' ' . ($index + 1),
                'in'      => date('H:i', strtotime($session['in'])),
                'out'     => date('H:i', strtotime($session['out'])),
                'minutes' => $mode === StaffAttendance::MODE_SHIFT_BASED
                    ? $this->calculator->sessionMinutesWithinShift($session, $shift)
                    : $this->calculator->sessionMinutes($session),
                'full'    => $this->calculator->sessionMinutes($session),
                'counted' => true,
            ];
        }
        foreach ($result['open'] as $openIn) {
            $breakdown[] = [
                'label'   => trans('session') . ' ' . (count($breakdown) + 1),
                'in'      => date('H:i', strtotime($openIn)),
                'out'     => null,
                'minutes' => 0,
                'full'    => 0,
                'counted' => false,
            ];
        }

        return [
            'user'            => $user,
            'date'            => $date,
            'attendance'      => $attendance,
            'punches'         => $punches,
            'snapshot'        => $snapshot,
            'sessions'        => $result['sessions'],
            'open_session'    => $result['open'][0] ?? null,
            'breakdown'       => $breakdown,
            'working_minutes' => $attendance?->working_minutes ?? $result['working_minutes'],
            'type'            => $attendance?->type ?? $result['type'],
            'corrections'     => $attendance
                ? $attendance->corrections()->with('changedBy:id,first_name,last_name')->latest()->get()
                : collect(),
        ];
    }

    /**
     * Finalized rows for a date range, for the date-wise and staff-wise reports.
     *
     * @param  array<string, mixed>  $filters
     */
    public function attendanceQuery(int $schoolId, string $from, string $to, array $filters = [])
    {
        $query = StaffAttendance::with(['user:id,first_name,last_name'])
            ->where('school_id', $schoolId)
            ->whereBetween('date', [$from, $to]);

        if (!empty($filters['staff_id'])) {
            $query->where('staff_id', $filters['staff_id']);
        }

        if (isset($filters['type']) && $filters['type'] !== '' && $filters['type'] !== null) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['shift_id'])) {
            $query->where('shift_id', $filters['shift_id']);
        }

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (!empty($filters['missing_punch_out'])) {
            $query->where('incomplete_sessions', '>', 0);
        }

        return $query;
    }

    /**
     * Present / half day / absent counts grouped by the shift that applied.
     *
     * @return array<int, array<string, mixed>>
     */
    public function shiftWise(int $schoolId, string $from, string $to): array
    {
        $rows = StaffAttendance::selectRaw('shift_id, shift_name, COUNT(*) as staff_days')
            ->selectRaw('SUM(type = ?) as present', [StaffAttendance::TYPE_PRESENT])
            ->selectRaw('SUM(type = ?) as half_day', [StaffAttendance::TYPE_HALF_DAY])
            ->selectRaw('SUM(type = ?) as absent', [StaffAttendance::TYPE_ABSENT])
            ->selectRaw('SUM(incomplete_sessions > 0) as missing_punch_out')
            ->selectRaw('AVG(working_minutes) as avg_minutes')
            ->where('school_id', $schoolId)
            ->whereBetween('date', [$from, $to])
            ->whereNotNull('shift_id')
            ->groupBy('shift_id', 'shift_name')
            ->orderBy('shift_name')
            ->get();

        return $rows->map(static fn($row) => [
            'shift_id'          => $row->shift_id,
            'shift_name'        => $row->shift_name,
            'staff_days'        => (int) $row->staff_days,
            'present'           => (int) $row->present,
            'half_day'          => (int) $row->half_day,
            'absent'            => (int) $row->absent,
            'missing_punch_out' => (int) $row->missing_punch_out,
            'avg_minutes'       => (int) round((float) $row->avg_minutes),
        ])->all();
    }

    /**
     * Daily totals across the range, for the status-wise report.
     *
     * @return array<int, array<string, mixed>>
     */
    public function statusWise(int $schoolId, string $from, string $to): array
    {
        $rows = StaffAttendance::selectRaw('date, COUNT(*) as staff_count')
            ->selectRaw('SUM(type = ?) as present', [StaffAttendance::TYPE_PRESENT])
            ->selectRaw('SUM(type = ?) as half_day', [StaffAttendance::TYPE_HALF_DAY])
            ->selectRaw('SUM(type = ?) as absent', [StaffAttendance::TYPE_ABSENT])
            ->selectRaw('SUM(type = ?) as holiday', [StaffAttendance::TYPE_HOLIDAY])
            ->selectRaw('SUM(working_minutes) as total_minutes')
            ->where('school_id', $schoolId)
            ->whereBetween('date', [$from, $to])
            ->groupBy('date')
            ->orderByDesc('date')
            ->get();

        return $rows->map(static fn($row) => [
            'date'          => $row->getRawOriginal('date'),
            'staff_count'   => (int) $row->staff_count,
            'present'       => (int) $row->present,
            'half_day'      => (int) $row->half_day,
            'absent'        => (int) $row->absent,
            'holiday'       => (int) $row->holiday,
            'total_minutes' => (int) $row->total_minutes,
        ])->all();
    }

    /**
     * One cell per staff-day for the month matrix.
     *
     * @return array{staff: array<int, array<string, mixed>>, days: int}
     */
    public function monthMatrix(int $schoolId, int $year, int $month): array
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));
        $days = (int) date('t', strtotime($from));

        $staff = $this->staffQuery($schoolId)->get();

        $attendance = StaffAttendance::where('school_id', $schoolId)
            ->whereBetween('date', [$from, $to])
            ->get()
            ->groupBy('staff_id');

        $matrix = [];

        foreach ($staff as $user) {
            $rows  = $attendance->get($user->id) ?? collect();
            $byDay = [];

            foreach ($rows as $row) {
                $byDay[(int) date('j', strtotime($row->getRawOriginal('date')))] = [
                    'type'       => (int) $row->type,
                    'incomplete' => (int) $row->incomplete_sessions > 0,
                ];
            }

            $matrix[] = [
                'staff_id' => $user->id,
                'name'     => $user->full_name,
                'days'     => $byDay,
                'present'  => $rows->where('type', StaffAttendance::TYPE_PRESENT)->count(),
                'half_day' => $rows->where('type', StaffAttendance::TYPE_HALF_DAY)->count(),
                'absent'   => $rows->where('type', StaffAttendance::TYPE_ABSENT)->count(),
                'minutes'  => (int) $rows->sum('working_minutes'),
            ];
        }

        return ['staff' => $matrix, 'days' => $days];
    }

    /**
     * Active teaching and non-teaching staff, with optional filters.
     *
     * @param  array<string, mixed>  $filters
     */
    private function staffQuery(int $schoolId, array $filters = [])
    {
        $roles = ['Teacher', 'Staff', 'Driver', 'Helper'];
        if (!empty($filters['staff_type'])) {
            $roles = [$filters['staff_type']];
        }


        return User::with('roles:id,name,custom_role')
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->whereHas('roles', function ($q) use ($filters, $roles) {
                if (!empty($filters['staff_type'])) {
                    if ($filters['staff_type'] === 'Teacher') {
                        $q->where('name', 'Teacher');
                    } elseif ($filters['staff_type'] === 'Staff') {
                        $q->where(function ($roleQuery) {
                            $roleQuery->where('custom_role', 1)
                                ->orWhereIn('name', ['Driver', 'Helper']);
                        });
                    }
                } else {
                    $q->where(function ($roleQuery) use ($roles) {
                        $roleQuery->whereIn('name', $roles)
                            ->orWhere('custom_role', 1);
                    });
                }
            })
            ->when(
                !empty($filters['staff_id']),
                fn($q) => $q->where('id', $filters['staff_id'])
            )
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $search = $filters['search'];

                $q->where(function ($inner) use ($search) {
                    $inner->where('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%");
                });
            })
            ->orderBy('first_name');

        // return User::with('roles:id,name')
        //     ->where('school_id', $schoolId)
        //     ->where('status', 1)
        //     ->whereHas('roles', fn($q) => $q->whereIn('name', $roles))
        //     ->when(!empty($filters['staff_id']), fn($q) => $q->where('id', $filters['staff_id']))
        //     ->when(!empty($filters['search']), function ($q) use ($filters) {
        //         $search = $filters['search'];
        //         $q->where(function ($inner) use ($search) {
        //             $inner->where('first_name', 'LIKE', "%{$search}%")
        //                 ->orWhere('last_name', 'LIKE', "%{$search}%");
        //         });
        //     })
        //     ->orderBy('first_name');
    }

    /**
     * @param  array<int, int>  $staffIds
     */
    private function shiftsFor(int $schoolId, array $staffIds, string $date): array
    {
        $assignments = \App\Models\StaffShiftAssignment::with('shift')
            ->where('school_id', $schoolId)
            ->whereIn('staff_id', $staffIds)
            ->onDate($date)
            ->orderBy('effective_from')
            ->get();

        $shifts = [];
        foreach ($assignments as $assignment) {
            if ($assignment->shift) {
                $shifts[$assignment->staff_id] = $assignment->shift;
            }
        }

        return $shifts;
    }

    /**
     * @param  array<int, int>  $staffIds
     * @return array<int, bool>
     */
    private function leaveFor(int $schoolId, array $staffIds, string $date): array
    {
        $leaveIds = Leave::where('school_id', $schoolId)
            ->whereIn('user_id', $staffIds)
            ->where('status', 1)
            ->pluck('id', 'user_id');

        if ($leaveIds->isEmpty()) {
            return [];
        }

        $onDate = LeaveDetail::where('school_id', $schoolId)
            ->whereIn('leave_id', $leaveIds->values())
            ->whereDate('date', $date)
            ->pluck('leave_id')
            ->flip();

        $result = [];
        foreach ($leaveIds as $userId => $leaveId) {
            if ($onDate->has($leaveId)) {
                $result[$userId] = true;
            }
        }

        return $result;
    }

    /**
     * What the live floor shows before the day is finalized.
     *
     * @param  Collection<int, StaffAttendancePunch>  $punches
     */
    private function liveState(Collection $punches, array $result, bool $onLeave): string
    {
        if ($onLeave) {
            return 'leave';
        }

        if ($punches->isEmpty()) {
            return 'no_punch';
        }

        $last = $punches->sortBy('punched_at')->last();

        if ($last->type === StaffAttendancePunch::TYPE_IN) {
            return 'on_duty';
        }

        return $result['incomplete_sessions'] > 0 ? 'open_session' : 'off_duty';
    }
}
