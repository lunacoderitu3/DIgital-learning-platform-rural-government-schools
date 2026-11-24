<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Timetable;
use App\Repositories\SubjectTeacher\SubjectTeacherInterface;
use App\Repositories\Timetable\TimetableInterface;
use App\Services\CachingService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Online Class write APIs for the Staff/Teacher app.
 *
 * An "online class" is an academic timetable Lecture row augmented with the
 * online-class fields (meeting_link, start_date, end_date, repeat, note). The
 * web admin panel (OnlineClassController) manages the same rows.
 *
 * Reads are served by the EXISTING APIs (no new read endpoints were added):
 *  - the list of online classes  -> GET teacher/teacher_timetable
 *    (rows where `meeting_link` is set; status is derived on the client)
 *  - the form's subject options  -> POST teacher/class-detail
 *  - the periods for a subject    -> GET teacher/teacher_timetable
 *    (filtered by subject_teacher_id)
 *
 * Only the create/update/delete write operations are exposed here, because no
 * equivalent existed for the mobile app.
 */
class OnlineClassApiController extends Controller
{
    private TimetableInterface $timetable;
    private SubjectTeacherInterface $subjectTeacher;
    private CachingService $cache;

    public function __construct(
        TimetableInterface $timetable,
        SubjectTeacherInterface $subjectTeacher,
        CachingService $cache
    ) {
        $this->timetable = $timetable;
        $this->subjectTeacher = $subjectTeacher;
        $this->cache = $cache;
    }

    /** School Admin manages every class; a teacher only their own. */
    private function isAdmin(): bool
    {
        return Auth::user()->hasRole('School Admin');
    }

    /**
     * GET teacher/online-classes/subject-classes
     *
     * The combined "Subject & Class" options for the create form — mirrors the
     * web admin `OnlineClassController@index` exactly: subject-teacher mappings
     * that already have a timetable, labelled
     * "Subject - Type — Class Section — Teacher".
     */
    public function subjectClasses()
    {
        ResponseService::noFeatureThenSendJson('Timetable Management');
        try {
            $sessionYearId = $this->cache->getDefaultSessionYear()->id;

            $builder = $this->subjectTeacher->builder()
                ->where('session_year_id', $sessionYearId)
                ->whereHas('timetable')
                ->with([
                    'subject:id,name,type',
                    'teacher:id,first_name,last_name',
                    'class_section.class.stream',
                    'class_section.class.shift',
                    'class_section.section',
                    'class_section.medium',
                ])
                ->CurrentSemesterData();

            if (!$this->isAdmin()) {
                $builder->where('teacher_id', Auth::user()->id);
            }

            $data = $builder->get()->map(function ($st) {
                $subject = $st->subject->name ?? '';
                $type = $st->subject->type ?? '';
                $classSection = optional($st->class_section)->full_name ?? '';
                $teacher = $st->teacher->full_name ?? '';

                // "Subject - Type — Class — Teacher" (matches the web dropdown).
                $label = $subject;
                if ($type !== '') {
                    $label .= ' - ' . $type;
                }
                if ($classSection !== '') {
                    $label .= ' — ' . $classSection;
                }
                if ($teacher !== '') {
                    $label .= ' — ' . $teacher;
                }

                return [
                    'id' => $st->id,
                    'subject_id' => $st->subject_id,
                    'subject' => $subject,
                    'subject_type' => $type,
                    'class_section' => $classSection,
                    'teacher' => $teacher,
                    'label' => $label,
                ];
            })->values();

            ResponseService::successResponse('', $data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "OnlineClassApiController -> subjectClasses");
            ResponseService::errorResponse();
        }
    }

