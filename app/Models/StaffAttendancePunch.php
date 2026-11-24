<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * A raw scan event. A punch is not an attendance result — Present / Half day /
 * Absent belongs to staff_attendances. These rows are never rewritten by a
 * manual correction.
 */
class StaffAttendancePunch extends Model
{
    use HasFactory;

    public const TYPE_IN  = 'IN';
    public const TYPE_OUT = 'OUT';

    protected $fillable = [
        'staff_id',
        'date',
        'type',
        'punched_at',
        'latitude',
        'longitude',
        'distance',
        'qr_code_id',
        'request_uuid',
        'source',
        'school_id',
    ];

    protected $casts = [
        'date'       => 'date',
        'punched_at' => 'datetime',
        'latitude'   => 'float',
        'longitude'  => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'staff_id')->withTrashed();
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'user_id');
    }

    public function qrCode()
    {
        return $this->belongsTo(StaffAttendanceQrCode::class, 'qr_code_id');
    }

    public function scopeOwner($query)
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $query->where('school_id', Auth::user()->school_id);
        }
        return $query;
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('date', $date);
    }
}
