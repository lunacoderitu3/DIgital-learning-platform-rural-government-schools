<?php

namespace App\Services\StaffAttendance;

use App\Models\Shift;
use App\Models\StaffAttendance;
use App\Models\StaffAttendanceQrCode;
use App\Models\StaffShiftAssignment;
use App\Models\User;
use App\Services\CachingService;
use Illuminate\Support\Facades\Auth;

/**
 * Reads and validates the school-level QR attendance configuration.
 *
 * Settings live as key/value rows in school_settings, reusing the existing
 * settings architecture rather than adding a table.
 */
class QrAttendanceSettingService
{
    public const ENABLED          = 'qr_attendance_enabled';
    public const SCHOOL_START     = 'qr_attendance_school_start_time';
    public const SCHOOL_END       = 'qr_attendance_school_end_time';
    public const LATITUDE         = 'qr_attendance_latitude';
    public const LONGITUDE        = 'qr_attendance_longitude';
    public const RADIUS           = 'qr_attendance_radius';
    public const CALCULATION_MODE = 'qr_attendance_calculation_mode';
    public const COOLDOWN         = 'qr_attendance_cooldown_seconds';
    public const NOTIFY_MPO       = 'qr_attendance_notify_missing_punch_out';
    public const NOTIFY_EMAILS    = 'qr_attendance_notification_emails';
    public const MAPS_API_KEY     = 'google_maps_api_key';

    /** Raw punch rows are cleared by the monthly job once older than this. */
    public const PUNCH_RETENTION_MONTHS = 1;

    public const DEFAULT_COOLDOWN_SECONDS = 30;

    /** Finalization runs this long after school end time. */
    public const FINALIZATION_OFFSET_MINUTES = 60;

    public static function keys(): array
    {
        return [
            self::ENABLED,
            self::SCHOOL_START,
            self::SCHOOL_END,
            self::LATITUDE,
            self::LONGITUDE,
            self::RADIUS,
            self::CALCULATION_MODE,
            self::COOLDOWN,
            self::NOTIFY_MPO,
            self::NOTIFY_EMAILS,
            self::MAPS_API_KEY,
        ];
    }

    /**
     * All QR settings for a school, with defaults filled in.
     */
    public function all(?int $schoolId = null): array
    {
        $schoolId = $schoolId ?: (Auth::user()->school_id ?? null);
        $settings = (array) app(CachingService::class)->getSchoolSettings(self::keys(), $schoolId);

        return [
            self::ENABLED          => (int) ($settings[self::ENABLED] ?? 0),
            self::SCHOOL_START     => $settings[self::SCHOOL_START] ?? null,
            self::SCHOOL_END       => $settings[self::SCHOOL_END] ?? null,
            self::LATITUDE         => isset($settings[self::LATITUDE]) && $settings[self::LATITUDE] !== ''
                ? (float) $settings[self::LATITUDE] : null,
            self::LONGITUDE        => isset($settings[self::LONGITUDE]) && $settings[self::LONGITUDE] !== ''
                ? (float) $settings[self::LONGITUDE] : null,
            self::RADIUS           => isset($settings[self::RADIUS]) && $settings[self::RADIUS] !== ''
                ? (int) $settings[self::RADIUS] : null,
            self::CALCULATION_MODE => $settings[self::CALCULATION_MODE] ?? StaffAttendance::MODE_SHIFT_BASED,
            self::COOLDOWN         => (int) ($settings[self::COOLDOWN] ?? self::DEFAULT_COOLDOWN_SECONDS),
            self::NOTIFY_MPO       => (int) ($settings[self::NOTIFY_MPO] ?? 1),
            self::NOTIFY_EMAILS    => $settings[self::NOTIFY_EMAILS] ?? null,
            self::MAPS_API_KEY     => $settings[self::MAPS_API_KEY] ?? null,
        ];
    }

    public function isEnabled(?int $schoolId = null): bool
    {
        return $this->all($schoolId)[self::ENABLED] === 1;
    }

    public function calculationMode(?int $schoolId = null): string
    {
        $mode = $this->all($schoolId)[self::CALCULATION_MODE];

        return $mode === StaffAttendance::MODE_DAY_BASED
            ? StaffAttendance::MODE_DAY_BASED
            : StaffAttendance::MODE_SHIFT_BASED;
    }

    /**
     * The clock time at which a day becomes eligible for finalization:
     * school end + 1 hour, as H:i:s.
     */
    public function finalizationTime(?int $schoolId = null): ?string
    {
        $end = $this->all($schoolId)[self::SCHOOL_END];
        if (empty($end)) {
            return null;
        }

        return date('H:i:s', strtotime($end) + (self::FINALIZATION_OFFSET_MINUTES * 60));
    }

