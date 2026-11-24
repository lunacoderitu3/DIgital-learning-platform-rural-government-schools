<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Traits\DateFormatTrait;
use App\Services\CachingService;

class StaffAttendance extends Model
{
    use HasFactory, DateFormatTrait;

    protected $hidden = ["remark"];
    /** type column values */
    public const TYPE_ABSENT   = 0;
    public const TYPE_PRESENT  = 1;
    public const TYPE_HALF_DAY = 2;
    public const TYPE_HOLIDAY  = 3;
    public const TYPE_FIRST_HALF_PRESENT = 4;
    public const TYPE_SECOND_HALF_PRESENT = 5;

    /** source column values */
    public const SOURCE_QR     = 'QR';
    public const SOURCE_MANUAL = 'MANUAL';

    /** calculation_mode column values */
    public const MODE_SHIFT_BASED = 'SHIFT_BASED';
    public const MODE_DAY_BASED   = 'DAY_BASED';

    protected $fillable = [
        'staff_id',
        'session_year_id',
        'type',
        'date',
        'reason',
        'remark',
        'school_id',
        'leave_id',
        'leave_detail_id',
        'working_minutes',
        'completed_sessions',
        'incomplete_sessions',
        'shift_id',
        'shift_name',
        'shift_start_time',
        'shift_end_time',
        'full_day_minutes',
        'half_day_minutes',
        'calculation_mode',
        'source',
        'finalized_at',
    ];

    protected $appends = ['get_date_original', 'date_format'];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'staff_id')->withTrashed();
    }

    /**
     * The shift the row was calculated against. The snapshot columns on this
     * table are what reports should read — the shift itself may have been
     * edited since finalization.
     */
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function corrections()
    {
        return $this->hasMany(StaffAttendanceCorrection::class, 'staff_attendance_id');
    }

    /**
     * Raw punches behind this row. Cleared by the monthly retention job, so a
     * finalized row can legitimately have none.
     */
    public function punches()
    {
        return $this->hasMany(StaffAttendancePunch::class, 'staff_id', 'staff_id')
            ->whereColumn('staff_attendance_punches.date', 'staff_attendances.date')
            ->orderBy('punched_at');
    }

    public function scopeOwner($query)
    {
        if(Auth::user()) {
            if (Auth::user()->hasRole('Super Admin')) {
                return $query;
            }

            if (Auth::user()->hasRole('School Admin') || Auth::user()->hasRole('Teacher')) {
                $sessionYearId = app(CachingService::class)->getSessionYear()->id;
                return $query->where(['school_id' => Auth::user()->school_id, 'session_year_id' => $sessionYearId]);
            }

            if (Auth::user()->hasRole('Staff')) {
                $sessionYearId = app(CachingService::class)->getSessionYear()->id;
                return $query->where(['school_id' => Auth::user()->school_id, 'session_year_id' => $sessionYearId]);
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

    public function getDateAttribute($value)
    {
        return $this->formatDateOnly($value);
    }

    public function getDateFormatAttribute()
    {
        $cache = app(CachingService::class);
        return $cache->getSchoolSettings('date_format');
    }

    public function getGetDateOriginalAttribute()
    {
        return $this->getRawOriginal('date');
    }
} 