    /**
     * GET teacher/online-classes/periods?subject_teacher_id=
     *
     * The existing timetable periods for the chosen subject-teacher — mirrors
     * the web admin `OnlineClassController@periods` (same payload incl.
     * `has_link`).
     */
    public function periods(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Timetable Management');

        $validator = Validator::make($request->all(), [
            'subject_teacher_id' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $sessionYearId = $this->cache->getDefaultSessionYear()->id;

            $builder = $this->timetable->builder()
                ->where('subject_teacher_id', $request->subject_teacher_id)
                ->where('session_year_id', $sessionYearId)
                ->where('type', 'Lecture')
                ->CurrentSemesterData();

            if (!$this->isAdmin()) {
                $builder->whereHas('subject_teacher', function ($q) {
                    $q->where('teacher_id', Auth::user()->id);
                });
            }

            $periods = $builder
                ->orderByRaw("FIELD(day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')")
                ->orderBy('start_time')
                ->get(['id', 'day', 'start_time', 'end_time', 'meeting_link']);

            $data = $periods->map(function ($period) {
                return [
                    'id' => $period->id,
                    'day' => $period->day,
                    'start_time' => Carbon::parse($period->start_time)->format('h:i A'),
                    'end_time' => Carbon::parse($period->end_time)->format('h:i A'),
                    'has_link' => !empty($period->meeting_link),
                    'status' => $period->status,
                ];
            })->values();

            ResponseService::successResponse('', $data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "OnlineClassApiController -> periods");
            ResponseService::errorResponse();
        }
    }

    /**
     * POST teacher/online-classes
     *
     * Attaches the online-class configuration to the selected timetable
     * period(s). "Every Class Period" covers every slot of the subject; the
     * other repeat types are limited to the selected period_ids.
     */
    public function store(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Timetable Management');

        $validator = Validator::make($request->all(), [
            'subject_teacher_id' => 'required|numeric',
            'repeat' => 'required|in:' . implode(',', Timetable::REPEAT_TYPES),
            'start_date' => 'required_unless:repeat,' . Timetable::REPEAT_NO,
            'end_date' => 'required_unless:repeat,' . Timetable::REPEAT_NO,
            'meeting_link' => 'required|url',
            'period_ids' => 'nullable|array',
            'period_ids.*' => 'numeric',
            'note' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            $sessionYearId = $this->cache->getDefaultSessionYear()->id;

            // A teacher may only configure their own subject-teacher mapping.
            if (!$this->isAdmin()) {
                $owns = $this->subjectTeacher->builder()
                    ->where('id', $request->subject_teacher_id)
                    ->where('teacher_id', Auth::user()->id)
                    ->exists();
                if (!$owns) {
                    ResponseService::errorResponse(trans('permission_denied'));
                }
            }

            [$startDate, $endDate] = $this->resolveDates($request);

            $builder = $this->timetable->builder()
                ->where('subject_teacher_id', $request->subject_teacher_id)
                ->where('session_year_id', $sessionYearId)
                ->where('type', 'Lecture')
                ->CurrentSemesterData();

            if ($request->repeat !== Timetable::REPEAT_EVERY_CLASS_PERIOD) {
                if (empty($request->period_ids)) {
                    ResponseService::errorResponse(trans('select_at_least_one_period'));
                }
                $builder->whereIn('id', $request->period_ids);
            }

            $timetables = $builder->get();
            if ($timetables->isEmpty()) {
                ResponseService::errorResponse(trans('no_periods_found'));
            }

            foreach ($timetables as $timetable) {
                $currentStartDate = $startDate;
                if ($request->repeat === Timetable::REPEAT_NO) {
                    $dayName = $timetable->day;
                    $todayName = Carbon::now()->format('l');
                    $currentTime = Carbon::now()->format('H:i:s');

                    if ($dayName === $todayName && $timetable->start_time > $currentTime) {
                        $currentStartDate = Carbon::now()->format('Y-m-d');
                    } else {
                        $currentStartDate = Carbon::parse('next ' . $dayName)->format('Y-m-d');
                    }
                }

                $timetable->update([
                    'meeting_link' => $request->meeting_link,
                    'start_date' => $currentStartDate,
                    'end_date' => $endDate,
                    'repeat' => $request->repeat,
                    'note' => $request->note ?? $timetable->note,
                ]);
            }

            DB::commit();
            ResponseService::successResponse('Online Class Created Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "OnlineClassApiController -> store");
            ResponseService::errorResponse();
        }
    }

    /**
     * POST teacher/online-classes/update
     *
     * Updates a single online-class row's schedule fields.
     */
    public function update(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Timetable Management');

        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric',
            'repeat' => 'required|in:' . implode(',', Timetable::REPEAT_TYPES),
            'start_date' => 'required_unless:repeat,' . Timetable::REPEAT_NO,
            'end_date' => 'required_unless:repeat,' . Timetable::REPEAT_NO,
            'meeting_link' => 'required|url',
            'note' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $timetable = $this->findOwnedOrFail($request->id);

            [$startDate, $endDate] = $this->resolveDates($request);

            if ($request->repeat === Timetable::REPEAT_NO) {
                $dayName = $timetable->day;
                $todayName = Carbon::now()->format('l');
                $currentTime = Carbon::now()->format('H:i:s');

                if ($dayName === $todayName && $timetable->start_time > $currentTime) {
                    $startDate = Carbon::now()->format('Y-m-d');
                } else {
                    $startDate = Carbon::parse('next ' . $dayName)->format('Y-m-d');
                }
            }

            $timetable->update([
                'meeting_link' => $request->meeting_link,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'repeat' => $request->repeat,
                'note' => $request->note,
            ]);

            ResponseService::successResponse('Online Class Updated Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "OnlineClassApiController -> update");
            ResponseService::errorResponse();
        }
    }

    /**
     * POST teacher/online-classes/delete
     *
     * Clears only the online-class fields so the underlying academic lecture
     * row stays intact.
     */
    public function destroy(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Timetable Management');

        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $timetable = $this->findOwnedOrFail($request->id);

            $timetable->update([
                'meeting_link' => null,
                'start_date' => null,
                'end_date' => null,
                'repeat' => null,
            ]);

            ResponseService::successResponse('Online Class Deleted Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "OnlineClassApiController -> destroy");
            ResponseService::errorResponse();
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Finds the timetable row, enforcing teacher ownership when applicable. */
    private function findOwnedOrFail($id): Timetable
    {
        $timetable = $this->timetable->findById($id);

        if (!$this->isAdmin()) {
            $owns = $this->subjectTeacher->builder()
                ->where('id', $timetable->subject_teacher_id)
                ->where('teacher_id', Auth::user()->id)
                ->exists();
            if (!$owns) {
                ResponseService::errorResponse(trans('permission_denied'));
            }
        }

        return $timetable;
    }

    /**
     * Normalises the posted dates (dd-mm-yyyy) to Y-m-d for storage and
     * validates the range. Returns [startDate, endDate|null].
     */
    private function resolveDates(Request $request): array
    {
        $startDate = null;
        $endDate = null;

        if ($request->repeat !== Timetable::REPEAT_NO) {
            $startDate = Carbon::createFromFormat('d-m-Y', $request->start_date)->format('Y-m-d');
            $endDate = Carbon::createFromFormat('d-m-Y', $request->end_date)->format('Y-m-d');
            if ($endDate < $startDate) {
                ResponseService::errorResponse(trans('end_date_must_be_greater_than_or_equal_to_start_date'));
            }
        }

        return [$startDate, $endDate];
    }
}
