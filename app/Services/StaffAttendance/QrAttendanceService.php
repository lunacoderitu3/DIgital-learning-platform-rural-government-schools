<?php

namespace App\Services\StaffAttendance;

use App\Exceptions\QrAttendanceException;
use App\Models\Shift;
use App\Models\StaffAttendance;
use App\Models\StaffAttendancePunch;
use App\Models\StaffAttendanceQrCode;
use App\Models\StaffShiftAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Validates a scan and records the punch.
 *
 * Nothing here trusts the client. The staff member, the school, the punch type
 * and the attendance date are all resolved server-side; the request only
 * carries the scanned token, the device coordinates and a scan id.
 */
class QrAttendanceService
{
    public function __construct(
        private readonly QrAttendanceSettingService $settings,
        private readonly AttendanceCalculationService $calculator
    ) {}

    /**
     * Record a punch for the authenticated staff member.
     *
     * @param  array{qr_token: string, latitude?: float|null, longitude?: float|null, request_uuid?: string|null}  $payload
     * @return array{punch: StaffAttendancePunch, next_action: string, working_minutes: int, sessions: array, duplicate: bool}
     *
     * @throws QrAttendanceException
     */
    public function punch(User $staff, array $payload): array
    {
        $schoolId = $staff->school_id;

        // Students and guardians share the same API surface; only staff punch.
        if (!$schoolId || $staff->status != 1) {
            throw QrAttendanceException::staffNotEligible();
        }

        $config = $this->settings->all($schoolId);

        if ($config[QrAttendanceSettingService::ENABLED] !== 1) {
            throw QrAttendanceException::disabled();
        }

        $missing = $this->settings->missingRequirements($schoolId);
        if (!empty($missing)) {
            throw QrAttendanceException::notConfigured($missing);
        }

        // A repeated request from a lost response must return the original
        // punch, not create a second one.
        if (!empty($payload['request_uuid'])) {
            $existing = StaffAttendancePunch::where('school_id', $schoolId)
                ->where('request_uuid', $payload['request_uuid'])
                ->first();

            if ($existing) {
                return $this->result($staff, $existing, true);
            }
        }

        $date = $this->currentDate($schoolId);
        $now  = $this->currentTimestamp($schoolId);

        $this->assertWithinSchoolHours($config, $now);

        $shift = $this->resolveShift($staff->id, $schoolId, $date);
        if (!$shift) {
            throw QrAttendanceException::shiftMissing();
        }

        $qrCode = $this->resolveQrCode($payload['qr_token'] ?? '', $schoolId, $date);

        $distance = $this->assertWithinRadius($config, $payload);

        return DB::transaction(function () use ($staff, $schoolId, $date, $now, $config, $qrCode, $distance, $payload) {
            // Lock this staff member's punches for the date so two simultaneous
            // scans cannot both read the same last punch and both insert IN.
            $punches = StaffAttendancePunch::where('staff_id', $staff->id)
                ->where('school_id', $schoolId)
                ->whereDate('date', $date)
                ->orderBy('punched_at')
                ->lockForUpdate()
                ->get();

            $this->assertCooldownPassed($punches, $config, $now);

            $last = $punches->last();
            $type = (!$last || $last->type === StaffAttendancePunch::TYPE_OUT)
                ? StaffAttendancePunch::TYPE_IN
                : StaffAttendancePunch::TYPE_OUT;

            $punch = StaffAttendancePunch::create([
                'staff_id'     => $staff->id,
                'date'         => $date,
                'type'         => $type,
                'punched_at'   => $now,
                'latitude'     => $payload['latitude'] ?? null,
                'longitude'    => $payload['longitude'] ?? null,
                'distance'     => $distance,
                'qr_code_id'   => $qrCode->id,
                'request_uuid' => $payload['request_uuid'] ?? null,
                'source'       => StaffAttendance::SOURCE_QR,
                'school_id'    => $schoolId,
            ]);

            return $this->result($staff, $punch, false, $punches->push($punch));
        });
    }

    /**
     * Today's state for the staff member's own screen: current status, sessions
     * so far and what the next scan will do.
     */
    public function todayStatus(User $staff): array
    {
        $date    = $this->currentDate($staff->school_id);
        $punches = StaffAttendancePunch::where('staff_id', $staff->id)
            ->where('school_id', $staff->school_id)
            ->whereDate('date', $date)
            ->orderBy('punched_at')
            ->get();

        $shift    = $this->resolveShift($staff->id, $staff->school_id, $date);
        $sessions = $this->calculator->buildSessions($punches);
        $mode     = $this->settings->calculationMode($staff->school_id);

        $last = $punches->last();

        return [
            'date'            => $date,
            'shift'           => $shift ? [
                'id'               => $shift->id,
                'name'             => $shift->name,
                'start_time'       => $shift->rawStartTime(),
                'end_time'         => $shift->rawEndTime(),
                'full_day_minutes' => $shift->full_day_minutes,
                'half_day_minutes' => $shift->half_day_minutes,
            ] : null,
            'on_duty'         => $last && $last->type === StaffAttendancePunch::TYPE_IN,
            'next_action'     => (!$last || $last->type === StaffAttendancePunch::TYPE_OUT)
                ? StaffAttendancePunch::TYPE_IN
                : StaffAttendancePunch::TYPE_OUT,
            'working_minutes' => $this->calculator->countedMinutes($sessions['completed'], $shift, $mode),
            'sessions'        => $sessions['completed'],
            'open_session'    => $sessions['open'][0] ?? null,
        ];
    }

