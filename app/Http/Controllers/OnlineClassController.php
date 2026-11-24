<?php

namespace App\Http\Controllers;

use App\Models\Timetable;
use App\Repositories\ClassSchool\ClassSchoolInterface;
use App\Repositories\Subject\SubjectInterface;
use App\Repositories\SubjectTeacher\SubjectTeacherInterface;
use App\Repositories\Timetable\TimetableInterface;
use App\Services\BootstrapTableService;
use App\Services\CachingService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class OnlineClassController extends Controller
{
    private TimetableInterface $timetable;
    private SubjectTeacherInterface $subjectTeacher;
    private ClassSchoolInterface $class;
    private SubjectInterface $subject;
    private CachingService $cache;

    public function __construct(TimetableInterface $timetable, SubjectTeacherInterface $subjectTeacher, ClassSchoolInterface $class, SubjectInterface $subject, CachingService $cache)
    {
        $this->timetable = $timetable;
        $this->subjectTeacher = $subjectTeacher;
        $this->class = $class;
        $this->subject = $subject;
        $this->cache = $cache;
    }

    public function index()
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');
        ResponseService::noPermissionThenRedirect('timetable-list');

        $sessionYearId = $this->cache->getSessionYear()->id;

        // Subject & Class options : only subject-teacher mappings that already have timetable slots,
        // because an online class is attached to an existing timetable period.
        $subjectTeachers = $this->subjectTeacher->builder()
            ->where('session_year_id', $sessionYearId)
            ->whereHas('timetable')
            ->with([
                'subject:id,name,type',
                'teacher:id,first_name,last_name',
                'class_section.class',
                'class_section.section',
                'class_section.medium',
            ])
            ->CurrentSemesterData()
            ->get();

        $classes = $this->class->builder()->with('stream', 'shift', 'medium')->get()->pluck('full_name', 'id');
        $subjects = $this->subject->builder()->pluck('name', 'id');
        $repeatTypes = Timetable::REPEAT_TYPES;

        return view('online-class.index', compact('subjectTeachers', 'classes', 'subjects', 'repeatTypes'));
    }

    /**
     * Return the existing timetable periods for a subject-teacher mapping.
     * Used to populate the "Which period(s)?" checkboxes on the create form.
     */
    public function periods(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Timetable Management');
        ResponseService::noPermissionThenSendJson('timetable-list');

        $request->validate(['subject_teacher_id' => 'required|numeric']);

        $sessionYearId = $this->cache->getSessionYear()->id;

        $periods = $this->timetable->builder()
            ->where('subject_teacher_id', $request->subject_teacher_id)
            ->where('session_year_id', $sessionYearId)
            ->where('type', 'Lecture')
            ->CurrentSemesterData()
            ->orderByRaw("FIELD(day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')")
            ->orderBy('start_time')
            ->get(['id', 'day', 'start_time', 'end_time', 'meeting_link']);

        $data = $periods->map(function ($period) {
            return [
                'id'         => $period->id,
                'day'        => $period->day,
                'start_time' => Carbon::parse($period->start_time)->format('h:i A'),
                'end_time'   => Carbon::parse($period->end_time)->format('h:i A'),
                'has_link'   => !empty($period->meeting_link),
                'status'     => $period->status,
            ];
        });

        ResponseService::successResponse('', $data);
    }

    /**
     * Get subjects based on the selected class section for filtering online classes.
     */
    public function getSubjects(Request $request)
    {
        $classSectionId = $request->class_section_id;

        $sessionYearId = $this->cache->getSessionYear()->id;

        $query = $this->subjectTeacher->builder()
            ->where('session_year_id', $sessionYearId)
            ->whereHas('timetable')
            // ->CurrentSemesterData()
            ->with('subject:id,name,type');

        if (!empty($classSectionId)) {
            // $query->where('class_section_id', $classSectionId);
            $query = $query->whereHas('class_section', function ($q) use ($classSectionId) {
                $q->where('class_id', $classSectionId);
            });
        }

        $subjects = $query->get()
            ->pluck('subject')
            ->unique('id')
            ->values();

        return response()->json($subjects);
    }

    public function store(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');

        $request->validate([
            'subject_teacher_id' => 'required|numeric',
            'repeat'             => 'required|in:' . implode(',', Timetable::REPEAT_TYPES),
            'start_date'         => 'required_unless:repeat,' . Timetable::REPEAT_NO,
            'end_date'           => 'required_unless:repeat,' . Timetable::REPEAT_NO,
            'meeting_link'       => 'required|url',
            'period_ids'         => 'nullable|array',
            'period_ids.*'       => 'numeric',
            'note'               => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $sessionYearId = $this->cache->getSessionYear()->id;

            // Datepicker posts dd-mm-yyyy : normalise to Y-m-d for storage.
            $startDate = null;
            $endDate = null;
            if ($request->repeat !== Timetable::REPEAT_NO) {
                $startDate = Carbon::createFromFormat('d-m-Y', $request->start_date)->format('Y-m-d');
                $endDate = Carbon::createFromFormat('d-m-Y', $request->end_date)->format('Y-m-d');
                if ($endDate < $startDate) {
                    ResponseService::errorResponse(__('end_date_must_be_greater_than_or_equal_to_start_date'));
                }
            }

            // Resolve the timetable slots this online class will be attached to.
            $query = $this->timetable->builder()
                ->where('subject_teacher_id', $request->subject_teacher_id)
                ->where('session_year_id', $sessionYearId)
                ->where('type', 'Lecture')
                ->CurrentSemesterData();

            // "Every Class Period" covers every existing slot of the subject; the other
            // repeat types are limited to the period(s) the user selected.
            if ($request->repeat !== Timetable::REPEAT_EVERY_CLASS_PERIOD) {
                if (empty($request->period_ids)) {
                    ResponseService::errorResponse(__('select_at_least_one_period'));
                }
                $query->whereIn('id', $request->period_ids);
            }

            $timetables = $query->get();

            if ($timetables->isEmpty()) {
                ResponseService::errorResponse(__('no_periods_found'));
            }

            // Augment each existing timetable row with the online class configuration.
            // Re-submitting the same combination simply overwrites it (no duplicate rows).
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
                    'start_date'   => $currentStartDate,
                    'end_date'     => $endDate,
                    'repeat'       => $request->repeat,
                    'note'         => $request->note ?? $timetable->note,
                ]);
            }

            DB::commit();
            ResponseService::successResponse('Data Stored Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "OnlineClassController -> store");
            ResponseService::errorResponse();
        }
    }

    public function show(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');

        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $sessionYearId = $this->cache->getSessionYear()->id;

        $sql = $this->timetable->builder()
            ->OnlyOnlineClasses()
            ->where('session_year_id', $sessionYearId)
            // ->CurrentSemesterData()
            ->with([
                'subject:id,name,type',
                'class_section.class',
                'class_section.class.stream',
                'class_section.class.shift',
                'class_section.section',
                'class_section.medium',
                'teacher:users.id,first_name,last_name',
            ]);

        if (!empty($request->search)) {
            $search = $request->search;
            $sql->where(function ($query) use ($search) {
                $query->where('meeting_link', 'LIKE', "%$search%")
                    ->orWhere('repeat', 'LIKE', "%$search%")
                    ->orWhereHas('subject', function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%$search%");
                    });
            });
        }

        if (!empty($request->class_section_id)) {
            $sql->whereHas('class_section', function ($q) use ($request) {
                $q->where('class_id', $request->class_section_id);
            });
        }

        if (!empty($request->subject_id)) {
            $sql->where('subject_id', $request->subject_id);
        }

        if (!empty($request->repeat)) {
            $sql->where('repeat', $request->repeat);
        }

        $total = $sql->count();
        if ($offset >= $total && $total > 0) {
            $offset = floor(($total - 1) / $limit) * $limit;
        }
        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $rows = array();
        $no = $offset + 1;
        foreach ($res as $row) {
            $operate = BootstrapTableService::editButton(url('online-class/' . $row->id), true);
            $operate .= BootstrapTableService::deleteButton(url('online-class/' . $row->id));

            $tempRow = [];
            $tempRow['id'] = $row->id;
            $tempRow['no'] = $no++;
            $tempRow['subject'] = $row->subject ? $row->subject->name . ' - ' . $row->subject->type : '';
            $tempRow['class_section'] = $row->class_section ? $row->class_section->full_name : '';
            $tempRow['day'] = $row->day;
            $tempRow['period'] = Carbon::parse($row->start_time)->format('h:i A') . ' - ' . Carbon::parse($row->end_time)->format('h:i A');
            $tempRow['repeat'] = $row->repeat;
            $tempRow['meeting_link'] = $row->meeting_link;
            $tempRow['note'] = $row->note;
            // Displayed (formatted) dates
            $tempRow['start_date'] = $row->start_date ? Carbon::parse($row->start_date)->format('d M Y') : '';
            $tempRow['end_date'] = $row->end_date ? Carbon::parse($row->end_date)->format('d M Y') : '';
            // Raw dates (Y-m-d) for the edit datepicker
            $tempRow['start_date_raw'] = $row->start_date ? Carbon::parse($row->start_date)->format('Y-m-d') : '';
            $tempRow['end_date_raw'] = $row->end_date ? Carbon::parse($row->end_date)->format('Y-m-d') : '';
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        return response()->json(['total' => $total, 'rows' => $rows]);
    }

    public function update(Request $request, $id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');

        $request->validate([
            'repeat'       => 'required|in:' . implode(',', Timetable::REPEAT_TYPES),
            'start_date'   => 'required',
            'end_date'     => 'required_unless:repeat,' . Timetable::REPEAT_NO,
            'meeting_link' => 'required|url',
            'note'         => 'nullable|string',
        ]);

        try {
            // Datepicker posts dd-mm-yyyy : normalise to Y-m-d for storage.
            $startDate = Carbon::createFromFormat('d-m-Y', $request->start_date)->format('Y-m-d');
            $endDate = null;
            if ($request->repeat !== Timetable::REPEAT_NO) {
                $endDate = Carbon::createFromFormat('d-m-Y', $request->end_date)->format('Y-m-d');
                if ($endDate < $startDate) {
                    ResponseService::errorResponse(__('end_date_must_be_greater_than_or_equal_to_start_date'));
                }
            }

            $this->timetable->update($id, [
                'meeting_link' => $request->meeting_link,
                'start_date'   => $startDate,
                'end_date'     => $endDate,
                'repeat'       => $request->repeat,
                'note'         => $request->note,
            ]);

            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "OnlineClassController -> update");
            ResponseService::errorResponse();
        }
    }

    /**
     * "Delete" an online class. We only clear the online-class fields so the
     * underlying academic timetable lecture row stays intact.
     */
    public function destroy($id)
    {
        ResponseService::noFeatureThenRedirect('Timetable Management');

        try {
            $timetable = $this->timetable->findById($id);
            $timetable->update([
                'meeting_link' => null,
                'start_date'   => null,
                'end_date'     => null,
                'repeat'       => null,
            ]);

            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "OnlineClassController -> destroy");
            ResponseService::errorResponse();
        }
    }
}
