<?php

namespace App\Models;

use App\Repositories\Semester\SemesterInterface;
use App\Services\CachingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Traits\DateFormatTrait;
use Carbon\Carbon;

class Timetable extends Model
{
    use DateFormatTrait;

    /** Online class repeat types */
    public const REPEAT_NO = 'No Repeat';
    public const REPEAT_EVERY_CLASS_PERIOD = 'Every Class Period';
    public const REPEAT_WEEKLY = 'Weekly';

    public const REPEAT_TYPES = [
        self::REPEAT_NO,
        self::REPEAT_EVERY_CLASS_PERIOD,
        self::REPEAT_WEEKLY,
    ];

    protected $fillable = [
        "subject_teacher_id",
        "class_section_id",
        "subject_id",
        "start_time",
        "end_time",
        "note",
        "day",
        "type",
        "semester_id",
        "school_id",
        "session_year_id",
        // Online class fields (extends timetable into an online class)
        "start_date",
        "end_date",
        "repeat",
        "meeting_link"
    ];

    protected $appends = ['title', 'status'];
    protected $hidden = ['created_at', 'updated_at'];


    public function subject_teacher()
    {
        return $this->belongsTo(SubjectTeacher::class);
    }

    public function class_section()
    {
        return $this->belongsTo(ClassSection::class)->withTrashed();
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class)->withTrashed();
    }

    public function teacher()
    {
        return $this->hasOneThrough(User::class, SubjectTeacher::class, 'id', 'id', 'subject_teacher_id', 'teacher_id')->withTrashed();
    }

    public function scopeOwner($query)
    {
        if (Auth::user()) {
            $user = Auth::user();
            $sessionYearId = app(CachingService::class)->getSessionYear()->id;
            if ($user->hasRole('School Admin')) {
                return $query->where(['school_id' => $user->school_id, 'session_year_id' => $sessionYearId]);
            }

            if (Auth::user()->hasRole('Student')) {
                return $query->where(['school_id' => Auth::user()->school_id, 'session_year_id' => $sessionYearId]);
            }
            if (Auth::user()->school_id) {
                return $query->where(['school_id' => $user->school_id, 'session_year_id' => $sessionYearId]);
            }
        }
        return $query;
    }

    public function getTitleAttribute()
    {
        if ($this->type === "Lecture") {
            if ($this->relationLoaded('subject') && $this->relationLoaded('teacher')) {
                if (!isset($this->subject->name) && !isset($this->teacher->full_name)) {
                    return $this->note;
                }
                $teacherName = $this->teacher->full_name ?? '';
                return $this->subject->name . ' ( ' . $this->subject->type . ' ) ' . ' - ' . $teacherName;
            }

            if ($this->relationLoaded('subject')) {
                return $this->subject->name . ' ( ' . $this->subject->type . ' ) ';
            }

            return $this->note;
        }

        if ($this->type === "Break") {
            return trans("Break");
        }
        return $this->note;
    }

    public function getStatusAttribute()
    {
        $today = Carbon::now()->format('l');

        if ($this->day !== $today) {
            return 1;
        }

        $currentTime = Carbon::now()->format('H:i:s');
        if ($this->start_time > $currentTime) {
            return 1;
        }

        return 0;
    }

    /** Only timetable rows that have been configured as an online class. */
    public function scopeOnlyOnlineClasses($query)
    {
        return $query->whereNotNull('meeting_link');
    }

    public function scopeCurrentSemesterData($query)
    {
        // $currentSemester = app(SemesterInterface::class)->default();
        $currentSemester = app(CachingService::class)->getSemester();
        if ($currentSemester) {
            $query->where(function ($query) use ($currentSemester) {
                $query->where('timetables.semester_id', $currentSemester->id)->orWhereNull('timetables.semester_id');
            });
        }
    }
}