    /**
     * The shift in force for a staff member on a date. One shift per staff, so
     * the newest assignment covering the date wins.
     */
    public function resolveShift(int $staffId, int $schoolId, string $date): ?Shift
    {
        $assignment = StaffShiftAssignment::with('shift')
            ->where('staff_id', $staffId)
            ->where('school_id', $schoolId)
            ->onDate($date)
            ->orderByDesc('effective_from')
            ->first();

        return $assignment?->shift;
    }

    /**
     * Distance in metres between two coordinates. Haversine, earth radius
     * 6,371,000 m — accurate enough well beyond any campus radius.
     */
    public function distanceInMetres(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * Scanning stops at school end time. Anything later is a manual correction.
     */
    private function assertWithinSchoolHours(array $config, string $now): void
    {
        $end = $config[QrAttendanceSettingService::SCHOOL_END];
        if (empty($end)) {
            return;
        }

        if (strtotime(substr($now, 11)) > strtotime($end)) {
            throw QrAttendanceException::dayClosed();
        }
    }

    private function resolveQrCode(string $token, int $schoolId, string $date): StaffAttendanceQrCode
    {
        if (empty($token)) {
            throw QrAttendanceException::invalidQr();
        }

        // Scoped to the school, so a code from another school never resolves.
        $qrCode = StaffAttendanceQrCode::where('school_id', $schoolId)
            ->where('token', $token)
            ->first();

        if (!$qrCode) {
            throw QrAttendanceException::invalidQr();
        }

        if ($qrCode->status !== StaffAttendanceQrCode::STATUS_ACTIVE) {
            throw QrAttendanceException::inactiveQr();
        }

        if (!$qrCode->isValidOn($date)) {
            throw QrAttendanceException::expiredQr();
        }

        return $qrCode;
    }

    private function assertWithinRadius(array $config, array $payload): int
    {
        $lat = $payload['latitude'] ?? null;
        $lng = $payload['longitude'] ?? null;

        if ($lat === null || $lng === null) {
            throw QrAttendanceException::locationUnavailable();
        }

        $distance = $this->distanceInMetres(
            (float) $lat,
            (float) $lng,
            (float) $config[QrAttendanceSettingService::LATITUDE],
            (float) $config[QrAttendanceSettingService::LONGITUDE]
        );

        $radius = (int) $config[QrAttendanceSettingService::RADIUS];

        // A device exactly on the boundary is accepted.
        if ($distance > $radius) {
            throw QrAttendanceException::outsideRadius($distance, $radius);
        }

        return $distance;
    }

    /**
     * @param  Collection<int, StaffAttendancePunch>  $punches
     */
    private function assertCooldownPassed(Collection $punches, array $config, string $now): void
    {
        $cooldown = (int) $config[QrAttendanceSettingService::COOLDOWN];
        $last     = $punches->last();

        if ($cooldown <= 0 || !$last) {
            return;
        }

        $elapsed = strtotime($now) - strtotime($last->punched_at->format('Y-m-d H:i:s'));

        if ($elapsed < $cooldown) {
            throw QrAttendanceException::cooldown($cooldown - $elapsed);
        }
    }

    /**
     * @param  Collection<int, StaffAttendancePunch>|null  $punches
     */
    private function result(User $staff, StaffAttendancePunch $punch, bool $duplicate, ?Collection $punches = null): array
    {
        $punches ??= StaffAttendancePunch::where('staff_id', $staff->id)
            ->where('school_id', $staff->school_id)
            ->whereDate('date', $punch->date->toDateString())
            ->orderBy('punched_at')
            ->get();

        $shift    = $this->resolveShift($staff->id, $staff->school_id, $punch->date->toDateString());
        $sessions = $this->calculator->buildSessions($punches);
        $mode     = $this->settings->calculationMode($staff->school_id);

        return [
            'punch'           => $punch,
            'next_action'     => $punch->type === StaffAttendancePunch::TYPE_IN
                ? StaffAttendancePunch::TYPE_OUT
                : StaffAttendancePunch::TYPE_IN,
            'working_minutes' => $this->calculator->countedMinutes($sessions['completed'], $shift, $mode),
            'sessions'        => $sessions['completed'],
            'duplicate'       => $duplicate,
        ];
    }

    /**
     * Attendance date and timestamps come from the school's timezone, never
     * blindly from the PHP server timezone.
     */
    private function currentDate(?int $schoolId = null): string
    {
        return schoolNow($schoolId)->toDateString();
    }

    private function currentTimestamp(?int $schoolId = null): string
    {
        return schoolNow($schoolId)->format('Y-m-d H:i:s');
    }
}
