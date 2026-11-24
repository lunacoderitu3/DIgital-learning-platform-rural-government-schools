<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Traits\DateFormatTrait;
use App\Services\CachingService;

class LessonCommon extends Model
{
    use HasFactory, DateFormatTrait;

    protected $fillable = [
        'lesson_id',
        'class_section_id',
        'syllabus_id'
    ];

    protected $appends = ['class_section_with_medium', 'subject_with_name', 'semester_id'];

    public function scopeOwner($query)
    {
        if (Auth::user()) {
            if (Auth::user()->hasRole('Super Admin')) {
                return $query;
            }

            if (Auth::user()->hasRole('School Admin') || Auth::user()->hasRole('Student')) {
                $sessionYearId = app(CachingService::class)->getSessionYear()->id;
                return $query->where('school_id', Auth::user()->school_id)->whereHas('syllabus.class_subject', function ($q) use ($sessionYearId) {
                    $q->where('session_year_id', $sessionYearId);
                });
            }

            if (Auth::user()->hasRole('Teacher')) {
                $teacherId = Auth::user()->id;
                return $query->whereHas('subject_teacher', function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                });
                return $query->where('school_id', Auth::user()->school_id);
            }
        }

        return $query;
    }

    public function subject_teacher()
    {
        return $this->belongsTo(SubjectTeacher::class, 'class_subject_id', 'class_subject_id');
    }

    // public function class_subject()
    // {
    //     return $this->belongsTo(ClassSubject::class);
    // }

    public function class_section()
    {
        return $this->belongsTo(ClassSection::class)->with('class', 'class.shift', 'section', 'medium')->withTrashed();
    }

    public function getClassSectionWithMediumAttribute()
    {
        if ($this->relationLoaded('class_section')) {
            $shiftName = ($this->class_section->class->shift ?? null) ? ' (' . $this->class_section->class->shift->name . ')' : '';
            return $this->class_section->class->name . ' ' . ($this->class_section->section?->name ?? '') . ' - ' . $this->class_section->medium->name . $shiftName;
        }
        return null;
    }

    public function getSubjectWithNameAttribute()
    {
        if ($this->relationLoaded('syllabus.class_subject')) {
            if ($this->syllabus->class_subject) {
                return $this->syllabus->class_subject->subject->name . ' - ' . $this->syllabus->class_subject->subject->type;
            }
        }
        return null;
    }

    public function getSemesterIdAttribute()
    {
        $sessionYear = app(CachingService::class)->getSessionYear();
        $sessionYearId = $sessionYear ? $sessionYear->id : null;

        if ($this->relationLoaded('syllabus') && $this->syllabus && $this->syllabus->relationLoaded('class_subject') && $this->relationLoaded('class_section') && $this->class_section) {
            $classId = $this->class_section->class_id;
            $classSubject = $this->syllabus->class_subject->first(function ($cs) use ($classId, $sessionYearId) {
                return $cs->class_id == $classId && (!$sessionYearId || $cs->session_year_id == $sessionYearId);
            });
            if ($classSubject) {
                return $classSubject->semester_id;
            }
        }

        // Fallback
        $classSection = $this->class_section;
        if ($classSection && $this->syllabus_id) {
            $query = ClassSubject::where('class_id', $classSection->class_id)
                ->where('syllabus_id', $this->syllabus_id);
            if ($sessionYearId) {
                $query->where('session_year_id', $sessionYearId);
            }
            $classSubject = $query->first();
            if ($classSubject) {
                return $classSubject->semester_id;
            }
        }
        return null;
    }

    public function getCreatedAtAttribute()
    {
        return $this->formatDateValue($this->getRawOriginal('created_at'));
    }

    public function getUpdatedAtAttribute()
    {
        return $this->formatDateValue($this->getRawOriginal('updated_at'));
    }

    /**
     * Get the syllabus that owns the LessonCommon
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function syllabus()
    {
        return $this->belongsTo(Syllabus::class, 'syllabus_id', 'id');
    }
}
