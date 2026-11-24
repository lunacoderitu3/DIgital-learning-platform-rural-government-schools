<?php

namespace App\Services\StaffAttendance;

use App\Models\Holiday;
use App\Models\Leave;
use App\Models\LeaveDetail;
use App\Models\LeaveMaster;
use App\Models\SessionYear;
use App\Models\Shift;
use App\Models\StaffAttendance;
use App\Models\StaffAttendancePunch;
use App\Models\StaffShiftAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Closes one school day: turns that date's punches into finalized attendance
 * rows and reports the staff members who never punched out.
 *
 * Safe to run more than once. One row per staff member per date, and a row an
 * admin has already corrected by hand is never overwritten.
 */
class AttendanceFinalizationService
{
    public function __construct(
        private readonly QrAttendanceSettingService $settings,
        private readonly AttendanceCalculationService $calculator
    ) {}

    /**
     * Finalize one school for one date.
     *
     * @return array{
     *     processed: int, present: int, half_day: int, absent: int,
     *     skipped: int, missing_punch_out: array<int, array<string, mixed>>
     * }
     */
    public function finalizeSchool(int $schoolId, string $date): array
    {
        $mode          = $this->settings->calculationMode($schoolId);
        $sessionYearId = $this->sessionYearId($schoolId, $date);
        $isHoliday     = Holiday::where('school_id', $schoolId)->whereDate('date', $date)->exists();
        $leaveMaster   = LeaveMaster::where('session_year_id', $sessionYearId)->first();

        $staffIds = $this->eligibleStaffIds($schoolId);
        if (empty($staffIds)) {
            return $this->emptySummary();
        }

        // Bulk fetch everything the loop needs — no per-staff queries.
        $punchesByStaff = StaffAttendancePunch::where('school_id', $schoolId)
            ->whereDate('date', $date)
            ->whereIn('staff_id', $staffIds)
            ->orderBy('punched_at')
            ->get()
            ->groupBy('staff_id');

        $shiftsByStaff   = $this->shiftsForDate($schoolId, $staffIds, $date);
        $leaveByStaff    = $this->approvedLeaveByStaff($schoolId, $staffIds, $date);
        $existingByStaff = StaffAttendance::where('school_id', $schoolId)
            ->whereDate('date', $date)
            ->whereIn('staff_id', $staffIds)
            ->get()
            ->keyBy('staff_id');

        $summary = $this->emptySummary();

        foreach ($staffIds as $staffId) {
            $existing = $existingByStaff->get($staffId);

            // A hand-corrected day is the admin's decision. Leave it alone.
            if ($existing && $existing->source === StaffAttendance::SOURCE_MANUAL) {
                $summary['skipped']++;
                continue;
            }

            $shift    = $shiftsByStaff[$staffId] ?? null;
            $punches  = $punchesByStaff->get($staffId) ?? collect();
            $result   = $this->calculator->calculate($punches, $shift, $mode);

            // Holiday and approved leave outrank a no-punch absent mark.
            $type = $result['type'];
            if ($isHoliday) {
                $type = StaffAttendance::TYPE_HOLIDAY;
            } elseif (isset($leaveByStaff[$staffId]) && $result['working_minutes'] === 0) {
                // Leave rows are owned by the leave module; skip rather than
                // overwrite what it already recorded.
                $summary['skipped']++;
                continue;
            }

            try {
                DB::transaction(function () use ($schoolId, $staffId, $date, $sessionYearId, $type, $result, $shift, $mode, $leaveByStaff, $leaveMaster) {
                    StaffAttendance::updateOrCreate(
                        ['staff_id' => $staffId, 'date' => $date, 'school_id' => $schoolId],
                        [
                            'session_year_id'     => $sessionYearId,
                            'type'                => $type,
                            'working_minutes'     => $result['working_minutes'],
                            'completed_sessions'  => $result['completed_sessions'],
                            'incomplete_sessions' => $result['incomplete_sessions'],
                            // Snapshot: later shift edits must not rewrite this day.
                            'shift_id'            => $shift?->id,
                            'shift_name'          => $shift?->name,
                            'shift_start_time'    => $shift?->rawStartTime(),
                            'shift_end_time'      => $shift?->rawEndTime(),
                            'full_day_minutes'    => $shift?->full_day_minutes,
                            'half_day_minutes'    => $shift?->half_day_minutes,
                            'calculation_mode'    => $mode,
                            'source'              => StaffAttendance::SOURCE_QR,
                            'finalized_at'        => schoolNow($schoolId),
                        ]
                    );

                    // Auto-create Leave if applicable
                    if (!isset($leaveByStaff[$staffId]) && $leaveMaster) {
                        $leaveType = match ((int)$type) {
                            StaffAttendance::TYPE_ABSENT => 'Full',
                            StaffAttendance::TYPE_FIRST_HALF_PRESENT => 'Second Half',
                            StaffAttendance::TYPE_SECOND_HALF_PRESENT => 'First Half',
                            StaffAttendance::TYPE_HALF_DAY => 'Half',
                            default => null,
                        };

                        if ($leaveType !== null) {
                            // Verify leave doesn't already exist for this date and user
                            $existingLeave = LeaveDetail::where('school_id', $schoolId)
                                ->where('date', $date)
                                ->whereHas('leave', function ($q) use ($staffId) {
                                    $q->where('user_id', $staffId);
                                })->exists();

                            if (!$existingLeave) {
                                $leave = Leave::create([
                                    'user_id' => $staffId,
                                    'reason' => 'Auto Generated Leave',
                                    'from_date' => $date,
                                    'to_date' => $date,
                                    'status' => 1,
                                    'school_id' => $schoolId,
                                    'leave_master_id' => $leaveMaster->id
                                ]);

                                LeaveDetail::create([
                                    'leave_id' => $leave->id,
                                    'date' => $date,
                                    'type' => $leaveType,
                                    'school_id' => $schoolId,
                                ]);
                            }
                        }
                    }
                });
            } catch (\Throwable $e) {
                Log::error('Staff QR attendance finalization failed for one staff member.', [
                    'school_id' => $schoolId,
                    'staff_id'  => $staffId,
                    'date'      => $date,
                    'shift_id'  => $shift?->id,
                    'operation' => 'finalize',
                    'exception' => $e,
                ]);
                $summary['skipped']++;
                continue;
            }

            $summary['processed']++;
            match ($type) {
                StaffAttendance::TYPE_PRESENT  => $summary['present']++,
                StaffAttendance::TYPE_HALF_DAY => $summary['half_day']++,
                StaffAttendance::TYPE_FIRST_HALF_PRESENT => $summary['first_half_present']++,
                StaffAttendance::TYPE_SECOND_HALF_PRESENT => $summary['second_half_present']++,
                StaffAttendance::TYPE_ABSENT   => $summary['absent']++,
                default                        => null,
            };

            // An unmatched punch in is never closed automatically — it is
            // reported for manual correction instead.
            if ($result['incomplete_sessions'] > 0) {
                $summary['missing_punch_out'][] = [
                    'staff_id'        => $staffId,
                    'date'            => $date,
                    'shift_name'      => $shift?->name,
                    'punch_in'        => $result['open'][0] ?? null,
                    'working_minutes' => $result['working_minutes'],
                    'type'            => $type,
                ];
            }
        }

        return $summary;
    }

