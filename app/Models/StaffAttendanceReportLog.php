<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Record of an attendance report already mailed for a school on a date.
 *
 * Finalization runs every minute, so the report job needs a durable answer to
 * "has this already gone out today?". The unique index on
 * (school_id, date, report_type) is what actually enforces once-per-day; the
 * queries here are the fast path in front of it. Nothing has to be reset — the
 * next date simply has no row.
 */
class StaffAttendanceReportLog extends Model
{
    use HasFactory;

    public const TYPE_MISSING_PUNCH_OUT = 'missing_punch_out';

    /**
     * A claim older than this with nothing delivered belongs to a worker that
     * died mid-send. It is taken over rather than blocking the day's report
     * forever.
     */
    public const STALE_CLAIM_MINUTES = 30;

    protected $fillable = [
        'school_id',
        'date',
        'report_type',
        'staff_count',
        'recipients',
        'sent_at',
    ];

    protected $casts = [
        'date'        => 'date',
        'sent_at'     => 'datetime',
        'staff_count' => 'integer',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function scopeForReport($query, int $schoolId, string $date, string $type = self::TYPE_MISSING_PUNCH_OUT)
    {
        return $query->where('school_id', $schoolId)
            ->whereDate('date', $date)
            ->where('report_type', $type);
    }

    /**
     * Whether the report is already delivered, or claimed by a worker that is
     * delivering it right now. Either way a second send must not start.
     *
     * An abandoned claim — no sent_at, older than the stale window — reports
     * false, so a report lost to a killed worker is still delivered.
     */
    public static function alreadyHandled(int $schoolId, string $date, string $type = self::TYPE_MISSING_PUNCH_OUT): bool
    {
        return static::query()
            ->forReport($schoolId, $date, $type)
            ->where(function ($query) {
                $query->whereNotNull('sent_at')
                    ->orWhere('created_at', '>=', now()->subMinutes(self::STALE_CLAIM_MINUTES));
            })
            ->exists();
    }
}
