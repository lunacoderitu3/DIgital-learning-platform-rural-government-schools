<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * One staff member holds one shift at a time. A shift change closes the current
 * row and opens a new one, so a finalized day always knows which shift applied.
 *
 * staff_id references users.id, matching staff_attendances.
 */
class StaffShiftAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'shift_id',
        'effective_from',
        'effective_to',
        'school_id',
        'created_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
    ];

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'user_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'staff_id')->withTrashed();
    }

    public function scopeOwner($query)
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $query->where('school_id', Auth::user()->school_id);
        }
        return $query;
    }

    /**
     * The assignment in force on a given date.
     */
    public function scopeOnDate($query, string $date)
    {
        return $query->whereDate('effective_from', '<=', $date)
            ->where(static function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            });
    }
}
