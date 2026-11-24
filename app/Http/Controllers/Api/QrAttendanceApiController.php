<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\QrAttendanceException;
use App\Http\Controllers\Controller;
use App\Models\StaffAttendance;
use App\Models\StaffAttendancePunch;
use App\Services\ResponseService;
use App\Services\StaffAttendance\AttendanceCalculationService;
use App\Services\StaffAttendance\QrAttendanceService;
use App\Services\StaffAttendance\QrAttendanceSettingService;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Staff-facing QR attendance endpoints.
 *
 * The client sends only what it can honestly know: the scanned token, the
 * device coordinates and a scan id. Whether the scan is a punch in or a punch
 * out, which staff member it belongs to and which date it lands on are all
 * decided on the server.
 */
class QrAttendanceApiController extends Controller
{
    public function __construct(
        private readonly QrAttendanceService $qrAttendance,
        private readonly QrAttendanceSettingService $settings,
        private readonly AttendanceCalculationService $calculator
    ) {}

    /**
     * GET /api/staff/qr-attendance/config
     *
     * What the app needs before opening the scanner: whether the feature is on,
     * where the school is, how far away the staff member may stand, and what
     * the next scan will do.
     */
    public function config()
    {
        try {
            $user   = Auth::user();
            $config = $this->settings->all($user->school_id);

            if ($config[QrAttendanceSettingService::ENABLED] !== 1) {
                ResponseService::successResponse(trans('qr_attendance_disabled'), [
                    'enabled' => false,
                ]);
            }

            $status = $this->qrAttendance->todayStatus($user);

            ResponseService::successResponse('Success', [
                'enabled'          => true,
                'latitude'         => $config[QrAttendanceSettingService::LATITUDE],
                'longitude'        => $config[QrAttendanceSettingService::LONGITUDE],
                'radius'           => $config[QrAttendanceSettingService::RADIUS],
                'cooldown_seconds' => $config[QrAttendanceSettingService::COOLDOWN],
                'school_start_time' => $config[QrAttendanceSettingService::SCHOOL_START],
                'school_end_time'  => $config[QrAttendanceSettingService::SCHOOL_END],
                'calculation_mode' => $config[QrAttendanceSettingService::CALCULATION_MODE],
                'today'            => $status,
            ]);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'QrAttendanceApiController -> config');
            ResponseService::errorResponse(trans('attendance_processing_failed'));
        }
    }

    /**
     * POST /api/staff/qr-attendance/punch
     *
     * Records one punch. Retrying with the same request_uuid returns the
     * original punch instead of creating a second one.
     */
    public function punch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'qr_token'     => 'required|string',
            'latitude'     => 'required|numeric|between:-90,90',
            'longitude'    => 'required|numeric|between:-180,180',
            'request_uuid' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $result = $this->qrAttendance->punch(Auth::user(), [
                'qr_token'     => $request->input('qr_token'),
                'latitude'     => (float) $request->input('latitude'),
                'longitude'    => (float) $request->input('longitude'),
                'request_uuid' => $request->input('request_uuid'),
            ]);

            $punch = $result['punch'];

            ResponseService::successResponse(
                $punch->type === StaffAttendancePunch::TYPE_IN
                    ? trans('punch_in') . ' - ' . trans('attendance_processed_successfully')
                    : trans('punch_out') . ' - ' . trans('attendance_processed_successfully'),
                [
                    'punch_type'      => $punch->type,
                    'punched_at'      => $punch->punched_at->format('Y-m-d H:i:s'),
                    'distance'        => $punch->distance,
                    'next_action'     => $result['next_action'],
                    'working_minutes' => $result['working_minutes'],
                    'sessions'        => $result['sessions'],
                    'duplicate'       => $result['duplicate'],
                ]
            );
        } catch (QrAttendanceException $e) {
            // A refusal the staff member can act on — never a stack trace.
            ResponseService::errorResponse($e->getMessage(), ['error_code' => $e->errorCode]);
        } catch (Throwable $e) {
            Log::error('Staff QR attendance punch failed.', [
                'staff_id'  => Auth::id(),
                'school_id' => Auth::user()->school_id ?? null,
                'operation' => 'punch',
                'exception' => $e,
            ]);
            ResponseService::errorResponse(trans('attendance_processing_failed'));
        }
    }

    /**
     * GET /api/staff/qr-attendance/today
     *
     * Today's own sessions and next action, for the home screen.
     */
    public function today()
    {
        try {
            ResponseService::successResponse('Success', $this->qrAttendance->todayStatus(Auth::user()));
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'QrAttendanceApiController -> today');
            ResponseService::errorResponse(trans('attendance_processing_failed'));
        }
    }

    /**
     * GET /api/staff/qr-attendance/my-attendance
     *
     * The staff member's own finalized history. Read-only.
     */
    public function myAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'nullable|date',
            'to_date'   => 'nullable|date|after_or_equal:from_date',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $user = Auth::user();
            $from = $request->input('from_date', date('Y-m-01'));
            $to   = $request->input('to_date', date('Y-m-t'));

            $rows = StaffAttendance::where('staff_id', $user->id)
                ->where('school_id', $user->school_id)
                ->whereBetween('date', [$from, $to])
                ->orderByDesc('date')
                ->get();

            $summary = [
                'present'         => $rows->where('type', StaffAttendance::TYPE_PRESENT)->count(),
                'half_day'        => $rows->where('type', StaffAttendance::TYPE_HALF_DAY)->count(),
                'absent'          => $rows->where('type', StaffAttendance::TYPE_ABSENT)->count(),
                'holiday'         => $rows->where('type', StaffAttendance::TYPE_HOLIDAY)->count(),
                'working_minutes' => (int) $rows->sum('working_minutes'),
            ];

            ResponseService::successResponse('Success', [
                'from_date' => $from,
                'to_date'   => $to,
                'summary'   => $summary,
                'rows'      => $rows->map(static fn($row) => [
                    'date'                => $row->getRawOriginal('date'),
                    'type'                => (int) $row->type,
                    'working_minutes'     => (int) $row->working_minutes,
                    'completed_sessions'  => (int) $row->completed_sessions,
                    'incomplete_sessions' => (int) $row->incomplete_sessions,
                    'shift_name'          => $row->shift_name,
                    'source'              => $row->source,
                ])->values(),
            ]);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'QrAttendanceApiController -> myAttendance');
            ResponseService::errorResponse(trans('attendance_processing_failed'));
        }
    }

    /**
     * GET /api/staff/qr-attendance/punches
     *
     * Own raw punch log for a date. Cleared by the monthly retention job, so an
     * older date can legitimately come back empty.
     */
    public function punches(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $user = Auth::user();
            $date = $request->input('date', date('Y-m-d'));

            $punches = StaffAttendancePunch::where('staff_id', $user->id)
                ->where('school_id', $user->school_id)
                ->whereDate('date', $date)
                ->orderBy('punched_at')
                ->get();

            $sessions = $this->calculator->buildSessions($punches);

            ResponseService::successResponse('Success', [
                'date'     => $date,
                'punches'  => $punches->map(static fn($punch) => [
                    'type'       => $punch->type,
                    'punched_at' => $punch->punched_at->format('Y-m-d H:i:s'),
                    'distance'   => $punch->distance,
                ])->values(),
                'sessions' => $sessions['completed'],
                'open'     => $sessions['open'],
            ]);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'QrAttendanceApiController -> punches');
            ResponseService::errorResponse(trans('attendance_processing_failed'));
        }
    }
}
