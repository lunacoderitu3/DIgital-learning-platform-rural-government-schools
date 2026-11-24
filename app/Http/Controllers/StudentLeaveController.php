<?php

namespace App\Http\Controllers;

use App\Models\ClassSection;
use App\Models\Leave;
use App\Services\CachingService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use App\Models\Students;
use App\Services\BootstrapTableService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\LeaveDetail;
use App\Models\Attendance;
use App\Models\LeaveMaster;
use App\Services\NotificationService;
use Carbon\CarbonPeriod;
use Carbon\Carbon;
use Throwable;

class StudentLeaveController extends Controller
{
    private CachingService $cache;

    public function __construct(CachingService $cache)
    {
        $this->cache = $cache;
    }

    public function index()
    {
        ResponseService::noFeatureThenRedirect('Student Leave Management');
        ResponseService::noPermissionThenRedirect('student-leave-list');

        $session_year_id = $this->cache->getSessionYear()->id;

        if (Auth::user()->hasRole('Teacher')) {

            // Teacher only sees their assigned Class Sections for the current session year
            $class_sections = ClassSection::whereHas('class_teachers', function ($query) use ($session_year_id) {
                $query->where('teacher_id', Auth::user()->id)
                    ->where('session_year_id', $session_year_id);
            })->with('class.stream', 'section', 'medium')->get();

            // Teacher sees students from their assigned sections for the current session year
            $students = Students::with('user', 'class_section.class', 'class_section.section')
                ->where('session_year_id', $session_year_id) // Filter by current session year
                ->whereIn('class_section_id', $class_sections->pluck('id'))
                ->get();
        } else {
            // School Admin sees ALL class sections and current session year students
            $class_sections = ClassSection::with('class.stream', 'section', 'medium')->get();

            $students = Students::with('user', 'class_section.class', 'class_section.section')
                ->where('session_year_id', $session_year_id) // Filter by current session year
                ->get();
        }

        return view('Student-leave.index', compact('class_sections', 'students'));
    }



    public function show(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Student Leave Management');
        ResponseService::noPermissionThenSendJson('student-leave-list');

        $offset = $request->input('offset', 0);
        $limit  = $request->input('limit', 10);
        $sort   = $request->input('sort', 'id');
        $order  = $request->input('order', 'DESC');
        $search = $request->input('search');

        $session_year_id     = $this->cache->getSessionYear()->id;

        // Eager load user (student) and added_by_user (applicant)
        $sql = Leave::with([
            'user.student.class_section.class.stream',
            'user.student.class_section.medium',
            'user.student.class_section.section',
            'added_by_user',
            'leave_detail',
            'file'
        ])
            ->whereHas('user', function ($q) {
                $q->has('student');
            })
            ->whereHas('leave_master', function ($q) use ($session_year_id) {
                $q->where('session_year_id', $session_year_id);
            });

        // Role-based Access Rules
        if (Auth::user()->hasRole('Teacher')) {

            $teacherClassSectionIds = ClassSection::whereHas('class_teachers', function ($query) use ($session_year_id) {
                $query->where('teacher_id', Auth::user()->id)
                    ->where('session_year_id', $session_year_id);
            })->pluck('id')->toArray();

            $sql->whereHas('user.student', function ($q) use ($teacherClassSectionIds) {
                $q->whereIn('class_section_id', $teacherClassSectionIds);
            });
        }

        // Class Section Filter
        if ($request->filled('class_section_id')) {
            $sql->whereHas('user.student', function ($q) use ($request) {
                $q->where('class_section_id', $request->class_section_id);
            });
        }

        // Status Filter
        if ($request->filled('status') && $request->status !== 'all') {
            $sql->where('status', $request->status);
        }

        // Filter Upcoming
        $filter_upcoming = $request->input('filter_upcoming');
        if ($filter_upcoming && $filter_upcoming !== 'All') {
            if ($filter_upcoming == 'Today') {
                $sql->whereDate('from_date', '<=', Carbon::now()->format('Y-m-d'))->whereDate('to_date', '>=', Carbon::now()->format('Y-m-d'));
            }
            if ($filter_upcoming == 'Tomorrow') {
                $tomorrow_date = Carbon::now()->addDay()->format('Y-m-d');
                $sql->whereHas('leave_detail', function ($q) use ($tomorrow_date) {
                    $q->whereDate('date', '<=', $tomorrow_date)->whereDate('date', '>=', $tomorrow_date);
                });
            }
            if ($filter_upcoming == 'Upcoming') {
                $upcoming_date = Carbon::now()->addDays(1)->format('Y-m-d');
                $sql->whereHas('leave_detail', function ($q) use ($upcoming_date) {
                    $q->whereDate('date', '>', $upcoming_date);
                });
            }
        }

        // Search Filter
        if ($search) {
            $sql->where(function ($query) use ($search) {
                $query->where('reason', 'LIKE', "%{$search}%")
                    ->orWhere('note', 'LIKE', "%{$search}%")
                    ->orWhere('from_date', 'LIKE', "%{$search}%")
                    ->orWhere('to_date', 'LIKE', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->whereRaw('concat(first_name," ",last_name) like ?', ["%{$search}%"]);
                    })
                    ->orWhereHas('added_by_user', function ($q) use ($search) {
                        $q->whereRaw('concat(first_name," ",last_name) like ?', ["%{$search}%"]);
                    });
            });
        }

