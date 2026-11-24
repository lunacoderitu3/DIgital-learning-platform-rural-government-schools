<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use App\Traits\DateFormatTrait;

class Shift extends Model {
    use HasFactory, SoftDeletes, DateFormatTrait;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'full_day_minutes',
        'half_day_minutes',
        'status',
        'school_id'
    ];

    public function scopeOwner($query) {
        if (Auth::user()) {

            if (Auth::user()->school_id) {
                if (Auth::user()->hasRole('School Admin')) {
                    return $query->where('school_id', Auth::user()->school_id);
                }
        
                if (Auth::user()->hasRole('Teacher')) {
                    return $query->where('school_id', Auth::user()->school_id);
                }
        
                if (Auth::user()->hasRole('Student')) {
                    return $query->where('school_id', Auth::user()->school_id);
                }
                return $query->where('school_id', Auth::user()->school_id);
            }
    
            if (!Auth::user()->school_id) {
                if (Auth::user()->hasRole('Super Admin')) {
                    return $query;
                }
                return $query;
            }
        }

        return $query;
    }

    public function getCreatedAtAttribute()
    {
        return $this->formatDateValue($this->getRawOriginal('created_at'));
    }

    public function getUpdatedAtAttribute()
    {
        return $this->formatDateValue($this->getRawOriginal('updated_at'));
    }

    public function getStartTimeAttribute($value)
    {
        return $this->formatTimeOnly($value);
    }

    public function getEndTimeAttribute($value)
    {
        return $this->formatTimeOnly($value);
    }

    /**
     * Get all routes for this shift.
     */
    public function routes()
    {
        return $this->hasMany(Route::class);
    }

    /**
     * Staff currently or previously assigned to this shift.
     */
    public function staffShiftAssignments()
    {
        return $this->hasMany(StaffShiftAssignment::class);
    }

    /*
     * start_time / end_time are display-formatted by the accessors above.
     * Attendance arithmetic must use the stored values, never the formatted ones.
     */
    public function rawStartTime(): ?string
    {
        return $this->getRawOriginal('start_time');
    }

    public function rawEndTime(): ?string
    {
        return $this->getRawOriginal('end_time');
    }

    /**
     * Length of the shift window in whole minutes. Overnight shifts are not
     * supported, so a non-positive result means the shift is misconfigured.
     */
    public function durationMinutes(): int
    {
        $start = $this->rawStartTime();
        $end   = $this->rawEndTime();

        if (empty($start) || empty($end)) {
            return 0;
        }

        return max(0, (int) round((strtotime($end) - strtotime($start)) / 60));
    }
}
