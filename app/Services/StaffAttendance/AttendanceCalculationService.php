<?php

namespace App\Services\StaffAttendance;

use App\Models\Shift;
use App\Models\StaffAttendance;
use App\Models\StaffAttendancePunch;
use Illuminate\Support\Collection;

/**
 * Turns raw punch events into counted working minutes and a Present / Half day
 * / Absent result.
 *
 * The rules this encodes, all from the specification:
 *  - Only completed IN → OUT pairs contribute working time.
 *  - An unmatched IN is kept and reported, never closed with an invented time.
 *  - Shift-based mode counts only the overlap with the assigned shift window.
 *  - Day-based mode counts every completed session in full.
 *  - Gaps between sessions (breaks) are excluded automatically, because only
 *    the sessions themselves are summed.
 *  - Everything is whole minutes. Never decimal hours: 4h 59m 59s is a half
 *    day, not a full day.
 */
class AttendanceCalculationService
{
    /**
     * Pair punches into sessions, in punch order.
     *
     * A second consecutive IN closes nothing and starts nothing new — the first
     * IN stays open and is reported. An OUT with no open IN is ignored for
     * calculation but stays in the punch log.
     *
     * @param  Collection<int, StaffAttendancePunch>  $punches
     * @return array{completed: array<int, array{in: string, out: string}>, open: array<int, string>}
     */
    public function buildSessions(Collection $punches): array
    {
        $completed = [];
        $open      = [];
        $openIn    = null;

        foreach ($punches->sortBy('punched_at') as $punch) {
            $time = $punch->punched_at instanceof \DateTimeInterface
                ? $punch->punched_at->format('Y-m-d H:i:s')
                : (string) $punch->punched_at;

            if ($punch->type === StaffAttendancePunch::TYPE_IN) {
                // A new IN while one is already open leaves the earlier one unmatched.
                if ($openIn !== null) {
                    $open[] = $openIn;
                }
                $openIn = $time;
                continue;
            }

            if ($openIn !== null) {
                $completed[] = ['in' => $openIn, 'out' => $time];
                $openIn = null;
            }
        }

        if ($openIn !== null) {
            $open[] = $openIn;
        }

        return ['completed' => $completed, 'open' => $open];
    }

    /**
     * Counted working minutes for a set of completed sessions.
     *
     * @param  array<int, array{in: string, out: string}>  $completedSessions
     * @return array{total: int, first_half: int, second_half: int}
     */
    public function countedMinutes(array $completedSessions, ?Shift $shift, string $mode): array
    {
        $minutes = 0;
        $firstHalfMinutes = 0;
        $secondHalfMinutes = 0;

        foreach ($completedSessions as $session) {
            if ($mode === StaffAttendance::MODE_SHIFT_BASED) {
                $minutes += $this->sessionMinutesWithinShift($session, $shift);
            } else {
                $minutes += $this->sessionMinutes($session);
            }

            if ($shift && !empty($shift->rawStartTime()) && !empty($shift->rawEndTime())) {
                $date = substr($session['in'], 0, 10);
                $shiftStart = strtotime($date . ' ' . $shift->rawStartTime());
                $shiftEnd   = strtotime($date . ' ' . $shift->rawEndTime());
                
                if ($shiftStart && $shiftEnd && $shiftEnd > $shiftStart) {
                    $midpoint = $shiftStart + (($shiftEnd - $shiftStart) / 2);

                    $firstHalfMinutes += $this->sessionMinutesWithinWindow($session, $shiftStart, (int)$midpoint);
                    $secondHalfMinutes += $this->sessionMinutesWithinWindow($session, (int)$midpoint, $shiftEnd);
                }
            }
        }

        return [
            'total'       => $minutes,
            'first_half'  => $firstHalfMinutes,
            'second_half' => $secondHalfMinutes,
        ];
    }

    /**
     * Full length of one completed session, in whole minutes.
     *
     * @param  array{in: string, out: string}  $session
     */
    public function sessionMinutes(array $session): int
    {
        $in  = strtotime($session['in']);
        $out = strtotime($session['out']);

        if ($in === false || $out === false || $out <= $in) {
            return 0;
        }

        return (int) floor(($out - $in) / 60);
    }

