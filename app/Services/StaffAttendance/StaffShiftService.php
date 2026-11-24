<?php

namespace App\Services\StaffAttendance;

use App\Models\Shift;
use App\Models\StaffShiftAssignment;
use Illuminate\Support\Facades\Auth;

/**
 * Assigns the one shift a staff member holds.
 *
 * Called from the Manage Teacher and Manage Staff forms — there is no separate
 * assignment screen. Changing a shift closes the current assignment and opens a
 * new one from the effective date, so attendance already finalized keeps the
 * shift it was calculated with.
 */
class StaffShiftService
{
    /**
     * Give a staff member a shift from a date onward.
     *
     * Passing a null shift closes the current assignment without opening a new
     * one, which is how a shift is cleared.
     *
     * @param  int  $staffId  users.id
     */
    public function assign(int $staffId, ?int $shiftId, ?string $effectiveFrom = null, ?int $schoolId = null): ?StaffShiftAssignment
    {
        $schoolId      = $schoolId ?: (Auth::user()->school_id ?? null);
        $effectiveFrom = $effectiveFrom ?: schoolNow($schoolId)->toDateString();

        $current = $this->currentAssignment($staffId, $schoolId);

        // Same shift already running from the same date or earlier — nothing to do.
        if ($current && $current->shift_id === $shiftId && $current->effective_from->toDateString() <= $effectiveFrom) {
            return $current;
        }

        if ($current) {
            $this->closeAssignment($current, $effectiveFrom);
        }

        if (empty($shiftId)) {
            return null;
        }

        return StaffShiftAssignment::create([
            'staff_id'       => $staffId,
            'shift_id'       => $shiftId,
            'effective_from' => $effectiveFrom,
            'effective_to'   => null,
            'school_id'      => $schoolId,
            'created_by'     => Auth::id(),
        ]);
    }

    /**
     * The assignment in force today, if any.
     */
    public function currentAssignment(int $staffId, ?int $schoolId = null): ?StaffShiftAssignment
    {
        $schoolId = $schoolId ?: (Auth::user()->school_id ?? null);

        return StaffShiftAssignment::where('staff_id', $staffId)
            ->where('school_id', $schoolId)
            ->whereNull('effective_to')
            ->orderByDesc('effective_from')
            ->first()
            ?? StaffShiftAssignment::where('staff_id', $staffId)
            ->where('school_id', $schoolId)
            ->onDate(schoolNow($schoolId)->toDateString())
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * The shift id in force today, for pre-selecting the form field.
     */
    public function currentShiftId(int $staffId, ?int $schoolId = null): ?int
    {
        return $this->currentAssignment($staffId, $schoolId)?->shift_id;
    }

    /**
     * Current shift id for many staff members at once, keyed by staff id.
     * One query — used by the teacher and staff list tables.
     *
     * @param  array<int, int>  $staffIds
     * @return array<int, array{shift_id: int, shift_name: string|null}>
     */
    public function currentShiftsFor(array $staffIds, ?int $schoolId = null): array
    {
        $schoolId = $schoolId ?: (Auth::user()->school_id ?? null);

        if (empty($staffIds)) {
            return [];
        }

        $assignments = StaffShiftAssignment::with('shift:id,name')
            ->where('school_id', $schoolId)
            ->whereIn('staff_id', $staffIds)
            ->onDate(schoolNow($schoolId)->toDateString())
            ->orderBy('effective_from')
            ->get();

        $map = [];
        foreach ($assignments as $assignment) {
            $map[$assignment->staff_id] = [
                'shift_id'   => $assignment->shift_id,
                'shift_name' => $assignment->shift?->name,
            ];
        }

        return $map;
    }

    /**
     * Active shifts for the form dropdown. A retired shift stays on old
     * attendance but cannot be picked for someone new.
     */
    public function selectableShifts(?int $schoolId = null)
    {
        $schoolId = $schoolId ?: (Auth::user()->school_id ?? null);

        return Shift::where('school_id', $schoolId)
            ->where('status', 1)
            ->orderBy('start_time')
            ->get(['id', 'name', 'start_time', 'end_time', 'full_day_minutes', 'half_day_minutes']);
    }

    /**
     * Close an assignment the day before the replacement starts, so the two
     * never overlap and no date is left uncovered.
     */
    private function closeAssignment(StaffShiftAssignment $assignment, string $newEffectiveFrom): void
    {
        $closesOn = \Carbon\Carbon::parse($newEffectiveFrom)->subDay()->toDateString();

        // A replacement starting on or before the current assignment's own start
        // means the current one never really applied — remove it instead of
        // leaving a row with effective_to before effective_from.
        if ($assignment->effective_from->toDateString() > $closesOn) {
            $assignment->delete();
            return;
        }

        $assignment->update(['effective_to' => $closesOn]);
    }
}
