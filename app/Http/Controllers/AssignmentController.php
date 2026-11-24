<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentCommon;
use App\Models\ClassSubject;
use App\Models\Students;
use App\Repositories\Assignment\AssignmentInterface;
use App\Repositories\AssignmentCommon\AssignmentCommonInterface;
use App\Repositories\AssignmentSubmission\AssignmentSubmissionInterface;
use App\Repositories\ClassSection\ClassSectionInterface;
use App\Repositories\ClassSubject\ClassSubjectInterface;
use App\Repositories\Files\FilesInterface;
use App\Repositories\Semester\SemesterInterface;
use App\Repositories\SessionYear\SessionYearInterface;
use App\Repositories\Student\StudentInterface;
use App\Repositories\Subject\SubjectInterface;
use App\Repositories\SubjectTeacher\SubjectTeacherInterface;
use App\Repositories\StudentSubject\StudentSubjectInterface;
use App\Rules\MaxFileSize;
use App\Rules\SafeUpload;
use App\Services\BootstrapTableService;
use App\Services\CachingService;
use App\Services\GeneralFunctionService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AssignmentController extends Controller
{
    private AssignmentInterface $assignment;
    private ClassSectionInterface $classSection;
    private SubjectInterface $subject;
    private FilesInterface $files;
    private StudentInterface $student;
    private AssignmentSubmissionInterface $assignmentSubmission;
    private SessionYearInterface $sessionYear;
    private CachingService $cache;
    private SubjectTeacherInterface $subjectTeacher;
    private AssignmentCommonInterface $assignmentCommon;
    private ClassSubjectInterface $class_subjects;
    private SemesterInterface $semester;
    private StudentSubjectInterface $studentSubject;

    public function __construct(AssignmentInterface $assignment, ClassSectionInterface $classSection, SubjectInterface $subject, FilesInterface $files, StudentInterface $student, AssignmentSubmissionInterface $assignmentSubmission, SessionYearInterface $sessionYear, CachingService $cachingService, SubjectTeacherInterface $subjectTeacher, AssignmentCommonInterface $assignmentCommon, ClassSubjectInterface $class_subjects, SemesterInterface $semester, StudentSubjectInterface $studentSubject)
    {
        $this->assignment = $assignment;
        $this->classSection = $classSection;
        $this->subject = $subject;
        $this->files = $files;
        $this->student = $student;
        $this->assignmentSubmission = $assignmentSubmission;
        $this->sessionYear = $sessionYear;
        $this->cache = $cachingService;
        $this->subjectTeacher = $subjectTeacher;
        $this->assignmentCommon = $assignmentCommon;
        $this->class_subjects = $class_subjects;
        $this->semester = $semester;
        $this->studentSubject = $studentSubject;
    }

    public function index()
    {
        ResponseService::noFeatureThenRedirect('Assignment Management');
        ResponseService::noPermissionThenRedirect('assignment-list');
        $sessionYear = $this->cache->getSessionYear();
        $semester = $this->cache->getSemester();

        $classSections = $this->classSection->builder()->with('class', 'class.stream', 'class.shift', 'section', 'medium')->get();
        $subjectTeachers = $this->subjectTeacher->builder()->with('subject:id,name,type')->where('session_year_id', $sessionYear->id)
            ->with(['class_subject' => function ($q) use ($semester, $sessionYear) {
                $q->where('semester_id', $semester->id)
                    ->Where('session_year_id', $sessionYear->id)
                    ->orWhereNull('semester_id')
                    ->with('semester', 'class');
            }])->get();
        $classes = app(GeneralFunctionService::class)->getClassesForUser();


        return response(view('assignment.index', compact('classSections', 'subjectTeachers', 'classes')));
    }



    public function store(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Assignment Management');
        ResponseService::noPermissionThenRedirect('assignment-create');

        $file_upload_size_limit = $this->cache->getSystemSettings('file_upload_size_limit');

        $request->validate([
            "class_section_id" => 'required|array',
            "class_section_id.*" => 'numeric',
            "subject_id" => 'required|numeric',
            "name" => 'required',
            "description" => 'nullable',
            "due_date" => 'required|date',
            "points" => 'required',
            "resubmission" => 'nullable|boolean',
            "extra_days_for_resubmission" => 'nullable|numeric',
            'file' => 'nullable|array',
            'file.*' => [
                'file',
                'mimes:jpeg,png,jpg,gif,webp,pdf,doc,docx',
                new SafeUpload([SafeUpload::IMAGE, SafeUpload::DOCUMENT]),
                new MaxFileSize($file_upload_size_limit)
            ],
            'add_url' => ['nullable', 'url'],
        ], [
            'file.*' => trans(
                'The file Uploaded must be less than :file_upload_size_limit MB.',
                ['file_upload_size_limit' => $file_upload_size_limit]
            ),
        ]);

        try {
            DB::beginTransaction();

            /* ================= ASSIGNMENT CORE (UNCHANGED) ================= */

            $sessionYear = $this->cache->getSessionYear();
            $semester = $this->cache->getSemester();

            $assignmentData = [
                ...$request->all(),
                'due_date' => date('Y-m-d H:i', strtotime($request->due_date)),
                'resubmission' => $request->resubmission ? 1 : 0,
                'extra_days_for_resubmission' =>
                $request->resubmission ? $request->extra_days_for_resubmission : null,
                'session_year_id' => $sessionYear->id,
                'created_by' => Auth::user()->id,
                'semester_id' => $request->semester_id ?? null,
                'class_section_id' => null
            ];

            $assignment = $this->assignment->create($assignmentData);

            $assignmentCommonData = [];

            if ($request->class_section_id) {
                foreach ($request->class_section_id as $section_id) {
                    $classSection = $this->classSection
                        ->builder()
                        ->where('id', $section_id)
                        ->first();

                    $classSubject = ClassSubject::where('class_id', $classSection->class_id)->where('subject_id', $request->subject_id)->where('session_year_id', $sessionYear->id);

                    if ($request->semester_id) {
                        $classSubject = $classSubject->where('semester_id', $request->semester_id);
                    }

                    $classSubject = $classSubject->first();

                    $assignmentCommonData[] = [
                        'assignment_id' => $assignment->id,
                        'class_section_id' => $section_id,
                        'class_subject_id' => $classSubject->id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                }

                AssignmentCommon::insert($assignmentCommonData);
            }

            /* ================= FILE UPLOAD (UNCHANGED) ================= */

            if ($request->hasFile('file')) {
                $fileData = [];
                $assoc = $this->files->model()->modal()->associate($assignment);

                foreach ($request->file('file') as $file) {
                    $fileData[] = [
                        'modal_type' => $assoc->modal_type,
                        'modal_id' => $assoc->modal_id,
                        'file_name' => $file->getClientOriginalName(),
                        'type' => 1,
                        'file_url' => $file,
                    ];
                }

                $this->files->createBulk($fileData);
            }

            if ($request->add_url) {
                $urlData = [];
                $assoc = $this->files->model()->modal()->associate($assignment);

                foreach ((array) $request->add_url as $url) {
                    $urlData[] = [
                        'modal_type' => $assoc->modal_type,
                        'modal_id' => $assoc->modal_id,
                        'file_name' => basename(parse_url($url, PHP_URL_PATH) ?? ''),
                        'type' => 4,
                        'file_url' => $url,
                    ];
                }

                $this->files->createBulk($urlData);
            }

            DB::commit();
            $defaultSemester = $this->cache->getDefaultSemesterData();
            if ($request->semester_id && $defaultSemester->id && ($request->semester_id != $defaultSemester->id)) {
                ResponseService::successResponse('Data Stored Successfully');
            }

            /* ================= FIXED NOTIFICATIONS ================= */

            sendAssignmentNotification($assignment, $request->name, $request->due_date, 'New assignment added in');

            ResponseService::successResponse('Data Stored Successfully');
        } catch (Throwable $e) {

            if (Str::contains($e->getMessage(), ['does not exist', 'file_get_contents'])) {
                DB::commit();
                ResponseService::warningResponse(
                    "Data Stored successfully. But App push notification not send."
                );
            } else {
                DB::rollBack();
                ResponseService::logErrorResponse($e, "Assignment Controller -> Store Method");
                ResponseService::errorResponse();
            }
        }
    }

    public function show(Request $request)
    {
        ResponseService::noFeatureThenRedirect('Assignment Management');
        ResponseService::noPermissionThenRedirect('assignment-list');
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'ASC');
        $search = request('search');

        $session_year_id = $this->cache->getSessionYear()->id ?? null;
        // $semester_id = $this->cache->getSemester()->id ?? null;
        $semester_id = $request->semester_id ?? $this->cache->getSemester()->id ?? null;

        $sql = $this->assignment->builder()->with([
            'file',
            'assignment_commons.class_section.class.stream',
            'assignment_commons.class_section.class.shift',
            'assignment_commons.class_section.section',
            'assignment_commons.class_section.medium',
            'assignment_commons.class_subject.subject',
        ])
            ->where('session_year_id', $session_year_id)
            ->when($semester_id, function ($query) use ($semester_id) {
                $query->where('semester_id', $semester_id)->orWhere('semester_id', null);
            })
            ->where(function ($query) use ($search) {
                $query->when($search, function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('id', 'LIKE', "%$search%")
                            ->orwhere('name', 'LIKE', "%$search%")
                            ->orwhere('instructions', 'LIKE', "%$search%")
                            ->orwhere('points', 'LIKE', "%$search%")
                            ->orwhere('session_year_id', 'LIKE', "%$search%")
                            ->orwhere('extra_days_for_resubmission', 'LIKE', "%$search%")
                            ->orwhere('due_date', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%")
                            ->orwhere('created_at', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%")
                            ->orwhere('updated_at', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%")
                            ->orWhereHas('class_section.class', function ($q) use ($search) {
                                $q->where('name', 'LIKE', "%$search%");
                            })->orWhereHas('class_section.section', function ($q) use ($search) {
                                $q->where('name', 'LIKE', "%$search%");
                            })->orWhereHas('class_subject.subject', function ($q) use ($search) {
                                $q->where('name', 'LIKE', "%$search%");
                            });
                    });
                });
            })
            ->when(request('subject_id') != null, function ($query) {
                $subject_id = request('subject_id');
                $query->whereHas('assignment_commons', function ($query) use ($subject_id) {
                    $query->where('class_subject_id', $subject_id);
                });
            })
            ->when(request('class_id') != null, function ($query) {
                $class_id = request('class_id');

                $query->whereHas('assignment_commons', function ($q) use ($class_id) {
                    $q->where('class_section_id', $class_id);
                });
            });

        $total = $sql->count();
        if ($offset >= $total && $total > 0) {
            $lastPage = floor(($total - 1) / $limit) * $limit; // calculate last page offset
            $offset = $lastPage;
        }
        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;

        foreach ($res as $row) {
            $row = (object) $row;

            $assignmentCommons = $row->assignment_commons->map(function ($common) {
                return $common->class_section ? $common->class_section->full_name : null;
            });

            $assignmentCommons->filter()->map(function ($name) {
                return "{$name},";
            })->toArray();

            // Get subject first name in assignment common
            $subjectName = $row->assignment_commons->map(function ($common) {
                return $common->class_subject->subject->name_with_type;
            })->first();


            //Show Edit and Soft Delete Buttons
            // $operate = BootstrapTableService::editButton(route('assignment.update', $row->id));
            // $operate .= BootstrapTableService::button("fa fa-eye", route('assignment.submissionDetails', $row->id), ['btn-success'], ['title' => trans('View Submissions')]);
            // $operate .= BootstrapTableService::deleteButton(route('assignment.destroy', $row->id));


            $operate = BootstrapTableService::menuEditButton('edit', route('assignment.update', $row->id));
            $operate .= BootstrapTableService::menuButton("view_submission", route('assignment.submissionDetails', $row->id));
            $operate .= BootstrapTableService::menuDeleteButton('delete', route('assignment.destroy', $row->id));

            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['org_due_date'] = $row->getRawOriginal('due_date');
            $tempRow['subject_name'] = $subjectName;
            $tempRow['class_section_with_medium'] = $assignmentCommons;
            $tempRow['operate'] = BootstrapTableService::menuItem($operate);
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    // public function edit($id)
    // {
    //     ResponseService::noFeatureThenRedirect('Assignment Management');
    //     ResponseService::noPermissionThenRedirect('assignment-edit');
    //     $assignment = $this->assignment->builder()->with('class_section.class', 'class_section.section', 'class_section.medium', 'file', 'class_subject.subject', 'assignment_commons')->where('id', $id)->first();
    //     $classSections = $this->classSection->builder()->with('class', 'class.stream', 'section', 'medium')->get();
    //     $subjectTeachers = $this->subjectTeacher->builder()->with('subject:id,name,type')->get();
    //     $sessionYears = $this->sessionYear->all();

    //     $user = Auth::user();
    //     $assignmentCommons = AssignmentCommon::where('assignment_id', $id)->get();
    //     // dd($assignment->file);
    //     return response(view('assignment.edit', compact('assignment', 'classSections', 'subjectTeachers', 'sessionYears', 'assignmentCommons')));
    // }

    public function update($id, Request $request)
    {
        ResponseService::noFeatureThenRedirect('Assignment Management');
        ResponseService::noPermissionThenRedirect('assignment-edit');

        $file_upload_size_limit = $this->cache->getSystemSettings('file_upload_size_limit');

        $request->validate([
            "class_section_id" => 'required|array',
            "class_section_id.*" => 'numeric',
            // "class_subject_id" => 'required|numeric',
            "name" => 'required',
            "description" => 'nullable',
            "due_date" => 'required|date',
            "points" => 'required',
            "resubmission" => 'nullable|boolean',
            "extra_days_for_resubmission" => 'nullable|numeric',
            'file' => 'nullable|array',
            'file.*' => [
                'file',
                'mimes:jpeg,png,jpg,gif,webp,pdf,doc,docx',
                new SafeUpload([SafeUpload::IMAGE, SafeUpload::DOCUMENT]),
                new MaxFileSize($file_upload_size_limit)
            ],
            'add_url' => ['nullable', 'url'],
        ], [
            'file.*' => trans('The file Uploaded must be less than :file_upload_size_limit MB.', [
                'file_upload_size_limit' => $file_upload_size_limit,
            ]),
        ]);

        try {
            DB::beginTransaction();

            /* ================= CORE UPDATE LOGIC (UNCHANGED) ================= */

            $sessionYear = $this->cache->getSessionYear();
            $semester = $this->cache->getSemester();
            $notifyUser = [];

            $assignmentData = [
                'name' => $request->name,
                'due_date' => date('Y-m-d H:i', strtotime($request->due_date)),
                'resubmission' => $request->resubmission ? 1 : 0,
                'extra_days_for_resubmission' =>
                $request->resubmission ? $request->extra_days_for_resubmission : null,
                'edited_by' => Auth::user()->id,
                'points' => $request->points,
                'instructions' => $request->instructions,
            ];

            $assignment = $this->assignment->update($id, $assignmentData);

            $subjectName = '';

            $assignment = Assignment::with('assignment_commons')->find($id);

            foreach ($assignment->assignment_commons as $assignment_common) {

                $classSection = $this->classSection
                    ->builder()
                    ->where('id', $assignment_common->class_section_id)
                    ->with('class')
                    ->first();

                $classSubjects = $this->class_subjects
                    ->builder()
                    ->where('id', $assignment_common->class_subject_id)
                    ->with('subject')
                    ->first();
                $subjectName = $classSubjects->subject;

                $getClassSubjectType = $this->class_subjects
                    ->findById($classSubjects->id, ['type']);

                if ($getClassSubjectType->type == 'Elective') {
                    $notifyUser[] = $this->studentSubject
                        ->builder()
                        ->select('student_id')
                        ->whereIn('class_section_id', $assignment->assignment_commons->pluck('class_section_id')->toArray())
                        ->where(['class_subject_id' => $assignment_common->class_subject_id])
                        ->get()
                        ->pluck('student_id');
                }
            }

            /* ================= FILE HANDLING (UNCHANGED) ================= */

            if ($request->hasFile('file')) {
                $fileData = [];
                $assignmentModelAssociate = $this->files
                    ->model()
                    ->modal()
                    ->associate($assignment);

                foreach ($request->file as $file_upload) {
                    $fileData[] = [
                        'modal_type' => $assignmentModelAssociate->modal_type,
                        'modal_id' => $assignmentModelAssociate->modal_id,
                        'file_name' => $file_upload->getClientOriginalName(),
                        'type' => 1,
                        'file_url' => $file_upload
                    ];
                }

                $this->files->createBulk($fileData);
            }

            if ($request->add_url) {
                $fileInstance = $this->files->model();
                $assignmentModelAssociate = $fileInstance->modal()->associate($assignment);

                $tempUrlData = [
                    [
                        'id' => $request->add_url_id ?? null,
                        'modal_type' => $assignmentModelAssociate->modal_type,
                        'modal_id' => $assignmentModelAssociate->modal_id,
                        'file_name' => '',
                        'type' => 4,
                        'file_url' => $request->add_url,
                    ]
                ];

                $this->files->upsert(
                    $tempUrlData,
                    ['id'],
                    ['id', 'modal_type', 'modal_id', 'file_name', 'type', 'file_url']
                );
            } else {
                if ($request->add_url_id) {
                    $this->files->deleteById($request->add_url_id);
                }
            }

            $assignment->save();

            DB::commit();

            $defaultSemester = $this->cache->getDefaultSemesterData();
            if ($request->semester_id && $defaultSemester->id && ($request->semester_id != $defaultSemester->id)) {
                ResponseService::successResponse('Data Stored Successfully');
            }

            /* ================= FIXED NOTIFICATIONS ================= */
            sendAssignmentNotification($assignment, $request->name, $request->due_date, 'Updated assignment in');

            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {

            if (
                Str::contains($e->getMessage(), [
                    'does not exist',
                    'file_get_contents'
                ])
            ) {
                DB::commit();
                ResponseService::warningResponse(
                    "Data Stored successfully. But App push notification not send."
                );
            } else {
                DB::rollback();
                ResponseService::logErrorResponse($e, "Assignment Controller -> Update Method");
                ResponseService::errorResponse();
            }
        }
    }

    public function destroy($id)
    {
        ResponseService::noFeatureThenRedirect('Assignment Management');
        ResponseService::noPermissionThenSendJson('assignment-delete');
        try {
            $this->assignment->deleteById($id);
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "Assignment Controller -> Destroy Method");
            ResponseService::errorResponse();
        }
    }

    public function viewAssignmentSubmission()
    {
        ResponseService::noFeatureThenRedirect('Assignment Management');
        ResponseService::noPermissionThenRedirect('assignment-submission');
        $sessionYear = $this->cache->getSessionYear();
        $classSections = $this->classSection->builder()->with('class', 'class.shift', 'class.stream', 'section', 'medium')->get();
        $subjectTeachers = $this->subjectTeacher->builder()->where('session_year_id', $sessionYear->id)->with('subject:id,name,type', 'class_subject:id,class_id,subject_id,semester_id', 'class_subject.semester', 'class_subject.class')->get();
        return response(view('assignment.submission', compact('classSections', 'subjectTeachers')));
    }

    // public function assignmentSubmissionDetails($id, $class_section_id, $subject_id)
    public function assignmentSubmissionDetails($id)
    {
        $sessionYear = $this->cache->getSessionYear();
        $semesterId = $this->cache->getSemester()->id ?? null;
        $assignment = Assignment::with([
            'file',
            'assignment_commons.class_section.class.stream',
            'assignment_commons.class_section.class.shift',
            'assignment_commons.class_section.section',
            'assignment_commons.class_section.medium',
            'assignment_commons.class_subject.subject',
        ])->where('id', $id)->first();

        return response(view('assignment.details', compact('assignment')));
    }

    public function showAssignmentSubmissionDetails($id, $class_section_id, $subject_id)
    {
        ResponseService::noFeatureThenRedirect('Assignment Management');
        ResponseService::noPermissionThenRedirect('assignment-submission');
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'ASC');
        $search = request('search');
        $sessionYear = $this->cache->getSessionYear();
        $semesterId = $this->cache->getSemester()->id ?? null;

        $sql = $this->assignmentSubmission->builder()->with('assignment', 'student:first_name,last_name,id,image,email', 'file', 'session_year')->where('assignment_id', $id)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")
                        ->orwhere('created_at', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%")
                        ->orwhere('updated_at', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%")
                        ->orwhere('points', 'LIKE', "%$search%")
                        ->orwhere('feedback', 'LIKE', "%$search%")
                        ->orWhereHas('student', function ($query) use ($search) {
                            $query->whereRaw("concat(users.first_name,' ',users.last_name) LIKE '%" . $search . "%'");
                        });
                });
            });

        $total = $sql->count();
        if ($offset >= $total && $total > 0) {
            $lastPage = floor(($total - 1) / $limit) * $limit; // calculate last page offset
            $offset = $lastPage;
        }
        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();
        if (!$res) {
            ResponseService::errorResponse("Assignment Submission not found");
        }
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;
        foreach ($res as $row) {

            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function bulkAssignmentSubmissionUpdate(Request $request)
    {
        // return response()->json($request->assignment_data);
        ResponseService::noFeatureThenRedirect('Assignment Management');
        ResponseService::noPermissionThenRedirect('assignment-submission');
        $request->validate([
            'assignment_name' => 'required',
            'subject_name' => 'required',
            'assignment_data' => 'required|array',
            'user_ids' => 'required|string',
        ], [
            'user_ids.required' => 'Please select at least one student.',
        ]);

        // Get user IDs as array (remove any whitespace and filter empty values)
        $userIds = array_filter(
            array_map('trim', explode(',', $request->input('user_ids')))
        );


        try {
            DB::beginTransaction();
            $assignmentSubmissionData = [];
            $acceptedStudentIds = [];
            $rejectedStudentIds = [];
            foreach ($request->assignment_data as $item) {
                if (in_array($item['id'], $userIds)) { // Only process if ID is in user_ids
                    $assignmentSubmissionData[] = [
                        'id' => $item['id'],
                        'student_id' => $item['student_id'],
                        'status' => $item['status'],
                        'points' => $item['points'] ?? '',
                        'feedback' => $item['feedback'],
                    ];

                    if ($item['status'] == 1) {
                        $acceptedStudentIds[] = (int) $item['student_id'];
                    } else {
                        $rejectedStudentIds[] = (int) $item['student_id'];
                    }
                }
            }
            $acceptedGuardianIds = $this->student->builder()->whereIn('user_id', $acceptedStudentIds)->pluck('guardian_id')->toArray();
            $acceptedTitle = "Assignment accepted";
            $acceptedBody = $request->assignment_name . " accepted in " . $request->subject_name . " subject";

            if ($rejectedStudentIds != []) {
                $rejectedGuardianIds = $this->student->builder()->whereIn('user_id', $rejectedStudentIds)->pluck('guardian_id')->toArray();
                $rejectedTitle = "Assignment rejected";
                $rejectedBody = $request->assignment_name . " rejected in " . $request->subject_name . " subject";
            }

            // Upsert: Update existing by id or insert if doesn't exist
            $this->assignmentSubmission->upsert(
                $assignmentSubmissionData,
                ['id'], // Unique key for upsert
                ['student_id', 'status', 'points', 'feedback'] // Fields to update
            );

            DB::commit();

            // notification
            $type = "assignment";
            if (!empty($acceptedStudentIds) || !empty($acceptedGuardianIds)) {
                $acceptedUsers = array_merge($acceptedStudentIds, $acceptedGuardianIds);
                send_notification($acceptedUsers, $acceptedTitle, $acceptedBody, $type);
            }
            if (!empty($rejectedStudentIds) || !empty($rejectedGuardianIds)) {
                $rejectedUsers = array_merge($rejectedStudentIds, $rejectedGuardianIds);
                send_notification($rejectedUsers, $rejectedTitle, $rejectedBody, $type);
            }

            ResponseService::successResponse("Data Updated Successfully");
        } catch (Throwable $e) {
            if (
                Str::contains($e->getMessage(), [
                    'does not exist',
                    'file_get_contents'
                ])
            ) {
                DB::commit();
                ResponseService::warningResponse("Data Stored successfully. But App push notification not send.");
            } else {
                DB::rollback();
                ResponseService::logErrorResponse($e);
                ResponseService::errorResponse();
            }
        }
    }

    public function assignmentSubmissionList()
    {
        ResponseService::noFeatureThenRedirect('Assignment Management');
        ResponseService::noPermissionThenRedirect('assignment-submission');
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'ASC');
        $search = request('search');
        // $semester_id = request('semester_id');
        $sessionYear = $this->cache->getSessionYear();
        $semesterId = $this->cache->getSemester()->id ?? null;

        $sql = $this->assignmentSubmission->builder()->with(['assignment.assignment_commons.class_subject.subject', 'student:first_name,last_name,id,image,email', 'file', 'session_year', 'assignment.assignment_commons.class_section.class.stream', 'assignment.assignment_commons.class_section.class.shift', 'assignment.assignment_commons.class_section.medium', 'student.student'])
            //search query
            ->whereHas('assignment', function ($query) use ($sessionYear, $semesterId) {
                $query->when($semesterId, function ($query) use ($semesterId) {
                    $query->where('semester_id', $semesterId)->orWhereNull('semester_id');
                });
                $query->where('session_year_id', $sessionYear->id);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")
                        ->orwhere('session_year_id', 'LIKE', "%$search%")
                        ->orwhere('created_at', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%")
                        ->orwhere('updated_at', 'LIKE', "%" . date('Y-m-d H:i:s', strtotime($search)) . "%")
                        ->orWhereHas('assignment.assignment_commons.class_subject.subject', function ($query) use ($search) {
                            $query->where('name', 'LIKE', "%$search%");
                        })->orWhereHas('assignment', function ($query) use ($search) {
                            $query->where('name', 'LIKE', "%$search%");
                        })->orWhereHas('student', function ($query) use ($search) {
                            $query->whereRaw("concat(users.first_name,' ',users.last_name) LIKE '%" . $search . "%'");
                        });
                });
            })
            //subject filter data
            ->when(request('subject_id') != null, function ($query) {
                $subject_id = request('subject_id');
                $query->where(function ($query) use ($subject_id) {
                    $query->whereHas('assignment.assignment_commons', function ($q) use ($subject_id) {
                        $q->where('class_subject_id', $subject_id);
                    });
                });
            })->when(request('class_section_id') != null, function ($query) {
                $class_section_id = request('class_section_id');
                $query->where(function ($query) use ($class_section_id) {
                    $query->whereHas('assignment.assignment_commons', function ($q) use ($class_section_id) {
                        $q->where('class_section_id', $class_section_id);
                    });
                });
            });

        $total = $sql->count();
        if ($offset >= $total && $total > 0) {
            $lastPage = floor(($total - 1) / $limit) * $limit; // calculate last page offset
            $offset = $lastPage;
        }
        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;
        foreach ($res as $row) {
            $row = (object) $row;
            $operate = BootstrapTableService::editButton(route('assignment.submission.update', $row->id));
            $classSubject = '';
            $classSection = '';
            foreach ($row->assignment->assignment_commons as $key => $assignment_common) {
                if ($assignment_common->class_section_id == $row->student->student->class_section_id) {
                    $classSubject = $assignment_common->class_subject;
                    $classSection = $assignment_common->class_section;
                    break;
                }
            }
            $row->assignment->class_subject = $classSubject;
            $row->assignment->class_section = $classSection;

            $tempRow = $row->toArray();

            $tempRow['student'] = $row->student;
            $tempRow['points'] = $row->points;
            $tempRow['no'] = $no++;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }


    public function updateAssignmentSubmission($id, Request $request)
    {
        ResponseService::noFeatureThenRedirect('Assignment Management');
        ResponseService::noPermissionThenRedirect('assignment-submission');
        $request->validate([
            'status' => 'required|numeric|in:1,2',
            'feedback' => 'nullable',
        ]);

        try {
            DB::beginTransaction();
            $updateAssignmentSubmissionData = array(
                'feedback' => $request->feedback,
                'points' => $request->status == 1 ? $request->points : 0,
                'status' => $request->status,
            );
            $assignmentSubmission = $this->assignmentSubmission->update($id, $updateAssignmentSubmissionData);

            $assignmentData = $this->assignment->builder()->where('id', $assignmentSubmission->assignment_id)->with('assignment_commons.class_subject.subject')->first();

            $assignmentData->class_subject = $assignmentData->assignment_commons->first()->class_subject;


            if ($request->status == 1) {
                $title = "Assignment accepted";
                $body = $assignmentData->name . " accepted in " . $assignmentData->class_subject->subject->name_with_type . " subject";
            } else {
                $title = "Assignment rejected";
                $body = $assignmentData->name . " rejected in " . $assignmentData->class_subject->subject->name_with_type . " subject";
            }

            $type = "assignment";
            $students = $this->student->builder()->where('user_id', $assignmentSubmission->student_id)->get();
            $guardian_id = $students->pluck('guardian_id')->toArray();
            $student_id = $students->pluck('user_id')->toArray();
            $user = array_merge($student_id, $guardian_id);
            $customData = [
                'assignment_id' => $assignmentData->id,
                'class_subject_id' => $assignmentData->class_subject->id,
                "assigment_status_update" => 1,
                'school_id' => Auth::user()->school_id ?? null
            ];

            DB::commit();

            send_notification($user, $title, $body, $type, $customData);

            ResponseService::successResponse("Data Updated Successfully");
        } catch (Throwable $e) {
            if (
                Str::contains($e->getMessage(), [
                    'does not exist',
                    'file_get_contents'
                ])
            ) {
                DB::commit();
                ResponseService::warningResponse("Data Stored successfully. But App push notification not send.");
            } else {
                DB::rollback();
                ResponseService::logErrorResponse($e);
                ResponseService::errorResponse();
            }
        }
    }
}
