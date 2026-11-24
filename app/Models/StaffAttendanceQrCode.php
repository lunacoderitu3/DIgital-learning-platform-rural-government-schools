<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * One code per school, created once. Only its end date changes, so a printed
 * poster never has to be replaced.
 */
class StaffAttendanceQrCode extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'token',
        'status',
        'end_date',
        'generated_at',
        'created_by',
        'school_id',
    ];

    protected $casts = [
        'end_date'     => 'date',
        'generated_at' => 'datetime',
    ];

    public function scopeOwner($query)
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $query->where('school_id', Auth::user()->school_id);
        }
        return $query;
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * The end date is inclusive for the whole day, in the school's timezone.
     */
    public function isValidOn(string $date): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }
        if (empty($this->end_date)) {
            return true;
        }
        return $this->end_date->toDateString() >= $date;
    }
}
