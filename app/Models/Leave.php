<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Traits\DateFormatTrait;

class Leave extends Model
{
    use HasFactory, DateFormatTrait;

    protected $fillable = ['user_id', 'reason', 'from_date', 'to_date', 'status', 'school_id', 'leave_master_id', 'added_by', 'note'];

    protected $appends = ['org_from_date', 'org_to_date'];

    public function scopeOwner()
    {
        if (Auth::user()) {
            return $this->where('school_id', Auth::user()->school_id);
        }
    }

    /**
     * Get the user that owns the Leave
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
    public function added_by_user()
    {
        return $this->belongsTo(User::class, 'added_by')->withTrashed();
    }

    /**
     * Get all of the leave_detail for the Leave
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function leave_detail()
    {
        return $this->hasMany(LeaveDetail::class);
    }

    public function attendance()
    {
        return $this->hasMany(StaffAttendance::class, 'leave_id');
    }

    public function getCreatedAtAttribute()
    {
        return $this->formatDateValue($this->getRawOriginal('created_at'));
    }

    /**
     * Get the leave_master that owns the Leave
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function leave_master()
    {
        return $this->belongsTo(LeaveMaster::class);
    }

    public function file()
    {
        return $this->morphMany(File::class, 'modal');
    }

    public function getUpdatedAtAttribute()
    {
        return $this->formatDateValue($this->getRawOriginal('updated_at'));
    }

    public function getFromDateAttribute($value)
    {
        return $this->formatDateOnly($value);
    }

    public function getToDateAttribute($value)
    {
        return $this->formatDateOnly($value);
    }

    public function getOrgFromDateAttribute()
    {
        return $this->getRawOriginal('from_date');
    }

    public function getOrgToDateAttribute()
    {
        return $this->getRawOriginal('to_date');
    }
}
