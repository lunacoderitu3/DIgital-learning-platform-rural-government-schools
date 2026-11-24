<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\StaffAttendance;
use App\Models\StaffAttendanceCorrection;
use App\Models\StaffAttendancePunch;
use App\Models\User;
use App\Services\ResponseService;
use App\Services\StaffAttendance\AttendanceReportService;
use App\Services\StaffAttendance\QrAttendanceSettingService;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Admin screens over QR staff attendance: the live floor, the finalized
 * register, one staff member's day, and the reports.
 */
class QrAttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceReportService $reports,
        private readonly QrAttendanceSettingService $settings
    ) {}

    /* ------------------------------------------------------------------ */
    /* Today                                                               */
    /* ------------------------------------------------------------------ */

    public function today(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Staff Attendance Management');
        ResponseService::noPermissionThenRedirect('staff-attendance-list');

        $schoolId = Auth::user()->school_id;
        $date     = $request->input('date');
        $date     = $date ? date('Y-m-d', strtotime($date)) : schoolNow($schoolId)->toDateString();

        $config  = $this->settings->all($schoolId);
        $rows    = $this->reports->dayRows($schoolId, $date, $request->only(['staff_type', 'search']));
        $summary = $this->reports->daySummary($rows, $schoolId, $date);
        $scans   = $this->reports->recentScans($schoolId, $date);

        $finalizationTime = $this->settings->finalizationTime($schoolId);
        $lastFinalized    = StaffAttendance::where('school_id', $schoolId)
            ->whereNotNull('finalized_at')
            ->max('finalized_at');

        $staffWithoutShift = collect($rows)->whereNull('shift_id')->count();

        return response(view('qr-attendance.today', compact(
            'date',
            'config',
            'rows',
            'summary',
            'scans',
            'finalizationTime',
            'lastFinalized',
            'staffWithoutShift'
        )));
    }

    /* ------------------------------------------------------------------ */
    /* Register                                                            */
    /* ------------------------------------------------------------------ */

    public function register(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Staff Attendance Management');
        ResponseService::noPermissionThenRedirect('staff-attendance-list');

        $schoolId = Auth::user()->school_id;
        $date     = $request->input('date');
        $date     = $date ? date('Y-m-d', strtotime($date)) : schoolNow($schoolId)->toDateString();

        $rows    = $this->reports->dayRows($schoolId, $date, $request->only(['staff_type', 'search']));
        $rows    = $this->applyRegisterFilters($rows, $request);
        $summary = $this->reports->daySummary($rows, $schoolId, $date);

        $shifts = Shift::owner()->where('status', 1)->orderBy('start_time')->get();

        $month  = (int) $request->input('month', schoolNow($schoolId)->month);
        $year   = (int) $request->input('year', schoolNow($schoolId)->year);
        $matrix = $this->reports->monthMatrix($schoolId, $year, $month);

        return response(view('qr-attendance.register', compact(
            'date',
            'rows',
            'summary',
            'shifts',
            'matrix',
            'month',
            'year'
        )));
    }

    /* ------------------------------------------------------------------ */
    /* Staff day detail                                                    */
    /* ------------------------------------------------------------------ */

    public function detail(Request $request, $staffId)
    {
        ResponseService::noFeatureThenRedirect('Staff Attendance Management');
        ResponseService::noPermissionThenRedirect('staff-attendance-list');

        $schoolId = Auth::user()->school_id;
        $date     = $request->input('date');
        $date     = $date ? date('Y-m-d', strtotime($date)) : schoolNow($schoolId)->toDateString();

        $day = $this->reports->staffDay($schoolId, (int) $staffId, $date);

        if (!$day) {
            return redirect()->route('qr-attendance.register')
                ->with('error', trans('no_data_found'));
        }

        return response(view('qr-attendance.detail', compact('day', 'date')));
    }

    /**
     * Manual correction. Writes a new final result plus an audit row; the raw
     * QR punches behind the day are never touched.
     */
    public function correct(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Staff Attendance Management');
        ResponseService::noPermissionThenSendJson('staff-attendance-edit');

        $validator = Validator::make($request->all(), [
            'staff_id'        => 'required|exists:users,id',
            'date'            => 'required|date',
            'type'            => 'required|in:0,1,2,3',
            'working_minutes' => 'nullable|integer|min:0|max:1440',
            'reason'          => 'required|string|max:255',
            'note'            => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }

        $schoolId = Auth::user()->school_id;

        try {
            DB::beginTransaction();

            $staff = User::where('school_id', $schoolId)->findOrFail($request->staff_id);
            $date  = date('Y-m-d', strtotime($request->date));

            $attendance = StaffAttendance::where('school_id', $schoolId)
                ->where('staff_id', $staff->id)
                ->whereDate('date', $date)
                ->first();

            $oldType    = $attendance?->type;
            $oldMinutes = $attendance?->working_minutes;

            $attendance = StaffAttendance::updateOrCreate(
                ['staff_id' => $staff->id, 'date' => $date, 'school_id' => $schoolId],
                [
                    'session_year_id' => $attendance?->session_year_id
                        ?? app(\App\Services\CachingService::class)->getSessionYear()->id,
                    'type'            => (int) $request->type,
                    'working_minutes' => $request->filled('working_minutes')
                        ? (int) $request->working_minutes
                        : $oldMinutes,
                    'reason'          => $request->reason,
                    'source'          => StaffAttendance::SOURCE_MANUAL,
                    'finalized_at'    => $attendance?->finalized_at ?? schoolNow($schoolId),
                ]
            );

            StaffAttendanceCorrection::create([
                'staff_attendance_id' => $attendance->id,
                'staff_id'            => $staff->id,
                'date'                => $date,
                'old_type'            => $oldType,
                'new_type'            => (int) $request->type,
                'old_working_minutes' => $oldMinutes,
                'new_working_minutes' => $attendance->working_minutes,
                'reason'              => $request->reason,
                'note'                => $request->note,
                'changed_by'          => Auth::id(),
                'school_id'           => $schoolId,
            ]);

            DB::commit();
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'QrAttendanceController -> correct method');
            ResponseService::errorResponse(trans('attendance_processing_failed'));
        }
    }

    /* ------------------------------------------------------------------ */
    /* Reports                                                             */
    /* ------------------------------------------------------------------ */

    public function reports(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Staff Attendance Management');
        ResponseService::noPermissionThenRedirect('staff-attendance-list');

        $schoolId = Auth::user()->school_id;

        $from = $request->input('from_date', schoolNow($schoolId)->startOfMonth()->toDateString());
        $to   = $request->input('to_date', schoolNow($schoolId)->toDateString());
        $tab  = $request->input('tab', 'datewise');

        $from = date('Y-m-d', strtotime($from));
        $to = date('Y-m-d', strtotime($to));

        $filters = [
            'staff_id'          => $request->input('staff_id'),
            'type'              => $request->input('type'),
            'shift_id'          => $request->input('shift_id'),
            'source'            => $request->input('source'),
            'missing_punch_out' => $tab === 'mpo',
        ];

        $shifts = Shift::owner()->orderBy('start_time')->get();
        $staff  = User::where('school_id', $schoolId)
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['Teacher', 'Staff']))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        $data = match ($tab) {
            'shiftwise'   => ['shiftWise' => $this->reports->shiftWise($schoolId, $from, $to)],
            'statuswise'  => ['statusWise' => $this->reports->statusWise($schoolId, $from, $to)],
            'punches'     => ['punches' => $this->punchHistory($schoolId, $from, $to, $filters)],
            'corrections' => ['corrections' => $this->correctionHistory($schoolId, $from, $to, $filters)],
            default       => ['attendance' => $this->reports
                ->attendanceQuery($schoolId, $from, $to, $filters)
                ->orderByDesc('date')
                ->paginate(15)
                ->withQueryString()],
        };

        // The raw punch log only reaches back as far as the retention window.
        $punchesFrom = StaffAttendancePunch::where('school_id', $schoolId)->min('date');

        return response(view('qr-attendance.reports', array_merge($data, compact(
            'from',
            'to',
            'tab',
            'shifts',
            'staff',
            'filters',
            'punchesFrom'
        ))));
    }

    /**
     * Raw scan log. Cleared monthly, so an older range legitimately comes back
     * empty even though the finalized attendance for it still exists.
     */
    private function punchHistory(int $schoolId, string $from, string $to, array $filters)
    {
        return StaffAttendancePunch::with('user:id,first_name,last_name')
            ->where('school_id', $schoolId)
            ->whereBetween('date', [$from, $to])
            ->when(!empty($filters['staff_id']), fn($q) => $q->where('staff_id', $filters['staff_id']))
            ->orderByDesc('punched_at')
            ->paginate(100)
            ->withQueryString();
    }

    private function correctionHistory(int $schoolId, string $from, string $to, array $filters)
    {
        return StaffAttendanceCorrection::with([
            'user:id,first_name,last_name',
            'changedBy:id,first_name,last_name',
        ])
            ->where('school_id', $schoolId)
            ->whereBetween('date', [$from, $to])
            ->when(!empty($filters['staff_id']), fn($q) => $q->where('staff_id', $filters['staff_id']))
            ->latest()
            ->paginate(50)
            ->withQueryString();
    }

    /**
     * Register-only filters that read the assembled row rather than the query.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function applyRegisterFilters(array $rows, Request $request): array
    {
        $c = collect($rows);

        if ($request->filled('shift_id')) {
            $c = $c->where('shift_id', (int) $request->shift_id);
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $c = $c->where('type', (int) $request->type);
        }

        if ($request->filled('source')) {
            $c = $c->where('source', $request->source);
        }

        if ($request->boolean('missing_punch_out')) {
            $c = $c->where('incomplete_sessions', '>', 0);
        }

        return $c->values()->all();
    }
}
