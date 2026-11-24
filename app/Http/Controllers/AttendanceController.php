<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\LeaveDetail;
use App\Models\LeaveMaster;
use App\Repositories\Attendance\AttendanceInterface;
use App\Repositories\ClassSection\ClassSectionInterface;
use App\Repositories\Student\StudentInterface;
use App\Services\CachingService;
use App\Services\NotificationService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class AttendanceController extends Controller
{

    private AttendanceInterface $attendance;
    private ClassSectionInterface $classSection;
    private StudentInterface $student;
    private CachingService $cache;

    public function __construct(AttendanceInterface $attendance, ClassSectionInterface $classSection, StudentInterface $student, CachingService $cachingService)
    {
        $this->attendance = $attendance;
        $this->classSection = $classSection;
        $this->student = $student;
        $this->cache = $cachingService;
    }


    public function index()
    {
        ResponseService::noFeatureThenRedirect('Attendance Management');
        ResponseService::noAnyPermissionThenRedirect(['class-teacher', 'attendance-list']);
        $classSections = $this->classSection->builder()->ClassTeacher()->with('class', 'class.stream', 'class.shift', 'section', 'medium')->get();
        $sessionYear = $this->cache->getSessionYear();
        return view('attendance.index', compact('classSections', 'sessionYear'));
    }


    public function view()
    {
        ResponseService::noFeatureThenRedirect('Attendance Management');
        ResponseService::noAnyPermissionThenRedirect(['class-teacher', 'attendance-list']);
        $class_sections = $this->classSection->builder()->ClassTeacher()->with('class', 'class.stream', 'class.shift', 'section', 'medium')->get();
        $sessionYear = $this->cache->getSessionYear();
        return view('attendance.view', compact('class_sections', 'sessionYear'));
    }

    public function store(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Attendance Management');
        ResponseService::noAnyPermissionThenRedirect(['class-teacher', 'attendance-create', 'attendance-edit']);

        $request->validate([
            'class_section_id' => 'required',
            'date'             => 'required|date',
        ]);

        try {
            DB::beginTransaction();

            $attendanceData = [];
            $sessionYear = $this->cache->getDefaultSessionYear();
            $student_ids = [];
            $attendance_date = date('Y-m-d', strtotime($request->date));

            // -------------------------------------------------------------------------
            // STEP 1: Check students marked as Present (type = 1) with Approved Leaves
            // -------------------------------------------------------------------------
            $presentStudentIds = collect($request->attendance_data)
                ->filter(fn($v) => isset($v['type']) && (int)$v['type'] === 1)
                ->pluck('student_id')
                ->toArray();

            if (!empty($presentStudentIds)) {
                $matchingLeaves = Leave::with(['user', 'leave_detail', 'file'])
                    ->whereIn('user_id', $presentStudentIds)
                    ->where('status', 1)
                    ->where('from_date', '<=', $attendance_date)
                    ->where('to_date', '>=', $attendance_date)
                    ->get();

                $leaveConflicts = [];

                foreach ($matchingLeaves as $leave) {
                    $rawFromDate = $leave->getRawOriginal('from_date');
                    $rawToDate   = $leave->getRawOriginal('to_date');

                    if ($rawFromDate === $rawToDate) {
                        foreach ($leave->file as $file) {
                            $file->delete();
                        }
                        $leave->leave_detail()->delete();
                        $leave->delete();
                    } else {
                        $leaveConflicts[] = [
                            'student_name' => $leave->user->full_name ?? 'Student',
                            'from_date'    => $leave->from_date,
                            'to_date'      => $leave->to_date,
                        ];
                    }
                }

                if (!empty($leaveConflicts)) {
                    DB::rollBack();

                    $conflictDetails = [];
                    foreach ($leaveConflicts as $conflict) {
                        $conflictDetails[] = $conflict['student_name'] . ' (' . $conflict['from_date'] . ' to ' . $conflict['to_date'] . ')';
                    }

                    return ResponseService::errorResponse(
                        __('Approved multi-day leave exists for :names. Please update the leave first, then attendance can be updated.', [
                            'names' => implode(', ', $conflictDetails)
                        ])
                    );
                }
            }

            // -------------------------------------------------------------------------
            // STEP 2: Handle students marked as Leave (type = 2) -> Auto-create leave if not exists
            // -------------------------------------------------------------------------

            $leaveStudentIds = collect($request->attendance_data)
                ->filter(fn($v) => isset($v['type']) && (int)$v['type'] === 2)
                ->pluck('student_id')
                ->toArray();

            if (!empty($leaveStudentIds) && empty($request->holiday)) {
                $leaveMaster = LeaveMaster::where('session_year_id', $sessionYear->id)->first();
                $schoolId = Auth::user()->school_id ?? null;

                foreach ($leaveStudentIds as $studentUserId) {
                    $existingLeave = Leave::where('user_id', $studentUserId)
                        ->where('from_date', '<=', $attendance_date)
                        ->where('to_date', '>=', $attendance_date)
                        ->first();

                    if (!$existingLeave) {
                        $leave = Leave::create([
                            'user_id'         => $studentUserId,
                            'added_by'        => Auth::id(),
                            'reason'          => 'Marked as Leave via Attendance',
                            'from_date'       => $attendance_date,
                            'to_date'         => $attendance_date,
                            'status'          => 1,
                            'leave_master_id' => $leaveMaster?->id,
                            'school_id'       => $schoolId,
                        ]);

                        LeaveDetail::create([
                            'leave_id'  => $leave->id,
                            'date'      => $attendance_date,
                            'type'      => 'full_day',
                            'school_id' => $schoolId,
                        ]);
                    }
                }
            }

            // -------------------------------------------------------------------------
            // STEP 3: Upsert attendance records
            // -------------------------------------------------------------------------
            foreach ($request->attendance_data as $value) {
                $data = (object) $value;
                $type = $request->holiday ?? $data->type;

                $attendanceData[] = [
                    'id'               => $data->id,
                    'class_section_id' => $request->class_section_id,
                    'student_id'       => $data->student_id,
                    'session_year_id'  => $sessionYear->id,
                    'type'             => $type,
                    'date'             => $attendance_date,
                ];

                if ($data->type == 0) {
                    $student_ids[] = $data->student_id;
                }
            }

            $this->attendance->upsert(
                $attendanceData,
                ['id'],
                ['class_section_id', 'student_id', 'session_year_id', 'type', 'date']
            );

            DB::commit();

            // -------------------------------------------------------------------------
            // STEP 4: Absent notification (push + WhatsApp) — fires only after a
            // successful commit, and only for students actually saved as Absent (type=0)
            // this run (collected into $student_ids in STEP 3 above).
            // -------------------------------------------------------------------------
            if ($request->absent_notification && !empty($student_ids)) {
                $students = $this->student->builder()
                    ->whereIn('user_id', $student_ids)
                    ->with('user')
                    ->get(['id', 'user_id', 'guardian_id']);

                $date = \Carbon\Carbon::parse($request->date)->format('F jS, Y');
                $title = 'Absent';
                $type = "attendance";

                $classSection = \App\Models\ClassSection::with('class', 'section')->find($request->class_section_id);
                $className = $classSection->full_name ?? '';
                $allPayloads = [];

                foreach ($students as $student) {
                    $recipient = [$student->guardian_id];

                    $childId = $student->id;
                    $childName = trim(($student->user->full_name ?? ''));

                    if ($childName === '') {
                        $childName = "Student #$childId";
                    }

                    $body = "Your child {$childName} is absent on {$date}";

                    $customData = [
                        'child_id'     => $childId,
                        'student_name' => $childName,
                        'class'        => $className,
                        'date'         => $date,
                        'school_id'    => Auth::user()->school_id ?? null,
                    ];

                    $payloads = buildPayloads($recipient, $title, $body, $type, $customData);
                    $allPayloads = array_merge($allPayloads, $payloads);
                }

                sendBulk($allPayloads);

                // Communication Center: WhatsApp absent alerts to the guardians
                $schoolName = app(CachingService::class)->getSchoolSettings('school_name') ?? '';
                foreach ($students as $student) {
                    NotificationService::sendToUsers('attendance_absent', [$student->guardian_id], [
                        'student_name' => trim($student->user->full_name ?? ''),
                        'class'        => $classSection->full_name ?? '',
                        'date'         => $date,
                        'school_name'  => $schoolName,
                    ], Auth::user()->school);
                }
            }

            return ResponseService::successResponse('Data Stored Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Attendance Controller -> Store method");
            return ResponseService::errorResponse();
        }
    }



    public function getAttendanceData(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Attendance Management');

        $date = date('Y-m-d', strtotime($request->date));
        $classSectionId = $request->class_section_id;
        $sessionYear = $this->cache->getDefaultSessionYear();

        // 1. Total active students in this class section
        $totalStudents = $this->student->builder()
            ->where('class_section_id', $classSectionId)
            ->where('session_year_id', $sessionYear->id)
            ->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->count();

        // 2. Count attendance records saved for this class and date
        $attendanceCount = $this->attendance->builder()
            ->where([
                'date'             => $date,
                'class_section_id' => $classSectionId,
                'session_year_id'  => $sessionYear->id,
            ])
            ->count();

        // 3. Check if marked as Holiday (type = 3)
        $isHoliday = $this->attendance->builder()
            ->where([
                'date'             => $date,
                'class_section_id' => $classSectionId,
                'session_year_id'  => $sessionYear->id,
                'type'             => 3,
            ])
            ->exists();


        $attendanceFilled = $isHoliday || ($totalStudents > 0 && $attendanceCount >= $totalStudents);

        return response()->json([
            'type'              => $isHoliday ? 3 : ($attendanceFilled ? 1 : 0),
            'is_holiday'        => $isHoliday,
            'attendance_filled' => $attendanceFilled,
        ]);
    }
    public function show(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Attendance Management');
        ResponseService::noAnyPermissionThenRedirect(['class-teacher', 'attendance-list']);

        $sort = $request->input('sort', 'roll_number');
        $order = $request->input('order', 'ASC');
        $search = $request->input('search');

        $class_section_id = $request->class_section_id;
        $date = date('Y-m-d', strtotime($request->date));
        $sessionYear = $this->cache->getDefaultSessionYear();

        // Roster always comes from Student, so no one drops off the list just because
        // one student already has an attendance/leave row for this date.
        $studentQuery = $this->student->builder()
            ->where('session_year_id', $sessionYear->id)
            ->where('class_section_id', $class_section_id)
            ->with('user')
            ->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->when($search, function ($query) use ($search) {
                $query->where('id', 'LIKE', "%$search%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->whereRaw("concat(users.first_name,' ',users.last_name) LIKE '%" . $search . "%'");
                    });
            });

        $total = $studentQuery->count();
        $studentQuery->orderBy($sort, $order);
        $students = $studentQuery->get();

        // Overlay whatever attendance/leave already exists for this date, keyed by student
        $attendanceTypeMap = $this->attendance->builder()
            ->where(['date' => $date, 'class_section_id' => $class_section_id, 'session_year_id' => $sessionYear->id])
            ->pluck('type', 'student_id');

        $rows = [];
        $no = 1;

        foreach ($students as $row) {
            $rows[] = [
                'id' => null,
                'no' => $no,
                'student_id' => $row->user_id,
                'user_id' => $row->user_id,
                'admission_no' => $row->admission_no ?? '',
                'roll_no' => $row->roll_number ?? '',
                'name' => '<input type="hidden" value="null" name="attendance_data[' . $no . '][id]"><input type="hidden" value="' . $row->user_id . '" name="attendance_data[' . $no . '][student_id]">' . ($row->user->first_name ?? '') . ' ' . ($row->user->last_name ?? ''),
                'type' => $attendanceTypeMap[$row->user_id] ?? null,
            ];
            $no++;
        }

        $bulkData['total'] = $total;
        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }




    public function attendance_show(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Attendance Management');
        ResponseService::noAnyPermissionThenRedirect(['class-teacher', 'attendance-list']);



        $offset = request('offset', 0);
        $limit = request('limit');
        $sort = request('sort', 'student_id');
        $order = request('order', 'ASC');
        $search = request('search');
        $attendanceType = request('attendance_type');

        $class_section_id = request('class_section_id');
        $date = date('Y-m-d', strtotime(request('date')));

        $validator = Validator::make($request->all(), ['class_section_id' => 'required', 'date' => 'required',]);
        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }

        $sessionYear = $this->cache->getSessionYear();

        $sql = $this->attendance->builder()->where(['date' => $date, 'class_section_id' => $class_section_id, 'session_year_id' => $sessionYear->id])->with('user.student')
            ->where(function ($query) use ($search) {
                $query->when($search, function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('id', 'LIKE', "%$search%")
                            ->orwhere('student_id', 'LIKE', "%$search%")
                            ->orWhereHas('user', function ($q) use ($search) {
                                $q->whereRaw("concat(first_name,' ',last_name) LIKE '%" . $search . "%'")
                                    ->orwhere('first_name', 'LIKE', "%$search%")
                                    ->orwhere('last_name', 'LIKE', "%$search%");
                            })->orWhereHas('user.student', function ($q) use ($search) {
                                $q->where('admission_no', 'LIKE', "%$search%")
                                    ->orwhere('id', 'LIKE', "%$search%")
                                    ->orwhere('user_id', 'LIKE', "%$search%")
                                    ->orwhere('roll_number', 'LIKE', "%$search%");
                            });
                    });
                });
            })
            ->when($attendanceType != null, function ($query) use ($attendanceType) {
                if ($attendanceType == 0) {
                    $query->whereIn('type', [0, 2]);
                } else {
                    $query->where('type', $attendanceType);
                }
                // $query->where('type', $attendanceType);
            });
        $total = $sql->count();

        $sql->orderBy($sort, $order);

        if ($limit) {
            if ($offset >= $total && $total > 0) {
                $lastPage = floor(($total - 1) / $limit) * $limit; // calculate last page offset
                $offset = $lastPage;
            }
            $sql->skip($offset)->take($limit);
        }

        $res = $sql->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;

        foreach ($res as $row) {
            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['type'] = $tempRow['type'] == 2 ? 0 : $tempRow['type'];
            $rows[] = $tempRow;
        }


        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function monthWiseIndex()
    {
        ResponseService::noFeatureThenRedirect('Attendance Management');
        ResponseService::noAnyPermissionThenRedirect(['class-teacher', 'attendance-list']);
        $class_sections = $this->classSection->builder()->ClassTeacher()->with('class', 'class.stream', 'class.shift', 'section', 'medium')->get();
        $months = sessionYearWiseMonthYear();

        return view('attendance.month_wise', compact('class_sections', 'months'));
    }


    public function monthWiseShow(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Attendance Management');
        ResponseService::noAnyPermissionThenRedirect(['class-teacher', 'attendance-list']);

        $sessionYear = $this->cache->getSessionYear();
        $sql = $this->student->builder()->with('user')
            ->where('class_section_id', $request->class_section_id)
            ->where('session_year_id', $sessionYear->id)
            ->orderBy('roll_number', 'ASC');

        $total = $sql->count();
        $res = $sql->get();

        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];

        $month = $request->month;
        $date = Carbon::create(null, $month, 1);

        // Fetch all attendance for the given class, session, and month in a single query
        $attendanceRecords = $this->attendance->builder()
            ->where('class_section_id', $request->class_section_id)
            ->where('session_year_id', $sessionYear->id)
            ->whereMonth('date', $month)
            ->get();

        // Map each student's day-wise attendance type
        $attendanceMap = [];
        foreach ($attendanceRecords as $record) {
            $recordDay = (int) date('d', strtotime($record->getRawOriginal('date')));
            // Passes exact type: 1 (Present), 0 (Absent), 2 (Approved Leave), 3 (Holiday)
            $attendanceMap[$record->student_id][$recordDay] = $record->type;
        }

        foreach ($res as $row) {
            $studentAttendance = [
                'full_name'   => $row->user->full_name,
                'roll_number' => $row->roll_number
            ];

            for ($day = 1; $day <= $date->daysInMonth; $day++) {
                // null when no record exists (or if leave was deleted)
                $studentAttendance["day_$day"] = $attendanceMap[$row->user_id][$day] ?? null;
            }
            $rows[] = $studentAttendance;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }
}