    /**
     * The part of one session that falls inside the shift window.
     *
     *   valid_start = max(punch_in, shift_start)
     *   valid_end   = min(punch_out, shift_end)
     *
     * @param  array{in: string, out: string}  $session
     */
    public function sessionMinutesWithinShift(array $session, ?Shift $shift): int
    {
        // Without a shift there is no window to clip against, so nothing counts.
        if (!$shift || empty($shift->rawStartTime()) || empty($shift->rawEndTime())) {
            return 0;
        }

        $date = substr($session['in'], 0, 10);

        $validStart = max(strtotime($session['in']), strtotime($date . ' ' . $shift->rawStartTime()));
        $validEnd   = min(strtotime($session['out']), strtotime($date . ' ' . $shift->rawEndTime()));

        if ($validEnd <= $validStart) {
            return 0;
        }

        return (int) floor(($validEnd - $validStart) / 60);
    }

    /**
     * The part of one session that falls inside a specific time window.
     *
     * @param  array{in: string, out: string}  $session
     */
    public function sessionMinutesWithinWindow(array $session, int $windowStart, int $windowEnd): int
    {
        $validStart = max(strtotime($session['in']), $windowStart);
        $validEnd   = min(strtotime($session['out']), $windowEnd);

        if ($validEnd <= $validStart) {
            return 0;
        }

        return (int) floor(($validEnd - $validStart) / 60);
    }

    /**
     * Present / Half day / Absent from counted minutes against the thresholds.
     *
     * Boundaries are inclusive at the bottom: exactly the full-day requirement
     * is a full day, exactly the half-day requirement is a half day.
     */
    public function determineType(int $workingMinutes, ?int $fullDayMinutes, ?int $halfDayMinutes, int $firstHalfMinutes = 0, int $secondHalfMinutes = 0): int
    {
        // A shift with no thresholds cannot produce a pass mark. Treat any
        // recorded work as present rather than silently marking absent.
        if (empty($fullDayMinutes)) {
            return $workingMinutes > 0 ? StaffAttendance::TYPE_PRESENT : StaffAttendance::TYPE_ABSENT;
        }

        if ($workingMinutes >= $fullDayMinutes) {
            return StaffAttendance::TYPE_PRESENT;
        }

        if (!empty($halfDayMinutes)) {
            if ($firstHalfMinutes >= $halfDayMinutes && $secondHalfMinutes < $halfDayMinutes) {
                return StaffAttendance::TYPE_FIRST_HALF_PRESENT;
            }
            
            if ($secondHalfMinutes >= $halfDayMinutes && $firstHalfMinutes < $halfDayMinutes) {
                return StaffAttendance::TYPE_SECOND_HALF_PRESENT;
            }

            if ($workingMinutes >= $halfDayMinutes) {
                return StaffAttendance::TYPE_HALF_DAY;
            }
        }

        return StaffAttendance::TYPE_ABSENT;
    }

    /**
     * Everything a finalized row needs for one staff member on one date.
     *
     * @param  Collection<int, StaffAttendancePunch>  $punches
     * @return array{
     *     working_minutes: int,
     *     completed_sessions: int,
     *     incomplete_sessions: int,
     *     type: int,
     *     sessions: array<int, array{in: string, out: string}>,
     *     open: array<int, string>
     * }
     */
    public function calculate(Collection $punches, ?Shift $shift, string $mode): array
    {
        $sessions = $this->buildSessions($punches);
        $minutes  = $this->countedMinutes($sessions['completed'], $shift, $mode);

        return [
            'working_minutes'     => $minutes['total'],
            'completed_sessions'  => count($sessions['completed']),
            'incomplete_sessions' => count($sessions['open']),
            'type'                => $this->determineType(
                $minutes['total'],
                $shift->full_day_minutes ?? null,
                $shift->half_day_minutes ?? null,
                $minutes['first_half'],
                $minutes['second_half']
            ),
            'sessions'            => $sessions['completed'],
            'open'                => $sessions['open'],
        ];
    }
}
