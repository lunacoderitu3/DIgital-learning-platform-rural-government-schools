<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TimetableCollection;
use App\Http\Resources\UserDataResource;
use App\Models\ClassTeacher;
use App\Models\School;
use App\Repositories\Announcement\AnnouncementInterface;
use App\Repositories\Assignment\AssignmentInterface;
use App\Repositories\AssignmentSubmission\AssignmentSubmissionInterface;
use App\Repositories\Attendance\AttendanceInterface;
use App\Repositories\DiaryStudent\DiaryStudentInterface;
use App\Repositories\Exam\ExamInterface;
use App\Repositories\ExamResult\ExamResultInterface;
use App\Repositories\Fees\FeesInterface;
use App\Repositories\FeesPaid\FeesPaidInterface;
use App\Repositories\Holiday\HolidayInterface;
use App\Repositories\Lessons\LessonsInterface;
use App\Repositories\OnlineExam\OnlineExamInterface;
use App\Repositories\PaymentConfiguration\PaymentConfigurationInterface;
use App\Repositories\PaymentTransaction\PaymentTransactionInterface;
use App\Repositories\SchoolSetting\SchoolSettingInterface;
use App\Repositories\Sliders\SlidersInterface;
use App\Repositories\Student\StudentInterface;
use App\Repositories\SubjectTeacher\SubjectTeacherInterface;
use App\Repositories\SystemSetting\SystemSettingInterface;
use App\Repositories\Timetable\TimetableInterface;
use App\Repositories\Topics\TopicsInterface;
use App\Repositories\User\UserInterface;
use App\Services\CachingService;
use App\Services\FeaturesService;
use App\Services\FcmTokenService;
use App\Services\GeneralFunctionService;
use App\Services\Payment\PaymentService;
use App\Services\ResponseService;
use App\Services\ManualPaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use JetBrains\PhpStorm\NoReturn;
use Throwable;
use App\Models\DiaryCategory;
use App\Models\FeeDiscount;
use App\Models\PromoteStudent;
use App\Models\CompulsoryFee;
use App\Models\OptionalFee;
use App\Models\PaymentTransaction;
use App\Models\Students;
use App\Models\User;
use App\Services\UserService;
use App\Services\UploadService;
use App\Rules\SafeUpload;
use App\Repositories\Files\FilesInterface;
use App\Rules\MaxFileSize;
use App\Repositories\Leave\LeaveInterface;
use App\Repositories\LeaveMaster\LeaveMasterInterface;
use App\Repositories\LeaveDetail\LeaveDetailInterface;
use Illuminate\Support\Str;
use App\Models\Leave;

// File repository interface removed due to missing class; keep untyped property
class ParentApiController extends Controller
{

    private StudentInterface $student;
    private UserInterface $user;
    private AssignmentInterface $assignment;
    private AssignmentSubmissionInterface $assignmentSubmission;
    private CachingService $cache;
    private TimetableInterface $timetable;
    private ExamInterface $exam;
    private ExamResultInterface $examResult;
    private LessonsInterface $lesson;
    private TopicsInterface $lessonTopic;
    private AttendanceInterface $attendance;
    private HolidayInterface $holiday;
    private SubjectTeacherInterface $subjectTeacher;
    private AnnouncementInterface $announcement;
    private OnlineExamInterface $onlineExam;
    private FeesInterface $fees;
    private PaymentTransactionInterface $paymentTransaction;
    private SlidersInterface $sliders;
    private FeesPaidInterface $feesPaid;
    private SubjectTeacherInterface $subjectTeachers;
    private PaymentConfigurationInterface $paymentConfigurations;
    private SystemSettingInterface $systemSetting;
    private SchoolSettingInterface $schoolSetting;
    private GeneralFunctionService $generalFunction;
    private DiaryStudentInterface $diaryStudent;
    private UserService $userService;
    private ManualPaymentService $manualPaymentService;
    private LeaveInterface $leave;
    private LeaveMasterInterface $leaveMaster;
    private LeaveDetailInterface $leaveDetail;
    private FilesInterface $files;


    public function __construct(
        StudentInterface $student,
        UserInterface $user,
        AssignmentInterface $assignment,
        AssignmentSubmissionInterface $assignmentSubmission,
        CachingService $cache,
        TimetableInterface $timetable,
        ExamInterface $exam,
        ExamResultInterface $examResult,
        LessonsInterface $lesson,
        TopicsInterface $lessonTopic,
        AttendanceInterface $attendance,
        HolidayInterface $holiday,
        SubjectTeacherInterface $subjectTeacher,
        AnnouncementInterface $announcement,
        OnlineExamInterface $onlineExam,
        FeesInterface $fees,
        PaymentTransactionInterface $paymentTransaction,
        SlidersInterface $sliders,
        PaymentConfigurationInterface $paymentConfigurations,
        FeesPaidInterface $feesPaid,
        SubjectTeacherInterface $subjectTeachers,
        SystemSettingInterface $systemSetting,
        SchoolSettingInterface $schoolSetting,
        GeneralFunctionService $generalFunction,
        DiaryStudentInterface $diaryStudent,
        UserService $userService,
        ManualPaymentService $manualPaymentService,
        LeaveMasterInterface $leaveMaster,
        LeaveDetailInterface $leaveDetail,
        LeaveInterface $leave,
        FilesInterface $files
    ) {
        $this->student = $student;
        $this->user = $user;
        $this->assignment = $assignment;
        $this->assignmentSubmission = $assignmentSubmission;
        $this->cache = $cache;
        $this->timetable = $timetable;
        $this->exam = $exam;
        $this->examResult = $examResult;
        $this->lesson = $lesson;
        $this->lessonTopic = $lessonTopic;
        $this->attendance = $attendance;
        $this->holiday = $holiday;
        $this->subjectTeacher = $subjectTeacher;
        $this->announcement = $announcement;
        $this->onlineExam = $onlineExam;
        $this->fees = $fees;
        $this->paymentTransaction = $paymentTransaction;
        $this->sliders = $sliders;
        $this->paymentConfigurations = $paymentConfigurations;
        $this->feesPaid = $feesPaid;
        $this->subjectTeachers = $subjectTeachers;
        $this->systemSetting = $systemSetting;
        $this->schoolSetting = $schoolSetting;
        $this->generalFunction = $generalFunction;
        $this->diaryStudent = $diaryStudent;
        $this->userService = $userService;
        $this->manualPaymentService = $manualPaymentService;
        $this->leaveMaster = $leaveMaster;
        $this->leaveDetail = $leaveDetail;
        $this->leave = $leave;
        $this->files = $files;
    }