    /**
     * Readiness checklist behind the enable switch.
     *
     * Returns one entry per requirement so the settings screen can name the
     * exact missing item instead of showing a generic validation error.
     *
     * @return array<int, array{key: string, label: string, passed: bool, detail: string|null}>
     */
    public function readiness(?int $schoolId = null): array
    {
        $schoolId = $schoolId ?: (Auth::user()->school_id ?? null);
        $settings = $this->all($schoolId);

        $activeShifts = Shift::where('school_id', $schoolId)->where('status', 1)->get();
        $validShifts  = $activeShifts->filter(fn($shift) => $this->shiftThresholdsValid($shift));

        $staffWithoutShift = $this->staffWithoutShiftCount($schoolId);

        $qr = StaffAttendanceQrCode::where('school_id', $schoolId)->active()->first();

        $checks = [
            [
                'key'    => 'school_timing',
                'label'  => trans('school_start_and_end_time'),
                'passed' => !empty($settings[self::SCHOOL_START]) && !empty($settings[self::SCHOOL_END]),
                'detail' => $settings[self::SCHOOL_START] && $settings[self::SCHOOL_END]
                    ? $settings[self::SCHOOL_START] . ' - ' . $settings[self::SCHOOL_END]
                    : null,
            ],
            [
                'key'    => 'location',
                'label'  => trans('attendance_location'),
                'passed' => $settings[self::LATITUDE] !== null && $settings[self::LONGITUDE] !== null,
                'detail' => $settings[self::LATITUDE] !== null
                    ? $settings[self::LATITUDE] . ', ' . $settings[self::LONGITUDE]
                    : null,
            ],
            [
                'key'    => 'radius',
                'label'  => trans('allowed_radius'),
                'passed' => !empty($settings[self::RADIUS]) && $settings[self::RADIUS] > 0,
                'detail' => $settings[self::RADIUS] ? $settings[self::RADIUS] . ' m' : null,
            ],
            [
                'key'    => 'calculation_mode',
                'label'  => trans('working_hours_calculation'),
                'passed' => !empty($settings[self::CALCULATION_MODE]),
                'detail' => $settings[self::CALCULATION_MODE],
            ],
            [
                'key'    => 'active_shift',
                'label'  => trans('at_least_one_active_staff_shift'),
                'passed' => $activeShifts->isNotEmpty(),
                'detail' => $activeShifts->count() . ' ' . trans('active_shifts'),
            ],
            [
                'key'    => 'shift_thresholds',
                'label'  => trans('full_half_day_thresholds_valid'),
                'passed' => $activeShifts->isNotEmpty() && $validShifts->count() === $activeShifts->count(),
                'detail' => $validShifts->count() . '/' . $activeShifts->count(),
            ],
            [
                'key'    => 'staff_shift_assigned',
                'label'  => trans('every_active_staff_member_has_a_shift'),
                'passed' => $staffWithoutShift === 0,
                'detail' => $staffWithoutShift > 0 ? $staffWithoutShift . ' ' . trans('without_shift') : null,
            ],
            [
                'key'    => 'qr_generated',
                'label'  => trans('attendance_qr_generated'),
                'passed' => (bool) $qr,
                'detail' => $qr && $qr->end_date ? $qr->end_date->toDateString() : null,
            ],
        ];

        return $checks;
    }

    /**
     * Which readiness items are still failing. Empty means QR can be enabled.
     *
     * @return array<int, string>
     */
    public function missingRequirements(?int $schoolId = null): array
    {
        return collect($this->readiness($schoolId))
            ->reject(fn($check) => $check['passed'])
            ->pluck('label')
            ->all();
    }

    /**
     * half_day < full_day <= shift duration, both above zero.
     */
    public function shiftThresholdsValid(Shift $shift): bool
    {
        $full = (int) $shift->full_day_minutes;
        $half = (int) $shift->half_day_minutes;

        if ($full <= 0 || $half <= 0 || $half >= $full) {
            return false;
        }

        $duration = $shift->durationMinutes();

        return $duration > 0 && $full <= $duration;
    }

    /**
     * Active staff members with no shift assignment in force today.
     */
    private function staffWithoutShiftCount(?int $schoolId): int
    {
        $today = date('Y-m-d');

        $assignedStaffIds = StaffShiftAssignment::where('school_id', $schoolId)
            ->onDate($today)
            ->pluck('staff_id');

        return User::where('school_id', $schoolId)
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['Teacher', 'Driver', 'Helper'])->orWhere('custom_role', 1))
            ->where('status', 1)
            ->whereNotIn('id', $assignedStaffIds)
            ->count();
    }
}