        $total = $sql->count();
        if ($offset >= $total && $total > 0) {
            $lastPage = floor(($total - 1) / $limit) * $limit;
            $offset = $lastPage;
        }

        $res = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];
        $no = $offset + 1;

        foreach ($res as $row) {
            $operate = '';
            $operate .= BootstrapTableService::editButton(route('student-leave.update-status', $row->id));
            $operate .= BootstrapTableService::deleteButton(route('student-leave.destroy', $row->id));

            $tempRow = $row->toArray();
            $tempRow['no']            = $no++;
            $tempRow['student_name']  = $row->user->full_name ?? '';
            $tempRow['class_section'] = $row->user->student->class_section->full_name ?? '';
            $tempRow['days']          = $row->leave_detail->count() ?: 1;
            $tempRow['org_from_date'] = date('d-m-Y', strtotime($row->getRawOriginal('from_date')));
            $tempRow['org_to_date']   = date('d-m-Y', strtotime($row->getRawOriginal('to_date')));
            $tempRow['operate']       = $operate;

            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }


    public function store(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Student Leave Management');
        ResponseService::noPermissionThenSendJson('student-leave-create');

        $request->validate([
            'student_id' => 'required|exists:students,id',
            'from_date'  => 'required|date_format:d-m-Y',
            'to_date'    => 'required|date_format:d-m-Y',
            'reason'     => 'required|string',
            'files'      => 'nullable|array',
            'files.*'    => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
        ]);

        try {
            DB::beginTransaction();

            $fromDate = Carbon::createFromFormat('d-m-Y', $request->from_date)->format('Y-m-d');
            $toDate   = Carbon::createFromFormat('d-m-Y', $request->to_date)->format('Y-m-d');

            if ($fromDate > $toDate) {
                ResponseService::errorResponse(__('To Date must be after or equal to From Date.'));
            }

            $sessionYear = $this->cache->getSessionYear();

            $student = Students::with('user', 'class_section')->findOrFail($request->student_id);

            if (Auth::user()->hasRole('Teacher')) {

                $teacherClassSectionIds = ClassSection::whereHas('class_teachers', function ($query) use ($sessionYear) {
                    $query->where('teacher_id', Auth::user()->id)
                        ->where('session_year_id', $sessionYear->id);
                })->pluck('id')->toArray();

                if (!in_array($student->class_section_id, $teacherClassSectionIds, true)) {
                    ResponseService::errorResponse(__('You can only add leave for students in your assigned class section.'));
                }
            }

            if ($fromDate < $sessionYear->start_date || $toDate > $sessionYear->end_date) {
                ResponseService::errorResponse(__('Selected dates must fall within the current academic session year.'));
            }

            $leaveMaster = LeaveMaster::where('session_year_id', $sessionYear->id)->first();
            if (!$leaveMaster) {
                ResponseService::errorResponse(__('Leave master settings not configured for current session year.'));
            }

            $exists = Leave::where('user_id', $student->user_id)
                ->where('status', '!=', 2)
                ->where(function ($q) use ($fromDate, $toDate) {
                    $q->where('from_date', '<=', $toDate)
                        ->where('to_date', '>=', $fromDate);
                })
                ->exists();

            if ($exists) {
                ResponseService::errorResponse(__('A leave request already exists for this student during this period.'));
            }

            // 1. Create Leave Record
            $leave = Leave::create([
                'user_id'         => $student->user_id,
                'added_by'        => Auth::id(),
                'reason'          => $request->reason,
                'from_date'       => $fromDate,
                'to_date'         => $toDate,
                'status'          => 1, // Auto-approved
                'leave_master_id' => $leaveMaster->id,
                'school_id'       => Auth::user()->school_id,
            ]);

            // 2. Save Attachments via Eloquent Morph Relationship (Auto-assigns modal_type & modal_id)
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file_upload) {
                    $path = $file_upload->store('leave', 'public');
                    $leave->file()->create([
                        'file_name' => $file_upload->getClientOriginalName(),
                        'type'      => 1, // 1 = File
                        'file_url'  => $path,
                        'school_id' => Auth::user()->school_id,
                    ]);
                }
            }

            // 3. Create Daily Leave Details
            $startDate = Carbon::parse($fromDate);
            $endDate   = Carbon::parse($toDate);
            $detail_records = [];

            while ($startDate->lte($endDate)) {
                $detail_records[] = [
                    'leave_id'   => $leave->id,
                    'date'       => $startDate->toDateString(),
                    'type'       => 'full_day',
                    'school_id'  => Auth::user()->school_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $startDate->addDay();
            }

            LeaveDetail::insert($detail_records);

            // 4. Sync Leave Days to Attendance Table
            $attendanceRecords = [];
            $attendanceDateCursor = Carbon::parse($fromDate);
            $attendanceDateEnd    = Carbon::parse($toDate);

            while ($attendanceDateCursor->lte($attendanceDateEnd)) {
                $attendanceRecords[] = [
                    'class_section_id' => $student->class_section_id,
                    'student_id'       => $student->user_id,
                    'session_year_id'  => $sessionYear->id,
                    'type'             => 2, // 2 = Leave
                    'date'             => $attendanceDateCursor->toDateString(),
                    'remark'           => 'Leave approved by ' . Auth::user()->full_name,
                    'school_id'        => Auth::user()->school_id,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
                $attendanceDateCursor->addDay();
            }

            Attendance::upsert(
                $attendanceRecords,
                ['class_section_id', 'student_id', 'session_year_id', 'date'],
                ['type', 'remark', 'updated_at']
            );

            DB::commit();

            $title = __('Leave Added and Approved');
            $body = __('A leave request from :from to :to has been logged and approved by :name.', [
                'from' => date('d-m-Y', strtotime($fromDate)),
                'to'   => date('d-m-Y', strtotime($toDate)),
                'name' => Auth::user()->full_name
            ]);

            $users = [
                $student->user_id,
                $student->guardian_id
            ];

            send_notification($users, $title, $body, "student_leave", ['_skip_whatsapp' => true]);

            $data = [
                'from_date'   => $leave->from_date ? Carbon::parse($leave->getRawOriginal('from_date'))->format('d-m-Y') : '',
                'to_date'     => $leave->to_date ? Carbon::parse($leave->getRawOriginal('to_date'))->format('d-m-Y') : '',
                'student_name' => $leave->user->full_name ?? '',
                'class' => $leave->user->student->class_section->full_name ?? '',
                'school_name' => app(CachingService::class)->getSchoolSettings('school_name') ?? '',
            ];
            NotificationService::sendToUsers('student_leave_approved', $users, $data, Auth::user()->school);


            ResponseService::successResponse(__('Leave Added and Approved Successfully'));
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Student Leave Controller -> Store Leave");
            ResponseService::errorResponse(__('Failed to add leave'));
        }
    }


    public function updateStatus(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Student Leave Management');
        ResponseService::noPermissionThenSendJson('student-leave-approve');

        $request->validate([
            'id'        => 'required|exists:leaves,id',
            'from_date' => 'required|date_format:d-m-Y',
            'to_date'   => 'required|date_format:d-m-Y',
            'reason'    => 'required|string',
            'status'    => 'required|in:0,1,2',
            'note'     => 'required_if:status,2|nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $sessionYear     = $this->cache->getSessionYear();
            $session_year_id = $sessionYear->id;
            $isAdmin = Auth::user()->hasRole('School Admin');

            $leave   = Leave::with('user.student.class_section.class.stream', 'user.student.class_section.section')->findOrFail($request->id);
            $student = $leave->user->student ?? null;

            // Teacher Validation
            if (Auth::user()->hasRole('Teacher')) {

                if (!$isAdmin && $leave->status != 0) {
                    ResponseService::errorResponse(__('This leave has already been decided and cannot be changed by a Teacher.'));
                }

                $teacherClassSectionIds = ClassSection::whereHas('class_teachers', function ($query) use ($session_year_id) {
                    $query->where('teacher_id', Auth::user()->id)
                        ->where('session_year_id', $session_year_id);
                })->pluck('id')->toArray();

                $isTeacherOfStudent = Leave::where('id', $request->id)
                    ->whereHas('user.student', function ($q) use ($teacherClassSectionIds) {
                        $q->whereIn('class_section_id', $teacherClassSectionIds);
                    })->exists();

                if (!$isTeacherOfStudent) {
                    ResponseService::errorResponse(__('You do not have permission to manage leave for this student.'));
                }
            }

            $fromDate = Carbon::createFromFormat('d-m-Y', $request->from_date)->format('Y-m-d');
            $toDate   = Carbon::createFromFormat('d-m-Y', $request->to_date)->format('Y-m-d');

            if ($fromDate > $toDate) {
                ResponseService::errorResponse(__('To Date must be after or equal to From Date.'));
            }

            $exists = Leave::where('user_id', $leave->user_id)
                ->where('id', '!=', $leave->id)
                ->where('status', '!=', 2)
                ->where(function ($q) use ($fromDate, $toDate) {
                    $q->where('from_date', '<=', $toDate)
                        ->where('to_date', '>=', $fromDate);
                })
                ->exists();

            if ($exists) {
                ResponseService::errorResponse(__('Another active leave request already exists for this student during this period.'));
            }

            // --- STEP 1: Capture OLD date range BEFORE saving (to clean up old attendance) ---
            $oldFromDate = $leave->getRawOriginal('from_date');
            $oldToDate   = $leave->getRawOriginal('to_date');

            // --- STEP 2: Update Leave ---
            $leave->from_date = $fromDate;
            $leave->to_date   = $toDate;
            $leave->reason    = $request->reason;
            $leave->note      = $request->note;
            $leave->status    = $request->status;
            $leave->save();

            // --- STEP 3: Re-create Leave Details ---
            $leave->leave_detail()->delete();
            $startDate = Carbon::parse($fromDate);
            $endDate   = Carbon::parse($toDate);
            $detail_records = [];

            while ($startDate->lte($endDate)) {
                $detail_records[] = [
                    'leave_id'   => $leave->id,
                    'date'       => $startDate->toDateString(),
                    'type'       => 'full_day',
                    'school_id'  => Auth::user()->school_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $startDate->addDay();
            }
            LeaveDetail::insert($detail_records);

            // --- STEP 4: Sync Attendance based on new Status ---
            if ($student && $student->class_section_id) {

                // Always delete OLD date range attendance records first (covers date change + rejection/pending)
                Attendance::where([
                    'class_section_id' => $student->class_section_id,
                    'student_id'       => $leave->user_id,
                    'session_year_id'  => $session_year_id,
                    'type'             => 2, // Leave type
                ])
                    ->whereBetween('date', [$oldFromDate, $oldToDate])
                    ->delete();

                // If Approved (1): insert new attendance records for new date range
                if ((int)$request->status === 1) {
                    $attendanceData = [];
                    $period = CarbonPeriod::create($fromDate, $toDate);

                    foreach ($period as $date) {
                        $attendanceData[] = [
                            'school_id'        => Auth::user()->school_id,
                            'class_section_id' => $student->class_section_id,
                            'student_id'       => $leave->user_id,
                            'session_year_id'  => $session_year_id,
                            'type'             => 2, // 2 = Leave
                            'date'             => $date->format('Y-m-d'),
                        ];
                    }

                    if (!empty($attendanceData)) {
                        Attendance::upsert(
                            $attendanceData,
                            ['class_section_id', 'student_id', 'session_year_id', 'date'],
                            ['type']
                        );
                    }
                }
            }

            DB::commit();

            // --- STEP 5: Send Notifications ---
            $notifyUser = [
                $leave->user_id,
                $leave->user->student->guardian_id
            ];

            $title = '';
            $body  = '';

            if ($request->status == 1) {
                $title = __('Leave Request Approved');
                $body  = __('Your leave request from :from to :to has been approved.', [
                    'from' => $request->from_date,
                    'to'   => $request->to_date
                ]);
            } elseif ($request->status == 2) {
                $title = __('Leave Request Rejected');
                $body  = __('Your leave request from :from to :to has been rejected.', [
                    'from' => $request->from_date,
                    'to'   => $request->to_date
                ]);

                if (!empty($request->note)) {
                    $body .= "\n" . __('Reason / Note: ') . $request->note;
                }
            }

            $customData = [
                'child_id' => $leave->user_id,
                '_skip_whatsapp' => true
            ];

            if (in_array($request->status, [1, 2])) {
                $data = [
                    'from_date'   => $leave->from_date ? Carbon::parse($leave->getRawOriginal('from_date'))->format('d-m-Y') : '',
                    'to_date'     => $leave->to_date ? Carbon::parse($leave->getRawOriginal('to_date'))->format('d-m-Y') : '',
                    'student_name' => $leave->user->full_name ?? '',
                    'class' => $leave->user->student->class_section->full_name ?? '',
                    'school_name' => app(CachingService::class)->getSchoolSettings('school_name') ?? '',
                ];
                NotificationService::sendToUsers($request->status == 1 ? 'student_leave_approved' : 'student_leave_rejected', $notifyUser, $data, Auth::user()->school);
            }

            send_notification($notifyUser, $title, $body, "student_leave", $customData);



            ResponseService::successResponse(__('Leave Updated Successfully'));
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, "Student Leave Controller -> Update Status");
            ResponseService::errorResponse(__('Failed to update leave'));
        }
    }

    public function destroy($id)
    {
        ResponseService::noFeatureThenSendJson('Student Leave Management');
        ResponseService::noPermissionThenSendJson('student-leave-approve');

        $leave = Leave::with(['user.student', 'file', 'leave_detail'])
            ->whereHas('user', function ($q) {
                $q->has('student');
            })->findOrFail($id);

        $session_year_id = $this->cache->getSessionYear()->id;
        try {
            DB::beginTransaction();

            $fromDate = $leave->getRawOriginal('from_date');
            $toDate   = $leave->getRawOriginal('to_date');
            $today    = now()->format('Y-m-d');


            $student = $leave->user->student ?? null;
            $isFutureLeave = $fromDate > $today;

            if ($isFutureLeave && $student && $student->class_section_id) {
                Attendance::where([
                    'class_section_id' => $student->class_section_id,
                    'student_id'       => $leave->user_id,
                    'session_year_id'  => $session_year_id,
                    'type'             => 2,
                ])
                    ->whereBetween('date', [$fromDate, $toDate])
                    ->delete();
            }

            // Delete attached files
            foreach ($leave->file as $file) {
                $file->delete();
            }

            // Delete daily breakdown records
            $leave->leave_detail()->delete();

            // Delete the leave record
            $leave->delete();

            DB::commit();

            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'StudentLeaveController -> destroy method');
            ResponseService::errorResponse();
        }
    }
}