    /**
     * Submit a student leave request applied by Parent/Guardian.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function cancelStudentLeave(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Student Leave Management');

        $validator = Validator::make($request->all(), [
            'leave_id' => 'required|exists:leaves,id',
            'child_id' => 'required|exists:students,id',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError();
        }

        try {
            $child = Students::where('guardian_id', Auth::id())->pluck('user_id');

            $leave = Leave::whereIn('user_id', $child)
                ->where('id', $request->leave_id)
                ->where('status', 0) // Only Pending can be cancelled
                ->firstOrFail();

            DB::beginTransaction();
            $leave->leave_detail()->delete();
            $leave->file()->delete();
            $leave->delete();
            DB::commit();

            ResponseService::successResponse('Leave request cancelled successfully.');
        } catch (Throwable $e) {
            Log::error($e);
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'ParentApiController -> cancelStudentLeave');
            ResponseService::errorResponse();
        }
    }

    public function getStudentLeave(Request $request)
    {

        ResponseService::noFeatureThenSendJson('Student Leave Management');

        $validator = Validator::make($request->all(), [
            'child_id' => 'required|exists:students,id',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError();
        }

        try {
            $child = Students::where('id', $request->child_id)
                ->where('guardian_id', Auth::id())
                ->with('user')
                ->firstOrFail();

            $sessionYear = $this->cache->getDefaultSessionYear();


            $leaves = Leave::with(['leave_detail', 'file'])
                ->where('user_id', $child->user_id)
                ->whereHas('leave_master', fn($q) => $q->where('session_year_id', $sessionYear->id))
                ->orderByDesc('from_date')
                ->get()
                ->map(function ($leave) {

                    $leave->total_days = Carbon::parse($leave->getRawOriginal('from_date'))
                        ->diffInDays(Carbon::parse($leave->getRawOriginal('to_date'))) + 1;
                    return $leave;
                });

            ResponseService::successResponse('Data Fetched Successfully', $leaves);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'ParentApiController -> getStudentLeave');
            ResponseService::errorResponse();
        }
    }

    public function applyLeave(Request $request)
    {
        // 1. Feature Access Check
        ResponseService::noFeatureThenSendJson('Student Leave Management');

        // 2. Request Validation
        $file_upload_size_limit = $this->cache->getSystemSettings('file_upload_size_limit');
        $today = Carbon::today()->toDateString();

        $validator = Validator::make($request->all(), [
            'child_id' => 'required|exists:students,id',
            'reason' => 'required|string',
            'from_date' => 'required|date|after_or_equal:' . $today,
            'to_date' => 'required|date|after_or_equal:from_date',
            'files' => 'nullable|array',
            'files.*' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf,doc,docx',
                new SafeUpload([SafeUpload::IMAGE, SafeUpload::DOCUMENT]),
                new MaxFileSize($file_upload_size_limit),
            ],
        ], [
            'from_date.after_or_equal' => __('Leave cannot be applied for past dates.'),
            'to_date.after_or_equal' => __('To date must be after or equal to from date.'),
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            // 3. Verify Child Ownership
            $child = $this->student->builder()
                ->where('id', $request->child_id)
                ->where('guardian_id', Auth::user()->id)
                ->with(['user', 'class_section.class_teachers'])
                ->first();

            if (!$child) {
                ResponseService::errorResponse(__('This child is not linked to your account.'));
            }

            // 4. Check Active Session Year & Leave Master Settings
            $sessionYear = $this->cache->getDefaultSessionYear();
            $leaveMaster = $this->leaveMaster->builder()
                ->where('session_year_id', $sessionYear->id)
                ->first();

            if (!$leaveMaster) {
                ResponseService::errorResponse(__('Kindly contact the school admin to update leave settings for continued access.'));
            }

            // 5. Calculate Date Range
            $from_date_obj = Carbon::parse($request->from_date);
            $to_date_obj = Carbon::parse($request->to_date);

            $from_date = $from_date_obj->toDateString();
            $to_date = $to_date_obj->toDateString();

            // 6. Enforce Academic / Session Year Boundaries
            if ($from_date < $sessionYear->getRawOriginal('start_date') || $to_date > $sessionYear->getRawOriginal('end_date')) {
                ResponseService::errorResponse(__('Selected leave dates must fall within the current academic session year (:start to :end).', [
                    'start' => date('d-m-Y', strtotime($sessionYear->start_date)),
                    'end' => date('d-m-Y', strtotime($sessionYear->end_date)),
                ]));
            }

            $exists = $this->leave->builder()
                ->where('user_id', $child->user_id)
                ->where('status', '!=', 2)
                ->where(function ($q) use ($from_date, $to_date) {
                    $q->where('from_date', '<=', $to_date)
                        ->where('to_date', '>=', $from_date);
                })
                ->exists();

            if ($exists) {
                ResponseService::errorResponse(__('A leave request already exists for this child during this period.'));
            }

            // 7. Validate Against Holidays
            $public_holidays = $this->holiday->builder()
                ->whereDate('date', '>=', $sessionYear->start_date)
                ->whereDate('date', '<=', $sessionYear->end_date)
                ->get()
                ->pluck('dmyFormat')
                ->toArray();

            $weekly_holidays = array_map('trim', explode(',', $leaveMaster->holiday ?? ''));

            $detail_records = [];
            $currentDate = $from_date_obj->copy();

            while ($currentDate->lte($to_date_obj)) {
                $date_str = $currentDate->format('d-m-Y');
                $day_name = $currentDate->format('l');

                if (!in_array($day_name, $weekly_holidays, true) && !in_array($date_str, $public_holidays, true)) {
                    $detail_records[] = [
                        'date' => $currentDate->toDateString(),
                        'type' => 'full_day',
                    ];
                }
                $currentDate->addDay();
            }

            if (empty($detail_records)) {
                ResponseService::errorResponse(__('Leave cannot be applied as all selected dates in the range are holidays.'));
            }

            // 8. Create Leave Record (Added 'added_by' => Auth::id())
            $leave_data = [
                'user_id'         => $child->user_id,
                'added_by'        => Auth::id(), // <-- STORES PARENT / GUARDIAN USER ID
                'reason'          => $request->reason,
                'from_date'       => $from_date,
                'to_date'         => $to_date,
                'status'          => 0, // Pending
                'leave_master_id' => $leaveMaster->id,
                'school_id'       => Auth::user()->school_id,
            ];

            $leave = $this->leave->create($leave_data);

            // 9. Bulk Create Leave Details
            $leave_details_bulk = array_map(function ($detail) use ($leave) {
                $detail['leave_id'] = $leave->id;
                return $detail;
            }, $detail_records);

            $this->leaveDetail->createBulk($leave_details_bulk);

            // 10. Process Attachments
            if ($request->hasFile('files')) {
                $fileData = [];
                $leaveModelAssociate = $this->files->model()->modal()->associate($leave);

                foreach ($request->file('files') as $file_upload) {
                    $fileData[] = [
                        'modal_type' => $leaveModelAssociate->modal_type,
                        'modal_id'   => $leaveModelAssociate->modal_id,
                        'file_name'  => $file_upload->getClientOriginalName(),
                        'type'       => 1,
                        'file_url'   => $file_upload->storeAs('files', UploadService::safeFileName($file_upload), 'public'),
                        'school_id'  => Auth::user()->school_id,
                    ];
                }

                $this->files->createBulk($fileData);
            }

            // 11. Find Approvers (Class Teachers + Staff with 'student-leave-approve')
            $users = $this->user->builder()
                ->whereHas('roles.permissions', function ($q) {
                    $q->whereIn('name', ['student-leave-approve']);
                })
                ->whereHas('roles', function ($q) {
                    $q->whereNot('name', 'Teacher');
                })
                ->pluck('id')
                ->toArray();

            $classTaecherPermission = $this->cache->getSchoolSettings('teacher_student_leave_permissions');

            $class_teachers = [];
            if ($classTaecherPermission == 1) {
                $class_teachers = ClassTeacher::where('class_section_id', $child->class_section_id)->where('session_year_id', $sessionYear->id)
                    ->pluck('teacher_id')
                    ->toArray();
            }
            $users = array_unique(array_merge($users, $class_teachers));

            $title = Auth::user()->full_name . ' has submitted a leave request for ' . ($child->user->full_name ?? 'student') . '.';
            $body = $request->reason;

            DB::commit();

            // 12. Dispatch Push Notification
            send_notification($users, $title, $body, "student_leave", ['_skip_whatsapp' => true]);

            ResponseService::successResponse(__('Leave Request Submitted Successfully'));
        } catch (Throwable $e) {
            DB::rollBack();

            if (Str::contains($e->getMessage(), ['does not exist', 'file_get_contents'])) {
                ResponseService::warningResponse(__('Data Stored successfully. But App push notification was not sent.'));
            }

            ResponseService::logErrorResponse($e, 'ParentApiController -> applyLeave');
            return ResponseService::errorResponse();
        }
    }
    public function getManualPaymentConfig(Request $request)
    {
        try {
            $request->validate(['child_id' => 'required|integer']);

            // child_id = students.id → resolve user via students.user_id
            $student = Students::with('user')->findOrFail($request->child_id);
            $childUser = $student->user;

            if (!$childUser) {
                ResponseService::errorResponse('Student user not found.');
            }

            $manualConfig = $this->paymentConfigurations->builder()
                ->with('file')
                ->where('school_id', $childUser->school_id)
                ->where('payment_method', 'Manual')
                ->first();

            ResponseService::successResponse('Manual payment config fetched successfully', [
                'enabled'         => (bool) ($manualConfig->status ?? 0),
                'payment_details' => $manualConfig->details ?? '',
                'qr_code'         => $manualConfig->file->file_url ?? '', // Morph file relation
            ]);
        } catch (Throwable $e) {
            Log::error('getManualPaymentConfig error: ' . $e->getMessage());
            ResponseService::errorResponse('Failed to retrieve manual payment config: ' . $e->getMessage());
        }
    }


    public function payCompulsoryManualFees(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|integer',
            'fees_id' => 'required|integer|exists:fees,id',
            'installment_ids' => 'nullable|array',
            'installment_ids.*' => 'required|integer|exists:fees_installments,id',
            'advance' => 'present|numeric|min:0',
            'receipt' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'notes' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();

            $studentData = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($studentData)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }
            $schoolId = $studentData->user->school_id;

            if (!$this->manualPaymentService->isManualPaymentEnabled($schoolId)) {
                ResponseService::errorResponse('Manual payment is currently disabled for this school.', null, config('constants.RESPONSE_CODE.MANUAL_PAYMENT_DISABLED'));
            }

            $userId = $studentData->user_id;



            if ($this->manualPaymentService->isCompulsoryFeeFullyPaid($userId, $request->fees_id)) {
                ResponseService::errorResponse("Fees Already Paid", "", config('constants.RESPONSE_CODE.FEE_ALREADY_PAID'));
            }

            $compulsory_fees = $studentData->user->compulsory_fees()->whereNull('deleted_at')->whereHas('fees_paid', function ($q) use ($request) {
                $q->where('fees_id', $request->fees_id);
            })->get();

            $pendingTxn = $compulsory_fees->first(function ($item) {
                return in_array($item->mode, ['Cheque', 'Manual']) && strtolower($item->status) === 'pending';
            });

            if (!empty($pendingTxn)) {
                $type = $pendingTxn->mode === 'Cheque' ? 'cheque' : 'manual';
                ResponseService::errorResponse("A {$type} payment for this fee is currently under review. New payments are locked until it is cleared or rejected.", null, config('constants.RESPONSE_CODE.CHEQUE_PENDING'));
            }

            $fees = $this->fees->builder()->where('id', $request->fees_id)->with([
                'fees_class_type' => function ($q) {
                    $q->where('optional', 0);
                },
                'fees_class_type.fees_type',
                'installments.compulsory_fees' => function ($query) use ($studentData) {
                    $query->whereNull('deleted_at')->where(['student_id' => $studentData->user_id]);
                },
                'fees_paid' => function ($query) use ($studentData) {
                    $query->where(['student_id' => $studentData->user_id])->with('compulsory_fee', 'optional_fee');
                }
            ])->firstOrFail();
            $fees->append(['total_compulsory_fees']);

            if ($fees->fees_paid->isNotEmpty() && $fees->fees_paid->first()->is_fully_paid) {
                ResponseService::errorResponse("Fees Already Paid", "", config('constants.RESPONSE_CODE.FEE_ALREADY_PAID'));
            }

            $existingDiscounts = FeeDiscount::where('student_id', $studentData->user_id)
                ->where('fees_id', $request->fees_id)->where('discount_scope', 'compulsory')->with('items')->get();

            $totalDiscountAmount = $existingDiscounts->sum('discount_value');
            $installmentDiscounts = [];
            foreach ($existingDiscounts as $discount) {
                foreach ($discount->items as $item) {
                    if ($item->fee_type == 'installment') {
                        $installmentDiscounts[$item->fee_type_id] = ($installmentDiscounts[$item->fee_type_id] ?? 0) + $item->discount_amount;
                    }
                }
            }

            $installmentsDataToSave = [];
            if (isset($request->installment_ids) && count($request->installment_ids)) {
                $amount = 0;
                $dueChargesAmount = $instalmentWiseDue = 0;
                $validInstallmentIDS = array_intersect($fees->installments->pluck('id')->toArray(), $request->installment_ids);
                if (empty($validInstallmentIDS)) {
                    ResponseService::errorResponse('Invalid Installment ID');
                }

                $totalInstallments = count($fees->installments);
                $remainingAmount = $fees->total_compulsory_fees;

                if (count($compulsory_fees) > 0) {
                    foreach ($compulsory_fees as $paidInstallment) {
                        if (!empty($paidInstallment->installment_id) && strtolower($paidInstallment->status) === 'success') {
                            --$totalInstallments;
                            $remainingAmount -= $paidInstallment->amount;
                        }
                    }
                }

                $installmentAmount = $remainingAmount;

                foreach ($validInstallmentIDS as $key => $installment_id) {
                    $installment = $fees->installments->first(function ($data) use ($installment_id) {
                        return $data->id == $installment_id;
                    });
                    $paidByAdvance = 0;
                    foreach ($installment->compulsory_fees ?? [] as $paidFees) {
                        if (!empty($paidFees) && strtolower($paidFees->status) === 'success') {
                            $paidByAdvance += $paidFees->amount;
                        }
                    }
                    $inst_discount = $installmentDiscounts[$installment_id] ?? 0;
                    $net_installment_amount = max(0, $installment['installment_amount'] - $inst_discount);
                    $installmentAmount = max(0, $net_installment_amount - $paidByAdvance);

                    if ($installmentAmount > 0 && new DateTime(date('Y-m-d')) > new DateTime($installment['due_date'])) {
                        if ($installment->due_charges_type == "percentage") {
                            $dueChargesAmount += ($installmentAmount * $installment['due_charges']) / 100;
                            $instalmentWiseDue = ($installmentAmount * $installment['due_charges']) / 100;
                        } else if ($installment->due_charges_type == "fixed") {
                            $dueChargesAmount += $installment->due_charges;
                            $instalmentWiseDue = $installment->due_charges;
                        }
                        $amount += $installmentAmount + $instalmentWiseDue;
                    } else {
                        $dueChargesAmount = 0;
                        $amount += $installmentAmount;
                        $instalmentWiseDue = 0;
                    }
                    $installmentsDataToSave[] = [
                        'id' => $installment_id,
                        'amount' => $installmentAmount,
                        'due_charges' => $instalmentWiseDue
                    ];
                }
                if ($request->advance > $remainingAmount) {
                    ResponseService::errorResponse("Advance Amount cannot be greater then : " . $remainingAmount);
                }
            } else {
                $dueChargesAmount = 0;
                $total_paid = 0;
                if (count($compulsory_fees) > 0) {
                    $total_paid = $compulsory_fees->filter(function ($item) {
                        return strtolower($item->status) === 'success';
                    })->sum('amount');
                }
                $netPayableTotal = max(0, $fees->total_compulsory_fees - $totalDiscountAmount);
                $remainingAmount = max(0, $netPayableTotal - $total_paid);
                $amount = $remainingAmount;
                if ($amount > 0 && new DateTime(date('Y-m-d')) > new DateTime($fees->due_date)) {
                    $dueChargesAmount = $fees->due_charges_amount;
                    $amount += $dueChargesAmount;
                }
            }

            if ($request->advance > 0) {
                $finalAmount = $request->advance + $amount;
            } else {
                $finalAmount = $amount;
            }

            if ($finalAmount <= 0) {
                ResponseService::errorResponse("This fee has already been paid.");
            }

            $manualPayment = $this->manualPaymentService->submitCompulsoryManualPayment([
                'student_id'      => $studentData->user_id,
                'fees_id'         => $request->fees_id,
                'fees_paid_id'    => $fees->fees_paid->isNotEmpty() ? $fees->fees_paid->first()->id : null,
                'installments'    => $installmentsDataToSave,
                'amount'          => $finalAmount,
                'due_charges'     => $dueChargesAmount,
                'notes'           => $request->notes,
                'school_id'       => $schoolId,
            ], $request->file('receipt'));

            DB::commit();
            ResponseService::successResponse("Manual Payment Request Submitted Successfully", [
                // Reference of the single payment transaction covering every installment
                'payment_reference'      => $manualPayment['reference'],
                'payment_transaction_id' => $manualPayment['payment_transaction']->id,
                'amount'                 => $manualPayment['amount'],
                'status'                 => CompulsoryFee::MANUAL_UNDER_REVIEW,
                'receipt_url'            => $manualPayment['payment_transaction']->receipt_url,
                'records'                => collect($manualPayment['fees'])->pluck('id'),
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function payOptionalManualFees(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|integer',
            'fees_id' => 'required|integer|exists:fees,id',
            'optional_id' => 'required|array|min:1',
            'optional_id.*' => 'required|integer|exists:fees_class_types,id',
            'receipt' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'notes' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $parentId = Auth::user()->id;
            $studentData = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($studentData)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }
            $classId = $studentData->class_section->class_id;
            $schoolId = $studentData->user->school_id;
            $userId = $studentData->user_id;

            if (!$this->manualPaymentService->isManualPaymentEnabled($schoolId)) {
                ResponseService::errorResponse('Manual payment is currently disabled for this school.', null, config('constants.RESPONSE_CODE.MANUAL_PAYMENT_DISABLED'));
            }

            if ($this->manualPaymentService->isOptionalFeeLocked($userId, $request->optional_id)) {
                ResponseService::errorResponse("A manual payment is already under review for this optional fee.", null, config('constants.RESPONSE_CODE.CHEQUE_PENDING'));
            }

            if ($this->manualPaymentService->isOptionalFeeFullyPaid($userId, $request->optional_id)) {
                ResponseService::errorResponse("This optional fee has already been fully paid.", null, config('constants.RESPONSE_CODE.FEE_ALREADY_PAID'));
            }

            $fees = $this->fees->builder()
                ->where('id', $request->fees_id)
                ->with([
                    'fees_class_type' => function ($q) use ($request) {
                        $q->where('optional', 1)->whereIn('id', $request->optional_id);
                    },
                    'fees_class_type.fees_type',
                    'fees_paid' => function ($query) use ($studentData) {
                        $query->where(['student_id' => $studentData->user_id])->with('optional_fee');
                    }
                ])->firstOrFail();

            $paid_optional_fees = $studentData->user->optional_fees()
                ->whereIn('fees_class_id', $request->optional_id)
                ->where('status', 'Success')
                ->whereHas('fees_paid', function ($q) use ($request) {
                    $q->where('fees_id', $request->fees_id);
                })->get()->groupBy('fees_class_id');

            $existingDiscounts = FeeDiscount::where('student_id', $studentData->user_id)
                ->where('fees_id', $request->fees_id)
                ->where('discount_scope', 'optional')
                ->with('items')
                ->get();
            $optionalDiscounts = [];
            foreach ($existingDiscounts as $discount) {
                foreach ($discount->items as $item) {
                    if ($item->fee_type == 'optional') {
                        $optionalDiscounts[$item->fee_type_id] = ($optionalDiscounts[$item->fee_type_id] ?? 0) + $item->discount_amount;
                    }
                }
            }

            $amount = 0;
            $optional_fees_to_save = [];

            foreach ($fees->fees_class_type as $row) {
                $alreadyPaidAmount = 0;
                if ($paid_optional_fees->has($row->id)) {
                    $alreadyPaidAmount = $paid_optional_fees[$row->id]->sum('amount');
                }

                $discountForThis = $optionalDiscounts[$row->id] ?? 0;
                $netFeeAmount = max(0, $row->amount - $discountForThis);
                $pendingAmount = max(0, $netFeeAmount - $alreadyPaidAmount);

                if ($pendingAmount > 0) {
                    $amount += $pendingAmount;
                    $optional_fees_to_save[] = [
                        'id' => $row->id,
                        'amount' => $pendingAmount
                    ];
                }
            }

            if ($amount <= 0) {
                ResponseService::errorResponse("Selected optional fees have already been fully paid or covered by discount.");
            }

            $manualPayment = $this->manualPaymentService->submitOptionalManualPayment([
                'student_id'    => $userId,
                'class_id'      => $classId,
                'fees_id'       => $request->fees_id,
                'optional_fees' => $optional_fees_to_save,
                'amount'        => $amount,
                'notes'         => $request->notes,
                'school_id'     => $schoolId,
            ], $request->file('receipt'));

            DB::commit();
            ResponseService::successResponse("Manual Optional Payment Request Submitted Successfully", [
                // Reference of the single payment transaction covering every optional fee
                'payment_reference'      => $manualPayment['reference'],
                'payment_transaction_id' => $manualPayment['payment_transaction']->id,
                'amount'                 => $manualPayment['amount'],
                'status'                 => OptionalFee::MANUAL_UNDER_REVIEW,
                'receipt_url'            => $manualPayment['payment_transaction']->receipt_url,
                'records'                => collect($manualPayment['fees'])->pluck('id'),
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }


    public function getManualPaymentStatus(Request $request)
    {
        try {
            $request->validate([
                'child_id'  => 'required|integer',  // students.id
                'type'      => 'required|in:compulsory,optional',
                'record_id' => 'required|integer',
            ]);

            // child_id = students.id → resolve users.id for DB lookup
            $student = Students::findOrFail($request->child_id);
            $userId  = $student->user_id;  // This is what's stored as student_id in fees tables

            $isCompulsory = $request->type === 'compulsory';

            $record = $isCompulsory
                ? CompulsoryFee::with('file', 'payment_transaction.file')
                ->where('student_id', $userId)   // scope by users.id
                ->findOrFail($request->record_id)
                : OptionalFee::with('file', 'payment_transaction.file')
                ->where('student_id', $userId)   // scope by users.id
                ->findOrFail($request->record_id);

            // One manual submission can cover several installments / optional fees: they
            // all share the payment transaction and are reviewed by the admin as one.
            $groupQuery = $isCompulsory
                ? CompulsoryFee::where('student_id', $userId)
                : OptionalFee::where('student_id', $userId);

            if (!empty($record->payment_transaction_id)) {
                $groupQuery->where('payment_transaction_id', $record->payment_transaction_id);
            } else {
                $groupQuery->where('id', $record->id);
            }

            $group = $groupQuery->whereNull('deleted_at')->get();

            return ResponseService::successResponse('Manual payment status retrieved', [
                'id'                => $record->id,
                'status'            => $record->status,
                'mode'              => $record->mode,
                'amount'            => $record->amount,
                'notes'             => $record->notes,
                'receipt_url'       => $record->receipt_url ?? $record->payment_transaction->receipt_url ?? null,
                'created_at'        => $record->created_at,
                'payment_reference' => $record->payment_transaction->order_id ?? null,
                'payment_status'    => $record->payment_transaction->payment_status ?? null,
                'total_amount'      => (float) $group->sum(fn($row) => (float) $row->amount + (float) ($row->due_charges ?? 0)),
                'records'           => $group->map(fn($row) => [
                    'id'          => $row->id,
                    'amount'      => (float) $row->amount,
                    'due_charges' => (float) ($row->due_charges ?? 0),
                    'status'      => $row->status,
                ])->values(),
            ]);
        } catch (Throwable $e) {
            Log::error('getManualPaymentStatus error: ' . $e->getMessage());
            return ResponseService::errorResponse('Failed to retrieve payment status: ' . $e->getMessage());
        }
    }


    #[NoReturn] public function login(Request $request)
    {



        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'school_code' => 'required'
        ], [
            'email.required' => 'The email field cannot be empty.',
            'email.email' => 'Please provide a valid email address.',
            'password.required' => 'The password field cannot be empty.',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        $school = School::on('mysql')->where('code', $request->school_code)->first();

        if ($school) {
            DB::setDefaultConnection('school');
            Config::set('database.connections.school.database', $school->database_name);
            DB::purge('school');
            DB::connection('school')->reconnect();
            DB::setDefaultConnection('school');
        } else {
            ResponseService::errorResponse('Invalid Login Credentials', null, config('constants.RESPONSE_CODE.INVALID_LOGIN'));
        }

        if (
            Auth::attempt([
                'email' => $request->email,
                'password' => $request->password
            ])
        ) {
            // $auth = Auth::user()->load('child.user', 'child.class_section.class', 'child.class_section.section', 'child.class_section.medium', 'child.user.school');

            $defaultSessionYear = $this->cache->getDefaultSessionYear();

            // Only active child
            $auth = Auth::user()->load([
                'child' => function ($q) use ($defaultSessionYear) {
                    $q->whereHas('user.student', function ($q) {
                        $q->where('status', 1);
                    })->with('class_section.class', 'class_section.section', 'class_section.medium', 'user.school')->where('session_year_id', $defaultSessionYear->id);
                }
            ]);
            // ==============================

            // $auth->assignRole('Guardian');
            if (!$auth->hasRole('Guardian')) {
                ResponseService::errorResponse('Invalid Login Credentials', null, config('constants.RESPONSE_CODE.INVALID_LOGIN'));
            }

            // check if guardian has any child
            if ($auth->child->isEmpty()) {
                ResponseService::errorResponse("You currently don't have any active children.", null, config('constants.RESPONSE_CODE.INVALID_LOGIN'));
            }

            // Store FCM tokens using FcmTokenService
            $fcmTokenService = app(FcmTokenService::class);
            if ($request->fcm_id) {
                $fcmTokenService->storeOrUpdateToken(
                    $auth,
                    $request->fcm_id,
                    $request->device_type ?? 'android',
                    $request->device_id ?? null
                );
            }


            // session(['database_name' => $school->database_name]);
            $token = $auth->createToken($auth->first_name)->plainTextToken;
            // $token = $auth->createToken('API Token', ['school_code' => $request->school_code])->plainTextToken;
            $user = $auth;
            $extraData = [
                'fcm_id' => $request->fcm_id,
                'web_fcm' => $request->web_fcm,
                'device_type' => $request->device_type,
            ];
            $user->extra_data = $extraData;

            $request->headers->set('school_code', $request->school_code);
            ResponseService::successResponse('User logged-in!', new UserDataResource($user), ['token' => $token], config('constants.RESPONSE_CODE.LOGIN_SUCCESS'));
        } else {
            ResponseService::errorResponse('Invalid Login Credentials', null, config('constants.RESPONSE_CODE.INVALID_LOGIN'));
        }
    }

    public function subjects(Request $request)
    {
        $validator = Validator::make($request->all(), ['child_id' => 'required|numeric',]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            //            $children = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->first();
            $children = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($children)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }
            $subjects = $children->currentSemesterSubjects();
            ResponseService::successResponse('Student Subject Fetched Successfully.', $subjects);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function classSubjects(Request $request)
    {
        $validator = Validator::make($request->all(), ['child_id' => 'required|numeric',]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            //            $children = $request->user()->guardianRelationChild()->where('id', $request->child_id)->first();
            $children = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($children)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }
            $subjects = $children->currentSemesterClassSubjects();
            ResponseService::successResponse('Class Subject Fetched Successfully.', $subjects);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getTimetable(Request $request)
    {
        $validator = Validator::make($request->all(), ['child_id' => 'required|numeric',]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $children = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($children)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }

            // Get student's subjects
            $studentSubjects = $children->currentSemesterSubjects();

            $core_subjects = $studentSubjects["core_subject"]->pluck('id')->toArray();

            $elective_subjects = $studentSubjects["elective_subject"] ?? [];

            if ($elective_subjects) {
                $elective_subjects = $elective_subjects->pluck('class_subject.subject.id')->toArray();
            }

            $subjectIds = array_merge($core_subjects, $elective_subjects);

            // Get timetable with filtered subjects
            $timetable = $this->timetable->builder()
                ->where('class_section_id', $children->class_section_id)
                ->where(function ($query) use ($subjectIds) {
                    $query->whereIn('subject_id', $subjectIds)
                        ->orWhereNull('subject_id');
                })
                ->where(function ($query) use ($subjectIds) {
                    $query->whereHas('subject_teacher', function ($q) use ($subjectIds) {
                        $q->whereIn('subject_id', $subjectIds);
                    })
                        ->orWhereDoesntHave('subject_teacher');
                })
                ->with([
                    'subject_teacher.subject:id,name,type,code,bg_color,image',
                    'subject_teacher.teacher:id,first_name,last_name'
                ])
                ->orderBy('day')
                ->orderBy('start_time')
                ->get();

            ResponseService::successResponse("Timetable Fetched Successfully", new TimetableCollection($timetable));
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }


    public function getLessons(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'lesson_id' => 'nullable|numeric',
            'class_subject_id' => 'required',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            //            $children = $request->user()->guardianRelationChild()->where('id', $request->child_id)->first();
            $children = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($children)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }
            $lessonQuery = $this->lesson->builder()->whereHas('lesson_commons', function ($q) use ($request, $children) {
                $q->whereHas('syllabus.class_subject', function ($q) use ($request) {
                    $q->where('id', $request->class_subject_id);
                })->where('class_section_id', $children->class_section_id);
            })->with('topic', 'file')->orderBy('id', 'desc');
            if ($request->lesson_id) {
                $lessonQuery->where('id', $request->lesson_id);
            }

            $lessonData = $lessonQuery->get();

            ResponseService::successResponse("Lessons Fetched Successfully", $lessonData);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }


    public function getLessonTopics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'lesson_id' => 'required|numeric',
            'topic_id' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $student = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($student)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }
            $data = $this->lessonTopic->builder()->where('lesson_id', $request->lesson_id)->with('file');
            if ($request->topic_id) {
                $data->where('id', $request->topic_id);
            }
            $data = $data->get();
            ResponseService::successResponse("Topics Fetched Successfully", $data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getAssignments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'assignment_id' => 'nullable|numeric',
            'class_subject_id' => 'nullable|numeric',
            'is_submitted' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            /* -------------------------------------------------
             | 1. Resolve child (student) safely
             -------------------------------------------------*/
            $child = Auth::user()
                ->guardianRelationChild()
                ->where('id', $request->child_id)
                ->whereHas('user', function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->with('class_section')
                ->first();

            if (
                !$child ||
                !$child->class_section_id ||
                !$child->class_section ||
                !$child->class_section->class_id
            ) {
                ResponseService::errorResponse(
                    "Child's Account is not Active. Contact School Support",
                    null,
                    config('constants.RESPONSE_CODE.INACTIVE_CHILD')
                );
            }

            $classSectionId = $child->class_section_id;
            $classId = $child->class_section->class_id;

            /* -------------------------------------------------
             | 2. Resolve allowed class_subject_ids for child
             -------------------------------------------------*/
            $allowedClassSubjectIds = DB::table('class_subjects')
                ->where('class_id', $classId)
                ->where(function ($q) use ($child) {

                    // Compulsory → always allowed
                    $q->where('type', 'Compulsory')

                        // Elective → only if assigned to child
                        ->orWhere(function ($q) use ($child) {
                            $q->where('type', 'Elective')
                                ->whereExists(function ($sub) use ($child) {
                                    $sub->select(DB::raw(1))
                                        ->from('student_subjects')
                                        ->whereColumn(
                                            'student_subjects.class_subject_id',
                                            'class_subjects.id'
                                        )
                                        ->where('student_subjects.student_id', $child->user_id);
                                });
                        });
                })
                ->pluck('id');

            /* -------------------------------------------------
             | 3. Base assignment query
             -------------------------------------------------*/
            $data = $this->assignment->builder()
                ->with([
                    'file',
                    'class_subject.subject',
                    'submission' => function ($query) use ($child) {
                        $query->where('student_id', $child->user_id)
                            ->with('file');
                    }
                ])
                ->whereHas('assignment_commons', function ($query) use ($classSectionId, $allowedClassSubjectIds) {
                    $query->where('class_section_id', $classSectionId)
                        ->whereIn('class_subject_id', $allowedClassSubjectIds);
                });

            /* -------------------------------------------------
             | 4. Filters
             -------------------------------------------------*/
            if ($request->assignment_id) {
                $data->where('id', $request->assignment_id);
            }

            // Optional class_subject filter (still validated server-side)
            if ($request->class_subject_id) {
                $data->whereHas('assignment_commons', function ($query) use ($request, $allowedClassSubjectIds) {
                    $query->where('class_subject_id', $request->class_subject_id)
                        ->whereIn('class_subject_id', $allowedClassSubjectIds);
                });
            }

            // Submission filter
            if (isset($request->is_submitted)) {
                if ((int) $request->is_submitted === 1) {
                    $data->whereHas('submission', function ($q) use ($child) {
                        $q->where('student_id', $child->user_id);
                    });
                } else {
                    $data->whereDoesntHave('submission', function ($q) use ($child) {
                        $q->where('student_id', $child->user_id);
                    });
                }
            }

            /* -------------------------------------------------
             | 5. Response
             -------------------------------------------------*/
            $result = $data
                ->orderBy('id', 'desc')
                ->paginate(15);

            ResponseService::successResponse(
                'Assignments Fetched Successfully',
                $result
            );
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }


