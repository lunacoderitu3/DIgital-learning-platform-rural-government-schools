<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Audit row for every manual override of a finalized attendance result.
 */
class StaffAttendanceCorrection extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_attendance_id',
        'staff_id',
        'date',
        'old_type',
        'new_type',
        'old_working_minutes',
        'new_working_minutes',
        'reason',
        'note',
        'changed_by',
        'school_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function attendance()
    {
        return $this->belongsTo(StaffAttendance::class, 'staff_attendance_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'staff_id')->withTrashed();
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by')->withTrashed();
    }

    public function scopeOwner($query)
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $query->where('school_id', Auth::user()->school_id);
        }
        return $query;
    }
}