    /**
     * Whether the date is due for finalization: school end time + 1 hour has
     * passed. Compared as "now >= due", not equality, so a scheduler that
     * misses the exact minute still processes the day.
     */
    public function isDue(int $schoolId, string $date): bool
    {
        $finalizationTime = $this->settings->finalizationTime($schoolId);
        if (empty($finalizationTime)) {
            return false;
        }

        // Compared in the school's own timezone, so schools in different
        // regions finalize at their own local school end + 1 hour.
        $due = \Carbon\Carbon::parse($date . ' ' . $finalizationTime, schoolTimezone($schoolId));

        return schoolNow($schoolId)->greaterThanOrEqualTo($due);
    }

    /**
     * Active teaching and non-teaching staff, as user ids.
     *
     * @return array<int, int>
     */
    public function eligibleStaffIds(int $schoolId): array
    {
        return User::where('school_id', $schoolId)
            ->where('status', 1)
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['Teacher', 'Staff']))
            ->pluck('id')
            ->all();
    }

    /**
     * The shift in force on the date, keyed by staff id. One query, not one per
     * staff member.
     *
     * @param  array<int, int>  $staffIds
     * @return array<int, Shift>
     */
    private function shiftsForDate(int $schoolId, array $staffIds, string $date): array
    {
        $assignments = StaffShiftAssignment::with('shift')
            ->where('school_id', $schoolId)
            ->whereIn('staff_id', $staffIds)
            ->onDate($date)
            ->orderBy('effective_from')
            ->get();

        $shifts = [];
        foreach ($assignments as $assignment) {
            // Ordered ascending, so the newest assignment covering the date wins.
            if ($assignment->shift) {
                $shifts[$assignment->staff_id] = $assignment->shift;
            }
        }

        return $shifts;
    }

    /**
     * Staff on approved leave for the date.
     *
     * @param  array<int, int>  $staffIds
     * @return array<int, bool>
     */
    private function approvedLeaveByStaff(int $schoolId, array $staffIds, string $date): array
    {
        $leaveIds = Leave::where('school_id', $schoolId)
            ->whereIn('user_id', $staffIds)
            ->where('status', 1)
            ->pluck('id', 'user_id');

        if ($leaveIds->isEmpty()) {
            return [];
        }

        $detailLeaveIds = LeaveDetail::where('school_id', $schoolId)
            ->whereIn('leave_id', $leaveIds->values())
            ->whereDate('date', $date)
            ->pluck('leave_id')
            ->flip();

        $onLeave = [];
        foreach ($leaveIds as $userId => $leaveId) {
            if ($detailLeaveIds->has($leaveId)) {
                $onLeave[$userId] = true;
            }
        }

        return $onLeave;
    }

    private function sessionYearId(int $schoolId, string $date): ?int
    {
        $sessionYear = SessionYear::where('school_id', $schoolId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        return $sessionYear?->id
            ?? SessionYear::where('school_id', $schoolId)->where('default', 1)->value('id');
    }

    private function emptySummary(): array
    {
        return [
            'processed'         => 0,
            'present'           => 0,
            'half_day'          => 0,
            'first_half_present' => 0,
            'second_half_present' => 0,
            'absent'            => 0,
            'skipped'           => 0,
            'missing_punch_out' => [],
        ];
    }
}