    public function getAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'month'    => 'nullable|numeric',
            'year'     => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $children = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($children)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }

            $sessionYear = $this->cache->getDefaultSessionYear($children->school_id);

            $attendance = $this->attendance->builder()->where(['student_id' => $children->user_id, 'session_year_id' => $sessionYear->id]);
            $holidays   = $this->holiday->builder();

            if (isset($request->month)) {
                $attendance = $attendance->whereMonth('date', $request->month);
                $holidays   = $holidays->whereMonth('date', $request->month);
            }

            if (isset($request->year)) {
                $attendance = $attendance->whereYear('date', $request->year);
                $holidays   = $holidays->whereYear('date', $request->year);
            }

            $attendance = $attendance->get();
            $holidays   = $holidays->get();


            $attendance->each(function ($record) {
                if ((int) $record->type === 2) {
                    $record->type = 0;
                }
            });

            $data = ['attendance' => $attendance, 'holidays' => $holidays, 'session_year' => $sessionYear];

            ResponseService::successResponse("Attendance Details Fetched Successfully", $data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getAnnouncements(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:subject,class',
            'child_id' => 'required_if:type,subject,class|numeric',
            'class_subject_id' => 'required_if:type,subject|numeric'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            //            $children = $request->user()->guardianRelationChild()->where('id', $request->child_id)->first();
            $children = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($children)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }
            $classSectionId = $children->class_section_id;

            $sessionYear = $this->cache->getDefaultSessionYear($children->school_id);
            if (isset($request->type) && $request->type == "subject") {
                $table = $this->subjectTeacher->builder()->where(['class_section_id' => $children->class_section_id, 'class_subject_id' => $request->class_subject_id])->get()->pluck('id');
                if ($table === null) {
                    ResponseService::errorResponse("Invalid Subject ID", null, config('constants.RESPONSE_CODE.INVALID_SUBJECT_ID'));
                }
            }
            $data = $this->announcement->builder()->with('file')->where('session_year_id', $sessionYear->id);

            if (isset($request->type) && $request->type == "class") {
                $data = $data->whereHas('announcement_class', function ($query) use ($classSectionId) {
                    $query->where(['class_section_id' => $classSectionId, 'class_subject_id' => null]);
                });
            }


            if (isset($request->type) && $request->type == "subject") {
                $data = $data->whereHas('announcement_class', function ($query) use ($classSectionId, $request) {
                    $query->where(['class_section_id' => $classSectionId, 'class_subject_id' => $request->class_subject_id]);
                });
            }

            $data = $data->orderBy('id', 'desc')->paginate(15);
            ResponseService::successResponse("Announcement Details Fetched Successfully", $data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getSessionYear(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            //            $children = $request->user()->guardianRelationChild()->where('id', $request->child_id)->first();
            $children = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($children)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }
            $sessionYear = $this->cache->getDefaultSessionYear($children->school_id);
            ResponseService::successResponse("Session Year Fetched Successfully", $sessionYear ?? []);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getChildProfileDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            //            $childData = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->with(['class_section' => function ($query) {
            //                $query->with('section', 'class', 'medium', 'class.shift', 'class.stream');
            //            }, 'guardian', 'user'                                                                                      => function ($q) {
            //                $q->with('extra_student_details.form_field', 'school');
            //            }])->first();

            $childData = Auth::user()->guardianRelationChild()->with([
                'class_section' => function ($query) {
                    $query->with('section', 'class', 'medium', 'class.shift', 'class.stream');
                },
                'guardian',
                'user' => function ($q) {
                    $q->with('extra_student_details.form_field', 'school');
                }
            ])->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($childData)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }

            $data = array(
                'id' => $childData->id,
                'first_name' => $childData->user->first_name,
                'last_name' => $childData->user->last_name,
                'country_code' => $childData->user->country_code,
                'mobile' => $childData->user->mobile,
                'roll_number' => $childData->roll_number,
                'admission_no' => $childData->admission_no,
                'admission_date' => $childData->admission_date,
                'gender' => $childData->user->gender,
                'image' => $childData->user->image,
                'dob' => $childData->user->dob,
                'current_address' => $childData->user->current_address,
                'permanent_address' => $childData->user->permanent_address,
                'occupation' => $childData->user->occupation,
                'status' => $childData->user->status,
                'fcm_id' => $childData->user->fcm_id,
                'school_id' => $childData->school_id,
                'session_year_id' => $childData->session_year_id,
                'email_verified_at' => $childData->user->email_verified_at,
                'created_at' => $childData->created_at,
                'updated_at' => $childData->updated_at,
                'class_section' => $childData->class_section,
                'guardian' => $childData->guardian,
                'extra_details' => $childData->user->extra_student_details,
                'school' => $childData->user->school,
            );
            ResponseService::successResponse('Data Fetched Successfully', $data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getExamList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'status' => 'nullable:digits:0,1,2,3'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            //            $student = $this->student->findById($request->child_id, ['id', 'user_id', 'class_section_id'], ['class_section']);
            $student = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($student)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }
            $classId = $student->class_section->class_id;
            $semester = $this->cache->getDefaultSemesterData();
            $exam = $this->exam->builder()
                ->where('class_id', $classId)
                ->whereHas('timetable.class_subject', function ($query) use ($semester) {
                    $query->where('semester_id', $semester->id)->orWhereNull('semester_id');
                })
                ->with([
                    'timetable' => function ($query) {
                        $query->selectRaw('* , SUM(total_marks) as total_marks')
                            ->groupBy('exam_id');
                    }
                ])->has('timetable')->orderBy('id', 'DESC')->get();

            $exam_data = array();
            // 3 => All Details, 0 => Upcoming, 1 => On Going, 2 => Completed
            foreach ($exam as $data) {
                if (isset($request->status) && $request->status != $data->exam_status && $request->status != 3) {
                    continue;
                }

                $exam_data[] = [
                    'id' => $data->id,
                    'name' => $data->name,
                    'description' => $data->description,
                    'publish' => $data->publish,
                    'session_year' => $data->session_year->name,
                    'exam_starting_date' => $data->start_date,
                    'exam_ending_date' => $data->end_date,
                    'exam_status' => $data->exam_status,
                ];
            }

            ResponseService::successResponse("Exam List fetched Successfully", $exam_data ?? []);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'parentApiController :- getExamList Method');
            ResponseService::errorResponse();
        }
    }

    public function getExamDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'exam_id' => 'required|nullable'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            //            $studentData = $this->student->findById($request->child_id, ['id', 'user_id', 'class_section_id'], ['class_section']);
            $studentData = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($studentData)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }
            $classId = $studentData->class_section->class_id;
            $studentSubjects = $studentData->currentSemesterSubjects();
            $coreSubjects = $studentSubjects['core_subject']->pluck('id')->toArray();
            $electiveSubjects = $studentSubjects['elective_subject'] ?? [];
            if ($electiveSubjects) {
                $electiveSubjects = $electiveSubjects->pluck('class_subject.subject.id')->toArray();
            }
            $subjectIds = array_merge($coreSubjects, $electiveSubjects);
            $examData = $this->exam->builder()
                ->where([
                    'id' => $request->exam_id,
                    'class_id' => $classId
                ])
                ->with([
                    'timetable' => function ($query) use ($subjectIds) {
                        $query->owner()
                            ->with(['class_subject.subject'])
                            ->whereHas('class_subject', function ($q) use ($subjectIds) {
                                $q->whereIn('subject_id', $subjectIds); // filter by actual subject
                            })
                            ->orderBy('date');
                    }
                ])
                ->first();


            if (!$examData) {
                ResponseService::successResponse("", []);
            }


            foreach ($examData->timetable as $data) {
                $exam_data[] = array(
                    'exam_timetable_id' => $data->id,
                    'total_marks' => $data->total_marks,
                    'passing_marks' => $data->passing_marks,
                    'date' => $data->date,
                    'starting_time' => $data->start_time,
                    'ending_time' => $data->end_time,
                    'subject' => array(
                        'id' => $data->class_subject->subject->id,
                        'class_subject_id' => $data->class_subject_id,
                        'name' => $data->class_subject->subject->name,
                        'bg_color' => $data->class_subject->subject->bg_color,
                        'image' => $data->class_subject->subject->image,
                        'type' => $data->class_subject->subject->type,
                    )
                );
            }
            ResponseService::successResponse("", $exam_data ?? []);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, 'parentApiController :- getExamDetails Method');
            ResponseService::errorResponse();
        }
    }

    public function getExamMarks(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            // Student Data
            //            $studentData = $this->student->findById($request->child_id, ['id', 'user_id', 'class_section_id'], ['class_section']);
            $studentData = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($studentData)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }
            // Exam Result Data
            $examResultDB = $this->examResult->builder()->with([
                'user' => function ($q) {
                    $q->select('id', 'first_name', 'last_name')->with('student:id,user_id,roll_number');
                },
                'exam.timetable:id,exam_id,start_time,end_time',
                'session_year',
                'exam.marks' => function ($q) use ($studentData) {
                    $q->where('student_id', $studentData->user_id)
                        ->with([
                            'class_subject' => function ($q) {
                                $q->withTrashed()->with([
                                    'subject' => function ($q) {
                                        $q->withTrashed();
                                    }
                                ]);
                            }
                        ]);;
                }
            ])->where('student_id', $studentData->user_id);

            if ($request->session_year_id) {
                $examResultDB = $examResultDB->where('session_year_id', $request->session_year_id);
            }

            if ($request->semester_id) {
                $examResultDB = $examResultDB->whereHas('exam.timetable.class_subject', function ($q) use ($request) {
                    $q->where('semester_id', $request->semester_id);
                });
            }

            $examResultDB = $examResultDB->get();


            // Check that Exam Result DB is not empty
            if (count($examResultDB)) {
                foreach ($examResultDB as $examResultData) {
                    $exam_result = array(
                        'result_id' => $examResultData->id,
                        'exam_id' => $examResultData->exam_id,
                        'exam_name' => $examResultData->exam->name,
                        'class_name' => $studentData->class_section->full_name,
                        'student_name' => $examResultData->user->full_name,
                        'exam_date' => $examResultData->exam->start_date,
                        'total_marks' => $examResultData->total_marks,
                        'obtained_marks' => $examResultData->obtained_marks,
                        'percentage' => $examResultData->percentage,
                        'grade' => $examResultData->grade,
                        'session_year' => $examResultData->session_year->name,
                    );
                    $exam_marks = array();
                    foreach ($examResultData->exam->marks as $marks) {
                        $exam_marks[] = array(
                            'marks_id' => $marks->id,
                            'subject_name' => $marks->class_subject->subject->name,
                            'subject_type' => $marks->class_subject->subject->type,
                            'total_marks' => $marks->timetable->total_marks,
                            'passing_marks' => $marks->timetable->passing_marks,
                            'obtained_marks' => $marks->obtained_marks,
                            'teacher_review' => $marks->teacher_review,
                            'grade' => $marks->grade,
                        );
                    }
                    $data[] = array(
                        'result' => $exam_result,
                        'exam_marks' => $exam_marks,
                    );
                }
                ResponseService::successResponse("Exam Result Fetched Successfully", $data ?? null);
            } else {
                ResponseService::successResponse("Exam Result Fetched Successfully", []);
            }
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getOnlineExamList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'class_subject_id' => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            //            $student = $this->student->findById($request->child_id, ['id', 'user_id', 'class_section_id', 'school_id'], ['class_section']);
            $student = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($student)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }
            $classSectionId = $student->class_section->id;
            $sessionYear = $this->cache->getDefaultSessionYear($student->school_id);
            $semester = $this->cache->getDefaultSemesterData();

            $onlineExamData = $this->onlineExam->builder()
                ->whereHas('online_exam_commons', function ($query) use ($student, $semester) {
                    $query->where('class_section_id', $student->class_section_id)
                        ->whereHas('class_subject', function ($query) use ($semester) {
                            $query->where('semester_id', $semester->id)->orWhereNull('semester_id');
                        });
                })
                ->where('session_year_id', $sessionYear->id)
                ->where('end_date', '>=', now())
                ->has('question_choice')
                ->with('class_subject', 'question_choice:id,online_exam_id,marks')
                ->whereDoesntHave('student_attempt', function ($q) use ($student) {
                    $q->where('student_id', $student->user_id);
                })
                ->when($request->class_subject_id, function ($query) use ($request) {
                    $query->whereHas('online_exam_commons', function ($q) use ($request) {
                        $q->where('class_subject_id', $request->class_subject_id);
                    });
                })
                ->orderby('start_date')
                ->paginate(15);

            ResponseService::successResponse('Data Fetched Successfully', $onlineExamData);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "Parent API ");
            ResponseService::errorResponse();
        }
    }

    public function getOnlineExamResultList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'class_subject_id' => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            //            $student = $this->student->findById($request->child_id, ['id', 'user_id', 'class_section_id', 'school_id'], ['class_section']);
            $student = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($student)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }
            $classSectionId = $student->class_section_id;
            if ($request->session_year_id) {
                $sessionYear = $request->session_year_id;
            } else {
                $sessionYear = $this->cache->getDefaultSessionYear($student->school_id);
                $sessionYear = $sessionYear->id;
            }

            $studentSubjects = $student->selectedStudentSubjects()->pluck('class_subject_id');
            // Get Online Exam Data Where Logged in Student have attempted data and Relation Data with Question Choice , Student's answer with user submitted question with question and its option
            $onlineExamData = $this->onlineExam->builder()
                ->when($request->class_subject_id, function ($query) use ($request, $classSectionId) {
                    $query->whereHas('online_exam_commons', function ($q) use ($request, $classSectionId) {
                        $q->where('class_subject_id', $request->class_subject_id)
                            ->where('class_section_id', $classSectionId);
                    });
                })
                ->where(['session_year_id' => $sessionYear])
                ->whereHas('student_attempt', function ($q) use ($student) {
                    $q->where('student_id', $student->user_id);
                })
                ->with('student_attempt')
                ->whereIn('class_subject_id', $studentSubjects)
                ->with([
                    'student_answers' => function ($q) use ($student) {
                        $q->where('student_id', $student->user_id)
                            ->with('user_submitted_questions.questions:id', 'user_submitted_questions.questions.options:id,question_id,is_answer');
                    }
                ])->with('question_choice:id,online_exam_id,marks', 'class_subject.subject:id,name,type,code,bg_color,image')
                // ->with('question_choice:id,online_exam_id,marks', 'student_answers.user_submitted_questions.questions:id', 'student_answers.user_submitted_questions.questions.options:id,question_id,is_answer', 'class_subject.subject:id,name,type,code,bg_color,image')
                ->orderBy('id', 'desc');

            if ($request->semester_id) {
                $onlineExamData = $onlineExamData->whereHas('class_subject', function ($q) use ($request) {
                    $q->where('semester_id', $request->semester_id);
                });
            }

            $onlineExamData = $onlineExamData->paginate(15)->toArray();

            $examListData = array(); // Initialized Empty examListData Array

            // Loop through Exam data
            foreach ($onlineExamData['data'] as $data) {
                // Reset for each exam
                $examSubmittedDate = null;
                $examSubmittedCreatedDate = null;

                // Get Total Marks of Particular Exam
                $totalMarks = collect($data['question_choice'])->sum('marks');


                // Initialized totalObtainedMarks with 0
                $totalObtainedMarks = 0;

                // Group Student's Answers by question_id
                $grouped_answers = [];
                foreach ($data['student_answers'] as $student_answer) {
                    $examSubmittedDate = date('Y-m-d', strtotime($student_answer['submitted_date']));
                    $examSubmittedCreatedDate = date('d-m-Y H:i', strtotime($student_answer['created_at']));

                    if (isset($student_answer['question_marks'])) {
                        if ($student_answer['true_answer'] == 1) {
                            $totalObtainedMarks += $student_answer['question_marks'];
                        }
                    } else {
                        $grouped_answers[$student_answer['question_id']][] = $student_answer;
                    }
                }

                // Fallback to student_attempt created_at if examSubmittedCreatedDate is not set
                if (!$examSubmittedCreatedDate && isset($data['student_attempt'][0]['created_at'])) {
                    $examSubmittedCreatedDate = date('d-m-Y H:i', strtotime($data['student_attempt'][0]['created_at']));
                }

                // Loop through Student's Grouped answers
                if (isset($grouped_answers) && count($grouped_answers) > 0) {
                    foreach ($grouped_answers as $student_answers) {

                        // Filter the options whose is_answer values is 1
                        $correct_option_ids = array_filter($student_answers[0]['user_submitted_questions']['questions']['options'], static function ($option) {
                            return $option['is_answer'] == 1;
                        });

                        // Get All Correct Options
                        $correct_option_ids = array_column($correct_option_ids, 'id');

                        // Get Student's Correct Options
                        $student_option_ids = array_column($student_answers, 'option_id');

                        // Check if the student's answers exactly match the correct answers then add marks with totalObtainedMarks
                        if (!array_diff($correct_option_ids, $student_option_ids) && !array_diff($student_option_ids, $correct_option_ids)) {
                            $totalObtainedMarks += $student_answers[0]['user_submitted_questions']['marks'];
                        }
                    }
                }

                // Make Exam List Data
                $examListData[] = array(
                    'online_exam_id' => $data['id'],
                    'subject' => array(
                        'id' => $data['class_subject']['subject']['id'],
                        'name' => $data['class_subject']['subject']['name'] . ' - ' . $data['class_subject']['subject']['type'],
                    ),
                    'title' => $data['title'],
                    'obtained_marks' => $totalObtainedMarks ?? "0",
                    'total_marks' => $totalMarks ?? "0",
                    'exam_submitted_date' => $examSubmittedCreatedDate
                );
            }

            $examList = array(
                'current_page' => $onlineExamData['current_page'],
                'data' => $examListData,
                'from' => $onlineExamData['from'],
                'last_page' => $onlineExamData['last_page'],
                'per_page' => $onlineExamData['per_page'],
                'to' => $onlineExamData['to'],
                'total' => $onlineExamData['total'],
            );

            ResponseService::successResponse("", $examList);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getOnlineExamResult(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'online_exam_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            //            $student = $this->student->findById($request->child_id, ['id', 'user_id', 'class_section_id'], ['class_section']);
            $student = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($student)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }
            // Online Exam Data
            $onlineExam = $this->onlineExam->builder()
                ->where('id', $request->online_exam_id)
                ->whereHas('student_attempt', function ($q) use ($student) {
                    $q->where('student_id', $student->user_id);
                })
                // ->with([
                //     'question_choice:id,online_exam_id,marks',
                //     'student_answers.user_submitted_questions.questions:id',
                //     'student_answers.user_submitted_questions.questions.options:id,question_id,is_answer',
                // ])
                ->with([
                    'student_answers' => function ($q) use ($student) {
                        $q->where('student_id', $student->user_id)
                            ->with('user_submitted_questions.questions:id', 'user_submitted_questions.questions.options:id,question_id,is_answer');
                    }
                ])->with('question_choice:id,online_exam_id,marks', 'class_subject.subject:id,name,type,code,bg_color,image')
                ->first();

            if (isset($onlineExam) && $onlineExam != null) {


                $totalQuestions = $onlineExam->question_choice->count();
                $totalMarks = $onlineExam->question_choice->sum('marks');


                $correctQuestionData = array();
                $inCorrectQuestionData = array();
                $correctQuestions = 0;
                $incorrectQuestions = 0;
                $totalObtainedMarks = "0";
                $correctQuestionIds = [];
                // Group Student's Answers by question_id
                $grouped_answers = [];
                foreach ($onlineExam->student_answers as $student_answer) {
                    if (isset($student_answer->question_marks)) {
                        if ($student_answer->true_answer == 1) {
                            $correctQuestions++;
                            $correctQuestionData[] = [
                                'question_id' => $student_answer->question_id,
                                'marks' => $student_answer->question_marks
                            ];
                            $totalObtainedMarks += $student_answer->question_marks;
                            $correctQuestionIds[] = $student_answer->question_id;
                        }
                    } else {
                        $grouped_answers[$student_answer['question_id']][] = $student_answer->toArray();
                    }
                }
                $incorrectQuestions = $totalQuestions - $correctQuestions;
                if (!empty($correctQuestionIds)) {
                    $incorrectQuestionsData = $onlineExam->question_choice
                        ->whereNotIn('id', $correctQuestionIds);
                } else {
                    $incorrectQuestionsData = $onlineExam->question_choice;
                }
                foreach ($incorrectQuestionsData as $incorrectData) {
                    $inCorrectQuestionData[] = [
                        'question_id' => $incorrectData->id,
                        'marks' => $incorrectData->marks
                    ];
                }

                // Loop through Student's Grouped answers
                if (isset($grouped_answers) && $grouped_answers != null) {
                    foreach ($grouped_answers as $student_answers) {

                        // Filter the options whose is_answer values is 1
                        $correct_option_ids = array_filter($student_answers[0]['user_submitted_questions']['questions']['options'], static function ($option) {
                            return $option['is_answer'] == 1;
                        });

                        // Get All Correct Options
                        $correct_option_ids = array_column($correct_option_ids, 'id');

                        // Get Student's Correct Options
                        $student_option_ids = array_column($student_answers, 'option_id');

                        // Check if the student's answers exactly match the correct answers then add marks with totalObtainedMarks
                        if (!array_diff($correct_option_ids, $student_option_ids) && !array_diff($student_option_ids, $correct_option_ids)) {

                            // Sum Question marks with ObtainedMarks
                            $totalObtainedMarks += $student_answers[0]['user_submitted_questions']['marks'];

                            // Get Correct Questions Ids
                            $correctQuestionIds[] = $student_answers[0]['user_submitted_questions']['id'];

                            // Increment Correct Question by 1
                            ++$correctQuestions;

                            // Correct Question Data
                            $correctQuestionData[] = array(
                                'question_id' => $student_answers[0]['user_submitted_questions']['id'],
                                'marks' => $student_answers[0]['user_submitted_questions']['marks']
                            );
                        }
                    }


                    // Check correctQuestionIds exists and not empty
                    if (!empty($correctQuestionIds)) {
                        // Get Incorrect Questions Excluding Correct answer using correctQuestionIds
                        $incorrectQuestionsData = $onlineExam->question_choice->whereNotIn('id', $correctQuestionIds);
                    } else {
                        // Get All Question Choice as incorrectQuestionsData
                        $incorrectQuestionsData = $onlineExam->question_choice;
                    }

                    // Total Incorrect Questions
                    $incorrectQuestions = $incorrectQuestionsData->count();

                    // Incorrect Question Data
                    $inCorrectQuestionData = array();
                    foreach ($incorrectQuestionsData as $incorrectData) {
                        $inCorrectQuestionData[] = array(
                            'question_id' => $incorrectData->id,
                            'marks' => $incorrectData->marks
                        );
                    }
                }

                // Final Array Data
                $onlineExamResult = array(
                    'total_questions' => $totalQuestions,
                    'correct_answers' => array(
                        'total_questions' => $correctQuestions,
                        'question_data' => $correctQuestionData
                    ),
                    'in_correct_answers' => array(
                        'total_questions' => $incorrectQuestions,
                        'question_data' => $inCorrectQuestionData
                    ),
                    'total_obtained_marks' => $totalObtainedMarks,
                    'total_marks' => $totalMarks ?? '0'
                );
                ResponseService::successResponse("", $onlineExamResult);
            } else {
                ResponseService::successResponse("", []);
            }
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getOnlineExamReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'class_subject_id' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            //            $student = $this->student->findById($request->child_id, ['id', 'user_id', 'class_section_id', 'school_id'], ['class_section']);
            $student = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($student)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }
            $sessionYear = $this->cache->getDefaultSessionYear($student->school_id);

            $onlineExams = $this->onlineExam->builder()
                ->has('question_choice')
                ->when($request->class_subject_id, function ($query) use ($request, $student) {
                    $query->whereHas('online_exam_commons', function ($q) use ($request, $student) {
                        $q->where('class_subject_id', $request->class_subject_id)
                            ->where('class_section_id', $student->class_section_id);
                    });
                })
                ->where(['session_year_id' => $sessionYear->id])
                ->whereHas('student_attempt', function ($q) use ($student) {
                    $q->where('student_id', $student->user_id);
                })
                // ->with([
                //     'question_choice:id,online_exam_id,marks',
                //     'student_answers.user_submitted_questions.questions:id',
                //     'student_answers.user_submitted_questions.questions.options:id,question_id,is_answer',
                //     'class_subject.subject:id,name,type,code,bg_color,image'
                // ])
                ->with([
                    'student_answers' => function ($q) use ($student) {
                        $q->where('student_id', $student->user_id)
                            ->with('user_submitted_questions.questions:id', 'user_submitted_questions.questions.options:id,question_id,is_answer');
                    }
                ])->with('question_choice:id,online_exam_id,marks', 'class_subject.subject:id,name,type,code,bg_color,image')
                ->paginate(10);
            $totalMarks = 0;
            $totalObtainedMarks = 0;
            if ($onlineExams->count() > 0) {
                $totalExamIds = $onlineExams->pluck('id')->toArray();
                $totalExamsAttempted = $this->user->builder()->role('Student')->where('id', $student->user_id)->has('online_exam_attempts')->count();

                $examList = array();
                foreach ($onlineExams->toArray()['data'] as $onlineExam) {
                    if (isset($onlineExam['student_answers'][0]['question_marks'])) {
                        $totalMarks = 0;
                    } else {
                        $totalMarks = collect($onlineExam['question_choice'])->sum('marks');
                    }

                    // Initialized totalObtainedMarks with 0
                    $totalObtainedMarks = "0";

                    // Group Student's Answers by question_id
                    $grouped_answers = [];
                    foreach ($onlineExam['student_answers'] as $student_answer) {
                        if (isset($student_answer['question_marks'])) {
                            $totalMarks += $student_answer['question_marks'];
                            if ($student_answer['true_answer'] == 1) {
                                $totalObtainedMarks += $student_answer['question_marks'];
                            }
                        } else {
                            $grouped_answers[$student_answer['question_id']][] = $student_answer;
                        }
                    }

                    // Loop through Student's Grouped answers
                    if (isset($grouped_answers) && count($grouped_answers) > 0) {
                        foreach ($grouped_answers as $student_answers) {

                            // Filter the options whose is_answer values is 1
                            $correct_option_ids = array_filter($student_answers[0]['user_submitted_questions']['questions']['options'], static function ($option) {
                                return $option['is_answer'] == 1;
                            });

                            // Get All Correct Options
                            $correct_option_ids = array_column($correct_option_ids, 'id');

                            // Get Student's Correct Options
                            $student_option_ids = array_column($student_answers, 'option_id');

                            // Check if the student's answers exactly match the correct answers then add marks with totalObtainedMarks
                            if (!array_diff($correct_option_ids, $student_option_ids) && !array_diff($student_option_ids, $correct_option_ids)) {
                                $totalObtainedMarks += $student_answers[0]['user_submitted_questions']['marks'];
                            }
                        }
                    }

                    // Add exam to the list
                    $examList[] = [
                        'online_exam_id' => $onlineExam['id'],
                        'title' => $onlineExam['title'],
                        'obtained_marks' => (string) $totalObtainedMarks,
                        'total_marks' => (string) $totalMarks,
                    ];
                }


                // Calculate Percentage
                if ($totalMarks > 0) {
                    // Avoid division by zero error
                    $percentage = number_format(($totalObtainedMarks * 100) / max($totalMarks, 1), 2);
                } else {
                    // If total marks is zero, then percentage is also zero
                    $percentage = 0;
                }

                // Build the final data array
                $onlineExamReportData = array(
                    'total_exams' => count($totalExamIds),
                    'attempted' => $totalExamsAttempted,
                    'missed_exams' => count($totalExamIds) - $totalExamsAttempted,
                    'total_marks' => (string) $totalMarks,
                    'total_obtained_marks' => (string) $totalObtainedMarks,
                    'percentage' => (string) $percentage,
                    'exam_list' => [
                        'current_page' => (string) $onlineExams->currentPage(),
                        'data' => array_values($examList),
                        'from' => (string) $onlineExams->firstItem(),
                        'last_page' => (string) $onlineExams->lastPage(),
                        'per_page' => (string) $onlineExams->perPage(),
                        'to' => (string) $onlineExams->lastItem(),
                        'total' => (string) $onlineExams->total(),
                    ],
                );
            } else {
                $onlineExamReportData = [];
            }


            // Return the response
            ResponseService::successResponse("", $onlineExamReportData);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getAssignmentReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'class_subject_id' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            //            $student = $this->student->findById($request->child_id, ['id', 'user_id', 'class_section_id', 'school_id'], ['class_section']);
            $student = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($student)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }
            $sessionYear = $this->cache->getDefaultSessionYear($student->school_id);

            // Assignment Data
            $assignments = $this->assignment->builder()
                ->where(['session_year_id' => $sessionYear->id])
                ->whereHas('assignment_commons', function ($query) use ($request, $student) {
                    $query->where('class_section_id', $student->class_section_id)
                        ->where('class_subject_id', $request->class_subject_id);
                })
                ->whereNotNull('points')
                ->get();

            // Get the assignment submissions
            $submitted_assignment_ids = $this->assignmentSubmission->builder()->where('student_id', $student->user_id)->whereIn('assignment_id', $assignments->pluck('id'))->pluck('assignment_id');

            // Calculate various statistics
            $total_assignments = $assignments->count();
            $total_submitted_assignments = $submitted_assignment_ids->count();
            $total_assignment_submitted_points = $assignments->sum('points');
            $total_points_obtained = $this->assignmentSubmission->builder()->whereIn('assignment_id', $submitted_assignment_ids)->sum('points');

            // Calculate the percentage
            $percentage = $total_assignment_submitted_points ? number_format(($total_points_obtained * 100) / $total_assignment_submitted_points, 2) : 0;

            // Get the submitted assignment data with points (using pagination manually)
            $perPage = 15;
            $currentPage = $request->input('page', 1);
            $offset = ($currentPage - 1) * $perPage;
            $submitted_assignment_data_with_points = $assignments->filter(function ($assignment) use ($submitted_assignment_ids) {
                return $assignment->points !== null && $submitted_assignment_ids->contains($assignment->id);
            })->slice($offset, $perPage)->map(function ($assignment) {
                return [
                    'assignment_id' => $assignment->id,
                    'assignment_name' => $assignment->name,
                    'obtained_points' => optional($assignment->submission)->points ?? 0,
                    'total_points' => $assignment->points
                ];
            });

            $assignment_report = [
                'assignments' => $total_assignments,
                'submitted_assignments' => $total_submitted_assignments,
                'unsubmitted_assignments' => $total_assignments - $total_submitted_assignments,
                'total_points' => $total_assignment_submitted_points,
                'total_obtained_points' => $total_points_obtained,
                'percentage' => $percentage,
                'submitted_assignment_with_points_data' => [
                    'current_page' => $currentPage,
                    'data' => array_values($submitted_assignment_data_with_points->toArray()),
                    'from' => $offset + 1,
                    'to' => $offset + $submitted_assignment_data_with_points->count(),
                    'per_page' => $perPage,
                    'total' => $total_submitted_assignments,
                    'last_page' => ceil($total_submitted_assignments / $perPage),
                ],
            ];


            ResponseService::successResponse("Data Fetched Successfully", $assignment_report);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    //Get Fees Details
    public function getFees(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required',
            'session_year_id' => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {

            $student = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            });

            if (isset($request->session_year_id) && $request->session_year_id != '') {
                $student = $student->where('session_year_id', $request->session_year_id);
            }

            $student = $student->first();


            $sessionYearId = $request->session_year_id;

            if (empty($student)) {
                // find student records from promote_students table
                $student = Students::where('id', $request->child_id)->with('class_section', 'user.optional_fees')->WhereHas('promote_student_history', function ($q) use ($sessionYearId) {
                    $q->where('session_year_id', $sessionYearId);
                })
                    ->with(['promote_student_history' => function ($q) use ($sessionYearId) {
                        $q->where('session_year_id', $sessionYearId)->with('class_section.class');
                    }])->first();
                if (empty($student)) {
                    ResponseService::successResponse("No records found");
                }
                $classId = $student->promote_student_history->class_section->class_id;
            } else {
                $classId = $student->class_section->class_id;
            }

            $fees = $this->fees->builder()->where('class_id', $classId)
                ->with([
                    'fees_class_type.fees_type',
                    'fees_paid' => function ($query) use ($student) {
                        $query->where(['student_id' => $student->user_id])
                            ->with(['compulsory_fee' => function ($q) {
                                $q->whereNull('deleted_at')->with('advance_fees', 'file');
                            }])

                            ->with([
                                'optional_fee' => function ($q) {
                                    $q->whereNull('deleted_at')->with('file');
                                }
                            ]);
                    },
                    'session_year',
                    'class.medium',
                    'class.stream'
                ])->where(['session_year_id' => $sessionYearId])->orderBy('id', 'desc')->paginate($request->per_page ?? 10);

            // Fetch all discounts for this student
            $existingDiscounts = FeeDiscount::where('student_id', $student->user_id)
                ->with('items')
                ->get();

            $currentDateTimestamp = new DateTime(date('Y-m-d'));

            foreach ($fees as $fee) {
                $dueCharges = 0;
                $totalFeesPaidAmount = 0;
                $totalFeesDuePaidAmount = 0;
                $fee->due_charges_in_percentage = $fee->due_charges ?? 0;
                $feesDateTimestamp = new DateTime($fee->due_date);

                $pendingInstallments = [];
                $pendingOptionalFees = [];
                $transactions = [];

                if ($fee->fees_paid && count($fee->fees_paid) > 0) {
                    foreach ($fee->fees_paid as $fp) {
                        if ($fp->compulsory_fee) {
                            foreach ($fp->compulsory_fee as $cf) {
                                $itemName = 'Full Payment';
                                if ($cf->type == 'Installment Payment') {
                                    $inst = $fee->installments->firstWhere('id', $cf->installment_id);
                                    $itemName = $inst ? $inst->name : 'Installment';
                                }
                                $transactions[] = [
                                    'id' => $cf->id,
                                    'type' => 'compulsory',
                                    'fee_name' => $itemName,
                                    'amount' => $cf->amount,
                                    'due_charges' => $cf->due_charges ?? 0,
                                    'mode' => $cf->mode,
                                    'status' => $cf->status,
                                    'date' => $cf->date,
                                    'cheque_no' => $cf->cheque_no,
                                    'payment_transaction_id' => $cf->payment_transaction_id,
                                    'notes' => $cf->notes,
                                    'reason' => $cf->reason,
                                    'file' => $cf->mode === 'Manual' ? $cf->file : null,
                                    'receipt_url' => $cf->mode === 'Manual' ? $cf->receipt_url : null,
                                    'created_at' => $cf->created_at,
                                ];

                                if ($cf->status == 'Pending') {
                                    if ($cf->type == 'Installment Payment') {
                                        $pendingInstallments[] = $cf->installment_id;
                                    } else {
                                        $pendingInstallments[] = 'Full';
                                    }
                                }
                            }
                        }
                        if ($fp->optional_fee) {
                            foreach ($fp->optional_fee as $of) {
                                $opt = collect($fee->optional_fees)->firstWhere('id', $of->fees_class_id);
                                $itemName = $opt && $opt->fees_type ? $opt->fees_type->name : 'Optional Fee';

                                $transactions[] = [
                                    'id' => $of->id,
                                    'type' => 'optional',
                                    'fee_name' => $itemName,
                                    'amount' => $of->amount,
                                    'due_charges' => 0,
                                    'mode' => $of->mode,
                                    'status' => $of->status,
                                    'date' => $of->date,
                                    'cheque_no' => $of->cheque_no,
                                    'payment_transaction_id' => $of->payment_transaction_id,
                                    'notes' => $of->notes,
                                    'reason' => $of->reason,
                                    'file' => $of->mode === 'Manual' ? $of->file : null,
                                    'receipt_url' => $of->mode === 'Manual' ? $of->receipt_url : null,
                                    'created_at' => $of->created_at,
                                ];
                                if ($of->status == 'Pending') {
                                    $pendingOptionalFees[] = $of->fees_class_id;
                                }
                            }
                        }
                    }
                }
                usort($transactions, function ($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });
                $fee->transactions = $transactions;


                // Build per-fee discount maps
                $feeDiscounts = $existingDiscounts->where('fees_id', $fee->id);
                $fee->existing_discounts = $feeDiscounts->values();
                $totalDiscountAmount = $feeDiscounts->sum('discount_value');
                $installmentDiscounts = [];
                $optionalDiscounts = [];
                foreach ($feeDiscounts as $discount) {
                    foreach ($discount->items as $item) {
                        if ($item->fee_type == 'installment') {
                            $installmentDiscounts[$item->fee_type_id] = ($installmentDiscounts[$item->fee_type_id] ?? 0) + $item->discount_amount;
                        } elseif ($item->fee_type == 'optional') {
                            $optionalDiscounts[$item->fee_type_id] = ($optionalDiscounts[$item->fee_type_id] ?? 0) + $item->discount_amount;
                        }
                    }
                }
                $fee->total_discount_amount = $totalDiscountAmount;

                // Set Optional Fees Data in response
                if (count($fee->optional_fees) > 0) {
                    collect($fee->optional_fees)->map(function ($optionalFees) use ($student, $optionalDiscounts, $pendingOptionalFees) {
                        // Check all optional fees to find if there is a success
                        $allOptionalFees = $student->user->optional_fees->whereNull('deleted_at')
                            ->where('fees_class_id', $optionalFees->id)
                            ->where('student_id', $student->user->id);

                        $hasSuccess = $allOptionalFees->contains('status', 'Success');
                        $hasFailed = $allOptionalFees->contains('status', 'Failed');

                        $isOptionalFeesPaid = $hasSuccess ? $allOptionalFees->where('status', 'Success')->first() : null;

                        $optionalFees['is_paid'] = $isOptionalFeesPaid ? true : false;
                        $optionalFees['is_pending'] = in_array($optionalFees->id, $pendingOptionalFees);

                        // Set specific status
                        if ($hasSuccess) {
                            $optionalFees['status'] = 'Success';
                        } elseif ($optionalFees['is_pending']) {
                            $optionalFees['status'] = 'Pending';
                        } elseif ($hasFailed) {
                            $optionalFees['status'] = 'Failed';
                        } else {
                            $optionalFees['status'] = 'Unpaid';
                        }

                        $optionalFees['paid_date'] = $isOptionalFeesPaid['date'] ?? null;
                        $optionalFees['discount_amount'] = $optionalDiscounts[$optionalFees->id] ?? 0;
                        $optionalFees['net_payable_amount'] = max(0, $optionalFees->amount - $optionalFees['discount_amount']);
                        return $optionalFees;
                    });
                }


                // Set Compulsory Fees Data in response
                if (count($fee->compulsory_fees) > 0) {
                    $fee->is_overdue = $currentDateTimestamp > $feesDateTimestamp; // true/false
                    collect($fee->compulsory_fees)->map(function ($compulsoryFees) use ($student, $pendingInstallments) {
                        $allCompulsoryFees = $student->user->compulsory_fees->whereNull('deleted_at')
                            ->where('type', 'Full Payment')
                            ->where('student_id', $student->user->id);

                        $hasSuccess = $allCompulsoryFees->contains('status', 'Success');
                        $hasFailed = $allCompulsoryFees->contains('status', 'Failed');

                        $compulsoryFees['is_paid'] = $hasSuccess;
                        $compulsoryFees['is_pending'] = in_array('Full', $pendingInstallments);

                        // Set specific status
                        if ($hasSuccess) {
                            $compulsoryFees['status'] = 'Success';
                        } elseif ($compulsoryFees['is_pending']) {
                            $compulsoryFees['status'] = 'Pending';
                        } elseif ($hasFailed) {
                            $compulsoryFees['status'] = 'Failed';
                        } else {
                            $compulsoryFees['status'] = 'Unpaid';
                        }

                        return $compulsoryFees;
                    });
                }

                // Set Installment Data in Response
                if (count($fee->installments) > 0) {
                    $totalFeesAmount = $fee->total_compulsory_fees;
                    $totalInstallments = count($fee->installments);
                    $paidInstallments = 0;
                    $totalPaidAmount = 0;

                    // First pass - identify paid installments
                    foreach ($fee->installments as $installment) {
                        $installment['paid_amount'] = 0;
                        $installment['discount_amount'] = $installmentDiscounts[$installment->id] ?? 0;
                        $installment['net_payable_amount'] = max(0, $installment->installment_amount - $installment['discount_amount']);

                        $installmentPaidAmount = $student->user->compulsory_fees
                            ->whereNull('deleted_at')
                            ->filter(function ($compulsoryFeesPaid) use ($installment, $student) {
                                return $compulsoryFeesPaid->type == "Installment Payment"
                                    && $compulsoryFeesPaid->installment_id == $installment->id
                                    && $compulsoryFeesPaid->student_id == $student->user->id && $compulsoryFeesPaid->status == 'Success';
                            });

                        $totalInstallmentPaidAmount = $installmentPaidAmount->sum('amount');
                        $installmentPaid = $installmentPaidAmount->first();

                        if (!empty($installmentPaid) || $installment['net_payable_amount'] <= 0) {
                            if (!empty($installmentPaid)) {
                                $totalPaidAmount += $totalInstallmentPaidAmount;
                                $installment['due_charges_amount'] = $installmentPaid->due_charges;
                                $totalFeesPaidAmount += $totalInstallmentPaidAmount;
                                $totalFeesDuePaidAmount += $installmentPaid->due_charges;
                            } else {
                                $installment['due_charges_amount'] = 0;
                            }

                            // Compare paid amount against the discounted net_payable_amount
                            if ($totalInstallmentPaidAmount >= $installment['net_payable_amount']) {
                                $installment['is_paid'] = true;
                                $paidInstallments++;
                            } else {
                                $installment['is_paid'] = false;
                            }
                            $installment['minimum_amount'] = $totalInstallmentPaidAmount;
                            $installment['maximum_amount'] = $totalInstallmentPaidAmount;
                            $installment['paid_amount'] = $totalInstallmentPaidAmount;
                            $installment['pending_amount'] = max(0, $installment['net_payable_amount'] - $totalInstallmentPaidAmount);
                            $installment['is_pending'] = in_array($installment->id, $pendingInstallments);

                            $hasFailedInstallment = $student->user->compulsory_fees
                                ->whereNull('deleted_at')
                                ->where('type', 'Installment Payment')
                                ->where('installment_id', $installment->id)
                                ->where('student_id', $student->user->id)
                                ->contains('status', 'Failed');

                            if ($installment['is_paid']) {
                                $installment['status'] = 'Success';
                            } elseif ($installment['is_pending']) {
                                $installment['status'] = 'Pending';
                            } elseif ($installment['paid_amount'] > 0) {
                                $installment['status'] = 'Partial';
                            } elseif ($hasFailedInstallment) {
                                $installment['status'] = 'Failed';
                            } else {
                                $installment['status'] = 'Unpaid';
                            }
                        } else {
                            $installment['is_paid'] = false;
                            $installment['pending_amount'] = $installment['net_payable_amount'];
                            $installment['is_pending'] = in_array($installment->id, $pendingInstallments);

                            $hasFailedInstallment = $student->user->compulsory_fees
                                ->whereNull('deleted_at')
                                ->where('type', 'Installment Payment')
                                ->where('installment_id', $installment->id)
                                ->where('student_id', $student->user->id)
                                ->contains('status', 'Failed');

                            if ($installment['is_pending']) {
                                $installment['status'] = 'Pending';
                            } elseif ($hasFailedInstallment) {
                                $installment['status'] = 'Failed';
                            } else {
                                $installment['status'] = 'Unpaid';
                            }
                        }
                    }

                    $fee->total_paid_amount = $totalFeesPaidAmount;
                    $fee->total_due_paid_amount = $totalFeesDuePaidAmount;
                    $fee->paid_amount = $totalPaidAmount;

                    // Calculate remaining amount based on discounted total
                    $netPayableTotal = max(0, $totalFeesAmount - $totalDiscountAmount);
                    $fee->original_fee_amount = $totalFeesAmount;
                    $fee->net_payable_amount = $netPayableTotal;
                    $remainingAmount = $netPayableTotal - $totalPaidAmount;
                    $fee->pending_amount = max(0, $remainingAmount);
                    $remainingInstallments = $totalInstallments - $paidInstallments;

                    // Second pass - handle unpaid installments
                    if ($remainingInstallments > 0) {
                        $unpaidInstallmentsProcessed = 0;
                        $amountDistributed = 0;

                        foreach ($fee->installments as $index => $installment) {
                            if ($installment['is_paid']) {
                                continue; // Skip paid installments
                            }

                            $unpaidInstallmentsProcessed++;
                            $installmentDueDateTimestamp = new DateTime($installment['due_date']);

                            // Use discounted net_payable_amount for minimum
                            $installment['minimum_amount'] = $installment['net_payable_amount'];

                            if ($unpaidInstallmentsProcessed == $remainingInstallments) {
                                $installment['maximum_amount'] = max(0, $remainingAmount);
                            } else {
                                $installment['maximum_amount'] = max(0, $remainingAmount - $amountDistributed);
                                $amountDistributed += $installment['net_payable_amount'];
                            }

                            // Calculate due charges if applicable
                            if ($currentDateTimestamp > $installmentDueDateTimestamp) {
                                if ($installment->due_charges_type == "percentage") {
                                    $installment['due_charges_amount'] = ($installment['minimum_amount'] * $installment['due_charges']) / 100;
                                } else if ($installment->due_charges_type == "fixed") {
                                    $installment['due_charges_amount'] = $installment->due_charges;
                                }
                            } else {
                                $installment['due_charges_amount'] = 0;
                            }
                            $dueCharges += $installment['due_charges_amount'];

                            $fee->due_charges = $dueCharges;
                        }
                    }

                    $previousInstallmentDate = new DateTime('now -1 day');
                    // Third pass - identify current installment
                    foreach ($fee->installments as $installment) {
                        $installmentDueDateTimestamp = new DateTime($installment['due_date']);

                        /* Current date should be less then the due date && greater than the due date of previous installments */
                        /* In case of first installment, previous installment date will be current date - 1 */
                        if ($currentDateTimestamp <= $installmentDueDateTimestamp && $currentDateTimestamp > $previousInstallmentDate) {
                            $installment['is_current'] = true;
                        } else {
                            $installment['is_current'] = false;
                        }
                        $previousInstallmentDate = new DateTime($installment['due_date']);
                    }
                }

                // Determine Global Fee Status
                $feeStatus = 'Unpaid';
                $hasPending = false;
                $hasFailed = false;
                $hasSuccess = false;

                if (count($transactions) > 0) {
                    $hasPending = collect($transactions)->contains('status', 'Pending');
                    $hasFailed = collect($transactions)->contains('status', 'Failed');
                    $hasSuccess = collect($transactions)->contains('status', 'Success');
                }

                $compulsoryPaidAmount = collect($transactions)->where('type', 'compulsory')->where('status', 'Success')->sum('amount');
                $compulsoryNetPayable = max(0, $fee->total_compulsory_fees - $totalDiscountAmount);

                if ($hasSuccess) {
                    if ($compulsoryNetPayable > 0) {
                        if ($compulsoryPaidAmount >= ($compulsoryNetPayable - 0.01)) {
                            $feeStatus = 'Success';
                        } else {
                            $feeStatus = $hasPending ? 'Pending' : 'Partial';
                        }
                    } else {
                        // Only optional fees exist and at least one is success
                        $feeStatus = 'Success';
                    }
                } elseif ($hasPending) {
                    $feeStatus = 'Pending';
                } elseif ($hasFailed) {
                    $feeStatus = 'Failed';
                }

                $fee->status = $feeStatus;
            }

            ResponseService::successResponse("Fees Fetched Successfully", $fees);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function payCompulsoryFees(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required',
            'fees_id' => 'required',
            'installment_ids' => 'nullable|array',
            'installment_ids.*' => 'required|integer',
            'advance' => 'present|numeric',
            'payment_method' => 'required|in:Stripe,Razorpay,Flutterwave,Paystack',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();


            $manualPaymentService = app(ManualPaymentService::class);
            if ($manualPaymentService->isOptionalFeeLocked($studentData->user_id, $request->optional_id)) {
                ResponseService::errorResponse("A manual payment is already under review for this optional fee.", null, config('constants.RESPONSE_CODE.CHEQUE_PENDING'));
            }

            $paymentConfigurations = $this->paymentConfigurations->builder()->where(['status' => 1, 'payment_method' => $request->payment_method])->first();

            if (empty($paymentConfigurations)) {
                ResponseService::errorResponse("Payment is not Enabled", [], config('constants.RESPONSE_CODE.ENABLE_PAYMENT_GATEWAY'));
            }

            $parentId = Auth::user()->id;

            $studentData = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($studentData)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }
            $schoolId = $studentData->user->school_id;
            $compulsory_fees = $studentData->user->compulsory_fees()->whereNull('deleted_at')->whereHas('fees_paid', function ($q) use ($request) {
                $q->where('fees_id', $request->fees_id);
            })->get();

            $pendingCheque = $compulsory_fees->first(function ($item) {
                return $item->mode === 'Cheque' && $item->status !== CompulsoryFee::CHEQUE_SUCCESS;
            });

            if (!empty($pendingCheque)) {
                ResponseService::errorResponse(
                    "A cheque payment for this fee is currently under review. New payments are locked until it is cleared or rejected.",
                    null,
                    config('constants.RESPONSE_CODE.CHEQUE_PENDING')
                );
            }



            $sessionYear = $this->cache->getDefaultSessionYear($schoolId);

            $fees = $this->fees->builder()
                ->where('id', $request->fees_id)
                ->with([
                    'fees_class_type' => function ($q) {
                        $q->where('optional', 0);
                    },
                    'fees_class_type.fees_type',
                    'installments.compulsory_fees' => function ($query) use ($studentData) {
                        $query->whereNull('deleted_at')->where(['student_id' => $studentData->user_id]);
                    },
                    'fees_paid' => function ($query) use ($studentData) {
                        $query->where(['student_id' => $studentData->user_id])->with('compulsory_fee', 'optional_fee');
                    }
                ])->firstOrFail();
            $fees->append(['total_compulsory_fees']);

            if ($fees->fees_paid->isNotEmpty() && $fees->fees_paid->first()->is_fully_paid) {
                ResponseService::errorResponse("Fees Already Paid", "", config('constants.RESPONSE_CODE.FEE_ALREADY_PAID'));
            }

            // Fetch all discounts for this student
            $existingDiscounts = FeeDiscount::where('student_id', $studentData->user_id)
                ->where('fees_id', $request->fees_id)
                ->where('discount_scope', 'compulsory')
                ->with('items')
                ->get();

            $totalDiscountAmount = $existingDiscounts->sum('discount_value');
            $installmentDiscounts = [];
            foreach ($existingDiscounts as $discount) {
                foreach ($discount->items as $item) {
                    if ($item->fee_type == 'installment') {
                        $installmentDiscounts[$item->fee_type_id] = ($installmentDiscounts[$item->fee_type_id] ?? 0) + $item->discount_amount;
                    }
                }
            }

            // If installment id is not empty then user is going to pay in installment
            $installmentDetails = [];
            if (isset($request->installment_ids) && count($request->installment_ids)) {
                $amount = 0;
                $dueChargesAmount = $instalmentWiseDue = 0;
                $validInstallmentIDS = array_intersect($fees->installments->pluck('id')->toArray(), $request->installment_ids);

                if (empty($validInstallmentIDS)) {
                    ResponseService::errorResponse('Invalid Installment ID');
                }

                $totalInstallments = count($fees->installments);
                $remainingAmount = $fees->total_compulsory_fees;

                if (count($compulsory_fees) > 0) {
                    // $validInstallmentIDS = array_diff($validInstallmentIDS, $compulsory_fees->pluck('installment_id')->toArray());
                    // $validInstallmentIDS = 
                    // Removing the Paid installments from total installments so that minimum amount can be calculated for the remaining installments.
                    foreach ($compulsory_fees as $paidInstallment) {
                        if (!empty($paidInstallment->installment_id)) {
                            --$totalInstallments;
                            $remainingAmount -= $paidInstallment->amount;
                        }
                    }
                }


                // Calculate amount per installment
                // dd($totalInstallments, $remainingAmount);
                // $installmentAmount = $remainingAmount / $totalInstallments;

                $installmentAmount = $remainingAmount;


                foreach ($validInstallmentIDS as $key => $installment_id) {
                    $installment = $fees->installments->first(function ($data) use ($installment_id) {
                        return $data->id == $installment_id;
                    });

                    $paidByAdvance = 0;
                    foreach ($installment->compulsory_fees ?? [] as $paidFees) {
                        if (!empty($paidFees)) {
                            $paidByAdvance += $paidFees->amount;
                        }
                    }

                    $inst_discount = $installmentDiscounts[$installment_id] ?? 0;
                    $net_installment_amount = max(0, $installment['installment_amount'] - $inst_discount);

                    $installmentAmount = max(0, $net_installment_amount - $paidByAdvance);
                    // Calculate Due Charges amount if installment is overdue
                    if ($installmentAmount > 0 && new DateTime(date('Y-m-d')) > new DateTime($installment['due_date'])) {
                        if ($installment->due_charges_type == "percentage") {
                            $dueChargesAmount += ($installmentAmount * $installment['due_charges']) / 100;
                            $instalmentWiseDue = ($installmentAmount * $installment['due_charges']) / 100;
                        } else if ($installment->due_charges_type == "fixed") {
                            $dueChargesAmount += $installment->due_charges;
                            $instalmentWiseDue = $installment->due_charges;
                        }
                        $amount += $installmentAmount + $instalmentWiseDue;
                    } else {
                        $dueChargesAmount = 0;
                        $amount += $installmentAmount;
                        $instalmentWiseDue = 0;
                    }
                    // Removing installment amount from remaining amount so that we can add validation for advance amount
                    // $remainingAmount -= $installmentAmount;
                    $installmentDetails[$key] = [
                        'id' => $installment_id,
                        'amount' => $installmentAmount,
                        'dueChargesAmount' => $instalmentWiseDue,
                        'due_date' => $installment['due_date'],
                    ];
                }

                // $amount = $remainingAmount;

                if ($request->advance > $remainingAmount) {
                    ResponseService::errorResponse("Advance Amount cannot be greater then : " . $remainingAmount);
                }
            } else {
                /* Full Payment */
                $dueChargesAmount = 0;

                $total_paid = 0;
                if (count($compulsory_fees) > 0) {
                    $total_paid = $compulsory_fees->sum('amount');
                }

                $netPayableTotal = max(0, $fees->total_compulsory_fees - $totalDiscountAmount);
                $remainingAmount = max(0, $netPayableTotal - $total_paid);

                $amount = $remainingAmount;

                if ($amount > 0 && new DateTime(date('Y-m-d')) > new DateTime($fees->due_date)) {
                    $dueChargesAmount = $fees->due_charges_amount;
                    $amount += $dueChargesAmount;
                }
            }



            // if advance amount is greater than 0 and less than amount then add advance amount to amount
            // if ($request->advance > 0 && $request->advance <= $amount) {
            //     $finalAmount = $request->advance + $amount;
            // } else {
            //     $finalAmount = $amount;
            // }

            if ($request->advance > 0) {
                $finalAmount = $request->advance + $amount;
            } else {
                $finalAmount = $amount;
            }

            if ($finalAmount <= 0) {
                ResponseService::errorResponse("No payable amount. Fee is fully covered by discount.");
            }

            //Add Payment Data to Payment Transactions Table
            $paymentTransactionData = $this->paymentTransaction->create([
                'user_id' => $parentId,
                'amount' => $finalAmount,
                'payment_gateway' => $request->payment_method,
                'payment_status' => 'Pending',
                'school_id' => $schoolId,
                'order_id' => null
            ]);

            $paymentIntent = PaymentService::create($request->payment_method, $schoolId)->createPaymentIntent(round($finalAmount, 2), [
                'user_id' => Auth::user()->id,
                'fees_id' => $request->fees_id,
                'student_id' => $studentData->user_id,
                'parent_id' => $parentId,
                'session_year_id' => $sessionYear->id,
                'payment_transaction_id' => $paymentTransactionData->id,
                'installment' => json_encode($installmentDetails, JSON_THROW_ON_ERROR),
                'total_amount' => $finalAmount,
                'advance_amount' => $request->advance,
                'dueChargesAmount' => $dueChargesAmount,
                'school_id' => $schoolId,
                'type' => 'fees',
                'fees_type' => 'compulsory',
                'is_fully_paid' => $amount > $fees->total_compulsory_fees,
                'name' => Auth::user()->full_name,
                'email' => Auth::user()->email,
                'phone' => Auth::user()->mobile,
            ]);


            if ($request->payment_method == "Flutterwave" || $request->payment_method == "Paystack") {
                $this->paymentTransaction->update($paymentTransactionData->id, ['order_id' => $paymentIntent['order_id'] ?? null, 'school_id' => $schoolId]);
                $paymentTransactionData = $this->paymentTransaction->findById($paymentTransactionData->id);
                DB::commit();

                // \Log::info("Payment Intent:", ['payment_intent' => $paymentIntent]);

                // Return only the payment_link for Flutterwave
                if ($request->payment_method == "Flutterwave") {
                    ResponseService::successResponse("", [
                        "payment_link" => $paymentIntent['payment_link']
                    ]);
                } else {
                    ResponseService::successResponse("", [
                        "payment_link" => $paymentIntent['data']['authorization_url']
                    ]);
                }
            } else {
                $this->paymentTransaction->update($paymentTransactionData->id, ['order_id' => $paymentIntent->id, 'school_id' => $schoolId]);

                $paymentTransactionData = $this->paymentTransaction->findById($paymentTransactionData->id);
                // Custom Array to Show as response
                $paymentGatewayDetails = array(
                    ...$paymentIntent->toArray(),
                    'payment_transaction_id' => $paymentTransactionData->id,
                );
                DB::commit();
                ResponseService::successResponse("", ["payment_intent" => $paymentGatewayDetails, "payment_transaction" => $paymentTransactionData]);
            }
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function payOptionalFees(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required',
            'fees_id' => 'required',
            'optional_id' => 'required|array',
            'optional_id.*' => 'required|integer',
            'payment_method' => 'required|in:Stripe,Razorpay,Flutterwave,Paystack',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $parentId = Auth::user()->id;
            $studentData = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($studentData)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }
            $classId = $studentData->class_section->class_id;
            $schoolId = $studentData->user->school_id;
            $sessionYear = $this->cache->getDefaultSessionYear($schoolId);


            $manualPaymentService = app(\App\Services\ManualPaymentService::class);
            if ($manualPaymentService->isOptionalFeeLocked($studentData->user_id, $request->optional_id)) {
                ResponseService::errorResponse("A manual payment is already under review for this optional fee.", null, config('constants.RESPONSE_CODE.CHEQUE_PENDING'));
            }

            $paymentConfigurations = $this->paymentConfigurations->builder()->where(['status' => 1, 'payment_method' => $request->payment_method])->first();

            if (empty($paymentConfigurations)) {
                ResponseService::errorResponse("Payment is not Enabled", [], config('constants.RESPONSE_CODE.ENABLE_PAYMENT_GATEWAY'));
            }


            // Fees Data
            $fees = $this->fees->builder()
                ->where('id', $request->fees_id)
                ->with([
                    'fees_class_type' => function ($q) use ($request) {
                        $q->where('optional', 1)->whereIn('id', $request->optional_id);
                    },
                    'fees_class_type.fees_type',
                    'fees_paid' => function ($query) use ($studentData) {
                        $query->where(['student_id' => $studentData->user_id])->with('optional_fee');
                    }
                ])->firstOrFail();

            $paid_optional_fees = $studentData->user->optional_fees()
                ->whereIn('fees_class_id', $request->optional_id)
                ->where('status', 'Success')
                ->whereHas('fees_paid', function ($q) use ($request) {
                    $q->where('fees_id', $request->fees_id);
                })->get()->groupBy('fees_class_id');
            // Fetch discounts for this student + fee
            $existingDiscounts = FeeDiscount::where('student_id', $studentData->user_id)
                ->where('fees_id', $request->fees_id)
                ->where('discount_scope', 'optional')
                ->with('items')
                ->get();
            $optionalDiscounts = [];
            foreach ($existingDiscounts as $discount) {
                foreach ($discount->items as $item) {
                    if ($item->fee_type == 'optional') {
                        $optionalDiscounts[$item->fee_type_id] = ($optionalDiscounts[$item->fee_type_id] ?? 0) + $item->discount_amount;
                    }
                }
            }

            $amount = 0;
            $total_original_amount = 0;
            $total_discount = 0;
            $optional_fee = [];
            $response_fees = [];

            foreach ($fees->fees_class_type as $row) {
                $alreadyPaidAmount = 0;
                if ($paid_optional_fees->has($row->id)) {
                    $alreadyPaidAmount = $paid_optional_fees[$row->id]->sum('amount');
                }

                $discountForThis = $optionalDiscounts[$row->id] ?? 0;
                $netFeeAmount = max(0, $row->amount - $discountForThis);
                $pendingAmount = max(0, $netFeeAmount - $alreadyPaidAmount);

                if ($pendingAmount > 0) {
                    $amount += $pendingAmount;
                    $total_original_amount += $row->amount;
                    $total_discount += $discountForThis;

                    $optional_fee[] = [
                        'id' => $row->id,
                        'original_amount' => $row->amount,
                        'discount_amount' => $discountForThis,
                        'net_fee_amount' => $netFeeAmount,
                        'already_paid_amount' => $alreadyPaidAmount,
                        'amount' => $pendingAmount
                    ];

                    $response_fees[] = [
                        'name' => $row->fees_type->name ?? 'Optional Fee',
                        'amount' => $row->amount,
                        'discount' => $discountForThis,
                        'payable' => $pendingAmount
                    ];
                }
            }

            if ($amount <= 0) {
                ResponseService::errorResponse("Selected optional fees have already been fully paid or covered by discount.");
            }
            // Add Payment Data to Payment Transactions Table
            $paymentTransactionData = $this->paymentTransaction->create([
                'user_id' => $parentId,
                'amount' => $amount,
                'payment_gateway' => $request->payment_method,
                'payment_status' => 'Pending',
                'school_id' => $schoolId,
                'order_id' => null
            ]);

            $paymentIntent = PaymentService::create($request->payment_method, $schoolId)->createPaymentIntent($amount, [
                'fees_id' => $request->fees_id,
                'student_id' => $studentData->user_id,
                'parent_id' => $parentId,
                'session_year_id' => $sessionYear->id,
                'payment_transaction_id' => $paymentTransactionData->id,
                'total_amount' => $amount,
                'school_id' => $schoolId,
                'class_id' => $classId,
                'optional_fees_id' => json_encode($optional_fee, JSON_THROW_ON_ERROR),
                'type' => 'fees',
                'fees_type' => 'optional',
                'name' => Auth::user()->full_name,
                'email' => Auth::user()->email,
                'mobile' => Auth::user()->mobile
            ]);

            if ($request->payment_method == "Flutterwave" || $request->payment_method == "Paystack") {
                $this->paymentTransaction->update($paymentTransactionData->id, ['order_id' => $paymentIntent['order_id'] ?? null, 'school_id' => $schoolId]);
                $paymentTransactionData = $this->paymentTransaction->findById($paymentTransactionData->id);
                DB::commit();

                \Log::info("Payment Intent:", ['payment_intent' => $paymentIntent]);

                // Return only the payment_link for Flutterwave
                if ($request->payment_method == "Flutterwave") {
                    ResponseService::successResponse("", [
                        "payment_link" => $paymentIntent['payment_link'],
                        "total_amount" => $total_original_amount,
                        "total_discount" => $total_discount,
                        "total_payable" => $amount,
                        "fees" => $response_fees
                    ]);
                } else {
                    ResponseService::successResponse("", [
                        "payment_link" => $paymentIntent['data']['authorization_url'],
                        "total_amount" => $total_original_amount,
                        "total_discount" => $total_discount,
                        "total_payable" => $amount,
                        "fees" => $response_fees
                    ]);
                }
            } else {
                $this->paymentTransaction->update($paymentTransactionData->id, ['order_id' => $paymentIntent->id, 'school_id' => $schoolId]);

                $paymentTransactionData = $this->paymentTransaction->findById($paymentTransactionData->id);
                // Custom Array to Show as response
                $paymentGatewayDetails = array(
                    ...$paymentIntent->toArray(),
                    'payment_transaction_id' => $paymentTransactionData->id,
                    'total_amount' => $total_original_amount,
                    'total_discount' => $total_discount,
                    'total_payable' => $amount,
                    'fees' => $response_fees
                );
                DB::commit();
                ResponseService::successResponse("", ["payment_intent" => $paymentGatewayDetails, "payment_transaction" => $paymentTransactionData]);
            }
            ResponseService::successResponse("Payment Intent Created Successfully");
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }



    public function getFeesTransactions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
            'fees_id' => 'nullable|numeric'
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $student = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($student)) {
                ResponseService::errorResponse("Child's Account is not Active.", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }

            $feesId = ($request->has('fees_id') && $request->fees_id != '') ? (int) $request->fees_id : null;

            // Same restriction on both fee tables : this child's rows, optionally scoped
            // to one fee. The fee rows carry the student, not the transaction (an online
            // payment stores the paying parent on payment_transactions.user_id), so this
            // is what keeps the history scoped to the child.
            $feeFilter = function ($q) use ($feesId, $student) {
                $q->where('student_id', $student->user_id);

                if ($feesId) {
                    $q->whereHas('fees_paid', function ($fp) use ($feesId) {
                        $fp->where('fees_id', $feesId);
                    });
                }
            };

            $compulsoryEager = function ($q) use ($feeFilter) {
                $feeFilter($q);
                $q->with('installment_fee:id,name', 'fees_paid:id,fees_id', 'file')->orderBy('id', 'desc');
            };

            $optionalEager = function ($q) use ($feeFilter) {
                $feeFilter($q);
                $q->with('fees_class_type.fees_type:id,name', 'fees_paid:id,fees_id', 'file')->orderBy('id', 'desc');
            };

            // The payment report lives in payment_transactions : one row per payment,
            // carrying the compulsory fees / optional fees it covers. Paginating the
            // transactions themselves keeps a payment and all its fees on one page.
            $paginatedTransactions = PaymentTransaction::where(function ($q) use ($feeFilter) {
                $q->whereHas('compulsory_fees', $feeFilter)
                    ->orWhereHas('optional_fees', $feeFilter);
            })
                ->with([
                    'compulsory_fees' => $compulsoryEager,
                    'optional_fees'   => $optionalEager,
                    'file',
                ])
                ->orderBy('id', 'desc')
                ->paginate(
                    (int) $request->input('per_page', 15),
                    ['*'],
                    'page',
                    (int) $request->input('page', 1)
                );

            $paginatedTransactions->getCollection()->transform(function ($paymentTransaction) {
                $compulsoryFees = $paymentTransaction->compulsory_fees->map(function ($cf) {
                    $itemName = 'Full Payment';
                    if ($cf->type == 'Installment Payment') {
                        $itemName = $cf->installment_fee ? $cf->installment_fee->name : 'Installment';
                    }

                    return [
                        'id' => $cf->id,
                        'type' => 'compulsory',
                        'fee_name' => $itemName,
                        'fees_id' => $cf->fees_paid->fees_id ?? null,
                        'installment_id' => $cf->installment_id,
                        'amount' => $cf->amount,
                        'due_charges' => $cf->due_charges ?? 0,
                        'mode' => $cf->mode,
                        'status' => $cf->status,
                        'date' => $cf->date,
                        'cheque_no' => $cf->cheque_no,
                        'notes' => $cf->notes,
                        'reason' => $cf->reason,
                        'file' => $cf->mode === 'Manual' ? $cf->file : null,
                        'receipt_url' => $cf->mode === 'Manual' ? $cf->receipt_url : null,
                        'created_at' => $cf->created_at,
                    ];
                })->values();

                $optionalFees = $paymentTransaction->optional_fees->map(function ($of) {
                    $itemName = $of->fees_class_type && $of->fees_class_type->fees_type ? $of->fees_class_type->fees_type->name : 'Optional Fee';

                    return [
                        'id' => $of->id,
                        'type' => 'optional',
                        'fee_name' => $itemName,
                        'fees_id' => $of->fees_paid->fees_id ?? null,
                        'fees_class_id' => $of->fees_class_id,
                        'amount' => $of->amount,
                        'due_charges' => 0,
                        'mode' => $of->mode,
                        'status' => $of->status,
                        'date' => $of->date,
                        'cheque_no' => $of->cheque_no,
                        'notes' => $of->notes,
                        'reason' => $of->reason,
                        'file' => $of->mode === 'Manual' ? $of->file : null,
                        'receipt_url' => $of->mode === 'Manual' ? $of->receipt_url : null,
                        'created_at' => $of->created_at,
                    ];
                })->values();

                // A payment keeps the mode of the fees it paid (Cash / Cheque / Manual);
                // online gateways only live on the transaction itself.
                $firstFee = $paymentTransaction->compulsory_fees->first() ?? $paymentTransaction->optional_fees->first();

                return [
                    'id' => $paymentTransaction->id,
                    'payment_transaction_id' => $paymentTransaction->id,
                    'payment_reference' => $paymentTransaction->order_id,
                    'payment_id' => $paymentTransaction->payment_id,
                    'payment_gateway' => $paymentTransaction->payment_gateway,
                    'payment_status' => $paymentTransaction->payment_status,
                    'payment_type' => $paymentTransaction->type,
                    'amount' => $paymentTransaction->amount,
                    'total_amount' => (float) $compulsoryFees->sum(fn($fee) => (float) $fee['amount'] + (float) $fee['due_charges'])
                        + (float) $optionalFees->sum(fn($fee) => (float) $fee['amount']),
                    'mode' => $firstFee->mode ?? null,
                    'status' => $firstFee->status ?? null,
                    'date' => $firstFee->date ?? null,
                    'cheque_no' => $firstFee->cheque_no ?? null,
                    'notes' => $firstFee->notes ?? null,
                    'reason' => $firstFee->reason ?? null,
                    'receipt_url' => $paymentTransaction->receipt_url,
                    'compulsory_fees' => $compulsoryFees,
                    'optional_fees' => $optionalFees,
                    'created_at' => $paymentTransaction->created_at,
                ];
            });

            ResponseService::successResponse("Fees Transaction History Fetched Successfully", $paginatedTransactions);
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }


    public function transactionReceiptPDF(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|in:compulsory,optional',
            'transaction_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $transactionId = $request->transaction_id;

            $transaction = PaymentTransaction::findOrFail($transactionId);

            // $child = Auth::user()->guardianRelationChild()->whereHas('user', function ($q) use ($transaction) {
            //     $q->where('id', $transaction->student_id)->whereNull('deleted_at');
            // })->first();

            // if (empty($child)) {
            //     ResponseService::errorResponse("Unauthorized or Child's Account is not Active.", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            // }

            $response = $this->userService->generatePaymentTransactionReceiptPDF($transaction->id, 'api');
        } catch (\Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
        return response()->json($response);
    }


    public function feesPaidReceiptPDF(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|integer',
            'fees_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            // $student = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
            //     $q->whereNull('deleted_at');
            // })->with('user:id,first_name,last_name', 'class_section.class.stream', 'class_section.section', 'class_section.medium')->first();

            // if (empty($student)) {
            //     ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            // }
            // $feesPaid = $this->feesPaid->builder()->where(['fees_id' => $request->fees_id, 'student_id' => $student->user_id])
            //     ->with([
            //         'fees' => function ($q) {
            //             $q->with('class:id,medium_id,name', 'class.medium', 'fees_class_type.fees_type');
            //         },
            //         'compulsory_fee.installment_fee:id,name',
            //         'optional_fee' => function ($q) {
            //             $q->with([
            //                 'fees_class_type' => function ($q) {
            //                     $q->select('id', 'fees_type_id')->with('fees_type:id,name');
            //                 }
            //             ]);
            //         },
            //     ])->first();
            // $systemVerticalLogo = $this->systemSetting->builder()->where('name', 'vertical_logo')->first();
            // $schoolVerticalLogo = $this->schoolSetting->builder()->where('name', 'vertical_logo')->first();
            // $school = $this->cache->getSchoolSettings();
            // $data = explode("storage/", $school['horizontal_logo'] ?? '');
            // $school['horizontal_logo'] = end($data);
            // if ($school['horizontal_logo'] == null) {
            //     $systemSettings = $this->cache->getSystemSettings();
            //     $data = explode("storage/", $systemSettings['horizontal_logo'] ?? '');
            //     $school['horizontal_logo'] = end($data);
            // }
            // //            return view('fees.fees_receipt', compact('systemVerticalLogo', 'school', 'feesPaid', 'student', 'schoolVerticalLogo'));

            $student = Students::find($request->child_id);
            if (empty($student)) {
                ResponseService::errorResponse("Student Not Found");
            }


            $response = $this->userService->generateSchoolFeesReceiptPDF($request->fees_id, $student->user_id, 'api');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
        return response()->json($response);
    }


    public function getSchoolSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id'        => 'required|numeric',
            'session_year_id' => 'nullable'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $child = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($child)) {
                ResponseService::errorResponse("Child's Account is not Active. Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }

            $schoolId    = $child->user->school_id;
            $settings    = $this->cache->getSchoolSettings('*', $schoolId);
            $sessionYear = $this->cache->getDefaultSessionYear($schoolId);
            $semester    = $this->cache->getDefaultSemesterData($schoolId);
            $features    = FeaturesService::getFeatures($schoolId);

            // 1. Fetch active Payment Gateways with Morph File Relation (QR Code)
            $paymentGateways = $this->paymentConfigurations->builder()
                ->with('file')
                ->where('school_id', $schoolId)
                ->where('status', 1)
                ->select([
                    'id',
                    'payment_method',
                    'api_key',
                    'currency_code',
                    'details',
                    'bank_name',
                    'account_name',
                    'account_no'
                ])
                ->get();

            // 2. Extract Manual Payment for Mobile App Compatibility
            $manualGateway = $paymentGateways->firstWhere('payment_method', 'Manual');

            $manualPayment = [
                'status'          => $manualGateway ? 1 : 0,
                'payment_details' => $manualGateway->details ?? '',
                'qr_code'         => $manualGateway && $manualGateway->file ? $manualGateway->file->file_url : '',
            ];

            $data = [
                'school_id'       => $schoolId,
                'settings'        => $settings,
                'session_year'    => $sessionYear,
                'semester'        => $semester,
                'features'        => (count($features) > 0) ? $features : (object) [],
                'payment_gateway' => $paymentGateways
            ];

            ResponseService::successResponse('Settings Fetched Successfully.', $data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getSliders(Request $request)
    {
        try {
            $data = $this->sliders->builder()->whereIn('type', [1, 3])->get();
            ResponseService::successResponse("Sliders Fetched Successfully", $data);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function test(Request $request)
    {
        // return $request->header();
        // DB::setDefaultConnection('school');
        // return DB::getDatabaseName();
        return Auth::user();
    }


    // add the transaction data in transaction table
    //    public function completeFeeTransaction(Request $request) {
    //        $validator = Validator::make($request->all(), [
    //            'child_id'               => 'required',
    //            'payment_transaction_id' => 'required',
    //            'payment_id'             => 'required',
    //            'payment_signature'      => 'required',
    //        ]);
    //        if ($validator->fails()) {
    //            ResponseService::validationError($validator->errors()->first());
    //        }
    //        try {
    //            DB::beginTransaction();
    //            $child = $this->student->findById($request->child_id);
    //            $this->paymentTransaction->update($request->payment_transaction_id, ['payment_id' => $request->payment_id, 'payment_signature' => $request->payment_signature, 'school_id' => $child->school_id]);
    //            DB::commit();
    //            ResponseService::successResponse("Data Updated Successfully");
    //        } catch (Throwable $e) {
    //            DB::rollBack();
    //            ResponseService::logErrorResponse($e);
    //            ResponseService::errorResponse();
    //        }
    //    }

    //get the fees paid list
    //    public function feesPaidList(Request $request) {
    //        $validator = Validator::make($request->all(), [
    //            'child_id'        => 'required',
    //            'session_year_id' => 'nullable'
    //        ]);
    //
    //        if ($validator->fails()) {
    //            ResponseService::validationError($validator->errors()->first());
    //        }
    //        try {
    //            $child = $this->student->findById($request->child_id);
    //            $currentSessionYear = $this->cache->getDefaultSessionYear($child->user->school_id);
    //            $sessionYearId = $request->session_year_id ?? $currentSessionYear->id;
    //            $fees_paid = $this->feesPaid->builder()->where(['student_id' => $child->user_id, 'session_year_id' => $sessionYearId])->with('session_year:id,name', 'class.medium')->get();
    //
    //            ResponseService::successResponse("", $fees_paid);
    //        } catch (Throwable $e) {
    //            DB::rollBack();
    //            ResponseService::logErrorResponse($e);
    //            ResponseService::errorResponse();
    //        }
    //    }


    // // Make Transaction Fail API
    // public function failPaymentTransactionStatus(Request $request){
    //     try{
    //         $update_status = PaymentTransaction::findOrFail($request->payment_transaction_id);
    //         $update_status->payment_status = 0;
    //         $update_status->save();
    //         $response = array(
    //             'error' => false,
    //             'message' => 'Data Updated Successfully',
    //             'code' => 200,
    //         );
    //     } catch (\Exception $e) {
    //         $response = array(
    //             'error' => true,
    //             'message' => trans('error_occurred'),
    //             'code' => 103,
    //         );
    //     }

    public function getStudentDiaries(Request $request)
    {
        try {
            $children = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($children)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }


            $diaryStudents = $this->diaryStudent->builder()
                ->where('student_id', $children->id)
                ->with([
                    'diary' => function ($query) {
                        $query->with(['diary_category', 'session_year', 'subject']);
                    }
                ])
                ->get();

            ResponseService::successResponse("Student Diaries Fetched Successfully", $diaryStudents);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }


    public function showStudentDiaryDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|nullable'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $children = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();

            if (empty($children)) {
                ResponseService::errorResponse("Child's Account is not Active.Contact School Support", NULL, config('constants.RESPONSE_CODE.INACTIVE_CHILD'));
            }

            $diaryStudents = $this->diaryStudent->builder()
                ->where('id', $request->id)
                ->where('student_id', $children->id)
                ->with([
                    'diary' => function ($query) {
                        $query->with(['diary_category', 'session_year', 'subject']);
                    }
                ])
                ->firstOrFail();

            ResponseService::successResponse("Student Diary Fetched Successfully", $diaryStudents);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getChildDiaryCategories(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {

            $children = Auth::user()->guardianRelationChild()->where('id', $request->child_id)->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })->first();
            // dd($children);
            $categoryIds = $this->diaryStudent
                ->builder()
                ->where('student_id', $children->user_id)
                ->with([
                    'diary' => function ($query) {
                        $query->withTrashed();
                    }
                ])
                ->get()
                ->pluck('diary.diary_category_id')
                ->unique()
                ->filter()
                ->values();

            // Fetch the DiaryCategory models with these IDs
            $categories = DiaryCategory::withTrashed()
                ->whereIn('id', $categoryIds)
                ->get();

            ResponseService::successResponse("Student Diary Categories Fetched Successfully", $categories);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getParentData()
    {
        try {

            $defaultSessionYear = $this->cache->getDefaultSessionYear();

            $auth = Auth::user()->load([
                'child' => function ($q) use ($defaultSessionYear) {
                    $q->whereHas('user.student', function ($q) use ($defaultSessionYear) {
                        $q->where('status', 1)->where('session_year_id', $defaultSessionYear->id);
                    })->with('class_section.class', 'class_section.section', 'class_section.medium', 'user.school');
                }
            ]);
            if (!$auth->hasRole('Guardian')) {
                ResponseService::errorResponse("Unauthorized Access");
            }
            ResponseService::successResponse("Parent Data Fetched Successfully", new UserDataResource($auth));
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }
}
