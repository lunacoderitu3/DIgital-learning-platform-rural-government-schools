<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\StaffAttendance;
use App\Models\StaffAttendanceQrCode;
use App\Repositories\SchoolSetting\SchoolSettingInterface;
use App\Services\CachingService;
use App\Services\ResponseService;
use App\Services\StaffAttendance\QrAttendanceSettingService;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * School-level QR attendance configuration and the school's single QR code.
 */
class QrAttendanceSettingsController extends Controller
{
    public function __construct(
        private readonly SchoolSettingInterface $schoolSettings,
        private readonly CachingService $cache,
        private readonly QrAttendanceSettingService $settings
    ) {}

    public function index()
    {
        ResponseService::noFeatureThenRedirect('Staff Attendance Management');
        ResponseService::noPermissionThenRedirect('qr-attendance-settings');

        $schoolId = Auth::user()->school_id;

        $config     = $this->settings->all($schoolId);

        if (isset($config['google_maps_api_key'])) {
            $config['google_maps_api_key'] = base64_decode($config['google_maps_api_key']);
        }
        $readiness  = $this->settings->readiness($schoolId);
        $qrCode     = StaffAttendanceQrCode::where('school_id', $schoolId)->active()->first();
        $shifts     = Shift::owner()->where('status', 1)->orderBy('start_time')->get();
        $latestShiftEnd = $shifts->map(static fn($shift) => $shift->rawEndTime())->filter()->max();

        return response(view('qr-attendance.settings', compact(
            'config',
            'readiness',
            'qrCode',
            'shifts',
            'latestShiftEnd'
        )));
    }

    public function store(Request $request)
    {
        ResponseService::noPermissionThenSendJson('qr-attendance-settings');

        $validator = Validator::make($request->all(), [
            'qr_attendance_enabled'                  => 'required|boolean',
            'qr_attendance_school_start_time'        => 'required|date_format:H:i',
            'qr_attendance_school_end_time'          => 'required|date_format:H:i|after:qr_attendance_school_start_time',
            'qr_attendance_latitude'                 => 'required|numeric|between:-90,90',
            'qr_attendance_longitude'                => 'required|numeric|between:-180,180',
            'qr_attendance_radius'                   => 'required|integer|min:10|max:5000',
            'qr_attendance_calculation_mode'         => 'required|in:' . StaffAttendance::MODE_SHIFT_BASED . ',' . StaffAttendance::MODE_DAY_BASED,
            'qr_attendance_cooldown_seconds'         => 'required|integer|min:0|max:600',
            'qr_attendance_notify_missing_punch_out' => 'required|boolean',
            'qr_attendance_notification_emails'      => 'nullable|string',
            'google_maps_api_key'      => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }

        $schoolId = Auth::user()->school_id;

        try {
            DB::beginTransaction();

            $payload = [];
            foreach (QrAttendanceSettingService::keys() as $key) {
                if (!$request->has($key)) {
                    continue;
                }
                $payload[] = [
                    'name'      => $key,
                    'data'      => (string) $request->input($key),
                    'type'      => 'string',
                    'school_id' => $schoolId,
                ];
            }

            $this->schoolSettings->upsert($payload, ['name', 'school_id'], ['data', 'type']);

            DB::commit();
            $this->cache->removeSchoolCache(config('constants.CACHE.SCHOOL.SETTINGS'));

            // Turning the feature on is gated on the readiness checklist, so the
            // admin is told exactly what is missing instead of getting a generic
            // validation error.
            if ((int) $request->input('qr_attendance_enabled') === 1) {
                $missing = $this->settings->missingRequirements($schoolId);

                if (!empty($missing)) {
                    $this->schoolSettings->updateOrCreate(
                        ['name' => QrAttendanceSettingService::ENABLED, 'school_id' => $schoolId],
                        ['data' => '0', 'type' => 'string']
                    );
                    $this->cache->removeSchoolCache(config('constants.CACHE.SCHOOL.SETTINGS'));

                    ResponseService::errorResponse(
                        trans('qr_attendance_configuration_incomplete', ['items' => implode(', ', $missing)])
                    );
                }
            }

            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'QrAttendanceSettingsController -> store method');
            ResponseService::errorResponse();
        }
    }

    /**
     * Creates the school's QR code the first time. There is one code per school
     * and it is never rotated — only its end date changes.
     */
    public function generateQr(Request $request)
    {
        ResponseService::noPermissionThenSendJson('qr-attendance-settings');

        $validator = Validator::make($request->all(), [
            'end_date' => 'nullable|date|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }

        $schoolId = Auth::user()->school_id;

        try {
            $existing = StaffAttendanceQrCode::where('school_id', $schoolId)->active()->first();

            if ($existing) {
                ResponseService::errorResponse(trans('attendance_qr_generated'));
            }

            $qrCode = StaffAttendanceQrCode::create([
                'token'        => (string) Str::uuid(),
                'status'       => StaffAttendanceQrCode::STATUS_ACTIVE,
                'end_date'     => $request->input('end_date'),
                'generated_at' => schoolNow($schoolId),
                'created_by'   => Auth::id(),
                'school_id'    => $schoolId,
            ]);

            ResponseService::successResponse('Data Stored Successfully', $qrCode);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'QrAttendanceSettingsController -> generateQr method');
            ResponseService::errorResponse();
        }
    }

    /**
     * Moves the end date forward. The token is untouched, so every printed
     * poster keeps working.
     */
    public function updateQrEndDate(Request $request)
    {
        ResponseService::noPermissionThenSendJson('qr-attendance-settings');

        $validator = Validator::make($request->all(), [
            'end_date' => 'required',
        ]);

        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }

        try {
            $qrCode = StaffAttendanceQrCode::where('school_id', Auth::user()->school_id)->active()->first();

            if (!$qrCode) {
                ResponseService::errorResponse(trans('qr_code_invalid'));
            }

            $qrCode->update(['end_date' => date('Y-m-d', strtotime($request->input('end_date')))]);

            ResponseService::successResponse('Data Updated Successfully', $qrCode);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'QrAttendanceSettingsController -> updateQrEndDate method');
            ResponseService::errorResponse();
        }
    }

    /**
     * The readiness checklist on its own, for refreshing the panel after a fix
     * elsewhere without reloading the settings page.
     */
    public function readiness()
    {
        ResponseService::noPermissionThenSendJson('qr-attendance-settings');

        $schoolId = Auth::user()->school_id;

        return response()->json([
            'error'     => false,
            'readiness' => $this->settings->readiness($schoolId),
            'can_enable' => empty($this->settings->missingRequirements($schoolId)),
        ]);
    }
}
